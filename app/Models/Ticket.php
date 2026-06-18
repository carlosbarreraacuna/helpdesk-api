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
        'sla_response_due_at',
        'sla_resolution_due_at',
        'sla_response_met_at',
        'sla_breach_notified_at',
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

    protected $appends = ['sla_status', 'attachment_url'];

    protected $casts = [
        'is_read'                   => 'boolean',
        'sla_due_date'              => 'datetime',
        'sla_response_due_at'       => 'datetime',
        'sla_resolution_due_at'     => 'datetime',
        'sla_response_met_at'       => 'datetime',
        'sla_breach_notified_at'    => 'datetime',
        'resolved_at'               => 'datetime',
        'closed_at'                 => 'datetime',
        'validation_requested_at'   => 'datetime',
        'validation_deadline'       => 'datetime',
        'validation_approved_at'    => 'datetime',
        'validation_rejected_count' => 'integer',
    ];

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Storage::disk('s3')->url($this->attachment_path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getSlaStatusAttribute(): string
    {
        if (!$this->sla_resolution_due_at) {
            return 'sin_sla';
        }

        if ($this->closed_at || $this->resolved_at) {
            $resolvedAt = $this->closed_at ?? $this->resolved_at;
            return $resolvedAt->lte($this->sla_resolution_due_at) ? 'cumplido' : 'incumplido';
        }

        if (now()->gt($this->sla_resolution_due_at)) {
            return 'vencido';
        }

        // At-risk: used >= alert_threshold% of total time
        $slaConfig = \App\Models\SlaConfig::forPriority($this->priority);
        if ($slaConfig) {
            $totalMin = $slaConfig->resolution_time_hours * 60;
            $usedMin  = $this->created_at->diffInMinutes(now());
            if ($totalMin > 0 && ($usedMin / $totalMin * 100) >= $slaConfig->alert_threshold) {
                return 'en_riesgo';
            }
        }

        return 'en_tiempo';
    }

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
