<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'dashboard',
                'display_name' => 'Dashboard',
                'description' => 'Panel principal del sistema',
                'icon' => 'home',
                'route' => '/dashboard',
                'order_index' => 1
            ],
            [
                'name' => 'tickets',
                'display_name' => 'Gestión de Tickets',
                'description' => 'Módulo completo de tickets',
                'icon' => 'ticket',
                'route' => '/tickets',
                'order_index' => 2
            ],
            [
                'name' => 'users',
                'display_name' => 'Gestión de Usuarios',
                'description' => 'Administración de usuarios del sistema',
                'icon' => 'users',
                'route' => '/admin/users',
                'order_index' => 3
            ],
            [
                'name' => 'roles',
                'display_name' => 'Gestión de Roles',
                'description' => 'Administración de roles y permisos',
                'icon' => 'shield',
                'route' => '/admin/roles-permissions',
                'order_index' => 4
            ],
            [
                'name' => 'areas',
                'display_name' => 'Gestión de Áreas',
                'description' => 'Departamentos y áreas de la empresa',
                'icon' => 'building',
                'route' => '/admin/areas',
                'order_index' => 5
            ],
            [
                'name' => 'reports',
                'display_name' => 'Reportes',
                'description' => 'Reportes e indicadores',
                'icon' => 'chart-bar',
                'route' => '/reports',
                'order_index' => 6
            ],
            [
                'name' => 'settings',
                'display_name' => 'Configuración',
                'description' => 'Configuración general del sistema',
                'icon' => 'settings',
                'route' => '/admin/settings',
                'order_index' => 7
            ],
        ];

        foreach ($modules as $module) {
            Module::create($module);
        }
    }
}
