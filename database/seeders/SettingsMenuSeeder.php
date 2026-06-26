<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsMenuSeeder extends Seeder
{
    public function run(): void
    {
        // Ítem padre "Configuración" (si no existe)
        $parent = MenuItem::firstOrCreate(
            ['key' => 'settings'],
            [
                'label'     => 'Configuración',
                'icon'      => 'Settings',
                'route'     => '#',
                'parent_id' => null,
                'order'     => 90,
                'is_active' => true,
                'is_system' => false,
            ]
        );

        // Subítem "Seguridad de la cuenta"
        $securityItem = MenuItem::firstOrCreate(
            ['key' => 'settings.security'],
            [
                'label'     => 'Seguridad',
                'icon'      => 'ShieldCheck',
                'route'     => '/settings/security',
                'parent_id' => $parent->id,
                'order'     => 1,
                'is_active' => true,
                'is_system' => false,
            ]
        );

        // Visible para admin, supervisor y agente
        $roles = Role::whereIn('name', ['admin', 'supervisor', 'agente'])->get();

        foreach ([$parent, $securityItem] as $item) {
            foreach ($roles as $role) {
                DB::table('menu_item_role')->updateOrInsert(
                    ['menu_item_id' => $item->id, 'role_id' => $role->id],
                    ['is_visible' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $this->command->info('Settings menu seeded.');
    }
}
