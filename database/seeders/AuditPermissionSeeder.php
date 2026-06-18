<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;

class AuditPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $settingsModule = Module::where('name', 'settings')->first();

        $permission = Permission::firstOrCreate(
            ['name' => 'audit.view'],
            [
                'module_id'    => $settingsModule->id,
                'display_name' => 'Ver Auditoría',
                'action_type'  => 'read',
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();

        RolePermission::firstOrCreate(
            ['role_id' => $adminRole->id, 'permission_id' => $permission->id],
            ['is_granted' => true]
        );

        $this->command->info('Audit permission seeded successfully.');
    }
}
