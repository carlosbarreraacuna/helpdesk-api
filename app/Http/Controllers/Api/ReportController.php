<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\Ticket;
use App\Models\User;
use App\Models\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * @tags Reportes
 */
class ReportController extends Controller
{
    /**
     * Obtiene los reportes disponibles para el usuario
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $roleId = $user->role_id;

        $reports = ReportTemplate::active()
            ->forRole($roleId)
            ->orderBy('order')
            ->get()
            ->map(function($report) use ($roleId) {
                $pivot = $report->roles()->where('role_id', $roleId)->first();
                $report->can_export = $pivot ? $pivot->pivot->can_export : false;
                return $report;
            });

        return response()->json($reports);
    }

    // ==========================================
    // MÉTRICAS
    // ==========================================

    public function totalTickets(Request $request)
    {
        try {
            $user = $request->user();
            \Log::info('totalTickets called', ['user_id' => $user?->id, 'role' => $user?->role?->name]);
            
            // Simplified version - just count all tickets for now
            $count = Ticket::count();
            \Log::info('Total tickets counted', ['count' => $count]);

            return response()->json([
                'value' => $count,
                'label' => 'Total de Tickets',
                'change' => 0, // Simplified for now
            ]);
        } catch (\Exception $e) {
            \Log::error('totalTickets error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to load metric',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function pendingTickets(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());
        
        $count = $query->whereHas('status', function($q) {
            $q->whereIn('name', ['nuevo', 'asignado', 'en_progreso']);
        })->count();

        return response()->json([
            'value' => $count,
            'label' => 'Tickets Pendientes',
        ]);
    }

    public function resolvedTickets(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());
        
        $count = $query->whereHas('status', function($q) {
            $q->whereIn('name', ['resuelto', 'cerrado']);
        })
        ->whereBetween('resolved_at', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        ])
        ->count();

        return response()->json([
            'value' => $count,
            'label' => 'Resueltos este mes',
        ]);
    }

    public function avgResolutionTime(Request $request)
    {
        try {
            $query = $this->applyUserFilters(Ticket::query(), $request->user());

            $avgMinutes = $query->whereNotNull('resolved_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 60) as avg_time')
                ->value('avg_time');

            $hours = $avgMinutes ? round($avgMinutes / 60, 1) : 0;

            return response()->json([
                'value' => $hours,
                'label' => 'Tiempo Promedio (horas)',
                'format' => 'hours',
            ]);
        } catch (\Exception $e) {
            \Log::error('avgResolutionTime error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to load metric',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // GRÁFICOS
    // ==========================================

    public function ticketsByStatus(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());
        
        $data = $query->select('status_id', DB::raw('count(*) as count'))
            ->with('status:id,name,color')
            ->groupBy('status_id')
            ->get()
            ->map(function($item) {
                return [
                    'label' => $item->status->name,
                    'value' => $item->count,
                    'color' => $item->status->color,
                ];
            });

        return response()->json([
            'labels' => $data->pluck('label'),
            'datasets' => [[
                'data' => $data->pluck('value'),
                'backgroundColor' => $data->pluck('color'),
            ]]
        ]);
    }

    public function ticketsByPriority(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());
        
        $data = $query->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->get();

        $colors = [
            'alta' => '#EF4444',
            'media' => '#F59E0B',
            'baja' => '#10B981',
        ];

        return response()->json([
            'labels' => $data->pluck('priority'),
            'datasets' => [[
                'label' => 'Tickets',
                'data' => $data->pluck('count'),
                'backgroundColor' => $data->pluck('priority')->map(fn($p) => $colors[$p] ?? '#6B7280'),
            ]]
        ]);
    }

    public function ticketsTrend(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);
        
        $query = $this->applyUserFilters(Ticket::query(), $request->user());

        // Tickets creados por día
        $created = $query->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Tickets resueltos por día
        $resolved = $this->applyUserFilters(Ticket::query(), $request->user())
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $startDate)
            ->selectRaw('DATE(resolved_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Generar todas las fechas
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = Carbon::now()->subDays($i)->format('Y-m-d');
        }

        return response()->json([
            'labels' => array_map(fn($d) => Carbon::parse($d)->format('d/m'), $dates),
            'datasets' => [
                [
                    'label' => 'Creados',
                    'data' => array_map(fn($d) => $created[$d] ?? 0, $dates),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => 'Resueltos',
                    'data' => array_map(fn($d) => $resolved[$d] ?? 0, $dates),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ]
        ]);
    }

    public function ticketsByArea(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());
        
        $data = $query->select('requester_area', DB::raw('count(*) as count'))
            ->groupBy('requester_area')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'labels' => $data->pluck('requester_area'),
            'datasets' => [[
                'label' => 'Tickets',
                'data' => $data->pluck('count'),
                'backgroundColor' => '#3B82F6',
            ]]
        ]);
    }

    public function ticketsByChannel(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());

        $data = $query->select('channel', DB::raw('count(*) as count'))
            ->groupBy('channel')
            ->get();

        $labels = [
            'portal' => 'Portal',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
        ];

        $colors = [
            'portal' => '#3B82F6',
            'email' => '#F59E0B',
            'whatsapp' => '#10B981',
        ];

        return response()->json([
            'labels' => $data->pluck('channel')->map(fn($c) => $labels[$c] ?? ($c ?: 'Sin canal')),
            'datasets' => [[
                'data' => $data->pluck('count'),
                'backgroundColor' => $data->pluck('channel')->map(fn($c) => $colors[$c] ?? '#6B7280'),
            ]]
        ]);
    }

    public function ticketsByCategory(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());

        $data = $query->select('category_id', DB::raw('count(*) as count'))
            ->with('category:id,name')
            ->groupBy('category_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'labels' => $data->map(fn($item) => $item->category->name ?? 'Sin categoría'),
            'datasets' => [[
                'label' => 'Tickets',
                'data' => $data->pluck('count'),
                'backgroundColor' => '#8B5CF6',
            ]]
        ]);
    }

    public function backlogAging(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());

        $open = $query->whereHas('status', function($q) {
            $q->whereNotIn('name', ['resuelto', 'cerrado']);
        })->with('status:id,name')->get();

        $buckets = [
            '0-2 días' => 0,
            '3-5 días' => 0,
            '6-10 días' => 0,
            '+10 días' => 0,
        ];

        foreach ($open as $ticket) {
            $days = $ticket->created_at->diffInDays(now());
            if ($days <= 2) $buckets['0-2 días']++;
            elseif ($days <= 5) $buckets['3-5 días']++;
            elseif ($days <= 10) $buckets['6-10 días']++;
            else $buckets['+10 días']++;
        }

        return response()->json([
            'labels' => array_keys($buckets),
            'datasets' => [[
                'label' => 'Tickets abiertos',
                'data' => array_values($buckets),
                'backgroundColor' => ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
            ]]
        ]);
    }

    public function validationRejectionRate(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());

        $totalValidated = $query->whereNotNull('validation_requested_at')->count();

        $query2 = $this->applyUserFilters(Ticket::query(), $request->user());
        $totalRejections = $query2->where('validation_rejected_count', '>', 0)->count();

        $rate = $totalValidated > 0 ? round(($totalRejections / $totalValidated) * 100, 1) : 0;

        return response()->json([
            'value' => $rate,
            'label' => 'Tasa de Rechazo de Validaciones',
            'format' => 'percent',
        ]);
    }

    public function unassignedOrStalledTickets(Request $request)
    {
        $user = $request->user();

        $query = Ticket::whereHas('status', function($q) {
            $q->whereNotIn('name', ['resuelto', 'cerrado']);
        });

        if ($user->role->name === 'agente') {
            $query->where('assigned_to', $user->id);
        } elseif ($user->role->name === 'supervisor') {
            $query->where(function($q) use ($user) {
                $q->whereNull('assigned_to')
                  ->orWhereHas('assignedAgent', function($q2) use ($user) {
                      $q2->where('area_id', $user->area_id);
                  });
            });
        }

        $stalledDays = 3;

        $tickets = $query->with('status', 'assignedAgent')
            ->get()
            ->filter(function($t) use ($stalledDays) {
                return is_null($t->assigned_to) || $t->created_at->diffInDays(now()) >= $stalledDays;
            })
            ->map(function($t) {
                return [
                    'ticket_number' => $t->ticket_number,
                    'status' => $t->status->name,
                    'priority' => $t->priority,
                    'agent' => $t->assignedAgent->name ?? 'Sin asignar',
                    'days_open' => (int) $t->created_at->diffInDays(now()),
                ];
            })
            ->values();

        return response()->json($tickets);
    }

    public function validationsRejectedByTeam(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());

        $tickets = $query->where('validation_rejected_count', '>', 0)
            ->with('assignedAgent', 'status')
            ->get()
            ->map(function($t) {
                return [
                    'ticket_number' => $t->ticket_number,
                    'agent' => $t->assignedAgent->name ?? 'Sin asignar',
                    'rejected_count' => $t->validation_rejected_count,
                    'status' => $t->status->name,
                ];
            });

        return response()->json($tickets);
    }

    public function ticketsNearSlaDeadline(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());

        $tickets = $query->whereHas('status', function($q) {
                $q->whereNotIn('name', ['resuelto', 'cerrado']);
            })
            ->whereNotNull('sla_resolution_due_at')
            ->with('status', 'assignedAgent')
            ->orderBy('sla_resolution_due_at')
            ->limit(15)
            ->get()
            ->map(function($t) {
                return [
                    'ticket_number' => $t->ticket_number,
                    'status' => $t->status->name,
                    'priority' => $t->priority,
                    'agent' => $t->assignedAgent->name ?? 'Sin asignar',
                    'sla_status' => $t->sla_status,
                    'sla_due_date' => $t->sla_resolution_due_at->format('d/m/Y H:i'),
                ];
            });

        return response()->json($tickets);
    }

    public function myPendingValidationTickets(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());

        $tickets = $query->whereHas('status', function($q) {
                $q->where('name', 'pendiente_validacion');
            })
            ->with('status', 'assignedAgent')
            ->orderBy('validation_requested_at')
            ->get()
            ->map(function($t) {
                return [
                    'ticket_number' => $t->ticket_number,
                    'agent' => $t->assignedAgent->name ?? 'Sin asignar',
                    'validation_requested_at' => $t->validation_requested_at?->format('d/m/Y H:i'),
                    'validation_deadline' => $t->validation_deadline?->format('d/m/Y H:i'),
                ];
            });

        return response()->json($tickets);
    }

    // ==========================================
    // TABLAS
    // ==========================================

    public function ticketsByAgent(Request $request)
    {
        $user = $request->user();
        
        $query = User::whereHas('role', function($q) {
            $q->where('name', 'agente');
        });

        // Si es supervisor, solo su área
        if ($user->role->name === 'supervisor') {
            $query->where('area_id', $user->area_id);
        }

        $agents = $query->with(['assignedTickets' => function($q) {
            $q->select('id', 'assigned_to', 'status_id', 'created_at', 'resolved_at', 'sla_due_date');
        }])->get()->map(function($agent) {
            $tickets = $agent->assignedTickets;
            $resolved = $tickets->filter(fn($t) => $t->resolved_at);
            
            return [
                'agent' => $agent->name,
                'assigned' => $tickets->count(),
                'resolved' => $resolved->count(),
                'in_progress' => $tickets->count() - $resolved->count(),
                'avg_time' => $this->calculateAvgTime($resolved),
                'sla_compliance' => $this->calculateSLACompliance($tickets),
            ];
        });

        return response()->json($agents);
    }

    public function slaCompliance(Request $request)
    {
        $query = $this->applyUserFilters(Ticket::query(), $request->user());
        
        $tickets = $query->whereNotNull('sla_due_date')
            ->with('status', 'assignedAgent')
            ->get()
            ->map(function($ticket) {
                $isCompliant = $ticket->resolved_at 
                    ? $ticket->resolved_at <= $ticket->sla_due_date 
                    : now() <= $ticket->sla_due_date;
                
                return [
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status->name,
                    'priority' => $ticket->priority,
                    'agent' => $ticket->assignedAgent->name ?? 'Sin asignar',
                    'sla_due_date' => $ticket->sla_due_date->format('d/m/Y H:i'),
                    'is_compliant' => $isCompliant,
                    'time_remaining' => $isCompliant 
                        ? $ticket->sla_due_date->diffForHumans()
                        : 'Vencido',
                ];
            });

        return response()->json($tickets);
    }

    // ==========================================
    // EXPORTACIÓN
    // ==========================================

    public function exportTickets(Request $request)
    {
        $format = $request->get('format', 'excel'); // excel, csv, pdf
        
        $query = $this->applyUserFilters(Ticket::query(), $request->user());
        $tickets = $query->with(['status', 'assignedAgent', 'area'])->get();

        // Aquí implementarías la lógica de exportación
        // usando paquetes como maatwebsite/excel o barryvdh/laravel-dompdf
        
        return response()->json([
            'message' => 'Exportación iniciada',
            'download_url' => '/downloads/tickets-' . time() . '.' . $format
        ]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function applyUserFilters($query, $user)
    {
        try {
            \Log::info('applyUserFilters called', ['user_id' => $user?->id, 'role' => $user?->role?->name]);
            
            if (!$user) {
                \Log::error('No user provided to applyUserFilters');
                return $query;
            }
            
            if ($user->role->name === 'agente') {
                \Log::info('Applying agent filter for user', ['user_id' => $user->id]);
                $query->where('assigned_to', $user->id);
            } elseif ($user->role->name === 'supervisor') {
                \Log::info('Applying supervisor filter for user', ['user_id' => $user->id, 'area_id' => $user->area_id]);
                $query->whereHas('assignedAgent', function($q) use ($user) {
                    $q->where('area_id', $user->area_id);
                });
            }
            
            return $query;
        } catch (\Exception $e) {
            \Log::error('applyUserFilters error', [
                'message' => $e->getMessage(),
                'user_id' => $user?->id,
                'role' => $user?->role?->name
            ]);
            throw $e;
        }
    }

    private function calculateChange($metric, $user)
    {
        // Comparar con el mes anterior
        $currentMonth = $this->applyUserFilters(Ticket::query(), $user)
            ->whereBetween('created_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth()
            ])->count();

        $lastMonth = $this->applyUserFilters(Ticket::query(), $user)
            ->whereBetween('created_at', [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth()
            ])->count();

        if ($lastMonth == 0) return 0;
        
        return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    private function calculateAvgTime($tickets)
    {
        if ($tickets->isEmpty()) return 0;
        
        $totalMinutes = $tickets->sum(function($ticket) {
            return $ticket->created_at->diffInMinutes($ticket->resolved_at);
        });
        
        return round($totalMinutes / $tickets->count() / 60, 1); // en horas
    }

    private function calculateSLACompliance($tickets)
    {
        if ($tickets->isEmpty()) return 0;
        
        $compliant = $tickets->filter(function($ticket) {
            if (!$ticket->resolved_at || !$ticket->sla_due_date) return false;
            return $ticket->resolved_at <= $ticket->sla_due_date;
        })->count();
        
        return round(($compliant / $tickets->count()) * 100, 1);
    }
}
