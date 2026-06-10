<?php

namespace App\Http\Controllers\Api\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use App\Models\UserSoftwareAssignment;
use Illuminate\Http\Request;

/**
 * @tags Inventario de Activos
 */
class UserTechProfileController extends Controller
{
    public function profile(Request $request, int $userId)
    {
        $authUser = $request->user();
        if ($authUser->role?->name === 'user' && $authUser->id !== $userId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $user = User::with(['role', 'area'])->findOrFail($userId);

        $currentAssets = Asset::with(['assetType', 'location'])
            ->where('current_user_id', $userId)->get();

        $historicalAssignments = AssetAssignment::with(['asset.assetType'])
            ->where('user_id', $userId)
            ->where('is_active', false)
            ->orderByDesc('assigned_at')->get();

        $ticketsSummary = \App\Models\Ticket::where('created_by', $userId)
            ->selectRaw('count(*) as total, sum(case when status in (\'open\',\'in_progress\',\'pending\') then 1 else 0 end) as open_count, sum(case when status = \'closed\' then 1 else 0 end) as closed_count')
            ->first();

        $software = UserSoftwareAssignment::where('user_id', $userId)
            ->where('is_active', true)->get();

        return response()->json([
            'user'            => $user,
            'current_assets'  => $currentAssets,
            'historical_assets' => $historicalAssignments,
            'tickets_summary' => $ticketsSummary,
            'software'        => $software,
        ]);
    }

    public function currentAssets(Request $request, int $userId)
    {
        $authUser = $request->user();
        if ($authUser->role?->name === 'user' && $authUser->id !== $userId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        return response()->json(
            Asset::with(['assetType', 'location'])->where('current_user_id', $userId)->get()
        );
    }

    public function assetHistory(Request $request, int $userId)
    {
        $authUser = $request->user();
        if ($authUser->role?->name === 'user' && $authUser->id !== $userId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        return response()->json(
            AssetAssignment::with(['asset.assetType'])
                ->where('user_id', $userId)->orderByDesc('assigned_at')->get()
        );
    }

    public function software(Request $request, int $userId)
    {
        $authUser = $request->user();
        if ($authUser->role?->name === 'user' && $authUser->id !== $userId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        return response()->json(
            UserSoftwareAssignment::where('user_id', $userId)->where('is_active', true)->get()
        );
    }

    public function storeSoftware(Request $request, int $userId)
    {
        $data = $request->validate([
            'software_name' => 'required|string|max:150',
            'version'       => 'nullable|string|max:50',
            'license_key'   => 'nullable|string|max:200',
            'assigned_at'   => 'required|date',
            'expires_at'    => 'nullable|date|after:assigned_at',
            'notes'         => 'nullable|string',
        ]);

        $sw = UserSoftwareAssignment::create([
            ...$data,
            'user_id'     => $userId,
            'assigned_by' => $request->user()->id,
        ]);

        return response()->json($sw, 201);
    }

    public function destroySoftware(Request $request, int $userId, UserSoftwareAssignment $software)
    {
        $software->update(['is_active' => false]);
        return response()->json(null, 204);
    }
}
