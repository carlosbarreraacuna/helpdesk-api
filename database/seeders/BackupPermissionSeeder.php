<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;

class BackupPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $settingsModule = Module::where('name', 'settings')->first();
        $adminRole = Role::where('name', 'admin')->first();

        $permissions = [
            ['name' => 'backups.view',    'display_name' => 'Ver Respaldos',       'action_type' => 'read'],
            ['name' => 'backups.restore', 'display_name' => 'Restaurar Respaldos', 'action_type' => 'update'],
        ];

        foreach ($permissions as $perm) {
            $permission = Permission::firstOrCreate(
                ['name' => $perm['name']],
                [
                    'module_id'    => $settingsModule->id,
                    'display_name' => $perm['display_name'],
                    'action_type'  => $perm['action_type'],
                ]
            );

            RolePermission::firstOrCreate(
                ['role_id' => $adminRole->id, 'permission_id' => $permission->id],
                ['is_granted' => true]
            );
        }

        $this->command->info('Backup permissions seeded successfully.');
    }
}
