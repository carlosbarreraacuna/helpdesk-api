<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\SlaConfig;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Ticket::with(['status', 'assignedAgent', 'createdBy']);

        // Filter according to role
        if ($user->role->name === 'usuario') {
            // User only sees their own tickets
            $query->where('requester_email', $user->email);
        } elseif ($user->role->name === 'agente') {
            // Agent only sees their assigned tickets
            $query->where('assigned_to', $user->id);
        } elseif ($user->role->name === 'supervisor') {
            // Supervisor sees tickets from their area
            $query->whereHas('assignedAgent', function($q) use ($user) {
                $q->where('area_id', $user->area_id);
            })->orWhereNull('assigned_to'); // Includes unassigned
        }
        // Admin sees all (no filter)

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email',
            'requester_area' => 'required|string',
            'description' => 'required|string',
            'priority' => 'required|in:baja,media,alta',
            'attachment' => 'nullable|file|mimes:jpg,png,jpeg,gif|max:5120',
        ]);

        // Generate unique ticket number
        $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(Ticket::count() + 1, 4, '0', STR_PAD_LEFT);
        
        // Generate verification code
        $verificationCode = rand(100000, 999999);

        // Upload file if exists
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('attachments', 'public');
        }

        // Get "new" status
        $newStatus = TicketStatus::where('name', 'nuevo')->first();

        $ticket = Ticket::create([
            'ticket_number' => $ticketNumber,
            'requester_name' => $validated['requester_name'],
            'requester_email' => $validated['requester_email'],
            'requester_area' => $validated['requester_area'],
            'description' => $validated['description'],
            'attachment_path' => $attachmentPath,
            'verification_code' => $verificationCode,
            'priority' => $validated['priority'],
            'status_id' => $newStatus->id,
        ]);

        // Register in history
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'action' => 'creado',
            'new_value' => 'Ticket creado desde portal público',
        ]);

        return response()->json([
            'message' => 'Ticket creado exitosamente',
            'ticket_number' => $ticketNumber,
            'verification_code' => $verificationCode,
        ], 201);
    }

    public function show($id)
    {
        $user = request()->user();
        $ticket = Ticket::with(['status', 'assignedAgent', 'createdBy', 'comments.user', 'history.user'])
            ->findOrFail($id);
        
        // Check permissions for role 'usuario'
        if ($user->role->name === 'usuario' && $ticket->requester_email !== $user->email) {
            return response()->json(['message' => 'No autorizado'], 403);
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
        
        // Calculate SLA
        $slaConfig = SlaConfig::where('priority', $validated['priority'] ?? $ticket->priority)->first();
        $slaDueDate = now()->addHours($slaConfig->response_time_hours);

        $ticket->update([
            'assigned_to' => $validated['agent_id'],
            'status_id' => TicketStatus::where('name', 'asignado')->first()->id,
            'priority' => $validated['priority'] ?? $ticket->priority,
            'sla_due_date' => $slaDueDate,
        ]);

        // History
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'asignado',
            'new_value' => "Asignado a " . User::find($validated['agent_id'])->name,
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

        // History
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'escalado',
            'new_value' => $validated['reason'],
        ]);

        return response()->json(['message' => 'Ticket escalado correctamente']);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|exists:ticket_statuses,name',
        ]);

        $ticket = Ticket::findOrFail($id);
        $newStatus = TicketStatus::where('name', $validated['status'])->first();

        $ticket->update([
            'status_id' => $newStatus->id,
        ]);

        // Update timestamps based on status
        if ($validated['status'] === 'resuelto') {
            $ticket->update(['resolved_at' => now()]);
        } elseif ($validated['status'] === 'cerrado') {
            $ticket->update(['closed_at' => now()]);
        }

        // History
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'cambio_estado',
            'old_value' => $ticket->status->name,
            'new_value' => $validated['status'],
        ]);

        return response()->json(['message' => 'Estado actualizado correctamente']);
    }

    public function addComment(Request $request, $id)
    {
        $validated = $request->validate([
            'comment' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        // Check permissions for role 'usuario'
        if ($user->role->name === 'usuario' && $ticket->requester_email !== $user->email) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'comment' => $validated['comment'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        return response()->json(['message' => 'Comentario agregado', 'comment' => $comment]);
    }

    public function getComments($id)
    {
        $user = request()->user();
        $ticket = Ticket::findOrFail($id);
        
        // Check permissions for role 'usuario'
        if ($user->role->name === 'usuario' && $ticket->requester_email !== $user->email) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        
        $comments = TicketComment::with('user')
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

    public function close(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);
        
        $ticket->update([
            'status_id' => TicketStatus::where('name', 'cerrado')->first()->id,
            'closed_at' => now(),
        ]);

        // History
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'cerrado',
            'new_value' => 'Ticket cerrado',
        ]);

        return response()->json(['message' => 'Ticket cerrado correctamente']);
    }
}
