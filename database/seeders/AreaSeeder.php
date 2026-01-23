<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $areas = [
            ['name' => 'Gestión ambiental', 'description' => 'Área encargada de la gestión ambiental'],
            ['name' => 'Recepción', 'description' => 'Área de recepción y atención al público'],
            ['name' => 'Tesorería', 'description' => 'Área encargada de la gestión financiera'],
            ['name' => 'Cobro coactivo', 'description' => 'Área de cobro coactivo y recaudación'],
            ['name' => 'Facturación', 'description' => 'Área de facturación y cobros'],
            ['name' => 'Calidad', 'description' => 'Área de control de calidad'],
            ['name' => 'Planeación', 'description' => 'Área de planeación estratégica'],
            ['name' => 'Jurídica', 'description' => 'Área de asuntos jurídicos'],
            ['name' => 'Gestión documental', 'description' => 'Área de gestión documental'],
            ['name' => 'Contratación', 'description' => 'Área de procesos de contratación'],
            ['name' => 'Sistemas', 'description' => 'Área de sistemas y tecnología'],
            ['name' => 'Dirección', 'description' => 'Área de dirección general'],
            ['name' => 'Comunicaciones y prensa', 'description' => 'Área de comunicaciones y prensa'],
            ['name' => 'Almacén', 'description' => 'Área de gestión de almacén'],
            ['name' => 'Sancionatorio', 'description' => 'Área de procesos sancionatorios'],
        ];

        foreach ($areas as $area) {
            Area::firstOrCreate(
                ['name' => $area['name']],
                ['description' => $area['description']]
            );
        }

        $this->command->info('Áreas creadas exitosamente');
    }
}
