<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Models\PermissionChangeLog;
use Illuminate\Http\Request;

/**
 * @tags Auditoría
 */
class AuditController extends Controller
{
    public function permissionLogs(Request $request)
    {
        $query = PermissionChangeLog::with([
            'changedBy:id,name,email',
            'permission:id,name,display_name',
        ]);

        if ($request->change_type && $request->change_type !== 'all') {
            $query->where('change_type', $request->change_type);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(50));
    }

    public function userPermissionLogs($userId)
    {
        return response()->json(
            PermissionChangeLog::with(['changedBy:id,name,email', 'permission:id,name,display_name'])
                ->where('change_type', 'user_permission')
                ->where('entity_id', $userId)
                ->orderByDesc('created_at')
                ->paginate(50)
        );
    }

    public function rolePermissionLogs($roleId)
    {
        return response()->json(
            PermissionChangeLog::with(['changedBy:id,name,email', 'permission:id,name,display_name'])
                ->where('change_type', 'role_permission')
                ->where('entity_id', $roleId)
                ->orderByDesc('created_at')
                ->paginate(50)
        );
    }

    public function authLogs(Request $request)
    {
        $query = AuthLog::with('user:id,name,email');

        if ($request->event && $request->event !== 'all') {
            $query->where('event', $request->event);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->ip_address) {
            $query->where('ip_address', $request->ip_address);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(50));
    }

    public function authLogsSummary()
    {
        $since = now()->subDays(30);

        return response()->json([
            'last_30_days' => [
                'login_success'  => AuthLog::where('event', 'login_success')->where('created_at', '>=', $since)->count(),
                'login_failed'   => AuthLog::where('event', 'login_failed')->where('created_at', '>=', $since)->count(),
                'login_inactive' => AuthLog::where('event', 'login_inactive')->where('created_at', '>=', $since)->count(),
                'logout'         => AuthLog::where('event', 'logout')->where('created_at', '>=', $since)->count(),
                'access_denied'  => AuthLog::where('event', 'access_denied')->where('created_at', '>=', $since)->count(),
            ],
        ]);
    }
}
