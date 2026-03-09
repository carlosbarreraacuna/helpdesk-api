<?php

namespace App\Http\Controllers\Api\Assets;

use App\Http\Controllers\Controller;
use App\Models\AssetMaintenance;
use App\Services\Assets\AssetMaintenanceService;
use Illuminate\Http\Request;

class AssetMaintenanceController extends Controller
{
    public function __construct(private AssetMaintenanceService $maintenanceService) {}

    public function index(Request $request)
    {
        $query = AssetMaintenance::with(['asset:id,name,internal_code', 'serviceProvider:id,name', 'creator:id,name']);

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('type'))     $query->where('maintenance_type', $request->type);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id'            => 'required|exists:assets,id',
            'maintenance_type'    => 'required|in:preventive,corrective',
            'description'         => 'nullable|string',
            'service_provider_id' => 'nullable|exists:service_providers,id',
            'scheduled_date'      => 'nullable|date',
            'notes'               => 'nullable|string',
            'ticket_ids'          => 'nullable|array',
            'ticket_ids.*'        => 'exists:tickets,id',
        ]);

        $ticketIds = $data['ticket_ids'] ?? [];
        unset($data['ticket_ids']);

        $maintenance = $this->maintenanceService->create($data, $request->user()->id);

        if (!empty($ticketIds)) {
            $maintenance->tickets()->sync($ticketIds);
        }

        return response()->json($maintenance->load('tickets:id,ticket_number'), 201);
    }

    public function show(AssetMaintenance $maintenance)
    {
        return response()->json(
            $maintenance->load(['asset:id,name,internal_code,status', 'serviceProvider', 'creator:id,name', 'tickets:id,ticket_number'])
        );
    }

    public function update(Request $request, AssetMaintenance $maintenance)
    {
        $data = $request->validate([
            'description'         => 'nullable|string',
            'service_provider_id' => 'nullable|exists:service_providers,id',
            'scheduled_date'      => 'nullable|date',
            'notes'               => 'nullable|string',
        ]);
        $maintenance->update($data);
        return response()->json($maintenance->fresh(['asset', 'serviceProvider', 'creator']));
    }

    public function updateStatus(Request $request, AssetMaintenance $maintenance)
    {
        $data = $request->validate([
            'status'         => 'required|in:scheduled,in_progress,completed,cancelled',
            'executed_date'  => 'nullable|date',
            'cost'           => 'nullable|numeric|min:0',
            'invoice_number' => 'nullable|string|max:80',
            'notes'          => 'nullable|string',
        ]);

        $status = $data['status'];
        unset($data['status']);

        $maintenance = $this->maintenanceService->updateStatus($maintenance, $status, $data, $request->user()->id);
        return response()->json($maintenance);
    }
}
