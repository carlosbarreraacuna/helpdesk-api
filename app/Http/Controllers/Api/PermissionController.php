<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Module;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\UserPermission;
use App\Models\PermissionChangeLog;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    // Listar todos los permisos agrupados por módulo
    public function index()
    {
        $modules = Module::with('permissions')->where('is_active', true)->get();
        return response()->json($modules);
    }
    
    // Crear nuevo permiso
    public function store(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'name' => 'required|unique:permissions',
            'display_name' => 'required',
            'description' => 'nullable',
            'action_type' => 'required|in:create,read,update,delete,special',
        ]);
        
        $permission = Permission::create($validated);
        
        return response()->json([
            'message' => 'Permiso creado exitosamente',
            'permission' => $permission
        ], 201);
    }
    
    // Obtener permisos de un rol específico
    public function getRolePermissions($roleId)
    {
        $role = Role::with('permissions.module')->findOrFail($roleId);
        
        $modules = Module::with(['permissions' => function($query) use ($roleId) {
            $query->leftJoin('role_permission', function($join) use ($roleId) {
                $join->on('permissions.id', '=', 'role_permission.permission_id')
                     ->where('role_permission.role_id', '=', $roleId);
            })
            ->select('permissions.*', 'role_permission.is_granted');
        }])->get();
        
        return response()->json($modules);
    }
    
    // Actualizar permisos de un rol
    public function updateRolePermissions(Request $request, $roleId)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.permission_id' => 'required|exists:permissions,id',
            'permissions.*.is_granted' => 'required|boolean',
        ]);
        
        $role = Role::findOrFail($roleId);
        
        foreach ($validated['permissions'] as $perm) {
            $existing = RolePermission::where('role_id', $roleId)
                ->where('permission_id', $perm['permission_id'])
                ->first();
            
            $oldValue = $existing ? $existing->is_granted : null;
            
            RolePermission::updateOrCreate(
                [
                    'role_id' => $roleId,
                    'permission_id' => $perm['permission_id'],
                ],
                [
                    'is_granted' => $perm['is_granted'],
                ]
            );
            
            // Log del cambio si el valor cambió
            if ($oldValue !== $perm['is_granted']) {
                PermissionChangeLog::create([
                    'changed_by' => auth()->id(),
                    'change_type' => 'role_permission',
                    'entity_id' => $roleId,
                    'permission_id' => $perm['permission_id'],
                    'old_value' => $oldValue,
                    'new_value' => $perm['is_granted'],
                ]);
            }
        }
        
        return response()->json([
            'message' => 'Permisos actualizados exitosamente'
        ]);
    }
    
    // Obtener permisos de un usuario específico
    public function getUserPermissions($userId)
    {
        $user = \App\Models\User::with(['role.permissions', 'permissions'])->findOrFail($userId);
        
        return response()->json([
            'user' => $user,
            'role_permissions' => $user->role->permissions,
            'user_permissions' => $user->permissions,
        ]);
    }
    
    // Toggle permiso específico para usuario
    public function toggleUserPermission(Request $request, $userId)
    {
        $validated = $request->validate([
            'permission_id' => 'required|exists:permissions,id',
        ]);
        
        $existing = UserPermission::where('user_id', $userId)
            ->where('permission_id', $validated['permission_id'])
            ->first();
        
        $newValue = !$existing || !$existing->is_granted;
        $oldValue = $existing ? $existing->is_granted : null;
        
        UserPermission::updateOrCreate(
            [
                'user_id' => $userId,
                'permission_id' => $validated['permission_id'],
            ],
            [
                'is_granted' => $newValue,
            ]
        );
        
        // Log del cambio
        PermissionChangeLog::create([
            'changed_by' => auth()->id(),
            'change_type' => 'user_permission',
            'entity_id' => $userId,
            'permission_id' => $validated['permission_id'],
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
        
        return response()->json([
            'message' => 'Permiso de usuario actualizado exitosamente',
            'is_granted' => $newValue
        ]);
    }
}
