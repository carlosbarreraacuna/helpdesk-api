<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class MenuItemRoleSeeder extends Seeder
{
    public function run()
    {
        $adminRole = Role::where('name', 'admin')->first();
        $supervisorRole = Role::where('name', 'supervisor')->first();
        $agenteRole = Role::where('name', 'agente')->first();
        $usuarioRole = Role::where('name', 'usuario')->first();

        $allMenuItems = MenuItem::all();

        // ADMIN - Ve todo
        foreach ($allMenuItems as $item) {
            DB::table('menu_item_role')->insert([
                'menu_item_id' => $item->id,
                'role_id' => $adminRole->id,
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // SUPERVISOR - Ve tickets, reportes, sin configuración avanzada
        $supervisorItems = MenuItem::whereIn('key', [
            'dashboard',
            'tickets',
            'reports',
        ])->get();

        foreach ($supervisorItems as $item) {
            DB::table('menu_item_role')->insert([
                'menu_item_id' => $item->id,
                'role_id' => $supervisorRole->id,
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // USUARIO - Solo ve Tickets
        $usuarioItems = MenuItem::whereIn('key', [
            'tickets',
        ])->get();

        foreach ($usuarioItems as $item) {
            DB::table('menu_item_role')->insert([
                'menu_item_id' => $item->id,
                'role_id' => $usuarioRole->id,
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // AGENTE - Solo dashboard y tickets
        $agenteItems = MenuItem::whereIn('key', [
            'dashboard',
            'tickets',
        ])->get();

        foreach ($agenteItems as $item) {
            DB::table('menu_item_role')->insert([
                'menu_item_id' => $item->id,
                'role_id' => $agenteRole->id,
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
