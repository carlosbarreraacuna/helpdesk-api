<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingInvitee;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function __construct(private GoogleCalendarService $google) {}

    /** List meetings — optionally filtered by ticket */
    public function index(Request $request)
    {
        $query = Meeting::with(['organizer:id,name,email', 'invitees', 'ticket:id,ticket_number'])
            ->orderBy('start_time', 'desc');

        if ($request->has('ticket_id')) {
            $query->where('ticket_id', $request->ticket_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('upcoming') && $request->boolean('upcoming')) {
            $query->where('start_time', '>=', now())->where('status', 'scheduled')->orderBy('start_time');
        }

        $perPage = $request->get('per_page', 20);
        return response()->json($query->paginate($perPage));
    }

    /** Create a meeting (and optionally push Meet event to Google Calendar) */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after:start_time',
            'ticket_id'   => 'nullable|exists:tickets,id',
            'invitees'    => 'nullable|array',
            'invitees.*.email' => 'required|email',
            'invitees.*.name'  => 'nullable|string',
        ]);

        $user = $request->user();

        // Auto-include ticket requester as invitee if linked
        if (!empty($validated['ticket_id'])) {
            $ticket = Ticket::findOrFail($validated['ticket_id']);
            $validated['invitees'] = array_merge($validated['invitees'] ?? [], [
                ['email' => $ticket->requester_email, 'name' => $ticket->requester_name],
            ]);
        }

        // Remove duplicates by email
        $seenEmails = [];
        $invitees   = [];
        foreach ($validated['invitees'] ?? [] as $inv) {
            if (!in_array(strtolower($inv['email']), $seenEmails, true)) {
                $seenEmails[] = strtolower($inv['email']);
                $invitees[]   = $inv;
            }
        }

        $meetLink       = null;
        $calendarEventId = null;

        if ($this->google->isConnected()) {
            try {
                $result          = $this->google->createMeetEvent([
                    'title'       => $validated['title'],
                    'description' => $validated['description'] ?? '',
                    'start_time'  => $validated['start_time'],
                    'end_time'    => $validated['end_time'],
                    'invitees'    => $invitees,
                ]);
                $meetLink        = $result['meet_link'];
                $calendarEventId = $result['event_id'];
            } catch (\Throwable $e) {
                // Still create the meeting locally even if Google fails
                \Log::warning('Google Calendar create failed: ' . $e->getMessage());
            }
        }

        $meeting = Meeting::create([
            'ticket_id'         => $validated['ticket_id'] ?? null,
            'organizer_id'      => $user->id,
            'title'             => $validated['title'],
            'description'       => $validated['description'] ?? null,
            'start_time'        => $validated['start_time'],
            'end_time'          => $validated['end_time'],
            'meet_link'         => $meetLink,
            'calendar_event_id' => $calendarEventId,
            'status'            => 'scheduled',
        ]);

        foreach ($invitees as $inv) {
            MeetingInvitee::create([
                'meeting_id' => $meeting->id,
                'email'      => $inv['email'],
                'name'       => $inv['name'] ?? null,
            ]);
        }

        // Add comment to ticket with Meet link
        if (!empty($validated['ticket_id']) && $meetLink) {
            $comment = TicketComment::create([
                'ticket_id'   => $validated['ticket_id'],
                'user_id'     => $user->id,
                'comment'     => "📹 **Reunión programada:** {$validated['title']}\n🗓 " .
                    \Carbon\Carbon::parse($validated['start_time'])->format('d/m/Y H:i') .
                    "\n🔗 [Unirse a Google Meet]({$meetLink})",
                'is_internal' => false,
            ]);
            broadcast(new \App\Events\TicketCommentAdded($comment));

            TicketHistory::create([
                'ticket_id' => $validated['ticket_id'],
                'user_id'   => $user->id,
                'action'    => 'reunion_programada',
                'new_value' => "Reunión: {$validated['title']} — {$meetLink}",
            ]);
        }

        $meeting->load(['organizer:id,name,email', 'invitees']);
        return response()->json($meeting, 201);
    }

    /** Get single meeting */
    public function show($id)
    {
        $meeting = Meeting::with(['organizer:id,name,email', 'invitees', 'ticket:id,ticket_number,requester_name'])->findOrFail($id);
        return response()->json($meeting);
    }

    /** Update meeting details and sync to Google Calendar */
    public function update(Request $request, $id)
    {
        $meeting   = Meeting::findOrFail($id);
        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'start_time'       => 'sometimes|date',
            'end_time'         => 'sometimes|date',
            'status'           => 'sometimes|in:scheduled,completed,cancelled',
            'duration_minutes' => 'nullable|integer|min:1',
            'notes'            => 'nullable|string',
        ]);

        $meeting->update($validated);

        if ($meeting->calendar_event_id && $this->google->isConnected()) {
            try {
                $this->google->updateEvent($meeting->calendar_event_id, $validated);
            } catch (\Throwable $e) {
                \Log::warning('Google Calendar update failed: ' . $e->getMessage());
            }
        }

        $meeting->load(['organizer:id,name,email', 'invitees']);
        return response()->json($meeting);
    }

    /** Cancel / delete a meeting */
    public function destroy($id)
    {
        $meeting = Meeting::findOrFail($id);

        if ($meeting->calendar_event_id && $this->google->isConnected()) {
            try {
                $this->google->deleteEvent($meeting->calendar_event_id);
            } catch (\Throwable $e) {
                \Log::warning('Google Calendar delete failed: ' . $e->getMessage());
            }
        }

        $meeting->delete();
        return response()->json(['message' => 'Reunión eliminada']);
    }

    /** Instantly create a Meet room and post link to ticket */
    public function quickMeet(Request $request, $ticketId)
    {
        $request->validate([
            'extra_invitees'         => 'nullable|array',
            'extra_invitees.*.email' => 'required|email',
            'extra_invitees.*.name'  => 'nullable|string',
        ]);

        $ticket = Ticket::findOrFail($ticketId);
        $user   = $request->user();

        $startTime = now();
        $endTime   = now()->addHour();

        // Always include requester; merge any extra invitees chosen by the agent
        $invitees = [['email' => $ticket->requester_email, 'name' => $ticket->requester_name]];
        foreach ($request->input('extra_invitees', []) as $extra) {
            if (!in_array(strtolower($extra['email']), array_column($invitees, 'email'), true)) {
                $invitees[] = ['email' => $extra['email'], 'name' => $extra['name'] ?? null];
            }
        }

        $meetLink        = null;
        $calendarEventId = null;

        if ($this->google->isConnected()) {
            $result          = $this->google->createMeetEvent([
                'title'       => "Soporte: {$ticket->ticket_number}",
                'description' => "Reunión de soporte para el ticket {$ticket->ticket_number}",
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'invitees'    => $invitees,
            ]);
            $meetLink        = $result['meet_link'];
            $calendarEventId = $result['event_id'];
        } else {
            return response()->json(['message' => 'Google Calendar no está conectado. Configúralo en Ajustes > Integraciones.'], 422);
        }

        $meeting = Meeting::create([
            'ticket_id'         => $ticket->id,
            'organizer_id'      => $user->id,
            'title'             => "Soporte: {$ticket->ticket_number}",
            'start_time'        => $startTime,
            'end_time'          => $endTime,
            'meet_link'         => $meetLink,
            'calendar_event_id' => $calendarEventId,
            'status'            => 'scheduled',
        ]);

        foreach ($invitees as $inv) {
            MeetingInvitee::create(['meeting_id' => $meeting->id, 'email' => $inv['email'], 'name' => $inv['name'] ?? null]);
        }

        $comment = TicketComment::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $user->id,
            'comment'     => "📹 Se ha iniciado una videollamada de soporte.\n🔗 [Unirse ahora a Google Meet]({$meetLink})",
            'is_internal' => false,
        ]);
        broadcast(new \App\Events\TicketCommentAdded($comment));

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'videollamada_iniciada',
            'new_value' => $meetLink,
        ]);

        $meeting->load(['organizer:id,name,email', 'invitees']);
        return response()->json(['meeting' => $meeting, 'meet_link' => $meetLink], 201);
    }

    /** Metrics summary */
    public function metrics()
    {
        return response()->json([
            'total'              => Meeting::count(),
            'scheduled'          => Meeting::where('status', 'scheduled')->count(),
            'completed'          => Meeting::where('status', 'completed')->count(),
            'cancelled'          => Meeting::where('status', 'cancelled')->count(),
            'avg_duration'       => round(Meeting::whereNotNull('duration_minutes')->avg('duration_minutes') ?? 0),
            'meetings_this_week' => Meeting::whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ]);
    }
}
