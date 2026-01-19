<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'level',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
                    ->withPivot('is_granted', 'conditions')
                    ->withTimestamps();
    }

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_role')
                ->withPivot('is_visible')
                ->withTimestamps();
    }

    public function visibleMenuItems()
    {
        return $this->menuItems()
                ->wherePivot('is_visible', true)
                ->where('is_active', true)
                ->orderBy('order');
    }

    public function reportTemplates()
    {
        return $this->belongsToMany(ReportTemplate::class, 'report_template_role')
                    ->withPivot('can_view', 'can_export')
                    ->withTimestamps();
    }

    /**
     * Obtiene solo los permisos activos del rol
     */
    public function activePermissions()
    {
        return $this->permissions()
                    ->where('role_permission.is_granted', true)
                    ->where('permissions.is_active', true);
    }

    /**
     * Asigna un permiso al rol
     */
    public function grantPermission($permissionId, $conditions = null)
    {
        $this->permissions()->syncWithoutDetaching([
            $permissionId => [
                'is_granted' => true,
                'conditions' => $conditions
            ]
        ]);
    }

    /**
     * Revoca un permiso del rol
     */
    public function revokePermission($permissionId)
    {
        $this->permissions()->updateExistingPivot($permissionId, [
            'is_granted' => false
        ]);
    }
}
