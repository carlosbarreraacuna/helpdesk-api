<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportTemplateController extends Controller
{
    public function index()
    {
        $templates = ReportTemplate::with('roles')->orderBy('order')->get();
        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|unique:report_templates',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:chart,table,metric,export',
            'chart_type' => 'nullable|in:bar,line,pie,doughnut,area',
            'icon' => 'nullable|string',
            'config' => 'nullable|array',
            'order' => 'integer|min:0',
        ]);

        $template = ReportTemplate::create($validated);

        // Asignar a todos los roles por defecto (solo admin visible)
        $roles = Role::all();
        foreach ($roles as $role) {
            $template->roles()->attach($role->id, [
                'can_view' => $role->name === 'admin',
                'can_export' => $role->name === 'admin',
            ]);
        }

        return response()->json([
            'message' => 'Reporte creado exitosamente',
            'template' => $template->load('roles')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $template = ReportTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'config' => 'nullable|array',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'message' => 'Reporte actualizado exitosamente',
            'template' => $template
        ]);
    }

    public function destroy($id)
    {
        $template = ReportTemplate::findOrFail($id);

        if ($template->is_system) {
            return response()->json([
                'message' => 'No se pueden eliminar reportes del sistema'
            ], 422);
        }

        $template->delete();

        return response()->json([
            'message' => 'Reporte eliminado exitosamente'
        ]);
    }

    public function getReportsForRole($roleId)
    {
        $role = Role::findOrFail($roleId);
        
        $reports = ReportTemplate::active()
            ->orderBy('order')
            ->get()
            ->map(function($report) use ($roleId) {
                $pivot = $report->roles()->where('role_id', $roleId)->first();
                $report->can_view = $pivot ? $pivot->pivot->can_view : false;
                $report->can_export = $pivot ? $pivot->pivot->can_export : false;
                return $report;
            });

        return response()->json([
            'role' => $role,
            'reports' => $reports
        ]);
    }

    public function updateRoleReports(Request $request, $roleId)
    {
        $validated = $request->validate([
            'reports' => 'required|array',
            'reports.*.report_template_id' => 'required|exists:report_templates,id',
            'reports.*.can_view' => 'required|boolean',
            'reports.*.can_export' => 'required|boolean',
        ]);

        $role = Role::findOrFail($roleId);

        foreach ($validated['reports'] as $report) {
            DB::table('report_template_role')
                ->updateOrInsert(
                    [
                        'report_template_id' => $report['report_template_id'],
                        'role_id' => $roleId,
                    ],
                    [
                        'can_view' => $report['can_view'],
                        'can_export' => $report['can_export'],
                        'updated_at' => now(),
                    ]
                );
        }

        return response()->json([
            'message' => 'Configuración de reportes actualizada exitosamente'
        ]);
    }
}
