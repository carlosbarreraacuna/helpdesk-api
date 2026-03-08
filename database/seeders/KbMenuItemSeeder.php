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
                'route'     => '/knowledge-base',
                'order'     => 4,
                'is_active' => true,
                'is_system' => false,
                'metadata'  => json_encode(['color' => 'blue']),
            ]
        );

        // Assign visibility to all roles
        $roles = Role::all();
        foreach ($roles as $role) {
            \DB::table('menu_item_role')->updateOrInsert(
                ['menu_item_id' => $menuItem->id, 'role_id' => $role->id],
                ['is_visible' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('KB Menu Item seeded successfully.');
    }
}
