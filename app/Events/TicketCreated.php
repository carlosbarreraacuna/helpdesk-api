<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $ticket;

    public function __construct(Ticket $ticket)
    {
        $ticket->load('status:id,name,color');
        $this->ticket = [
            'id'             => $ticket->id,
            'ticket_number'  => $ticket->ticket_number,
            'requester_name' => $ticket->requester_name,
            'requester_area' => $ticket->requester_area,
            'priority'       => $ticket->priority,
            'status'         => $ticket->status?->only(['id', 'name', 'color']),
            'created_at'     => $ticket->created_at?->toISOString(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('tickets.admin')];
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }
}
