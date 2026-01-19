<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuItemController extends Controller
{
    /**
     * Obtiene el menú para el usuario autenticado
     */
    public function getUserMenu(Request $request)
    {
        $user = $request->user();
        $roleId = $user->role_id;

        // Obtener items de nivel superior visibles para el rol
        $menuItems = MenuItem::active()
            ->topLevel()
            ->whereHas('roles', function($query) use ($roleId) {
                $query->where('role_id', $roleId)
                      ->where('is_visible', true);
            })
            ->with(['children' => function($query) use ($roleId) {
                $query->active()
                      ->whereHas('roles', function($q) use ($roleId) {
                          $q->where('role_id', $roleId)
                            ->where('is_visible', true);
                      })
                      ->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        return response()->json($menuItems);
    }

    /**
     * Lista todos los items del menú (Admin)
     */
    public function index()
    {
        $menuItems = MenuItem::with(['parent', 'children', 'roles'])
                            ->orderBy('order')
                            ->get();

        return response()->json($menuItems);
    }

    /**
     * Crea un nuevo item del menú
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|unique:menu_items',
            'label' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'route' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:menu_items,id',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $menuItem = MenuItem::create($validated);

        // Asignar a todos los roles por defecto
        $roles = Role::all();
        foreach ($roles as $role) {
            $menuItem->roles()->attach($role->id, [
                'is_visible' => $role->name === 'admin' // Solo visible para admin inicialmente
            ]);
        }

        return response()->json([
            'message' => 'Item de menú creado exitosamente',
            'menu_item' => $menuItem->load('roles')
        ], 201);
    }

    /**
     * Actualiza un item del menú
     */
    public function update(Request $request, $id)
    {
        $menuItem = MenuItem::findOrFail($id);

        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'route' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:menu_items,id',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $menuItem->update($validated);

        return response()->json([
            'message' => 'Item actualizado exitosamente',
            'menu_item' => $menuItem
        ]);
    }

    /**
     * Elimina un item del menú
     */
    public function destroy($id)
    {
        $menuItem = MenuItem::findOrFail($id);

        if ($menuItem->is_system) {
            return response()->json([
                'message' => 'No se pueden eliminar items del sistema'
            ], 422);
        }

        $menuItem->delete();

        return response()->json([
            'message' => 'Item eliminado exitosamente'
        ]);
    }

    /**
     * Obtiene la configuración del menú para un rol específico
     */
    public function getMenuForRole($roleId)
    {
        $role = Role::findOrFail($roleId);
        
        $menuItems = MenuItem::active()
            ->with(['children' => function($query) {
                $query->active()->orderBy('order');
            }])
            ->topLevel()
            ->orderBy('order')
            ->get()
            ->map(function($item) use ($roleId) {
                $pivot = $item->roles()->where('role_id', $roleId)->first();
                $item->is_visible = $pivot ? $pivot->pivot->is_visible : false;
                
                // Procesar children
                if ($item->children) {
                    $item->children->map(function($child) use ($roleId) {
                        $childPivot = $child->roles()->where('role_id', $roleId)->first();
                        $child->is_visible = $childPivot ? $childPivot->pivot->is_visible : false;
                        return $child;
                    });
                }
                
                return $item;
            });

        return response()->json([
            'role' => $role,
            'menu_items' => $menuItems
        ]);
    }

    /**
     * Actualiza la visibilidad de items del menú para un rol
     */
    public function updateRoleMenu(Request $request, $roleId)
    {
        $validated = $request->validate([
            'menu_items' => 'required|array',
            'menu_items.*.menu_item_id' => 'required|exists:menu_items,id',
            'menu_items.*.is_visible' => 'required|boolean',
        ]);

        $role = Role::findOrFail($roleId);

        foreach ($validated['menu_items'] as $item) {
            DB::table('menu_item_role')
                ->updateOrInsert(
                    [
                        'menu_item_id' => $item['menu_item_id'],
                        'role_id' => $roleId,
                    ],
                    [
                        'is_visible' => $item['is_visible'],
                        'updated_at' => now(),
                    ]
                );
        }

        return response()->json([
            'message' => 'Configuración de menú actualizada exitosamente'
        ]);
    }

    /**
     * Reordena los items del menú
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:menu_items,id',
            'items.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            MenuItem::where('id', $item['id'])
                    ->update(['order' => $item['order']]);
        }

        return response()->json([
            'message' => 'Orden actualizado exitosamente'
        ]);
    }
}
