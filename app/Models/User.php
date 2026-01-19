<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'area_id',
        'phone',
        'avatar',
        'is_active',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function createdTickets()
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function ticketComments()
    {
        return $this->hasMany(TicketComment::class);
    }

    public function ticketHistory()
    {
        return $this->hasMany(TicketHistory::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permission')
                    ->withPivot('is_granted', 'conditions')
                    ->withTimestamps();
    }

    public function hasPermission($permissionName)
    {
        // 1. Verificar permiso directo del usuario (tiene prioridad)
        $userPermission = $this->permissions()
            ->where('name', $permissionName)
            ->first();
        
        if ($userPermission) {
            return $userPermission->pivot->is_granted;
        }
        
        // 2. Verificar permiso del rol
        $rolePermission = $this->role->permissions()
            ->where('name', $permissionName)
            ->first();
        
        if ($rolePermission) {
            return $rolePermission->pivot->is_granted;
        }
        
        return false;
    }

    public function hasPermissionAccess($permissionName)
    {
        return $this->hasPermission($permissionName);
    }

    /**
     * Obtiene todos los permisos efectivos del usuario
     * (combinando permisos de rol + permisos especiales)
     */
    public function getAllPermissions()
    {
        $permissions = collect();
        
        // Permisos del rol
        if ($this->role) {
            $rolePermissions = $this->role->permissions()
                ->where('role_permission.is_granted', true)
                ->get()
                ->map(function($perm) {
                    $perm->source = 'role';
                    $perm->source_name = $this->role->display_name;
                    return $perm;
                });
            
            $permissions = $permissions->merge($rolePermissions);
        }
        
        // Permisos especiales del usuario
        $userPermissions = $this->permissions()
            ->wherePivot('is_granted', true)
            ->get()
            ->map(function($perm) {
                $perm->source = 'user';
                $perm->source_name = 'Permiso especial';
                return $perm;
            });
        
        $permissions = $permissions->merge($userPermissions);
        
        // Eliminar duplicados (los de usuario sobrescriben los de rol)
        return $permissions->unique('id');
    }

    /**
     * Obtiene permisos negados específicamente al usuario
     */
    public function getDeniedPermissions()
    {
        return $this->permissions()
            ->wherePivot('is_granted', false)
            ->get();
    }

    /**
     * Asigna un rol al usuario
     */
    public function assignRole($roleId)
    {
        $this->update(['role_id' => $roleId]);
        
        // Limpiar permisos especiales al cambiar de rol (opcional)
        // $this->permissions()->detach();
    }

    /**
     * Verifica si el usuario tiene un rol específico
     */
    public function hasRole($roleName)
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Verifica si el usuario es Admin
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    /**
     * Verifica si el usuario es Supervisor
     */
    public function isSupervisor()
    {
        return $this->hasRole('supervisor');
    }

    /**
     * Verifica si el usuario es Agente
     */
    public function isAgent()
    {
        return $this->hasRole('agente');
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para usuarios inactivos
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope para filtrar por rol
     */
    public function scopeByRole($query, $roleName)
    {
        return $query->whereHas('role', function($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    /**
     * Scope para filtrar por área
     */
    public function scopeByArea($query, $areaId)
    {
        return $query->where('area_id', $areaId);
    }
}
