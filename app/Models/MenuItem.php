<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'key',
        'label',
        'icon',
        'route',
        'parent_id',
        'order',
        'is_active',
        'is_system',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'metadata' => 'array',
    ];

    // Relación con roles
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'menu_item_role')
                    ->withPivot('is_visible')
                    ->withTimestamps();
    }

    // Parent/Children para menús anidados
    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
                    ->orderBy('order');
    }

    // Scope para items activos
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope para items de nivel superior
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    // Verifica si un rol puede ver este item
    public function isVisibleForRole($roleId)
    {
        $pivot = $this->roles()->where('role_id', $roleId)->first();
        return $pivot && $pivot->pivot->is_visible;
    }
}
