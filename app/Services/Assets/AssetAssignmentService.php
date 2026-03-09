<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetAssignment;
use Illuminate\Support\Facades\DB;

class AssetAssignmentService
{
    public function __construct(private AssetEventService $eventService) {}

    public function assign(Asset $asset, int $userId, int $assignedBy, ?string $reason = null): AssetAssignment
    {
        if (!$asset->is_shared && $asset->status !== 'available') {
            throw new \Exception("El activo no está disponible para asignación. Estado actual: {$asset->status}");
        }

        return DB::transaction(function () use ($asset, $userId, $assignedBy, $reason) {
            $assignment = AssetAssignment::create([
                'asset_id'    => $asset->id,
                'user_id'     => $userId,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'reason'      => $reason,
                'is_active'   => true,
            ]);

            if (!$asset->is_shared) {
                $asset->update([
                    'current_user_id' => $userId,
                    'status'          => 'assigned',
                ]);
            }

            $this->eventService->record(
                $asset, 'assignment',
                "Activo asignado al usuario ID {$userId}",
                $assignedBy,
                ['user_id' => $userId, 'reason' => $reason]
            );

            return $assignment;
        });
    }

    public function return(Asset $asset, int $returnedBy, ?string $returnNotes = null): AssetAssignment
    {
        $assignment = AssetAssignment::where('asset_id', $asset->id)
            ->where('is_active', true)
            ->latest('assigned_at')
            ->firstOrFail();

        return DB::transaction(function () use ($asset, $assignment, $returnedBy, $returnNotes) {
            $assignment->update([
                'returned_at'  => now(),
                'return_notes' => $returnNotes,
                'is_active'    => false,
            ]);

            $asset->update([
                'current_user_id' => null,
                'status'          => 'available',
            ]);

            $this->eventService->record(
                $asset, 'user_change',
                "Activo devuelto por usuario ID {$assignment->user_id}",
                $returnedBy,
                ['from_user' => $assignment->user_id, 'return_notes' => $returnNotes]
            );

            return $assignment;
        });
    }
}
