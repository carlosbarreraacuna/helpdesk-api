<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportTemplate;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class SupervisorReportTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'key' => 'unassigned_stalled_tickets',
                'name' => 'Tickets Sin Asignar / Estancados',
                'description' => 'Tickets sin agente asignado o sin movimiento en los últimos 3 días',
                'type' => 'table',
                'icon' => 'AlertOctagon',
                'is_system' => true,
                'order' => 17,
                'config' => json_encode([
                    'endpoint' => '/reports/tables/unassigned-stalled',
                    'columns' => ['ticket_number', 'status', 'priority', 'agent', 'days_open']
                ])
            ],
            [
                'key' => 'validations_rejected_team',
                'name' => 'Validaciones Rechazadas del Equipo',
                'description' => 'Tickets de tu equipo cuya validación fue rechazada por el usuario solicitante',
                'type' => 'table',
                'icon' => 'XOctagon',
                'is_system' => true,
                'order' => 18,
                'config' => json_encode([
                    'endpoint' => '/reports/tables/validations-rejected-team',
                    'columns' => ['ticket_number', 'agent', 'rejected_count', 'status']
                ])
            ],
        ];

        $roles = Role::whereIn('name', ['admin', 'supervisor'])->get()->keyBy('name');

        foreach ($templates as $template) {
            $report = ReportTemplate::firstOrCreate(
                ['key' => $template['key']],
                $template
            );

            foreach (['admin', 'supervisor'] as $roleName) {
                $role = $roles[$roleName] ?? null;
                if ($role && !$report->roles()->where('role_id', $role->id)->exists()) {
                    DB::table('report_template_role')->insert([
                        'report_template_id' => $report->id,
                        'role_id' => $role->id,
                        'can_view' => true,
                        'can_export' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
