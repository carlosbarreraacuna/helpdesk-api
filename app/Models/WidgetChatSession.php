<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetChatSession extends Model
{
    protected $fillable = [
        'user_id', 'ticket_id', 'assigned_agent_id',
        'status', 'search_context', 'started_at', 'closed_at',
    ];

    protected $casts = [
        'search_context' => 'array',
        'started_at'     => 'datetime',
        'closed_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WidgetChatMessage::class, 'session_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'active']);
    }
}
