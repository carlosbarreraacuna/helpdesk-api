<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class KbMenuSubItemsSeeder extends Seeder
{
    public function run(): void
    {
        $parent = MenuItem::where('key', 'knowledge_base')->first();

        if (!$parent) {
            $this->command->error('Parent menu item "knowledge_base" not found.');
            return;
        }

        // Update parent to use # route (it becomes a group, not a direct link)
        $parent->update(['route' => '#']);

        $subItems = [
            [
                'key'       => 'kb.articles',
                'label'     => 'Todos los Artículos',
                'icon'      => 'FileText',
                'route'     => '/knowledge-base',
                'order'     => 1,
                'is_active' => true,
                'is_system' => false,
            ],
            [
                'key'       => 'kb.new',
                'label'     => 'Nuevo Artículo',
                'icon'      => 'FilePlus',
                'route'     => '/knowledge-base/new',
                'order'     => 2,
                'is_active' => true,
                'is_system' => false,
            ],
            [
                'key'       => 'kb.manage',
                'label'     => 'Gestionar KB',
                'icon'      => 'Settings2',
                'route'     => '/knowledge-base/manage',
                'order'     => 3,
                'is_active' => true,
                'is_system' => false,
            ],
        ];

        $roles = Role::all();

        foreach ($subItems as $item) {
            $menuItem = MenuItem::firstOrCreate(
                ['key' => $item['key']],
                array_merge($item, ['parent_id' => $parent->id])
            );

            // Assign visibility to all roles
            foreach ($roles as $role) {
                DB::table('menu_item_role')->updateOrInsert(
                    ['menu_item_id' => $menuItem->id, 'role_id' => $role->id],
                    ['is_visible' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $this->command->info('KB sub-menu items seeded successfully.');
    }
}
