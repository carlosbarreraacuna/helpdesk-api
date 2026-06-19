<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class SlaMenuSeeder extends Seeder
{
    public function run(): void
    {
        $adminParent = MenuItem::where('key', 'admin')->first();

        $menuItem = MenuItem::firstOrCreate(
            ['key' => 'admin.sla'],
            [
                'label'     => 'Parametrización SLA',
                'icon'      => 'Timer',
                'route'     => '/admin/sla',
                'parent_id' => $adminParent->id,
                'order'     => 10,
                'is_active' => true,
                'is_system' => false,
            ]
        );

        // Solo visible para el rol admin (igual que el resto de items bajo Administración)
        $adminRole = Role::where('name', 'admin')->first();

        DB::table('menu_item_role')->updateOrInsert(
            ['menu_item_id' => $menuItem->id, 'role_id' => $adminRole->id],
            ['is_visible' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        $this->command->info('SLA menu item seeded successfully.');
    }
}
