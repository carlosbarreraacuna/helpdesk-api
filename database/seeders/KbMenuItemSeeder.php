<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MenuItem;
use App\Models\Role;

class KbMenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $menuItem = MenuItem::firstOrCreate(
            ['key' => 'knowledge_base'],
            [
                'label'     => 'Base de Conocimiento',
                'icon'      => 'BookOpen',
                'route'     => '#',
                'order'     => 4,
                'is_active' => true,
                'is_system' => false,
                'metadata'  => json_encode(['color' => 'blue']),
            ]
        );

        $children = [
            [
                'key'       => 'knowledge_base.articles',
                'label'     => 'Todos los Artículos',
                'icon'      => 'FileText',
                'route'     => '/knowledge-base',
                'order'     => 1,
                'is_active' => true,
                'is_system' => false,
                'parent_id' => $menuItem->id,
            ],
            [
                'key'       => 'knowledge_base.new',
                'label'     => 'Nuevo Artículo',
                'icon'      => 'FilePlus',
                'route'     => '/knowledge-base/new',
                'order'     => 2,
                'is_active' => true,
                'is_system' => false,
                'parent_id' => $menuItem->id,
            ],
            [
                'key'       => 'knowledge_base.manage',
                'label'     => 'Gestionar KB',
                'icon'      => 'Settings2',
                'route'     => '/knowledge-base/manage',
                'order'     => 3,
                'is_active' => true,
                'is_system' => false,
                'parent_id' => $menuItem->id,
            ],
        ];

        foreach ($children as $child) {
            MenuItem::firstOrCreate(['key' => $child['key']], $child);
        }

        // Assign visibility to all roles
        $allItems = MenuItem::where('key', 'knowledge_base')
            ->orWhere('key', 'like', 'knowledge_base.%')
            ->get();

        $roles = Role::all();
        foreach ($allItems as $item) {
            foreach ($roles as $role) {
                \DB::table('menu_item_role')->updateOrInsert(
                    ['menu_item_id' => $item->id, 'role_id' => $role->id],
                    ['is_visible' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $this->command->info('KB Menu Item seeded successfully.');
    }
}
