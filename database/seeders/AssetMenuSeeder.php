<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetMenuSeeder extends Seeder
{
    public function run(): void
    {
        $parent = DB::table('menu_items')->where('route', '/inventory')->first();
        if (!$parent) {
            $parentId = DB::table('menu_items')->insertGetId([
                'key'        => 'inventory',
                'label'      => 'Inventario',
                'route'      => '/inventory',
                'icon'       => 'Package',
                'parent_id'  => null,
                'order'      => 50,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $parentId = $parent->id;
        }

        $children = [
            ['key' => 'inventory.assets',       'label' => 'Activos',        'route' => '/inventory/assets',       'icon' => 'HardDrive', 'order' => 1],
            ['key' => 'inventory.maintenances', 'label' => 'Mantenimientos', 'route' => '/inventory/maintenances', 'icon' => 'Wrench',    'order' => 2],
            ['key' => 'inventory.providers',    'label' => 'Proveedores',    'route' => '/inventory/providers',    'icon' => 'Building2', 'order' => 3],
            ['key' => 'inventory.manage',       'label' => 'Configuración',  'route' => '/inventory/manage',       'icon' => 'Settings',  'order' => 4],
        ];

        $childIds = [];
        foreach ($children as $child) {
            $existing = DB::table('menu_items')->where('key', $child['key'])->first();
            if ($existing) {
                $childIds[] = $existing->id;
            } else {
                $childId = DB::table('menu_items')->insertGetId([
                    ...$child,
                    'parent_id'  => $parentId,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $childIds[] = $childId;
            }
        }

        // Asignar menú a roles (visible para admin, supervisor, agente)
        $roles = DB::table('roles')->whereIn('name', ['admin', 'supervisor', 'agente'])->get();
        foreach ($roles as $role) {
            // Parent item
            DB::table('menu_item_role')->insertOrIgnore([
                'menu_item_id' => $parentId,
                'role_id'      => $role->id,
                'is_visible'   => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            // Children items
            foreach ($childIds as $childId) {
                DB::table('menu_item_role')->insertOrIgnore([
                    'menu_item_id' => $childId,
                    'role_id'      => $role->id,
                    'is_visible'   => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }
}
