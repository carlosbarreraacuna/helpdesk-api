<?php

namespace App\Events;

use App\Models\WidgetChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class WidgetSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public int     $sessionId;
    public string  $status;
    public ?int    $assignedAgentId;
    public ?array  $assignedAgent;
    public ?int    $ticketId;

    public function __construct(WidgetChatSession $session)
    {
        $this->sessionId       = $session->id;
        $this->status          = $session->status;
        $this->assignedAgentId = $session->assigned_agent_id;
        $this->assignedAgent   = $session->assignedAgent ? ['id' => $session->assignedAgent->id, 'name' => $session->assignedAgent->name] : null;
        $this->ticketId        = $session->ticket_id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('widget.session.' . $this->sessionId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'                => $this->sessionId,
            'status'            => $this->status,
            'assigned_agent_id' => $this->assignedAgentId,
            'assigned_agent'    => $this->assignedAgent,
            'ticket_id'         => $this->ticketId,
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.updated';
    }
}
