<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class UpdateRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Actualizar roles existentes con display_name
        $roles = [
            ['name' => 'admin', 'display_name' => 'Administrador'],
            ['name' => 'supervisor', 'display_name' => 'Supervisor'],
            ['name' => 'agente', 'display_name' => 'Agente'],
            ['name' => 'usuario', 'display_name' => 'Usuario'],
        ];

        foreach ($roles as $roleData) {
            $role = Role::where('name', $roleData['name'])->first();
            if ($role) {
                $role->update(['display_name' => $roleData['display_name']]);
                $this->command->info("Rol '{$roleData['name']}' actualizado con display_name: {$roleData['display_name']}");
            } else {
                $this->command->warn("Rol '{$roleData['name']}' no encontrado");
            }
        }

        $this->command->info('Roles actualizados exitosamente.');
    }
}
