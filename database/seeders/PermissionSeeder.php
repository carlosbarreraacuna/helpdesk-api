<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Module;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = Module::all()->keyBy('name');
        
        $permissions = [
            // DASHBOARD
            ['module' => 'dashboard', 'name' => 'dashboard.view_global', 'display_name' => 'Ver Dashboard Global', 'action_type' => 'read'],
            ['module' => 'dashboard', 'name' => 'dashboard.view_area', 'display_name' => 'Ver Dashboard de Área', 'action_type' => 'read'],
            ['module' => 'dashboard', 'name' => 'dashboard.view_personal', 'display_name' => 'Ver Dashboard Personal', 'action_type' => 'read'],
            
            // TICKETS - VISUALIZACIÓN
            ['module' => 'tickets', 'name' => 'tickets.view_all', 'display_name' => 'Ver Todos los Tickets', 'action_type' => 'read'],
            ['module' => 'tickets', 'name' => 'tickets.view_area', 'display_name' => 'Ver Tickets de su Área', 'action_type' => 'read'],
            ['module' => 'tickets', 'name' => 'tickets.view_assigned', 'display_name' => 'Ver Tickets Asignados', 'action_type' => 'read'],
            ['module' => 'tickets', 'name' => 'tickets.view_unassigned', 'display_name' => 'Ver Tickets Sin Asignar', 'action_type' => 'read'],
            
            // TICKETS - CRUD
            ['module' => 'tickets', 'name' => 'tickets.create', 'display_name' => 'Crear Tickets', 'action_type' => 'create'],
            ['module' => 'tickets', 'name' => 'tickets.update', 'display_name' => 'Editar Tickets', 'action_type' => 'update'],
            ['module' => 'tickets', 'name' => 'tickets.delete', 'display_name' => 'Eliminar Tickets', 'action_type' => 'delete'],
            
            // TICKETS - ASIGNACIÓN
            ['module' => 'tickets', 'name' => 'tickets.assign', 'display_name' => 'Asignar Tickets', 'action_type' => 'special'],
            ['module' => 'tickets', 'name' => 'tickets.reassign', 'display_name' => 'Reasignar Tickets', 'action_type' => 'special'],
            ['module' => 'tickets', 'name' => 'tickets.self_assign', 'display_name' => 'Auto-asignarse Tickets', 'action_type' => 'special'],
            
            // TICKETS - ESCALAMIENTO
            ['module' => 'tickets', 'name' => 'tickets.escalate', 'display_name' => 'Escalar Tickets', 'action_type' => 'special'],
            ['module' => 'tickets', 'name' => 'tickets.receive_escalated', 'display_name' => 'Recibir Tickets Escalados', 'action_type' => 'special'],
            
            // TICKETS - ESTADOS
            ['module' => 'tickets', 'name' => 'tickets.change_status', 'display_name' => 'Cambiar Estado', 'action_type' => 'update'],
            ['module' => 'tickets', 'name' => 'tickets.close', 'display_name' => 'Cerrar Tickets', 'action_type' => 'special'],
            ['module' => 'tickets', 'name' => 'tickets.reopen', 'display_name' => 'Reabrir Tickets', 'action_type' => 'special'],
            ['module' => 'tickets', 'name' => 'tickets.resolve', 'display_name' => 'Marcar como Resuelto', 'action_type' => 'special'],
            
            // TICKETS - PRIORIDAD
            ['module' => 'tickets', 'name' => 'tickets.change_priority', 'display_name' => 'Cambiar Prioridad', 'action_type' => 'update'],
            
            // TICKETS - COMENTARIOS
            ['module' => 'tickets', 'name' => 'tickets.add_comment', 'display_name' => 'Agregar Comentarios', 'action_type' => 'create'],
            ['module' => 'tickets', 'name' => 'tickets.view_internal_comments', 'display_name' => 'Ver Comentarios Internos', 'action_type' => 'read'],
            ['module' => 'tickets', 'name' => 'tickets.delete_comment', 'display_name' => 'Eliminar Comentarios', 'action_type' => 'delete'],
            
            // USUARIOS
            ['module' => 'users', 'name' => 'users.view', 'display_name' => 'Ver Usuarios', 'action_type' => 'read'],
            ['module' => 'users', 'name' => 'users.create', 'display_name' => 'Crear Usuarios', 'action_type' => 'create'],
            ['module' => 'users', 'name' => 'users.update', 'display_name' => 'Editar Usuarios', 'action_type' => 'update'],
            ['module' => 'users', 'name' => 'users.delete', 'display_name' => 'Eliminar Usuarios', 'action_type' => 'delete'],
            ['module' => 'users', 'name' => 'users.change_role', 'display_name' => 'Cambiar Roles', 'action_type' => 'special'],
            ['module' => 'users', 'name' => 'users.toggle_status', 'display_name' => 'Activar/Desactivar Usuarios', 'action_type' => 'special'],
            ['module' => 'users', 'name' => 'users.reset_password', 'display_name' => 'Resetear Contraseñas', 'action_type' => 'special'],
            
            // ROLES
            ['module' => 'roles', 'name' => 'roles.view', 'display_name' => 'Ver Roles', 'action_type' => 'read'],
            ['module' => 'roles', 'name' => 'roles.create', 'display_name' => 'Crear Roles', 'action_type' => 'create'],
            ['module' => 'roles', 'name' => 'roles.update', 'display_name' => 'Editar Roles', 'action_type' => 'update'],
            ['module' => 'roles', 'name' => 'roles.delete', 'display_name' => 'Eliminar Roles', 'action_type' => 'delete'],
            ['module' => 'roles', 'name' => 'roles.manage_permissions', 'display_name' => 'Gestionar Permisos de Roles', 'action_type' => 'special'],
            
            // ÁREAS
            ['module' => 'areas', 'name' => 'areas.view', 'display_name' => 'Ver Áreas', 'action_type' => 'read'],
            ['module' => 'areas', 'name' => 'areas.create', 'display_name' => 'Crear Áreas', 'action_type' => 'create'],
            ['module' => 'areas', 'name' => 'areas.update', 'display_name' => 'Editar Áreas', 'action_type' => 'update'],
            ['module' => 'areas', 'name' => 'areas.delete', 'display_name' => 'Eliminar Áreas', 'action_type' => 'delete'],
            
            // REPORTES
            ['module' => 'reports', 'name' => 'reports.view_global', 'display_name' => 'Ver Reportes Globales', 'action_type' => 'read'],
            ['module' => 'reports', 'name' => 'reports.view_area', 'display_name' => 'Ver Reportes de Área', 'action_type' => 'read'],
            ['module' => 'reports', 'name' => 'reports.view_personal', 'display_name' => 'Ver Reportes Personales', 'action_type' => 'read'],
            ['module' => 'reports', 'name' => 'reports.export', 'display_name' => 'Exportar Reportes', 'action_type' => 'special'],
            ['module' => 'reports', 'name' => 'reports.manage', 'display_name' => 'Gestionar Reportes', 'action_type' => 'special'],
            
            // CONFIGURACIÓN
            ['module' => 'settings', 'name' => 'settings.view', 'display_name' => 'Ver Configuración', 'action_type' => 'read'],
            ['module' => 'settings', 'name' => 'settings.update', 'display_name' => 'Modificar Configuración', 'action_type' => 'update'],
            ['module' => 'settings', 'name' => 'settings.sla', 'display_name' => 'Configurar SLA', 'action_type' => 'special'],
            ['module' => 'settings', 'name' => 'settings.email_templates', 'display_name' => 'Gestionar Plantillas Email', 'action_type' => 'special'],
        ];
        
        foreach ($permissions as $perm) {
            Permission::create([
                'module_id' => $modules[$perm['module']]->id,
                'name' => $perm['name'],
                'display_name' => $perm['display_name'],
                'action_type' => $perm['action_type'],
            ]);
        }
    }
}
