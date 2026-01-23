<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRoleSeeder extends Seeder
{
    public function run()
    {
        // Crear rol "Usuario"
        $userRole = Role::firstOrCreate(
            ['name' => 'usuario'],
            [
                'display_name' => 'Usuario',
                'description' => 'Usuario final que puede crear y consultar sus propios tickets',
                'level' => 0, // Nivel más bajo
            ]
        );

        // Permisos específicos para usuarios
        $userPermissions = Permission::whereIn('name', [
            'tickets.create',           // Crear tickets
            'tickets.view_own',         // Ver solo sus tickets
            'tickets.add_comment',      // Agregar comentarios a sus tickets
        ])->get();

        // Sincronizar permisos (eliminar los que ya no son necesarios y agregar los nuevos)
        $permissionIds = $userPermissions->pluck('id')->toArray();
        $syncData = array_fill_keys($permissionIds, [
            'is_granted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $userRole->permissions()->sync($syncData);
        
        $this->command->info('Rol de Usuario creado con sus permisos correspondientes');
    }
}
