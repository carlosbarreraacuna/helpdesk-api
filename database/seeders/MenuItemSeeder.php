<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MenuItem;

class MenuItemSeeder extends Seeder
{
    public function run()
    {
        $menuItems = [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'LayoutDashboard',
                'route' => '/dashboard',
                'order' => 1,
                'is_system' => true,
                'metadata' => json_encode(['color' => 'blue'])
            ],
            [
                'key' => 'tickets',
                'label' => 'Tickets',
                'icon' => 'Ticket',
                'route' => '/tickets',
                'order' => 2,
                'is_system' => true,
                'metadata' => json_encode(['badge' => 'new'])
            ],
            [
                'key' => 'reports',
                'label' => 'Reportes',
                'icon' => 'BarChart3',
                'route' => '/reports',
                'order' => 3,
                'is_system' => false,
            ],
            
            // Sección de Administración (con hijos)
            [
                'key' => 'admin',
                'label' => 'Administración',
                'icon' => 'Settings',
                'route' => '#',
                'order' => 10,
                'is_system' => false,
            ],
        ];

        foreach ($menuItems as $item) {
            MenuItem::create($item);
        }

        // Sub-items de Administración
        $adminParent = MenuItem::where('key', 'admin')->first();
        
        $subItems = [
            [
                'key' => 'admin.users',
                'label' => 'Usuarios',
                'icon' => 'Users',
                'route' => '/admin/users',
                'parent_id' => $adminParent->id,
                'order' => 1,
                'is_system' => true,
            ],
            [
                'key' => 'admin.roles',
                'label' => 'Roles y Permisos',
                'icon' => 'Shield',
                'route' => '/admin/roles',
                'parent_id' => $adminParent->id,
                'order' => 2,
                'is_system' => true,
            ],
            [
                'key' => 'admin.areas',
                'label' => 'Áreas',
                'icon' => 'Building2',
                'route' => '/admin/areas',
                'parent_id' => $adminParent->id,
                'order' => 3,
                'is_system' => false,
            ],
            [
                'key' => 'admin.menu',
                'label' => 'Configurar Menú',
                'icon' => 'Menu',
                'route' => '/admin/menu',
                'parent_id' => $adminParent->id,
                'order' => 4,
                'is_system' => false,
            ],
        ];

        foreach ($subItems as $item) {
            MenuItem::create($item);
        }
    }
}
