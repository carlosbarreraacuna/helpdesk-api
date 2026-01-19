<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'requester_name',
        'requester_email',
        'requester_area',
        'description',
        'attachment_path',
        'verification_code',
        'priority',
        'status_id',
        'assigned_to',
        'created_by',
        'sla_due_date',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'sla_due_date' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function status()
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class);
    }

    public function history()
    {
        return $this->hasMany(TicketHistory::class);
    }

    public function publicComments()
    {
        return $this->hasMany(TicketComment::class)->where('is_internal', false);
    }

    public function internalComments()
    {
        return $this->hasMany(TicketComment::class)->where('is_internal', true);
    }
}
