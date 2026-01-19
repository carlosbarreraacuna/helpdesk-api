<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'type',
        'chart_type',
        'icon',
        'config',
        'is_system',
        'is_active',
        'order',
    ];

    protected $casts = [
        'config' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'report_template_role')
                    ->withPivot('can_view', 'can_export')
                    ->withTimestamps();
    }

    public function exports()
    {
        return $this->hasMany(ReportExport::class);
    }

    public function canView($roleId)
    {
        $pivot = $this->roles()->where('role_id', $roleId)->first();
        return $pivot && $pivot->pivot->can_view;
    }

    public function canExport($roleId)
    {
        $pivot = $this->roles()->where('role_id', $roleId)->first();
        return $pivot && $pivot->pivot->can_export;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRole($query, $roleId)
    {
        return $query->whereHas('roles', function($q) use ($roleId) {
            $q->where('role_id', $roleId)
              ->where('can_view', true);
        });
    }
}
