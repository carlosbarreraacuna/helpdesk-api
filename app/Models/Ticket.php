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
        'channel',
        'channel_ref',
        'is_read',
        'category_id',
        'work_group_id',
        'validation_requested_at',
        'validation_deadline',
        'validation_token',
        'validation_approved_at',
        'validation_rejected_count',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'sla_due_date' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'validation_requested_at' => 'datetime',
        'validation_deadline' => 'datetime',
        'validation_approved_at' => 'datetime',
        'validation_rejected_count' => 'integer',
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

    public function participants()
    {
        return $this->belongsToMany(User::class, 'ticket_participants', 'ticket_id', 'user_id')
                    ->withPivot('added_by')
                    ->withTimestamps();
    }

    public function participantRecords()
    {
        return $this->hasMany(TicketParticipant::class);
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function workGroup()
    {
        return $this->belongsTo(WorkGroup::class, 'work_group_id');
    }

    public function validations()
    {
        return $this->hasMany(TicketValidation::class);
    }
}
