<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;

class KbModuleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear módulo KB
        $module = Module::firstOrCreate(
            ['name' => 'knowledge_base'],
            [
                'display_name' => 'Base de Conocimiento',
                'description'  => 'Artículos de soluciones y documentación',
                'icon'         => 'book-open',
                'route'        => '/knowledge-base',
                'is_active'    => true,
                'order_index'  => 8,
            ]
        );

        // 2. Crear permisos KB
        $permissions = [
            ['name' => 'kb.view',    'display_name' => 'Ver Base de Conocimiento',          'action_type' => 'read'],
            ['name' => 'kb.create',  'display_name' => 'Crear Artículos KB',                'action_type' => 'create'],
            ['name' => 'kb.edit',    'display_name' => 'Editar/Versionar Artículos KB',     'action_type' => 'update'],
            ['name' => 'kb.publish', 'display_name' => 'Publicar/Archivar Artículos KB',    'action_type' => 'special'],
            ['name' => 'kb.manage',  'display_name' => 'Gestionar Categorías y Etiquetas KB','action_type' => 'special'],
            ['name' => 'kb.link',    'display_name' => 'Vincular Artículos a Tickets',      'action_type' => 'special'],
        ];

        $createdPermissions = [];
        foreach ($permissions as $perm) {
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

        // 3. Asignar permisos por rol
        $adminRole      = Role::where('name', 'admin')->first();
        $supervisorRole = Role::where('name', 'supervisor')->first();
        $agenteRole     = Role::where('name', 'agente')->first();

        // Admin: todos los permisos KB
        foreach ($createdPermissions as $permission) {
            RolePermission::firstOrCreate(
                ['role_id' => $adminRole->id, 'permission_id' => $permission->id],
                ['is_granted' => true]
            );
        }

        // Supervisor: view, create, edit, link
        $supervisorPerms = ['kb.view', 'kb.create', 'kb.edit', 'kb.link'];
        foreach ($supervisorPerms as $permName) {
            if (isset($createdPermissions[$permName])) {
                RolePermission::firstOrCreate(
                    ['role_id' => $supervisorRole->id, 'permission_id' => $createdPermissions[$permName]->id],
                    ['is_granted' => true]
                );
            }
        }

        // Agente: view, create, link
        $agentePerms = ['kb.view', 'kb.create', 'kb.link'];
        foreach ($agentePerms as $permName) {
            if (isset($createdPermissions[$permName])) {
                RolePermission::firstOrCreate(
                    ['role_id' => $agenteRole->id, 'permission_id' => $createdPermissions[$permName]->id],
                    ['is_granted' => true]
                );
            }
        }

        $this->command->info('KB Module seeded successfully.');
    }
}
