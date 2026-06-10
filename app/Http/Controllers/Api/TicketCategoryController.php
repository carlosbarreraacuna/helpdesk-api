<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketCategory;
use Illuminate\Http\Request;

/**
 * @tags Configuración
 */
class TicketCategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            TicketCategory::where('is_active', true)->orderBy('order_index')->get()
        );
    }

    public function indexAll()
    {
        return response()->json(
            TicketCategory::orderBy('order_index')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:ticket_categories,name',
            'description' => 'nullable|string|max:255',
            'order_index' => 'nullable|integer|min:0',
        ]);

        $category = TicketCategory::create($validated);
        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = TicketCategory::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100|unique:ticket_categories,name,' . $id,
            'description' => 'nullable|string|max:255',
            'order_index' => 'nullable|integer|min:0',
            'is_active'   => 'sometimes|boolean',
        ]);

        $category->update($validated);
        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = TicketCategory::findOrFail($id);

        if ($category->tickets()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: tiene tickets asociados.'], 422);
        }

        $category->delete();
        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
