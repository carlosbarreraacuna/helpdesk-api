<?php

namespace App\Http\Controllers\Api\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Assets\AssetAssignmentService;
use Illuminate\Http\Request;

/**
 * @tags Inventario de Activos
 */
class AssetAssignmentController extends Controller
{
    public function __construct(private AssetAssignmentService $assignmentService) {}

    public function assign(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason'  => 'nullable|string',
        ]);

        try {
            $assignment = $this->assignmentService->assign(
                $asset,
                $data['user_id'],
                $request->user()->id,
                $data['reason'] ?? null
            );
            return response()->json($assignment->load(['user:id,name,email', 'assignedBy:id,name']), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function return(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'return_notes' => 'nullable|string',
        ]);

        try {
            $assignment = $this->assignmentService->return(
                $asset,
                $request->user()->id,
                $data['return_notes'] ?? null
            );
            return response()->json($assignment->load(['user:id,name,email', 'assignedBy:id,name']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function userAssets(Request $request, int $userId)
    {
        $authUser = $request->user();

        // Usuario normal solo puede ver sus propios activos
        if ($authUser->role?->name === 'user' && $authUser->id !== $userId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $assets = Asset::with(['assetType', 'location'])
            ->where('current_user_id', $userId)
            ->get();

        return response()->json($assets);
    }
}
