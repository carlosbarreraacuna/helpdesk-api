<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketComment;
use App\Models\TicketParticipant;
use App\Models\TicketStatus;
use App\Models\TicketValidation;
use App\Models\HelpdeskSetting;
use App\Models\User;
use App\Models\WidgetChatSession;
use App\Models\WidgetChatMessage;
use App\Events\WidgetMessageSent;
use App\Services\EmailChannelService;
use App\Services\WhatsAppService;
use App\Services\TicketAssignmentService;
use App\Services\SlaService;
use App\Mail\TicketCreatedMail;
use App\Mail\TicketValidationRequestMail;
use App\Mail\TicketValidationResultMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @tags Tickets
 */
class TicketController extends Controller
{
    /**
     * Get all ticket statuses
     */
    public function getStatuses()
    {
        $statuses = TicketStatus::orderBy('order')->get();
        return response()->json($statuses);
    }

    /**
     * Resumen de tickets del usuario autenticado (portal funcionarios)
     */
    public function myTicketsStats(Request $request)
    {
        $user = $request->user();

        $participantTicketIds = TicketParticipant::where('user_id', $user->id)->pluck('ticket_id');
        $query = Ticket::where(function ($q) use ($user, $participantTicketIds) {
            $q->where('requester_email', $user->email)
              ->orWhereIn('id', $participantTicketIds);
        });

        $tickets = $query->with('status:id,name')->get();

        return response()->json([
            'total' => $tickets->count(),
            'abiertos' => $tickets->filter(fn($t) => !in_array($t->status->name, ['cerrado', 'resuelto']))->count(),
            'resueltos' => $tickets->filter(fn($t) => in_array($t->status->name, ['cerrado', 'resuelto']))->count(),
            'pendiente_validacion' => $tickets->filter(fn($t) => $t->status->name === 'pendiente_validacion')->count(),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Ticket::with(['status', 'assignedAgent', 'workGroup', 'createdBy']);

        // Filter according to role
        if ($user->role->name === 'usuario') {
            // User sees their own tickets OR tickets where they are a participant
            $participantTicketIds = TicketParticipant::where('user_id', $user->id)->pluck('ticket_id');
            $query->where(function ($q) use ($user, $participantTicketIds) {
                $q->where('requester_email', $user->email)
                  ->orWhereIn('id', $participantTicketIds);
            });
        } elseif ($user->role->name === 'agente') {
            // Agent only sees their assigned tickets
            $query->where('assigned_to', $user->id);
        }
        // Admin and Supervisor see all tickets (no filter)

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = '%' . $request->search . '%';
            \Log::info('Búsqueda aplicada:', ['search' => $request->search]);
            $query->where(function($q) use ($searchTerm) {
                // Case-insensitive search como en UserController
                $q->whereRaw('LOWER(ticket_number) LIKE LOWER(?)', [$searchTerm])
                  ->orWhereRaw('LOWER(requester_name) LIKE LOWER(?)', [$searchTerm])
                  ->orWhereRaw('LOWER(requester_email) LIKE LOWER(?)', [$searchTerm])
                  ->orWhereRaw('LOWER(description) LIKE LOWER(?)', [$searchTerm]);
            });
        }

        // Apply status filter
        if ($request->has('status_id') && !empty($request->status_id)) {
            \Log::info('Filtro de estado aplicado:', ['status_id' => $request->status_id]);
            $query->where('status_id', $request->status_id);
        }

        // Apply priority filter
        if ($request->has('priority') && !empty($request->priority)) {
            \Log::info('Filtro de prioridad aplicado:', ['priority' => $request->priority]);
            $query->where('priority', $request->priority);
        }

        // Apply area filter (if requester_area matches area_id)
        if ($request->has('area_id') && !empty($request->area_id)) {
            \Log::info('Filtro de área aplicado:', ['area_id' => $request->area_id]);
            $query->where('requester_area', 'like', '%' . $request->area_id . '%');
        }

        // Get per_page parameter with default of 10
        $perPage = $request->get('per_page', 10);
        \Log::info('Parámetros de paginación:', ['per_page' => $perPage]);
        
        $results = $query->orderByDesc('created_at')->paginate($perPage);
        \Log::info('Resultados de la consulta:', ['total' => $results->total(), 'per_page' => $results->perPage()]);

        // For portal users, append latest agent comment timestamp to detect unread replies
        if ($user->role->name === 'usuario') {
            $ticketIds = $results->pluck('id');
            $agentUserIds = User::whereHas('role', fn($q) => $q->whereIn('name', ['agente', 'supervisor', 'admin']))->pluck('id');
            $latestComments = TicketComment::whereIn('ticket_id', $ticketIds)
                ->where('is_internal', false)
                ->whereIn('user_id', $agentUserIds)
                ->selectRaw('ticket_id, MAX(created_at) as latest_at')
                ->groupBy('ticket_id')
                ->pluck('latest_at', 'ticket_id');

            $results->getCollection()->transform(function ($ticket) use ($latestComments) {
                $ticket->latest_agent_comment_at = $latestComments[$ticket->id] ?? null;
                return $ticket;
            });
        }

        // Hypothetical SLA due date per agent/group matrix, for the "SLA Agente"
        // / "SLA Grupo" columns in the ticket list — null when that agent/group
        // has no own SLA matrix configured (it just inherits the global one).
        $slaService = app(SlaService::class);
        $results->getCollection()->transform(function ($ticket) use ($slaService) {
            $ticket->sla_agent_due_at = $slaService->hypotheticalDueAt($ticket, 'agent');
            $ticket->sla_group_due_at = $slaService->hypotheticalDueAt($ticket, 'group');
            return $ticket;
        });

        return response()->json($results);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'requester_name'      => 'required|string|max:255',
            'requester_email'     => 'required|email',
            'requester_area'      => 'required|string',
            'description'         => 'required|string',
            'priority'            => 'required|in:baja,media,alta',
            'category_id'         => 'nullable|exists:ticket_categories,id',
            'attachments'         => 'nullable|array|max:20',
            'attachments.*'       => 'file',
            'bot_context'         => 'nullable|string',
            'bot_kb_article_id'   => 'nullable|integer',
            'bot_kb_article_title'=> 'nullable|string',
            'bot_kb_helpful'      => 'nullable|in:0,1',
        ]);

