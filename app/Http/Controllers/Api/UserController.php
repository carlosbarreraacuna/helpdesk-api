<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Area;
use App\Models\PermissionChangeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Lista todos los usuarios con filtros
     */
    public function index(Request $request)
    {
        $query = User::with(['role', 'area']);

        // Filtros opcionales - convertir strings vacíos a null
        $roleId = $request->get('role_id');
        $areaId = $request->get('area_id');
        $isActive = $request->get('is_active');
        $search = $request->get('search');

        if ($roleId && $roleId !== '') {
            $query->where('role_id', $roleId);
        }

        if ($areaId && $areaId !== '') {
            $query->where('area_id', $areaId);
        }

        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', $isActive);
        }

        if ($search && $search !== '') {
            $searchTerm = '%' . $search . '%';
            $query->where(function($q) use ($searchTerm) {
                // Case-insensitive search
                $q->whereRaw('LOWER(name) LIKE LOWER(?)', [$searchTerm])
                  ->orWhereRaw('LOWER(email) LIKE LOWER(?)', [$searchTerm])
                  ->orWhereRaw('LOWER(username) LIKE LOWER(?)', [$searchTerm]);
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json($users);
    }

    /**
     * Muestra un usuario específico con todos sus permisos
     */
    public function show($id)
    {
        $user = User::with(['role.permissions', 'area', 'permissions'])->findOrFail($id);
        
        // Agregar permisos efectivos
        $user->effective_permissions = $user->getAllPermissions();
        $user->denied_permissions = $user->getDeniedPermissions();

        return response()->json($user);
    }

    /**
     * Crea un nuevo usuario
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'area_id' => 'nullable|exists:areas,id',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        $user->load(['role', 'area']);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user' => $user
        ], 201);
    }

    /**
     * Actualiza un usuario
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users')->ignore($user->id)
            ],
            'role_id' => 'sometimes|required|exists:roles,id',
            'area_id' => 'nullable|exists:areas,id',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        // Si se cambia la contraseña
        if ($request->has('password')) {
            $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);
            $validated['password'] = Hash::make($request->password);
        }

        $user->update($validated);
        $user->load(['role', 'area']);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => $user
        ]);
    }

    /**
     * Elimina un usuario
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Evitar que el admin se elimine a sí mismo
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'No puedes eliminar tu propio usuario'
            ],422);
        }

        // Verificar si tiene tickets asignados
        if ($user->assignedTickets()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un usuario con tickets asignados. Reasigna los tickets primero.'
            ],422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Activa/Desactiva un usuario
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        // Evitar que el admin se desactive a sí mismo
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'No puedes desactivar tu propio usuario'
            ],422);
        }

        $user->update([
            'is_active' => !$user->is_active
        ]);

        return response()->json([
            'message' => $user->is_active ? 'Usuario activado' : 'Usuario desactivado',
            'user' => $user
        ]);
    }

    /**
     * Asigna un rol a un usuario
     */
    public function assignRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'clear_special_permissions' => 'boolean', // Opción para limpiar permisos especiales
        ]);

        $oldRole = $user->role;
        $user->assignRole($validated['role_id']);

        // Opcional: limpiar permisos especiales al cambiar de rol
        if ($request->get('clear_special_permissions', false)) {
            $user->permissions()->detach();
        }

        $user->load('role');

        return response()->json([
            'message' => 'Rol asignado exitosamente',
            'user' => $user,
            'permissions' => $user->getAllPermissions(),
        ]);
    }

    /**
     * Resetea la contraseña de un usuario
     */
    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }

    /**
     * Obtiene los permisos efectivos de un usuario
     */
    public function getPermissions($id)
    {
        $user = User::with(['role.permissions', 'permissions'])->findOrFail($id);

        return response()->json([
            'role_permissions' => $user->role ? $user->role->activePermissions()->get() : [],
            'user_permissions' => $user->permissions,
            'effective_permissions' => $user->getAllPermissions(),
            'denied_permissions' => $user->getDeniedPermissions(),
        ]);
    }

    /**
     * Asigna un permiso especial a un usuario
     */
    public function grantPermission(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'permission_id' => 'required|exists:permissions,id',
            'is_granted' => 'required|boolean',
            'reason' => 'nullable|string',
        ]);

        $user->permissions()->syncWithoutDetaching([
            $validated['permission_id'] => [
                'is_granted' => $validated['is_granted'],
            ]
        ]);

        // Log del cambio
        PermissionChangeLog::create([
            'changed_by' => auth()->id(),
            'change_type' => 'user_permission',
            'entity_id' => $user->id,
            'permission_id' => $validated['permission_id'],
            'new_value' => $validated['is_granted'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Permiso especial asignado exitosamente',
            'permissions' => $user->getAllPermissions(),
        ]);
    }

    /**
     * Quita un permiso especial de un usuario
     */
    public function revokePermission($userId, $permissionId)
    {
        $user = User::findOrFail($userId);
        $user->permissions()->detach($permissionId);

        return response()->json([
            'message' => 'Permiso especial removido',
            'permissions' => $user->getAllPermissions(),
        ]);
    }

    /**
     * Obtiene estadísticas de usuarios
     */
    public function stats()
    {
        $stats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'by_role' => User::select('role_id')
                ->selectRaw('COUNT(*) as count')
                ->with('role:id,name,display_name')
                ->groupBy('role_id')
                ->get(),
            'by_area' => User::select('area_id')
                ->selectRaw('COUNT(*) as count')
                ->with('area:id,name')
                ->groupBy('area_id')
                ->get(),
        ];

        return response()->json($stats);
    }
}
