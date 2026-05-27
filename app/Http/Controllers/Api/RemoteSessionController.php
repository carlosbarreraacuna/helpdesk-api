<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketRemoteSession;
use App\Models\TicketHistory;
use App\Models\Ticket;
use Illuminate\Http\Request;

class RemoteSessionController extends Controller
{
    public function index($ticketId)
    {
        $sessions = TicketRemoteSession::with('agent:id,name,email')
            ->where('ticket_id', $ticketId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => array_merge($s->toArray(), [
                'is_active'        => $s->is_active,
                'duration_minutes' => $s->duration_minutes,
            ]));

        return response()->json($sessions);
    }

    public function store(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'tool'         => 'required|in:anydesk,teamviewer,chrome_remote_desktop,rustdesk,other',
            'session_code' => 'required|string|max:100',
            'notes'        => 'nullable|string|max:500',
        ]);

        Ticket::findOrFail($ticketId);

        $session = TicketRemoteSession::create([
            'ticket_id'    => $ticketId,
            'agent_id'     => $request->user()->id,
            'tool'         => $validated['tool'],
            'session_code' => $validated['session_code'],
            'notes'        => $validated['notes'] ?? null,
            'started_at'   => now(),
        ]);

        TicketHistory::create([
            'ticket_id' => $ticketId,
            'user_id'   => $request->user()->id,
            'action'    => 'sesion_remota',
            'new_value' => 'Sesión remota iniciada (' . strtoupper($validated['tool']) . '): ' . $validated['session_code'],
        ]);

        $session->load('agent:id,name,email');

        return response()->json(array_merge($session->toArray(), [
            'is_active'        => $session->is_active,
            'duration_minutes' => $session->duration_minutes,
        ]), 201);
    }

    public function finish(Request $request, $ticketId, $sessionId)
    {
        $session = TicketRemoteSession::where('ticket_id', $ticketId)->findOrFail($sessionId);

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $session->update([
            'ended_at' => now(),
            'notes'    => $request->input('notes', $session->notes),
        ]);

        TicketHistory::create([
            'ticket_id' => $ticketId,
            'user_id'   => $request->user()->id,
            'action'    => 'sesion_remota',
            'new_value' => 'Sesión remota finalizada (' . strtoupper($session->tool) . ')'
                . ($session->duration_minutes ? ' — duración: ' . $session->duration_minutes . ' min' : ''),
        ]);

        return response()->json(array_merge($session->fresh()->toArray(), [
            'is_active'        => false,
            'duration_minutes' => $session->duration_minutes,
        ]));
    }

    public function destroy(Request $request, $ticketId, $sessionId)
    {
        $session = TicketRemoteSession::where('ticket_id', $ticketId)->findOrFail($sessionId);
        $session->delete();
        return response()->json(['message' => 'Sesión eliminada']);
    }
}
