<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KbCategory;
use App\Models\KbSubcategory;
use Illuminate\Support\Str;

class KbDefaultCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'name' => 'Hardware',
                'icon' => 'Monitor',
                'color' => 'blue',
                'subs' => ['Computadores', 'Monitores', 'Teclado y Mouse', 'Impresoras', 'Otros dispositivos'],
            ],
            [
                'name' => 'Software',
                'icon' => 'Code',
                'color' => 'green',
                'subs' => ['Instalación', 'Errores y fallas', 'Licencias', 'Office / Microsoft 365'],
            ],
            [
                'name' => 'Red y Conectividad',
                'icon' => 'Wifi',
                'color' => 'purple',
                'subs' => ['Wi-Fi', 'VPN', 'Internet lento', 'Acceso a recursos de red'],
            ],
            [
                'name' => 'Cuentas y Accesos',
                'icon' => 'Key',
                'color' => 'red',
                'subs' => ['Contraseñas', 'Acceso al sistema', 'Permisos'],
            ],
            [
                'name' => 'Correo Electrónico',
                'icon' => 'Mail',
                'color' => 'yellow',
                'subs' => ['Configuración', 'Problemas de envío/recepción', 'Outlook'],
            ],
            [
                'name' => 'General',
                'icon' => 'BookOpen',
                'color' => 'gray',
                'subs' => [],
            ],
        ];

        foreach ($data as $i => $cat) {
            $category = KbCategory::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name'        => $cat['name'],
                    'icon'        => $cat['icon'],
                    'color'       => $cat['color'],
                    'order_index' => $i + 1,
                    'is_active'   => true,
                ]
            );

            foreach ($cat['subs'] as $j => $subName) {
                KbSubcategory::firstOrCreate(
                    ['slug' => Str::slug($subName) . '-' . Str::random(4)],
                    [
                        'category_id' => $category->id,
                        'name'        => $subName,
                        'order_index' => $j + 1,
                        'is_active'   => true,
                    ]
                );
            }
        }

        $this->command->info('KB default categories seeded: ' . KbCategory::count() . ' categories.');
    }
}
