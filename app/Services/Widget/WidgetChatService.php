<?php

namespace App\Services\Widget;

use App\Events\TicketCreated;
use App\Events\WidgetMessageSent;
use App\Events\WidgetSessionUpdated;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\WidgetChatMessage;
use App\Models\WidgetChatSession;
use Illuminate\Support\Facades\DB;

class WidgetChatService
{
    public function getOrCreateSession(User $user, ?array $searchContext = null): WidgetChatSession
    {
        $session = WidgetChatSession::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'active'])
            ->latest()
            ->first();

        if (!$session) {
            $session = WidgetChatSession::create([
                'user_id'        => $user->id,
                'status'         => 'pending',
                'search_context' => $searchContext,
                'started_at'     => now(),
            ]);
        } elseif ($searchContext) {
            $session->update(['search_context' => $searchContext]);
        }

        return $session->load(['user:id,name,email', 'assignedAgent:id,name', 'ticket']);
    }

    public function sendMessage(WidgetChatSession $session, User $sender, string $body, ?string $attachmentPath = null, ?string $attachmentName = null): WidgetChatMessage
    {
        $senderType = $sender->id === $session->user_id ? 'user' : 'agent';

        $message = WidgetChatMessage::create([
            'session_id'      => $session->id,
            'sender_id'       => $sender->id,
            'sender_type'     => $senderType,
            'body'            => $body,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        $message->load('sender:id,name');

        // Crear ticket en el primer mensaje del usuario
        if ($senderType === 'user' && !$session->ticket_id) {
            $ticket = $this->createTicketFromSession($session, $body);
            $session->update(['ticket_id' => $ticket->id, 'status' => 'active']);
            $session->refresh();
            broadcast(new WidgetSessionUpdated($session))->toOthers();
        }

        // Si el agente responde, marcar sesión como activa
        if ($senderType === 'agent' && $session->status === 'pending') {
            $session->update(['status' => 'active']);
        }

        broadcast(new WidgetMessageSent($message));

        return $message;
    }

    public function markMessagesRead(WidgetChatSession $session, User $reader): void
    {
        WidgetChatMessage::where('session_id', $session->id)
            ->where('sender_id', '!=', $reader->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    public function assignAgent(WidgetChatSession $session, User $agent): WidgetChatSession
    {
        $session->update([
            'assigned_agent_id' => $agent->id,
            'status'            => 'active',
        ]);

        $systemMessage = WidgetChatMessage::create([
            'session_id'  => $session->id,
            'sender_id'   => $agent->id,
            'sender_type' => 'system',
            'body'        => "El agente {$agent->name} se ha unido a la conversación.",
        ]);

        $session->load(['user:id,name', 'assignedAgent:id,name']);
        broadcast(new WidgetSessionUpdated($session));
        broadcast(new WidgetMessageSent($systemMessage->load('sender:id,name')));

        return $session;
    }

    public function closeSession(WidgetChatSession $session): WidgetChatSession
    {
        $session->update(['status' => 'closed', 'closed_at' => now()]);
        broadcast(new WidgetSessionUpdated($session));
        return $session;
    }

    private function createTicketFromSession(WidgetChatSession $session, string $firstMessage): Ticket
    {
        $openStatus = TicketStatus::orderBy('order')->first();
        $context    = $session->search_context;

        $description = $firstMessage;
        if ($context && isset($context['query'])) {
            $description = "**Contexto del widget:**\n- Búsqueda: \"{$context['query']}\"\n\n**Mensaje:**\n{$firstMessage}";
        }

        $ticketNumber     = 'TKT-' . strtoupper(\Illuminate\Support\Str::random(8));
        $verificationCode = strtoupper(\Illuminate\Support\Str::random(6));

        $ticket = Ticket::create([
            'ticket_number'    => $ticketNumber,
            'requester_name'   => $session->user->name,
            'requester_email'  => $session->user->email,
            'requester_area'   => $session->user->area->name ?? 'Widget',
            'description'      => $description,
            'status_id'        => $openStatus->id,
            'created_by'       => $session->user_id,
            'priority'         => 'media',
            'verification_code'=> $verificationCode,
        ]);

        broadcast(new TicketCreated($ticket));

        return $ticket;
    }

    public function getAgentActiveSessions(): \Illuminate\Database\Eloquent\Collection
    {
        return WidgetChatSession::with(['user:id,name,email', 'assignedAgent:id,name', 'messages' => function ($q) {
            $q->latest()->limit(1);
        }])
            ->whereIn('status', ['pending', 'active'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get();
    }
}
