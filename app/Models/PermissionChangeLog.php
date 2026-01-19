<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionChangeLog extends Model
{
    use HasFactory;

    protected $table = 'permission_changes_log';
    
    protected $fillable = [
        'changed_by',
        'change_type',
        'entity_id',
        'permission_id',
        'old_value',
        'new_value',
        'reason',
    ];

    protected $casts = [
        'old_value' => 'boolean',
        'new_value' => 'boolean',
    ];

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
