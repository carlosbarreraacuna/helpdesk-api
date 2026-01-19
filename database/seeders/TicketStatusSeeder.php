<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TicketStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('ticket_statuses')->insert([
            ['name' => 'nuevo', 'color' => '#FCD34D', 'order' => 1],
            ['name' => 'asignado', 'color' => '#60A5FA', 'order' => 2],
            ['name' => 'en_progreso', 'color' => '#A78BFA', 'order' => 3],
            ['name' => 'pendiente_usuario', 'color' => '#FB923C', 'order' => 4],
            ['name' => 'escalado', 'color' => '#F472B6', 'order' => 5],
            ['name' => 'resuelto', 'color' => '#34D399', 'order' => 6],
            ['name' => 'cerrado', 'color' => '#9CA3AF', 'order' => 7],
            ['name' => 'reabierto', 'color' => '#EF4444', 'order' => 8],
        ]);
    }
}
