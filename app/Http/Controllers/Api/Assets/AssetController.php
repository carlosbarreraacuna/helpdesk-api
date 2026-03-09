<?php

namespace App\Http\Controllers\Api\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetFieldValue;
use App\Models\AssetLocationLog;
use App\Services\Assets\AssetEventService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(private AssetEventService $eventService) {}

    public function index(Request $request)
    {
        $query = Asset::with(['assetType', 'location', 'currentUser'])
            ->withCount('assignments');

        if ($request->filled('type_id')) {
            $query->where('asset_type_id', $request->type_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('user_id')) {
            $query->where('current_user_id', $request->user_id);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ilike', $s)
                  ->orWhere('internal_code', 'ilike', $s)
                  ->orWhere('serial_number', 'ilike', $s)
                  ->orWhere('inventory_tag', 'ilike', $s);
            });
        }

        return response()->json($query->orderBy('name')->paginate(20));
    }

    public function show(Asset $asset)
    {
        return response()->json(
            $asset->load(['assetType.fields', 'location.area', 'currentUser', 'fieldValues.field', 'activeAssignment.user'])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_type_id'     => 'required|exists:asset_types,id',
            'name'              => 'required|string|max:150',
            'internal_code'     => 'nullable|string|max:80|unique:assets',
            'serial_number'     => 'nullable|string|max:100|unique:assets',
            'inventory_tag'     => 'nullable|string|max:80|unique:assets',
            'location_id'       => 'nullable|exists:locations,id',
            'vendor'            => 'nullable|string|max:150',
            'purchase_date'     => 'nullable|date',
            'warranty_end_date' => 'nullable|date',
            'warranty_provider' => 'nullable|string|max:150',
            'notes'             => 'nullable|string',
            'is_shared'         => 'boolean',
            'field_values'      => 'nullable|array',
            'field_values.*.field_id' => 'required|exists:asset_type_fields,id',
            'field_values.*.value'    => 'nullable|string',
        ]);

        $fieldValues = $data['field_values'] ?? [];
        unset($data['field_values']);
        $data['created_by'] = $request->user()->id;

        $asset = Asset::create($data);

        foreach ($fieldValues as $fv) {
            AssetFieldValue::create([
                'asset_id' => $asset->id,
                'field_id' => $fv['field_id'],
                'value'    => $fv['value'] ?? null,
            ]);
        }

        $this->eventService->record($asset, 'created', 'Activo registrado en el sistema.', $request->user()->id);

        return response()->json($asset->load(['assetType', 'location', 'fieldValues.field']), 201);
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'name'              => 'sometimes|string|max:150',
            'internal_code'     => 'nullable|string|max:80|unique:assets,internal_code,' . $asset->id,
            'serial_number'     => 'nullable|string|max:100|unique:assets,serial_number,' . $asset->id,
            'inventory_tag'     => 'nullable|string|max:80|unique:assets,inventory_tag,' . $asset->id,
            'location_id'       => 'nullable|exists:locations,id',
            'vendor'            => 'nullable|string|max:150',
            'purchase_date'     => 'nullable|date',
            'warranty_end_date' => 'nullable|date',
            'warranty_provider' => 'nullable|string|max:150',
            'notes'             => 'nullable|string',
            'is_shared'         => 'sometimes|boolean',
            'field_values'      => 'nullable|array',
            'field_values.*.field_id' => 'required|exists:asset_type_fields,id',
            'field_values.*.value'    => 'nullable|string',
        ]);

        $fieldValues = $data['field_values'] ?? null;
        unset($data['field_values']);

        $asset->update($data);

        if ($fieldValues !== null) {
            foreach ($fieldValues as $fv) {
                AssetFieldValue::updateOrCreate(
                    ['asset_id' => $asset->id, 'field_id' => $fv['field_id']],
                    ['value' => $fv['value'] ?? null]
                );
            }
        }

        $this->eventService->record($asset, 'updated', 'Datos del activo actualizados.', $request->user()->id);

        return response()->json($asset->fresh(['assetType', 'location', 'fieldValues.field']));
    }

    public function updateStatus(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'status' => 'required|in:available,assigned,pending_return,in_repair,damaged,retired',
            'notes'  => 'nullable|string',
        ]);
        $old = $asset->status;
        $asset->update(['status' => $data['status']]);
        $this->eventService->record(
            $asset, 'status_change',
            "Estado cambiado de '{$old}' a '{$data['status']}'.",
            $request->user()->id,
            ['old_status' => $old, 'new_status' => $data['status'], 'notes' => $data['notes'] ?? null]
        );
        return response()->json($asset);
    }

    public function updateLocation(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'reason'      => 'nullable|string',
        ]);

        $fromLocationId = $asset->location_id;
        $asset->update(['location_id' => $data['location_id']]);

        AssetLocationLog::create([
            'asset_id'         => $asset->id,
            'from_location_id' => $fromLocationId,
            'to_location_id'   => $data['location_id'],
            'changed_by'       => $request->user()->id,
            'reason'           => $data['reason'] ?? null,
            'changed_at'       => now(),
        ]);

        $this->eventService->record(
            $asset, 'location_change',
            "Ubicación del activo actualizada.",
            $request->user()->id,
            ['from_location_id' => $fromLocationId, 'to_location_id' => $data['location_id']]
        );

        return response()->json($asset->fresh(['location.area']));
    }

    public function events(Asset $asset)
    {
        return response()->json($asset->events()->with('performedBy:id,name')->get());
    }

    public function assignments(Asset $asset)
    {
        return response()->json($asset->assignments()->with(['user:id,name,email', 'assignedBy:id,name'])->orderByDesc('assigned_at')->get());
    }

    public function maintenances(Asset $asset)
    {
        return response()->json($asset->maintenances()->with(['serviceProvider', 'creator:id,name'])->orderByDesc('created_at')->get());
    }

    public function destroy(Asset $asset)
    {
        $asset->update(['status' => 'retired']);
        $this->eventService->record($asset, 'retirement', 'Activo dado de baja.', request()->user()->id);
        return response()->json(null, 204);
    }
}
