<?php

namespace App\Events;

use App\Models\TicketComment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCommentAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $comment;

    public function __construct(TicketComment $ticketComment)
    {
        $ticketComment->loadMissing('user:id,name,email', 'attachments');

        $this->comment = [
            'id'          => $ticketComment->id,
            'ticket_id'   => $ticketComment->ticket_id,
            'comment'     => $ticketComment->comment,
            'is_internal' => $ticketComment->is_internal,
            'created_at'  => $ticketComment->created_at?->toISOString(),
            'user'        => $ticketComment->user
                ? ['id' => $ticketComment->user->id, 'name' => $ticketComment->user->name, 'email' => $ticketComment->user->email]
                : null,
            'attachments' => $ticketComment->attachments->map(fn($a) => [
                'url'  => $a->url,
                'name' => $a->name,
                'mime' => $a->mime,
            ])->values()->toArray(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('ticket.' . $this->comment['ticket_id'])];
    }

    public function broadcastAs(): string
    {
        return 'comment.added';
    }
}
