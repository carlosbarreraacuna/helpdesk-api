<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::with('permissions')->orderBy('order_index')->get();
        return response()->json($modules);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|unique:modules',
            'display_name' => 'required',
            'description' => 'nullable',
            'icon' => 'nullable',
            'route' => 'nullable',
            'order_index' => 'integer',
        ]);
        
        $module = Module::create($validated);
        
        return response()->json([
            'message' => 'Módulo creado exitosamente',
            'module' => $module
        ], 201);
    }

    public function show($id)
    {
        $module = Module::with('permissions')->findOrFail($id);
        return response()->json($module);
    }

    public function update(Request $request, $id)
    {
        $module = Module::findOrFail($id);
        
        $validated = $request->validate([
            'display_name' => 'sometimes|required',
            'description' => 'nullable',
            'icon' => 'nullable',
            'route' => 'nullable',
            'is_active' => 'sometimes|boolean',
            'order_index' => 'sometimes|integer',
        ]);
        
        $module->update($validated);
        
        return response()->json([
            'message' => 'Módulo actualizado exitosamente',
            'module' => $module
        ]);
    }

    public function destroy($id)
    {
        $module = Module::findOrFail($id);
        
        // Verificar que no tenga permisos asociados
        if ($module->permissions()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un módulo que tiene permisos asociados'
            ], 422);
        }
        
        $module->delete();
        
        return response()->json([
            'message' => 'Módulo eliminado exitosamente'
        ]);
    }
}
