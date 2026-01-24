<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignRolesToUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los usuarios que no tienen rol asignado
        $usersWithoutRole = User::whereNull('role_id')->get();
        
        if ($usersWithoutRole->isEmpty()) {
            $this->command->info('Todos los usuarios ya tienen roles asignados.');
            return;
        }

        // Obtener el rol de "Usuario" (nivel 0) o "admin" (nivel 1)
        $userRole = Role::where('name', 'usuario')->first();
        $adminRole = Role::where('name', 'admin')->first();

        foreach ($usersWithoutRole as $user) {
            // Asignar rol basado en el email o nombre
            if (str_contains($user->email, 'admin') || str_contains($user->name, 'Admin')) {
                $roleId = $adminRole?->id ?? 1;
            } else {
                $roleId = $userRole?->id ?? 2;
            }

            $user->update(['role_id' => $roleId]);
            
            $this->command->info("Usuario '{$user->name}' actualizado con rol ID: {$roleId}");
        }

        $this->command->info('Se asignaron roles a ' . $usersWithoutRole->count() . ' usuarios.');
    }
}
