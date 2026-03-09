<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSoftwareAssignment extends Model
{
    protected $fillable = [
        'user_id', 'software_name', 'version', 'license_key',
        'assigned_at', 'expires_at', 'assigned_by', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'assigned_at' => 'date',
        'expires_at'  => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