        // Generate unique ticket number
        $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(Ticket::count() + 1, 4, '0', STR_PAD_LEFT);
        
        // Generate verification code
        $verificationCode = rand(100000, 999999);

        // Validate total size ≤ 40 MB
        $uploadedFiles = $request->file('attachments') ?? [];
        $totalBytes    = array_sum(array_map(fn($f) => $f->getSize(), $uploadedFiles));
        if ($totalBytes > 40 * 1024 * 1024) {
            return response()->json(['message' => 'El total de archivos adjuntos no puede superar 40 MB'], 422);
        }

        // Upload files if present (first kept in attachment_path for backwards compat)
        $attachmentPath = null;
        if (!empty($uploadedFiles)) {
            $first = array_shift($uploadedFiles);
            $attachmentPath = $first->store('attachments', 's3');
        }

        // Get "new" status
        $newStatus = TicketStatus::where('name', 'nuevo')->first();

        $ticket = Ticket::create([
            'ticket_number'    => $ticketNumber,
            'requester_name'   => $validated['requester_name'],
            'requester_email'  => $validated['requester_email'],
            'requester_area'   => $validated['requester_area'],
            'description'      => $validated['description'],
            'attachment_path'  => $attachmentPath,
            'verification_code'=> $verificationCode,
            'priority'         => $validated['priority'],
            'category_id'      => $validated['category_id'] ?? null,
            'status_id'        => $newStatus->id,
        ]);

