<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Services\SlaService;
use Illuminate\Http\Request;

/**
 * @tags SLA
 */
class SlaController extends Controller
{
    public function __construct(private readonly SlaService $slaService) {}

    public function index()
    {
        return response()->json(SlaConfig::orderBy('priority')->get());
    }

    public function update(Request $request, int $id)
    {
        $config = SlaConfig::findOrFail($id);

        $validated = $request->validate([
            'response_time_hours'   => 'sometimes|integer|min:1|max:720',
            'resolution_time_hours' => 'sometimes|integer|min:1|max:720',
            'alert_threshold'       => 'sometimes|integer|min:10|max:99',
            'work_start_hour'       => 'sometimes|integer|min:0|max:23',
            'work_end_hour'         => 'sometimes|integer|min:1|max:24',
        ]);

        if (isset($validated['work_start_hour'], $validated['work_end_hour'])) {
            if ($validated['work_start_hour'] >= $validated['work_end_hour']) {
                return response()->json([
                    'message' => 'La hora de inicio debe ser anterior a la hora de fin.',
                ], 422);
            }
        }

        $config->update($validated);

        return response()->json($config->fresh());
    }

    /**
     * Recalculate SLA due dates for all open tickets using the latest config.
     * Anchored to each ticket's original creation date, so this applies
     * config changes retroactively without resetting the SLA clock.
     */
    public function recalculate(Request $request)
    {
        $result = $this->slaService->recalculateOpenTickets($request->user()->id);

        return response()->json([
            'message' => "Se revisaron {$result['count']} ticket(s) abierto(s); {$result['changed']} tuvieron cambios de SLA.",
            'count'   => $result['count'],
            'changed' => $result['changed'],
        ]);
    }

    /**
     * Paginated list of tickets whose SLA due dates were recalculated
     * (evidence trail for the "Recalcular SLA" action).
     */
    public function history(Request $request)
    {
        $query = $this->historyQuery($request);

        return response()->json($query->orderByDesc('created_at')->paginate(50));
    }

    /**
     * CSV export of the SLA recalculation history (evidence report).
     */
    public function exportHistory(Request $request)
    {
        $rows = $this->historyQuery($request)->orderByDesc('created_at')->get();

        $filename = 'sla_recalculos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 accents correctly

            fputcsv($out, [
                'Ticket', 'Solicitante', 'Prioridad',
                'SLA anterior', 'SLA nuevo',
                'Modificado por', 'Fecha del cambio',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->ticket?->ticket_number ?? $row->ticket_id,
                    $row->ticket?->requester_name ?? '',
                    $row->ticket?->priority ?? '',
                    $row->old_value,
                    $row->new_value,
                    $row->user?->name ?? 'Sistema',
                    $row->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function historyQuery(Request $request)
    {
        $query = TicketHistory::with([
            'ticket:id,ticket_number,requester_name,priority',
            'user:id,name,email',
        ])->where('action', 'sla_recalculado');

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    /**
     * SLA compliance report grouped by agent or by work group.
     * For each agent/group: counts per SLA status, % de cumplimiento, and
     * the list of individual tickets (so the UI can show summary + detail).
     */
    public function report(Request $request)
    {
        return response()->json($this->buildReport($request));
    }

    /**
     * CSV export of the SLA compliance report (one row per ticket).
     */
    public function exportReport(Request $request)
    {
        $groups = $this->buildReport($request);
        $groupBy = $request->input('group_by', 'agent');
        $groupLabel = $groupBy === 'group' ? 'Grupo de trabajo' : 'Agente';

        $filename = 'sla_cumplimiento_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($groups, $groupLabel) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                $groupLabel, 'Ticket', 'Prioridad', 'Estado SLA',
                'Creado', 'Vencimiento SLA', 'Resuelto/Cerrado',
            ]);

            foreach ($groups as $group) {
                foreach ($group['tickets'] as $ticket) {
                    fputcsv($out, [
                        $group['label'],
                        $ticket['ticket_number'],
                        $ticket['priority'],
                        $ticket['status'],
                        $ticket['created_at'],
                        $ticket['due_at'],
                        $ticket['resolved_at'] ?? '',
                    ]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<int, array{
     *     id: int, label: string, total: int, met: int, on_track: int,
     *     at_risk: int, breached: int, compliance_pct: float, tickets: array
     * }>
     */
    private function buildReport(Request $request): array
    {
        $groupBy = $request->input('group_by', 'agent'); // 'agent' | 'group'

        $query = Ticket::with(['assignedAgent:id,name', 'workGroup:id,name'])
            ->whereNotNull('sla_resolution_due_at');

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->priority && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        $tickets = $query->get();

        $groups = [];

        foreach ($tickets as $ticket) {
            $status = $this->slaService->getStatus($ticket);

            if ($groupBy === 'group') {
                $key   = $ticket->work_group_id ?? 0;
                $label = $ticket->workGroup?->name ?? 'Sin grupo asignado';
            } else {
                $key   = $ticket->assigned_to ?? 0;
                $label = $ticket->assignedAgent?->name ?? 'Sin agente asignado';
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id'       => $key,
                    'label'    => $label,
                    'met'      => 0,
                    'on_track' => 0,
                    'at_risk'  => 0,
                    'breached' => 0,
                    'tickets'  => [],
                ];
            }

            $groups[$key][$status]++;
            $groups[$key]['tickets'][] = [
                'id'            => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'priority'      => $ticket->priority,
                'status'        => $status,
                'created_at'    => $ticket->created_at?->format('Y-m-d H:i'),
                'due_at'        => $ticket->sla_resolution_due_at?->format('Y-m-d H:i'),
                'resolved_at'   => ($ticket->resolved_at ?? $ticket->closed_at)?->format('Y-m-d H:i'),
            ];
        }

        $result = array_values($groups);

        foreach ($result as &$group) {
            $total = $group['met'] + $group['on_track'] + $group['at_risk'] + $group['breached'];
            $group['total']          = $total;
            $group['compliance_pct'] = $total > 0
                ? round((($total - $group['breached']) / $total) * 100, 1)
                : 0;
        }

        usort($result, fn($a, $b) => $b['total'] <=> $a['total']);

        return $result;
    }

    /**
     * Returns SLA status summary for the dashboard.
     */
    public function dashboard()
    {
        $open = Ticket::whereNull('closed_at')->whereNull('resolved_at')
            ->whereNotNull('sla_resolution_due_at')
            ->get();

        $summary = [
            'on_track' => 0,
            'at_risk'  => 0,
            'breached' => 0,
        ];

        foreach ($open as $ticket) {
            $status = $this->slaService->getStatus($ticket);
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
        }

        return response()->json(['summary' => $summary]);
    }

    /**
     * Paginated, searchable list of open tickets whose SLA is currently
     * breached (due date already passed without being resolved/closed).
     */
    public function breachedTickets(Request $request)
    {
        $query = Ticket::whereNull('closed_at')
            ->whereNull('resolved_at')
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<=', now())
            ->with('assignedAgent:id,name');

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('requester_name', 'like', "%{$search}%");
            });
        }

        if ($request->priority && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        $tickets = $query->orderBy('sla_resolution_due_at')
            ->paginate($request->input('per_page', 10));

        $tickets->getCollection()->transform(fn (Ticket $ticket) => [
            'id'             => $ticket->id,
            'ticket_number'  => $ticket->ticket_number,
            'requester_name' => $ticket->requester_name,
            'priority'       => $ticket->priority,
            'due_at'         => $ticket->sla_resolution_due_at,
            'assigned_to'    => $ticket->assignedAgent?->name,
        ]);

        return response()->json($tickets);
    }
}
