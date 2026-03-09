<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetChatMessage extends Model
{
    protected $fillable = [
        'session_id', 'sender_id', 'sender_type',
        'body', 'attachment_path', 'attachment_name',
        'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WidgetChatSession::class, 'session_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
