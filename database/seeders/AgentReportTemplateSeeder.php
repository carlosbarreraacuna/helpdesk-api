<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportTemplate;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class AgentReportTemplateSeeder extends Seeder
{
    public function run()
    {
        $newTemplates = [
            [
                'key' => 'tickets_near_sla_deadline',
                'name' => 'Tickets Próximos a Vencer SLA',
                'description' => 'Tickets abiertos ordenados por cercanía a su fecha límite de SLA',
                'type' => 'table',
                'icon' => 'AlarmClock',
                'is_system' => true,
                'order' => 19,
                'config' => json_encode([
                    'endpoint' => '/reports/tables/tickets-near-sla-deadline',
                    'columns' => ['ticket_number', 'status', 'priority', 'agent', 'sla_status', 'sla_due_date']
                ])
            ],
            [
                'key' => 'my_pending_validation',
                'name' => 'Pendientes de Validación del Usuario',
                'description' => 'Tickets esperando que el solicitante valide la resolución',
                'type' => 'table',
                'icon' => 'UserCheck',
                'is_system' => true,
                'order' => 20,
                'config' => json_encode([
                    'endpoint' => '/reports/tables/my-pending-validation',
                    'columns' => ['ticket_number', 'agent', 'validation_requested_at', 'validation_deadline']
                ])
            ],
        ];

        $roles = Role::whereIn('name', ['admin', 'supervisor', 'agente'])->get()->keyBy('name');

        foreach ($newTemplates as $template) {
            $report = ReportTemplate::firstOrCreate(
                ['key' => $template['key']],
                $template
            );

            foreach (['admin', 'supervisor', 'agente'] as $roleName) {
                $role = $roles[$roleName] ?? null;
                if ($role && !$report->roles()->where('role_id', $role->id)->exists()) {
                    DB::table('report_template_role')->insert([
                        'report_template_id' => $report->id,
                        'role_id' => $role->id,
                        'can_view' => true,
                        'can_export' => $roleName !== 'agente',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Dar a agente acceso a métricas/tablas existentes que ya están scoped a su propia bandeja
        $agente = $roles['agente'] ?? null;
        if ($agente) {
            $existingKeysForAgent = ['avg_resolution_time', 'sla_compliance'];
            foreach (ReportTemplate::whereIn('key', $existingKeysForAgent)->get() as $report) {
                if (!$report->roles()->where('role_id', $agente->id)->exists()) {
                    DB::table('report_template_role')->insert([
                        'report_template_id' => $report->id,
                        'role_id' => $agente->id,
                        'can_view' => true,
                        'can_export' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
