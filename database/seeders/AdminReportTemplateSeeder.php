<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportTemplate;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class AdminReportTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'key' => 'tickets_by_channel',
                'name' => 'Tickets por Canal',
                'description' => 'Distribución de tickets por canal de ingreso (Portal, Email, WhatsApp)',
                'type' => 'chart',
                'chart_type' => 'doughnut',
                'icon' => 'Radio',
                'is_system' => true,
                'order' => 13,
                'config' => json_encode([
                    'endpoint' => '/reports/charts/tickets-by-channel'
                ])
            ],
            [
                'key' => 'tickets_by_category',
                'name' => 'Tickets por Categoría',
                'description' => 'Distribución de tickets por categoría',
                'type' => 'chart',
                'chart_type' => 'bar',
                'icon' => 'Tags',
                'is_system' => true,
                'order' => 14,
                'config' => json_encode([
                    'endpoint' => '/reports/charts/tickets-by-category'
                ])
            ],
            [
                'key' => 'backlog_aging',
                'name' => 'Antigüedad del Backlog',
                'description' => 'Tickets abiertos agrupados por días de antigüedad',
                'type' => 'chart',
                'chart_type' => 'bar',
                'icon' => 'Hourglass',
                'is_system' => true,
                'order' => 15,
                'config' => json_encode([
                    'endpoint' => '/reports/charts/backlog-aging'
                ])
            ],
            [
                'key' => 'validation_rejection_rate',
                'name' => 'Tasa de Rechazo de Validaciones',
                'description' => 'Porcentaje de validaciones rechazadas por el usuario solicitante',
                'type' => 'metric',
                'icon' => 'XCircle',
                'is_system' => true,
                'order' => 16,
                'config' => json_encode([
                    'color' => 'red',
                    'endpoint' => '/reports/metrics/validation-rejection-rate',
                    'format' => 'percent'
                ])
            ],
        ];

        $adminRole = Role::where('name', 'admin')->first();

        foreach ($templates as $template) {
            $report = ReportTemplate::firstOrCreate(
                ['key' => $template['key']],
                $template
            );

            if ($adminRole && !$report->roles()->where('role_id', $adminRole->id)->exists()) {
                DB::table('report_template_role')->insert([
                    'report_template_id' => $report->id,
                    'role_id' => $adminRole->id,
                    'can_view' => true,
                    'can_export' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
