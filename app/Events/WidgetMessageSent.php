<?php

namespace App\Events;

use App\Models\WidgetChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class WidgetMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public int    $messageId;
    public int    $sessionId;
    public int    $senderId;
    public string $senderType;
    public string $senderName;
    public string $body;
    public ?string $attachmentPath;
    public ?string $attachmentName;
    public bool   $isRead;
    public string $createdAt;

    public function __construct(WidgetChatMessage $message)
    {
        $this->messageId      = $message->id;
        $this->sessionId      = $message->session_id;
        $this->senderId       = $message->sender_id;
        $this->senderType     = $message->sender_type;
        $this->senderName     = $message->sender?->name ?? '';
        $this->body           = $message->body;
        $this->attachmentPath = $message->attachment_path;
        $this->attachmentName = $message->attachment_name;
        $this->isRead         = (bool) $message->is_read;
        $this->createdAt      = $message->created_at->toISOString();
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
            'id'              => $this->messageId,
            'session_id'      => $this->sessionId,
            'sender_id'       => $this->senderId,
            'sender_type'     => $this->senderType,
            'sender_name'     => $this->senderName,
            'body'            => $this->body,
            'attachment_path' => $this->attachmentPath,
            'attachment_name' => $this->attachmentName,
            'is_read'         => $this->isRead,
            'created_at'      => $this->createdAt,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
