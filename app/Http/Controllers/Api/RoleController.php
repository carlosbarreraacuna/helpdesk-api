<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|unique:roles',
            'display_name' => 'required',
            'description' => 'nullable',
            'level' => 'required|integer|min:1',
        ]);
        
        $role = Role::create($validated);
        
        return response()->json([
            'message' => 'Rol creado exitosamente',
            'role' => $role
        ], 201);
    }

    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json($role);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        
        $validated = $request->validate([
            'display_name' => 'sometimes|required',
            'description' => 'nullable',
            'level' => 'sometimes|required|integer|min:1',
        ]);
        
        $role->update($validated);
        
        return response()->json([
            'message' => 'Rol actualizado exitosamente',
            'role' => $role
        ]);
    }

    public function destroy($id)
    {
        // No permitir eliminar roles del sistema
        $protectedRoles = ['admin', 'supervisor', 'agente'];
        $role = Role::findOrFail($id);
        
        if (in_array($role->name, $protectedRoles)) {
            return response()->json([
                'message' => 'No se pueden eliminar roles del sistema'
            ], 403);
        }
        
        // Verificar que no haya usuarios con este rol
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un rol que tiene usuarios asignados'
            ], 422);
        }
        
        $role->delete();
        
        return response()->json([
            'message' => 'Rol eliminado exitosamente'
        ]);
    }
}
