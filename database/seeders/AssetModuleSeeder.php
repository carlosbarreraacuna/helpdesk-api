<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;

class AssetModuleSeeder extends Seeder
{
    public function run(): void
    {
        // Módulo
        $module = Module::firstOrCreate(
            ['name' => 'inventory'],
            [
                'display_name' => 'Inventario',
                'description'  => 'Gestión de activos, equipos y hoja de vida tecnológica',
                'icon'         => 'package',
                'route'        => '/inventory',
                'is_active'    => true,
                'order_index'  => 9,
            ]
        );

        // Permisos
        $permissionsData = [
            ['name' => 'assets.view',     'display_name' => 'Ver Inventario',              'action_type' => 'read'],
            ['name' => 'assets.create',   'display_name' => 'Crear Activos',               'action_type' => 'create'],
            ['name' => 'assets.update',   'display_name' => 'Editar Activos',              'action_type' => 'update'],
            ['name' => 'assets.assign',   'display_name' => 'Asignar/Devolver Activos',    'action_type' => 'special'],
            ['name' => 'assets.maintain', 'display_name' => 'Registrar Mantenimientos',    'action_type' => 'create'],
            ['name' => 'assets.manage',   'display_name' => 'Administrar Tipos y Config',  'action_type' => 'special'],
        ];

        $createdPermissions = [];
        foreach ($permissionsData as $perm) {
            $createdPermissions[$perm['name']] = Permission::firstOrCreate(
                ['name' => $perm['name']],
                [
                    'module_id'    => $module->id,
                    'display_name' => $perm['display_name'],
                    'action_type'  => $perm['action_type'],
                    'is_active'    => true,
                ]
            );
        }

        // Asignar permisos por rol
        $adminRole      = Role::where('name', 'admin')->first();
        $supervisorRole = Role::where('name', 'supervisor')->first();
        $agenteRole     = Role::where('name', 'agente')->first();

        // Admin: todos los permisos
        foreach ($createdPermissions as $permission) {
            RolePermission::firstOrCreate(
                ['role_id' => $adminRole->id, 'permission_id' => $permission->id],
                ['is_granted' => true]
            );
        }

        // Supervisor: view, create, update, assign, maintain
        $supervisorPerms = ['assets.view', 'assets.create', 'assets.update', 'assets.assign', 'assets.maintain'];
        foreach ($supervisorPerms as $permName) {
            if (isset($createdPermissions[$permName]) && $supervisorRole) {
                RolePermission::firstOrCreate(
                    ['role_id' => $supervisorRole->id, 'permission_id' => $createdPermissions[$permName]->id],
                    ['is_granted' => true]
                );
            }
        }

        // Agente: view, create, update, assign, maintain
        $agentePerms = ['assets.view', 'assets.create', 'assets.update', 'assets.assign', 'assets.maintain'];
        foreach ($agentePerms as $permName) {
            if (isset($createdPermissions[$permName]) && $agenteRole) {
                RolePermission::firstOrCreate(
                    ['role_id' => $agenteRole->id, 'permission_id' => $createdPermissions[$permName]->id],
                    ['is_granted' => true]
                );
            }
        }

        $this->command->info('Asset Module seeded successfully.');
    }
}
