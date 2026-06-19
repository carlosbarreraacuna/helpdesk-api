<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlaConfig;
use App\Models\Ticket;
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
    public function recalculate()
    {
        $count = $this->slaService->recalculateOpenTickets();

        return response()->json([
            'message' => "Se recalculó el SLA de {$count} ticket(s) abierto(s).",
            'count'   => $count,
        ]);
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

        $breachedTickets = [];

        foreach ($open as $ticket) {
            $status = $this->slaService->getStatus($ticket);
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
            if ($status === 'breached') {
                $breachedTickets[] = [
                    'id'             => $ticket->id,
                    'ticket_number'  => $ticket->ticket_number,
                    'requester_name' => $ticket->requester_name,
                    'priority'       => $ticket->priority,
                    'due_at'         => $ticket->sla_resolution_due_at,
                    'assigned_to'    => $ticket->assignedAgent?->name,
                ];
            }
        }

        return response()->json([
            'summary'          => $summary,
            'breached_tickets' => $breachedTickets,
        ]);
    }
}
