<?php

namespace Database\Seeders;

use App\Models\SlaConfig;
use Illuminate\Database\Seeder;

class SlaConfigSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'priority'              => 'alta',
                'response_time_hours'   => 4,
                'resolution_time_hours' => 8,
                'alert_threshold'       => 80,
                'work_start_hour'       => 8,
                'work_end_hour'         => 18,
            ],
            [
                'priority'              => 'media',
                'response_time_hours'   => 8,
                'resolution_time_hours' => 24,
                'alert_threshold'       => 80,
                'work_start_hour'       => 8,
                'work_end_hour'         => 18,
            ],
            [
                'priority'              => 'baja',
                'response_time_hours'   => 24,
                'resolution_time_hours' => 72,
                'alert_threshold'       => 80,
                'work_start_hour'       => 8,
                'work_end_hour'         => 18,
            ],
        ];

        foreach ($defaults as $config) {
            SlaConfig::updateOrCreate(
                ['priority' => $config['priority']],
                $config
            );
        }
    }
}
