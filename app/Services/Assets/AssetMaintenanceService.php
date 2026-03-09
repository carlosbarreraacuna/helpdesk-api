<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use Illuminate\Support\Facades\DB;

class AssetMaintenanceService
{
    public function __construct(private AssetEventService $eventService) {}

    public function create(array $data, int $createdBy): AssetMaintenance
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $maintenance = AssetMaintenance::create([...$data, 'created_by' => $createdBy]);

            $asset = Asset::findOrFail($data['asset_id']);

            if ($data['maintenance_type'] === 'corrective') {
                $asset->update(['status' => 'in_repair']);
            }

            $this->eventService->record(
                $asset, 'maintenance',
                "Mantenimiento {$data['maintenance_type']} registrado",
                $createdBy,
                ['maintenance_id' => $maintenance->id]
            );

            return $maintenance->load(['asset', 'serviceProvider', 'creator']);
        });
    }

    public function updateStatus(AssetMaintenance $maintenance, string $status, array $extra, int $updatedBy): AssetMaintenance
    {
        return DB::transaction(function () use ($maintenance, $status, $extra, $updatedBy) {
            $maintenance->update(['status' => $status, ...$extra]);

            $asset = $maintenance->asset;

            if ($status === 'completed') {
                $newStatus = $asset->current_user_id ? 'assigned' : 'available';
                $asset->update(['status' => $newStatus]);

                $this->eventService->record(
                    $asset, 'recovery',
                    "Mantenimiento completado. Activo recuperado.",
                    $updatedBy,
                    ['maintenance_id' => $maintenance->id, 'cost' => $extra['cost'] ?? null]
                );
            }

            return $maintenance->fresh(['asset', 'serviceProvider', 'creator']);
        });
    }
}
