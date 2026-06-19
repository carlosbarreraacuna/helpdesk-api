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

        $adminRole = Role::where('name', 'admin')->first();

        $items = [
            [
                'key'   => 'admin.sla',
                'label' => 'Parametrización SLA',
                'icon'  => 'Timer',
                'route' => '/admin/sla',
                'order' => 10,
            ],
            [
                'key'   => 'admin.sla-reportes',
                'label' => 'Reportes SLA',
                'icon'  => 'BarChart3',
                'route' => '/admin/sla-reportes',
                'order' => 11,
            ],
        ];

        foreach ($items as $item) {
            $menuItem = MenuItem::firstOrCreate(
                ['key' => $item['key']],
                [
                    'label'     => $item['label'],
                    'icon'      => $item['icon'],
                    'route'     => $item['route'],
                    'parent_id' => $adminParent->id,
                    'order'     => $item['order'],
                    'is_active' => true,
                    'is_system' => false,
                ]
            );

            // Solo visible para el rol admin (igual que el resto de items bajo Administración)
            DB::table('menu_item_role')->updateOrInsert(
                ['menu_item_id' => $menuItem->id, 'role_id' => $adminRole->id],
                ['is_visible' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('SLA menu items seeded successfully.');
    }
}
