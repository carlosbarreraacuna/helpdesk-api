<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SlaConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('sla_configs')->insert([
            ['priority' => 'alta', 'response_time_hours' => 4, 'alert_threshold' => 80],
            ['priority' => 'media', 'response_time_hours' => 24, 'alert_threshold' => 80],
            ['priority' => 'baja', 'response_time_hours' => 72, 'alert_threshold' => 80],
        ]);
    }
}
