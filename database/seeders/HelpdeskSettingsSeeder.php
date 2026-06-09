<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HelpdeskSetting;

class HelpdeskSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key'         => 'validation_timeout_hours',
                'value'       => '48',
                'description' => 'Horas antes de que un ticket en pendiente_validacion se cierre automáticamente si el usuario no responde.',
            ],
            [
                'key'         => 'max_validation_rejections',
                'value'       => '3',
                'description' => 'Número máximo de rechazos de validación antes de escalar el ticket automáticamente.',
            ],
        ];

        foreach ($defaults as $setting) {
            HelpdeskSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
