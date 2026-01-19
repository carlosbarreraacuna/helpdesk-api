<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\RolePermission;

class DefaultRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        $supervisorRole = Role::where('name', 'supervisor')->first();
        $agenteRole = Role::where('name', 'agente')->first();
        
        // ADMIN - TODOS LOS PERMISOS
        $allPermissions = Permission::all();
        foreach ($allPermissions as $permission) {
            RolePermission::create([
                'role_id' => $adminRole->id,
                'permission_id' => $permission->id,
                'is_granted' => true,
            ]);
        }
        
        // SUPERVISOR - PERMISOS LIMITADOS
        $supervisorPermissions = [
            'dashboard.view_area',
            'dashboard.view_personal',
            'tickets.view_area',
            'tickets.view_assigned',
            'tickets.view_unassigned',
            'tickets.create',
            'tickets.update',
            'tickets.assign',
            'tickets.reassign',
            'tickets.escalate',
            'tickets.receive_escalated',
            'tickets.change_status',
            'tickets.close',
            'tickets.reopen',
            'tickets.resolve',
            'tickets.change_priority',
            'tickets.add_comment',
            'tickets.view_internal_comments',
            'reports.view_area',
            'reports.export',
        ];
        
        foreach ($supervisorPermissions as $permName) {
            $permission = Permission::where('name', $permName)->first();
            if ($permission) {
                RolePermission::create([
                    'role_id' => $supervisorRole->id,
                    'permission_id' => $permission->id,
                    'is_granted' => true,
                ]);
            }
        }
        
        // AGENTE - PERMISOS MÍNIMOS
        $agentePermissions = [
            'dashboard.view_personal',
            'tickets.view_assigned',
            'tickets.update',
            'tickets.reassign',
            'tickets.escalate',
            'tickets.change_status',
            'tickets.close',
            'tickets.resolve',
            'tickets.change_priority',
            'tickets.add_comment',
            'tickets.view_internal_comments',
            'reports.view_personal',
        ];
        
        foreach ($agentePermissions as $permName) {
            $permission = Permission::where('name', $permName)->first();
            if ($permission) {
                RolePermission::create([
                    'role_id' => $agenteRole->id,
                    'permission_id' => $permission->id,
                    'is_granted' => true,
                ]);
            }
        }
    }
}