        // Store the first file's info in ticket_attachments as well (if uploaded)
        if ($attachmentPath) {
            $firstFile = $request->file('attachments')[0] ?? null;
            \App\Models\TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'path'      => $attachmentPath,
                'name'      => $firstFile ? $firstFile->getClientOriginalName() : basename($attachmentPath),
                'mime'      => $firstFile ? $firstFile->getMimeType() : null,
                'size'      => $firstFile ? $firstFile->getSize() : null,
            ]);
        }

        // Store remaining files
        foreach ($uploadedFiles as $file) {
            if (!$file || !$file->isValid()) continue;
            $path = $file->store('attachments', 's3');
            \App\Models\TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'path'      => $path,
                'name'      => $file->getClientOriginalName(),
                'mime'      => $file->getMimeType(),
                'size'      => $file->getSize(),
            ]);
        }

        // Calculate and assign SLA due dates
        app(SlaService::class)->calculateAndAssign($ticket);

        // Register in history
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'action'    => 'creado',
            'new_value' => 'Ticket creado desde portal público',
        ]);

        // Auto-assign to work group
        app(TicketAssignmentService::class)->assign($ticket);

        // If created from chatbot, save transcript as system comment
        $botContext      = $request->input('bot_context');
        $botKbTitle      = $request->input('bot_kb_article_title');
        $botKbHelpful    = $request->filled('bot_kb_helpful') ? (bool) $request->input('bot_kb_helpful') : null;

        if ($botContext) {
            $lines = ["🤖 Conversación previa con el asistente virtual:\n"];
            foreach (explode("\n", $botContext) as $line) {
                if (trim($line) !== '') {
                    $lines[] = $line;
                }
            }
            if ($botKbTitle) {
                $helpfulText = $botKbHelpful === true ? 'Sí' : ($botKbHelpful === false ? 'No' : 'No indicado');
                $lines[] = "\n📖 Artículo sugerido: {$botKbTitle} | ¿Fue útil? {$helpfulText}";
            }
            \App\Models\TicketComment::create([
                'ticket_id'   => $ticket->id,
                'comment'     => implode("\n", $lines),
                'user_id'     => null,
                'is_internal' => false,
            ]);
        }

        // Send email notifications
        $ticket->load(['status', 'category']);
        try {
            Mail::to($ticket->requester_email)
                ->queue(new TicketCreatedMail($ticket, $botContext, $botKbTitle, $botKbHelpful, false));
        } catch (\Exception $e) {
            \Log::warning('Failed to send ticket created email to requester', ['error' => $e->getMessage()]);
        }

        $ticket->refresh()->load(['assignedAgent']);
        if ($ticket->assignedAgent) {
            try {
                Mail::to($ticket->assignedAgent->email)
                    ->queue(new TicketCreatedMail($ticket, $botContext, $botKbTitle, $botKbHelpful, true));
            } catch (\Exception $e) {
                \Log::warning('Failed to send ticket created email to agent', ['error' => $e->getMessage()]);
            }
        }

        broadcast(new \App\Events\TicketCreated($ticket));

        return response()->json([
            'message' => 'Ticket creado exitosamente',
            'ticket_number' => $ticketNumber,
            'verification_code' => $verificationCode,
            'category' => $ticket->category?->name,
            'assigned_agent' => $ticket->assignedAgent?->name,
        ], 201);
    }

    public function show($id)
    {
        $user = request()->user();
        $ticket = Ticket::with(['status', 'assignedAgent', 'createdBy', 'comments.user', 'comments.attachments', 'history.user'])
            ->findOrFail($id);

        // Check permissions for role 'usuario'
        if ($user->role->name === 'usuario') {
            $isRequester  = $ticket->requester_email === $user->email;
            $isParticipant = TicketParticipant::where('ticket_id', $ticket->id)->where('user_id', $user->id)->exists();
            if (!$isRequester && !$isParticipant) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        // Mark as read when an agent/admin opens it
        if (in_array($user->role->name, ['agente', 'supervisor', 'admin']) && !$ticket->is_read) {
            $ticket->update(['is_read' => true]);
        }

        return response()->json($ticket);
    }

    public function peek($id)
    {
        $user   = request()->user();
        $ticket = Ticket::with(['status', 'assignedAgent', 'createdBy'])
            ->findOrFail($id);

        if ($user->role->name === 'usuario') {
            $isRequester  = $ticket->requester_email === $user->email;
            $isParticipant = TicketParticipant::where('ticket_id', $ticket->id)->where('user_id', $user->id)->exists();
            if (!$isRequester && !$isParticipant) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        return response()->json($ticket);
    }

    public function assign(Request $request, $id)
    {
        $user = $request->user();
        
        // Verify permissions
        if (!in_array($user->role->name, ['supervisor', 'admin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'priority' => 'nullable|in:alta,media,baja',
        ]);

        $ticket = Ticket::findOrFail($id);

        // Reassigning a ticket that already had a different agent counts as
        // an escalation/transfer; the first assignment is just "asignado".
        $isReassignment = $ticket->assigned_to && $ticket->assigned_to !== (int) $validated['agent_id'];
        $statusName     = $isReassignment ? 'escalado' : 'asignado';

        $ticket->update([
            'assigned_to' => $validated['agent_id'],
            'status_id' => TicketStatus::where('name', $statusName)->first()->id,
            'priority' => $validated['priority'] ?? $ticket->priority,
        ]);

        // Recalculate SLA now that the agent (and possibly priority) changed —
        // applies the agent's own SLA matrix if configured, else the group's,
        // else the global config.
        app(SlaService::class)->recalculateTicket($ticket, $user->id);

        // History
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => $isReassignment ? 'escalado' : 'asignado',
            'new_value' => ($isReassignment ? "Reasignado a " : "Asignado a ") . User::find($validated['agent_id'])->name,
        ]);

        return response()->json(['message' => 'Ticket asignado correctamente', 'ticket' => $ticket]);
    }

    public function escalate(Request $request, $id)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        // Verify that the agent can escalate (must be assigned to them)
        if ($user->role->name === 'agente' && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string',
            'supervisor_id' => 'required|exists:users,id',
        ]);

        $ticket->update([
            'assigned_to' => $validated['supervisor_id'],
            'status_id' => TicketStatus::where('name', 'escalado')->first()->id,
        ]);

        app(SlaService::class)->recalculateTicket($ticket, $user->id);

        // History
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'escalado',
            'new_value' => $validated['reason'],
        ]);

        return response()->json(['message' => 'Ticket escalado correctamente']);
    }

    public function returnToGroup(Request $request, $id)
    {
        $user   = $request->user();
        $ticket = Ticket::findOrFail($id);

        if ($user->role->name === 'agente' && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        app(TicketAssignmentService::class)->returnToGroup($ticket, $user, $validated['reason'] ?? '');

        return response()->json(['message' => 'Ticket devuelto al grupo correctamente.']);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|exists:ticket_statuses,name',
        ]);

        $ticket = Ticket::with('status')->findOrFail($id);
        $oldStatusName = $ticket->status->name;
        $newStatus = TicketStatus::where('name', $validated['status'])->first();

        $ticket->update(['status_id' => $newStatus->id]);

        if ($validated['status'] === 'resuelto') {
            $ticket->update(['resolved_at' => now()]);
            // Auto-trigger validation request when resolved
            $this->triggerValidationRequest($ticket, $request->user());
            return response()->json(['message' => 'Estado actualizado y solicitud de validación enviada al usuario']);
        } elseif ($validated['status'] === 'cerrado') {
            $ticket->update(['closed_at' => now()]);
        }

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $request->user()->id,
            'action'    => 'cambio_estado',
            'old_value' => $oldStatusName,
            'new_value' => $validated['status'],
        ]);

        return response()->json(['message' => 'Estado actualizado correctamente']);
    }

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'comment'     => 'nullable|string',
            'is_internal' => 'boolean',
        ]);

        // Collect uploaded files — accepts files[], files, or file field names
        $uploadedFiles = $this->collectUploadedFiles($request, ['files', 'file']);

        $comment_text = $request->input('comment') ?? '';

        if (empty(trim($comment_text)) && count($uploadedFiles) === 0) {
            return response()->json(['message' => 'Se requiere un mensaje o un archivo'], 422);
        }

        $user        = $request->user();
        $ticket      = Ticket::with('status')->findOrFail($id);
        $is_internal = $request->boolean('is_internal', false);

        if ($user->role->name === 'usuario') {
            $isRequester  = $ticket->requester_email === $user->email;
            $isParticipant = TicketParticipant::where('ticket_id', $ticket->id)->where('user_id', $user->id)->exists();
            if (!$isRequester && !$isParticipant) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        // Any public reply from staff moves the ticket to "en_progreso",
        // except while it's closed or waiting on the requester's validation
        // (those flows are driven explicitly, not by chat activity).
        if (!$is_internal && in_array($user->role->name, ['agente', 'supervisor', 'admin'])
            && !in_array($ticket->status->name, ['cerrado', 'pendiente_validacion'])) {
            $inProgress = TicketStatus::where('name', 'en_progreso')->first();
            if ($inProgress && $ticket->status_id !== $inProgress->id) {
                $ticket->update(['status_id' => $inProgress->id]);
            }
        }

        $comment = TicketComment::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $user->id,
            'comment'     => $comment_text,
            'is_internal' => $is_internal,
        ]);

        foreach ($uploadedFiles as $file) {
            if (!$file || !$file->isValid()) continue;
            if ($file->getSize() > 20 * 1024 * 1024) continue; // 20 MB max
            $path = $file->store("attachments/tickets/{$ticket->id}", 's3');
            \App\Models\TicketCommentAttachment::create([
                'comment_id' => $comment->id,
                'path'       => $path,
                'name'       => $file->getClientOriginalName(),
                'mime'       => $file->getMimeType(),
            ]);
        }

        $comment->load('attachments');

        // If the ticket has an active widget session and the comment is public,
        // create a WidgetChatMessage so the user's chat receives it in real time.
        if (!$is_internal) {
            $session = WidgetChatSession::where('ticket_id', $ticket->id)
                ->whereIn('status', ['active', 'pending'])
                ->first();

            if ($session) {
                $widgetMsg = WidgetChatMessage::create([
                    'session_id'      => $session->id,
                    'sender_id'       => $user->id,
                    'sender_type'     => 'agent',
                    'body'            => $comment_text,
                    'attachment_path' => null,
                    'attachment_name' => null,
                    'is_read'         => false,
                ]);
                $widgetMsg->load('sender:id,name');
                broadcast(new WidgetMessageSent($widgetMsg));
            }
        }

        broadcast(new \App\Events\TicketCommentAdded($comment));

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => $is_internal ? 'nota_interna' : 'comentario',
            'new_value' => mb_substr($comment_text ?? '', 0, 255),
        ]);

        return response()->json(['message' => 'Comentario agregado', 'comment' => $comment]);
    }

    public function getHistory($id)
    {
        $user   = request()->user();
        $ticket = Ticket::with(['status', 'assignedAgent'])->findOrFail($id);

        if ($user->role->name === 'usuario') {
            $isRequester   = $ticket->requester_email === $user->email;
            $isParticipant = TicketParticipant::where('ticket_id', $ticket->id)->where('user_id', $user->id)->exists();
            if (!$isRequester && !$isParticipant) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        $history  = TicketHistory::with('user:id,name,email')
            ->where('ticket_id', $id)
            ->orderBy('created_at')
            ->get();

        $comments = \App\Models\TicketComment::with(['user:id,name,email', 'attachments'])
            ->where('ticket_id', $id)
            ->orderBy('created_at')
            ->get();

        $timeline = collect();

        foreach ($history as $h) {
            $timeline->push([
                'id'          => 'h-' . $h->id,
                'type'        => $h->action,
                'category'    => 'history',
                'description' => $this->formatHistoryLabel($h->action, $h->old_value, $h->new_value),
                'old_value'   => $h->old_value,
                'new_value'   => $h->new_value,
                'user'        => $h->user ? ['name' => $h->user->name, 'email' => $h->user->email] : null,
                'timestamp'   => $h->created_at,
            ]);
        }

        foreach ($comments as $c) {
            $timeline->push([
                'id'               => 'c-' . $c->id,
                'type'             => $c->is_internal ? 'nota_interna' : 'comentario',
                'category'         => 'comment',
                'description'      => mb_substr($c->comment ?? '', 0, 180) . (mb_strlen($c->comment ?? '') > 180 ? '…' : ''),
                'user'             => $c->user ? ['name' => $c->user->name, 'email' => $c->user->email] : null,
                'attachments_count'=> $c->attachments->count(),
                'is_internal'      => $c->is_internal,
                'timestamp'        => $c->created_at,
            ]);
        }

        $sorted = $timeline->sortBy('timestamp')->values();

        return response()->json([
            'ticket'   => [
                'id'             => $ticket->id,
                'ticket_number'  => $ticket->ticket_number,
                'requester_name' => $ticket->requester_name,
                'requester_email'=> $ticket->requester_email,
                'status'         => $ticket->status,
                'assigned_agent' => $ticket->assignedAgent ? ['name' => $ticket->assignedAgent->name] : null,
                'created_at'     => $ticket->created_at,
                'closed_at'      => $ticket->closed_at,
            ],
            'timeline' => $sorted,
            'summary'  => [
                'total_events'   => $sorted->count(),
                'total_comments' => $comments->count(),
                'total_history'  => $history->count(),
            ],
        ]);
    }

    private function formatHistoryLabel(string $action, ?string $old, ?string $new): string
    {
        return match ($action) {
            'creado'                 => 'Ticket creado',
            'asignado'               => $new ?? 'Ticket asignado',
            'escalado'               => 'Ticket escalado' . ($new ? ": {$new}" : ''),
            'cambio_estado'          => 'Estado cambiado' . ($old && $new ? ' de "' . $old . '" a "' . $new . '"' : ($new ? ' a "' . $new . '"' : '')),
            'cerrado'                => 'Ticket cerrado',
            'respuesta_canal'        => $new ?? 'Respuesta enviada por canal',
            'validacion_solicitada'  => 'Validación solicitada al usuario',
            'validacion_aprobada'    => 'Usuario aprobó la solución' . ($new ? ": {$new}" : ''),
            'validacion_rechazada'   => 'Usuario rechazó — aún hay problemas' . ($new ? ": {$new}" : ''),
            default                  => ucfirst(str_replace('_', ' ', $action)) . ($new ? ": {$new}" : ''),
        };
    }

    public function getComments($id)
    {
        $user = request()->user();
        $ticket = Ticket::findOrFail($id);
        
        // Check permissions for role 'usuario'
        if ($user->role->name === 'usuario') {
            $isRequester  = $ticket->requester_email === $user->email;
            $isParticipant = TicketParticipant::where('ticket_id', $ticket->id)->where('user_id', $user->id)->exists();
            if (!$isRequester && !$isParticipant) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        $comments = TicketComment::with(['user', 'attachments'])
            ->where('ticket_id', $ticket->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($comments);
    }

    public function searchPublic(Request $request)
    {
        $validated = $request->validate([
            'ticket_number' => 'required|string',
            'verification_code' => 'required|string',
        ]);

        $ticket = Ticket::where('ticket_number', $validated['ticket_number'])
                        ->where('verification_code', $validated['verification_code'])
                        ->with(['status', 'comments' => function($q) {
                            $q->where('is_internal', false);
                        }, 'history'])
                        ->first();

        if (!$ticket) {
            return response()->json(['message' => 'Ticket no encontrado o código inválido'], 404);
        }

        return response()->json($ticket);
    }

    /**
     * Reply to a ticket using a specific channel (email, whatsapp, portal).
     * Also adds the reply as a public comment so the history stays unified.
     */
    public function replyByChannel(Request $request, $id)
    {
        $request->validate([
            'message' => 'nullable|string',
            'channel' => 'required|in:email,whatsapp,portal',
        ]);

        $message = $request->input('message') ?? '';
        $channel = $request->input('channel');

        $uploadedFiles = $this->collectUploadedFiles($request, ['files', 'file']);

        if (empty(trim($message)) && count($uploadedFiles) === 0) {
            return response()->json(['message' => 'Se requiere un mensaje o un archivo'], 422);
        }

        $user   = $request->user();
        $ticket = Ticket::with(['status', 'assignedAgent'])->findOrFail($id);

        // Any public reply from staff moves the ticket to "en_progreso",
        // except while it's closed or waiting on the requester's validation
        // (those flows are driven explicitly, not by chat activity).
        if (in_array($user->role->name, ['agente', 'supervisor', 'admin'])
            && !in_array($ticket->status->name, ['cerrado', 'pendiente_validacion'])) {
            $inProgress = TicketStatus::where('name', 'en_progreso')->first();
            if ($inProgress && $ticket->status_id !== $inProgress->id) {
                $ticket->update(['status_id' => $inProgress->id]);
            }
        }

        // Add to ticket timeline as a public comment
        $replyComment = TicketComment::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $user->id,
            'comment'     => $message,
            'is_internal' => false,
        ]);

        foreach ($uploadedFiles as $file) {
            if (!$file || !$file->isValid()) continue;
            if ($file->getSize() > 20 * 1024 * 1024) continue;
            $path = $file->store("attachments/tickets/{$ticket->id}", 's3');
            \App\Models\TicketCommentAttachment::create([
                'comment_id' => $replyComment->id,
                'path'       => $path,
                'name'       => $file->getClientOriginalName(),
                'mime'       => $file->getMimeType(),
            ]);
        }

        $replyComment->load('attachments');
        broadcast(new \App\Events\TicketCommentAdded($replyComment));

        $sent = false;

        switch ($channel) {
            case 'email':
            case 'portal':
                $emailService = app(EmailChannelService::class);
                $sent = $emailService->sendReply($ticket, $message, $user->id, $replyComment->attachments, $channel);
                break;

            case 'whatsapp':
                $requester = \App\Models\User::where('email', $ticket->requester_email)->first();
                if ($requester && $requester->whatsapp_phone) {
                    $wa = app(WhatsAppService::class);
                    $wa->sendText($requester->whatsapp_phone, $message);
                    $sent = true;
                }
                break;
        }

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'respuesta_canal',
            'new_value' => "Respuesta enviada por canal: {$channel}",
        ]);

        $replyComment->load(['user', 'attachments']);

        return response()->json([
            'message'   => 'Respuesta enviada',
            'channel'   => $channel,
            'delivered' => $sent,
            'comment'   => $replyComment,
        ]);
    }

    // ── Participants ──────────────────────────────────────────────────────────

    public function getParticipants($id)
    {
        $ticket = Ticket::findOrFail($id);
        $participants = $ticket->participants()->with('role')->get()->map(fn($u) => [
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'role'  => $u->role?->name,
        ]);
        return response()->json($participants);
    }

    public function addParticipant(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role->name, ['admin', 'supervisor', 'agente'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $ticket = Ticket::findOrFail($id);

        TicketParticipant::firstOrCreate(
            ['ticket_id' => $ticket->id, 'user_id' => $validated['user_id']],
            ['added_by'  => $user->id]
        );

        $participant = User::find($validated['user_id']);
        return response()->json([
            'id'    => $participant->id,
            'name'  => $participant->name,
            'email' => $participant->email,
        ]);
    }

    public function removeParticipant(Request $request, $id, $userId)
    {
        $user = $request->user();
        if (!in_array($user->role->name, ['admin', 'supervisor', 'agente'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        TicketParticipant::where('ticket_id', $id)->where('user_id', $userId)->delete();
        return response()->json(['message' => 'Participante eliminado']);
    }

    public function close(Request $request, $id)
    {
        $user   = $request->user();
        $ticket = Ticket::with('status')->findOrFail($id);

        // Only the assigned agent and supervisors/admins can close
        if ($user->role->name === 'agente' && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Block closure while the ticket is waiting on the user's validation
        if ($ticket->status->name === 'pendiente_validacion') {
            return response()->json([
                'message' => 'El ticket no puede cerrarse hasta que el usuario confirme que la solución fue satisfactoria.',
                'validation_pending' => true,
            ], 422);
        }

        $ticket->update([
            'status_id'      => TicketStatus::where('name', 'cerrado')->first()->id,
            'closed_at'      => now(),
            'validation_token' => null,
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'cerrado',
            'new_value' => 'Ticket cerrado',
        ]);

        return response()->json(['message' => 'Ticket cerrado correctamente']);
    }

    public function reopen(Request $request, $id)
    {
        $user   = $request->user();
        $ticket = Ticket::with('status')->findOrFail($id);

        if ($ticket->status->name !== 'cerrado') {
            return response()->json(['message' => 'Solo se pueden reabrir tickets cerrados'], 422);
        }

        $ticket->update([
            'status_id'                 => TicketStatus::where('name', 'reabierto')->first()->id,
            'closed_at'                 => null,
            'validation_requested_at'   => null,
            'validation_deadline'       => null,
            'validation_token'          => null,
            'validation_approved_at'    => null,
            'validation_rejected_count' => 0,
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'reabierto',
            'new_value' => $request->input('reason') ?: 'Ticket reabierto',
        ]);

        return response()->json(['message' => 'Ticket reabierto correctamente']);
    }

    public function requestValidation(Request $request, $id)
    {
        $user   = $request->user();
        $ticket = Ticket::with('status')->findOrFail($id);

        if (!in_array($user->role->name, ['agente', 'supervisor', 'admin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($user->role->name === 'agente' && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $this->triggerValidationRequest($ticket, $user);

        return response()->json(['message' => 'Solicitud de validación enviada al usuario']);
    }

    private function triggerValidationRequest(Ticket $ticket, ?User $requestedBy): void
    {
        $pendingStatus  = TicketStatus::where('name', 'pendiente_validacion')->first();
        $timeoutHours   = (int) HelpdeskSetting::get('validation_timeout_hours', 48);
        $token          = Str::random(48);
        $frontendUrl    = config('app.frontend_url', 'http://localhost:3000');
        $validationUrl  = "{$frontendUrl}/validate/{$token}";

        $ticket->update([
            'status_id'               => $pendingStatus->id,
            'validation_requested_at' => now(),
            'validation_deadline'     => now()->addHours($timeoutHours),
            'validation_token'        => $token,
            'validation_approved_at'  => null,
        ]);

        TicketValidation::create([
            'ticket_id'           => $ticket->id,
            'action'              => 'requested',
            'performed_by_user_id'=> $requestedBy?->id,
            'performed_by_email'  => $requestedBy?->email,
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $requestedBy?->id,
            'action'    => 'validacion_solicitada',
            'new_value' => 'Solicitud de validación enviada al usuario',
        ]);

        try {
            Mail::to($ticket->requester_email)
                ->queue(new TicketValidationRequestMail($ticket, $validationUrl));
        } catch (\Exception $e) {
            \Log::warning('Failed to send validation request email', ['error' => $e->getMessage()]);
        }
    }

    public function validateTicket(Request $request, string $token)
    {
        $validated = $request->validate([
            'action'  => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validated['action'] === 'rejected') {
            $request->validate(['comment' => 'required|string|max:1000']);
        }

        $ticket = Ticket::with(['status', 'assignedAgent'])
            ->where('validation_token', $token)
            ->firstOrFail();

        $pendingStatus = TicketStatus::where('name', 'pendiente_validacion')->first();
        if ($ticket->status_id !== $pendingStatus->id) {
            return response()->json(['message' => 'Este enlace ya no es válido o el ticket fue procesado.'], 422);
        }

        $user         = $request->user();
        $performerName = $user?->name ?? $ticket->requester_name;

        return $this->processValidationResult($ticket, $validated['action'], $validated['comment'] ?? null, $performerName, $user);
    }

    public function validateByPortal(Request $request, $id)
    {
        $validated = $request->validate([
            'action'  => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validated['action'] === 'rejected') {
            $request->validate(['comment' => 'required|string|max:1000']);
        }

        $user   = $request->user();
        $ticket = Ticket::with(['status', 'assignedAgent'])->findOrFail($id);

        // Only the requester (by email) can validate
        if ($ticket->requester_email !== $user->email) {
            return response()->json(['message' => 'Solo el solicitante puede validar este ticket.'], 403);
        }

        $pendingStatus = TicketStatus::where('name', 'pendiente_validacion')->first();
        if ($ticket->status_id !== $pendingStatus->id) {
            return response()->json(['message' => 'Este ticket no está pendiente de validación.'], 422);
        }

        // Delegate to shared logic by temporarily setting a fake token path
        $fakeRequest = new \Illuminate\Http\Request();
        $fakeRequest->merge([
            'action'  => $validated['action'],
            'comment' => $validated['comment'] ?? null,
        ]);
        $fakeRequest->setUserResolver(fn() => $user);

        return $this->processValidationResult($ticket, $validated['action'], $validated['comment'] ?? null, $user->name, $user);
    }

    private function processValidationResult(Ticket $ticket, string $action, ?string $comment, string $performerName, ?User $user): \Illuminate\Http\JsonResponse
    {
        $performerEmail = $user?->email ?? $ticket->requester_email;

        TicketValidation::create([
            'ticket_id'            => $ticket->id,
            'action'               => $action,
            'comment'              => $comment,
            'performed_by_email'   => $performerEmail,
            'performed_by_user_id' => $user?->id,
        ]);

        if ($action === 'approved') {
            $approvedStatus = TicketStatus::where('name', 'resuelto')->first();
            $ticket->update([
                'status_id'              => $approvedStatus->id,
                'validation_approved_at' => now(),
                'validation_token'       => null,
            ]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id'   => $user?->id,
                'action'    => 'validacion_aprobada',
                'new_value' => 'Usuario confirmó que la solución fue satisfactoria' . ($comment ? ": {$comment}" : ''),
            ]);

            $agentEmail = $ticket->assignedAgent?->email;
            if ($agentEmail) {
                try {
                    Mail::to($agentEmail)->queue(new TicketValidationResultMail($ticket, 'approved', $comment, $performerName));
                } catch (\Exception $e) {
                    \Log::warning('Failed to send validation approved email', ['error' => $e->getMessage()]);
                }
            }

            return response()->json(['message' => 'Gracias por confirmar. El agente podrá cerrar el ticket.', 'action' => 'approved']);
        }

        // Rejected
        $maxRejections = (int) HelpdeskSetting::get('max_validation_rejections', 3);
        $newCount      = $ticket->validation_rejected_count + 1;
        $inProgressStatus = TicketStatus::where('name', 'en_progreso')->first();

        $ticket->update([
            'status_id'                 => $inProgressStatus->id,
            'validation_requested_at'   => null,
            'validation_deadline'       => null,
            'validation_token'          => null,
            'validation_rejected_count' => $newCount,
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user?->id,
            'action'    => 'validacion_rechazada',
            'new_value' => "Usuario rechazó la validación ({$newCount}): " . ($comment ?? ''),
        ]);

        $agentEmail = $ticket->assignedAgent?->email;
        if ($agentEmail) {
            try {
                Mail::to($agentEmail)->queue(new TicketValidationResultMail($ticket, 'rejected', $comment, $performerName));
            } catch (\Exception $e) {
                \Log::warning('Failed to send validation rejected email', ['error' => $e->getMessage()]);
            }
        }

        if ($newCount >= $maxRejections) {
            $escalatedStatus = TicketStatus::where('name', 'escalado')->first();
            $ticket->update(['status_id' => $escalatedStatus->id]);
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id'   => null,
                'action'    => 'escalado',
                'new_value' => "Escalado automáticamente tras {$newCount} rechazos de validación.",
            ]);
        }

        return response()->json(['message' => 'Hemos recibido tu reporte. El equipo revisará el problema.', 'action' => 'rejected']);
    }

    public function getValidationStatus(Request $request, $id)
    {
        $ticket = Ticket::with(['status', 'validations'])->findOrFail($id);

        return response()->json([
            'validation_requested_at'  => $ticket->validation_requested_at,
            'validation_deadline'      => $ticket->validation_deadline,
            'validation_approved_at'   => $ticket->validation_approved_at,
            'validation_rejected_count'=> $ticket->validation_rejected_count,
            'status'                   => $ticket->status->name,
            'can_close'                => $ticket->status->name !== 'pendiente_validacion',
            'history'                  => $ticket->validations->map(fn($v) => [
                'action'     => $v->action,
                'comment'    => $v->comment,
                'by'         => $v->performed_by_email,
                'created_at' => $v->created_at,
            ]),
        ]);
    }

    private function collectUploadedFiles(Request $request, array $fieldNames): array
    {
        $files = [];
        foreach ($fieldNames as $field) {
            $input = $request->file($field);
            if ($input === null) continue;
            if (is_array($input)) {
                foreach ($input as $f) {
                    if ($f instanceof \Illuminate\Http\UploadedFile) $files[] = $f;
                }
            } elseif ($input instanceof \Illuminate\Http\UploadedFile) {
                $files[] = $input;
            }
        }
        return array_filter($files, fn($f) => $f->isValid());
    }
}
