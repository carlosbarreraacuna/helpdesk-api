<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('roles')->insert([
            ['name' => 'admin', 'display_name' => 'Administrador', 'description' => 'Administrador del sistema', 'level' => 3],
            ['name' => 'supervisor', 'display_name' => 'Supervisor', 'description' => 'Supervisor de área', 'level' => 2],
            ['name' => 'agente', 'display_name' => 'Agente', 'description' => 'Agente de soporte', 'level' => 1],
        ]);
    }
}
