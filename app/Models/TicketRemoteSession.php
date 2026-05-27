<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRemoteSession extends Model
{
    protected $fillable = [
        'ticket_id', 'agent_id', 'tool', 'session_code',
        'notes', 'started_at', 'ended_at',
    ];

    // Supported tools
    const TOOLS = ['anydesk', 'teamviewer', 'chrome_remote_desktop', 'rustdesk', 'other'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->started_at || !$this->ended_at) return null;
        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }
}
