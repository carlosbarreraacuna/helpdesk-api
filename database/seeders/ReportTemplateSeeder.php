<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportTemplate;

class ReportTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            // MÉTRICAS
            [
                'key' => 'total_tickets',
                'name' => 'Total de Tickets',
                'description' => 'Cantidad total de tickets en el sistema',
                'type' => 'metric',
                'icon' => 'Ticket',
                'is_system' => true,
                'order' => 1,
                'config' => json_encode([
                    'color' => 'blue',
                    'endpoint' => '/reports/metrics/total-tickets'
                ])
            ],
            [
                'key' => 'tickets_pending',
                'name' => 'Tickets Pendientes',
                'description' => 'Tickets en estado Nuevo o Asignado',
                'type' => 'metric',
                'icon' => 'Clock',
                'is_system' => true,
                'order' => 2,
                'config' => json_encode([
                    'color' => 'yellow',
                    'endpoint' => '/reports/metrics/pending-tickets'
                ])
            ],
            [
                'key' => 'tickets_resolved',
                'name' => 'Tickets Resueltos',
                'description' => 'Tickets resueltos en el período',
                'type' => 'metric',
                'icon' => 'CheckCircle',
                'is_system' => true,
                'order' => 3,
                'config' => json_encode([
                    'color' => 'green',
                    'endpoint' => '/reports/metrics/resolved-tickets'
                ])
            ],
            [
                'key' => 'avg_resolution_time',
                'name' => 'Tiempo Promedio de Resolución',
                'description' => 'Tiempo promedio en resolver tickets',
                'type' => 'metric',
                'icon' => 'Timer',
                'is_system' => true,
                'order' => 4,
                'config' => json_encode([
                    'color' => 'purple',
                    'endpoint' => '/reports/metrics/avg-resolution-time',
                    'format' => 'hours'
                ])
            ],

            // GRÁFICOS
            [
                'key' => 'tickets_by_status',
                'name' => 'Tickets por Estado',
                'description' => 'Distribución de tickets según su estado',
                'type' => 'chart',
                'chart_type' => 'doughnut',
                'icon' => 'PieChart',
                'is_system' => true,
                'order' => 5,
                'config' => json_encode([
                    'endpoint' => '/reports/charts/tickets-by-status'
                ])
            ],
            [
                'key' => 'tickets_by_priority',
                'name' => 'Tickets por Prioridad',
                'description' => 'Distribución de tickets por nivel de prioridad',
                'type' => 'chart',
                'chart_type' => 'bar',
                'icon' => 'BarChart3',
                'is_system' => true,
                'order' => 6,
                'config' => json_encode([
                    'endpoint' => '/reports/charts/tickets-by-priority'
                ])
            ],
            [
                'key' => 'tickets_trend',
                'name' => 'Tendencia de Tickets',
                'description' => 'Tickets creados vs resueltos en los últimos 30 días',
                'type' => 'chart',
                'chart_type' => 'line',
                'icon' => 'TrendingUp',
                'is_system' => true,
                'order' => 7,
                'config' => json_encode([
                    'endpoint' => '/reports/charts/tickets-trend',
                    'days' => 30
                ])
            ],
            [
                'key' => 'tickets_by_area',
                'name' => 'Tickets por Área',
                'description' => 'Distribución de tickets por área o departamento',
                'type' => 'chart',
                'chart_type' => 'bar',
                'icon' => 'Building2',
                'is_system' => true,
                'order' => 8,
                'config' => json_encode([
                    'endpoint' => '/reports/charts/tickets-by-area'
                ])
            ],

            // TABLAS
            [
                'key' => 'tickets_by_agent',
                'name' => 'Rendimiento por Agente',
                'description' => 'Estadísticas de tickets por cada agente',
                'type' => 'table',
                'icon' => 'Users',
                'is_system' => true,
                'order' => 9,
                'config' => json_encode([
                    'endpoint' => '/reports/tables/tickets-by-agent',
                    'columns' => ['agent', 'assigned', 'resolved', 'avg_time', 'sla_compliance']
                ])
            ],
            [
                'key' => 'sla_compliance',
                'name' => 'Cumplimiento de SLA',
                'description' => 'Tickets dentro y fuera del SLA',
                'type' => 'table',
                'icon' => 'AlertTriangle',
                'is_system' => true,
                'order' => 10,
                'config' => json_encode([
                    'endpoint' => '/reports/tables/sla-compliance'
                ])
            ],

            // EXPORTABLES
            [
                'key' => 'export_all_tickets',
                'name' => 'Exportar Todos los Tickets',
                'description' => 'Exporta listado completo de tickets con filtros',
                'type' => 'export',
                'icon' => 'Download',
                'is_system' => true,
                'order' => 11,
                'config' => json_encode([
                    'endpoint' => '/reports/export/all-tickets',
                    'formats' => ['excel', 'csv', 'pdf']
                ])
            ],
            [
                'key' => 'export_agent_performance',
                'name' => 'Exportar Rendimiento de Agentes',
                'description' => 'Reporte detallado del desempeño de cada agente',
                'type' => 'export',
                'icon' => 'FileSpreadsheet',
                'is_system' => true,
                'order' => 12,
                'config' => json_encode([
                    'endpoint' => '/reports/export/agent-performance',
                    'formats' => ['excel', 'pdf']
                ])
            ],
        ];

        foreach ($templates as $template) {
            ReportTemplate::create($template);
        }
    }
}
