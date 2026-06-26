<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SecurityMenuSeeder extends Seeder
{
    public function run(): void
    {
        $adminParent = MenuItem::where('key', 'admin')->first();

        $menuItem = MenuItem::firstOrCreate(
            ['key' => 'admin.security'],
            [
                'label'     => 'Seguridad del sistema',
                'icon'      => 'ShieldCheck',
                'route'     => '/admin/security',
                'parent_id' => $adminParent?->id,
                'order'     => 12,
                'is_active' => true,
                'is_system' => false,
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();

        if ($adminRole) {
            DB::table('menu_item_role')->updateOrInsert(
                ['menu_item_id' => $menuItem->id, 'role_id' => $adminRole->id],
                ['is_visible' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('Security menu item seeded.');
    }
}
