<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportTemplate;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class ReportTemplateRoleSeeder extends Seeder
{
    public function run()
    {
        $adminRole = Role::where('name', 'admin')->first();
        $supervisorRole = Role::where('name', 'supervisor')->first();
        $agenteRole = Role::where('name', 'agente')->first();

        $allReports = ReportTemplate::all();

        // ADMIN - Acceso completo a todos los reportes
        foreach ($allReports as $report) {
            DB::table('report_template_role')->insert([
                'report_template_id' => $report->id,
                'role_id' => $adminRole->id,
                'can_view' => true,
                'can_export' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // SUPERVISOR - Reportes de su área, puede exportar
        $supervisorReports = ReportTemplate::whereIn('key', [
            'total_tickets',
            'tickets_pending',
            'tickets_resolved',
            'avg_resolution_time',
            'tickets_by_status',
            'tickets_by_priority',
            'tickets_trend',
            'tickets_by_agent',
            'sla_compliance',
            'export_all_tickets',
        ])->get();

        foreach ($supervisorReports as $report) {
            DB::table('report_template_role')->insert([
                'report_template_id' => $report->id,
                'role_id' => $supervisorRole->id,
                'can_view' => true,
                'can_export' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // AGENTE - Solo métricas y gráficos básicos, sin exportar
        $agenteReports = ReportTemplate::whereIn('key', [
            'total_tickets',
            'tickets_pending',
            'tickets_resolved',
            'tickets_by_status',
            'tickets_by_priority',
        ])->get();

        foreach ($agenteReports as $report) {
            DB::table('report_template_role')->insert([
                'report_template_id' => $report->id,
                'role_id' => $agenteRole->id,
                'can_view' => true,
                'can_export' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
