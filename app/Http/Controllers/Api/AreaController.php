<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /**
     * Lista todas las áreas con paginación y búsqueda
     */
    public function index(Request $request)
    {
        $query = Area::query();

        // Búsqueda
        $search = $request->get('search');
        if ($search && $search !== '') {
            $searchTerm = '%' . $search . '%';
            $query->where(function($q) use ($searchTerm) {
                // Case-insensitive search
                $q->whereRaw('LOWER(name) LIKE LOWER(?)', [$searchTerm])
                  ->orWhereRaw('LOWER(description) LIKE LOWER(?)', [$searchTerm]);
            });
        }

        // Paginación
        $perPage = $request->get('per_page', 10);
        $areas = $query->orderBy('name')->paginate($perPage);

        return response()->json($areas);
    }

    /**
     * Muestra un área específica
     */
    public function show($id)
    {
        $area = Area::findOrFail($id);
        return response()->json($area);
    }

    /**
     * Crea una nueva área
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:areas,name',
            'description' => 'nullable|string',
        ]);

        $area = Area::create($validated);

        return response()->json([
            'message' => 'Área creada exitosamente',
            'area' => $area
        ], 201);
    }

    /**
     * Actualiza un área
     */
    public function update(Request $request, $id)
    {
        $area = Area::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:areas,name,' . $area->id,
            'description' => 'nullable|string',
        ]);

        $area->update($validated);

        return response()->json([
            'message' => 'Área actualizada exitosamente',
            'area' => $area
        ]);
    }

    /**
     * Elimina un área
     */
    public function destroy($id)
    {
        $area = Area::findOrFail($id);

        // Verificar si hay usuarios asignados a esta área
        if ($area->users()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un área con usuarios asignados'
            ], 422);
        }

        $area->delete();

        return response()->json([
            'message' => 'Área eliminada exitosamente'
        ]);
    }
}
