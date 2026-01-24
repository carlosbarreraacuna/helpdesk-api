<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Area;
use Illuminate\Database\Seeder;

class AssignAreasToUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todas las áreas disponibles
        $areas = Area::all();
        
        if ($areas->isEmpty()) {
            $this->command->warn('No hay áreas disponibles para asignar.');
            return;
        }

        // Obtener usuarios sin área asignada
        $usersWithoutArea = User::whereNull('area_id')->get();
        
        if ($usersWithoutArea->isEmpty()) {
            $this->command->info('Todos los usuarios ya tienen área asignada.');
            return;
        }

        $assignedCount = 0;
        $areasCount = $areas->count();

        foreach ($usersWithoutArea as $index => $user) {
            // Asignar área en forma rotativa
            $area = $areas->get($index % $areasCount);
            
            $user->update(['area_id' => $area->id]);
            $assignedCount++;
            
            $this->command->info(
                "Usuario ID:{$user->id} ({$user->name}) → Área: {$area->name}"
            );
        }

        $this->command->info("Se asignaron áreas a {$assignedCount} usuarios exitosamente.");
    }
}
