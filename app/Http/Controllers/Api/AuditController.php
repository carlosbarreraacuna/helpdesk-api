<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PermissionChangeLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function permissionLogs(Request $request)
    {
        $query = PermissionChangeLog::with([
            'changedBy:id,name,email',
            'permission:id,name,display_name'
        ]);
        
        // Filtros
        if ($request->change_type && $request->change_type !== 'all') {
            $query->where('change_type', $request->change_type);
        }
        
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $logs = $query->orderBy('created_at', 'desc')->paginate(50);
        
        return response()->json($logs);
    }
    
    public function userPermissionLogs($userId)
    {
        $logs = PermissionChangeLog::with([
            'changedBy:id,name,email',
            'permission:id,name,display_name'
        ])
        ->where('change_type', 'user_permission')
        ->where('entity_id', $userId)
        ->orderBy('created_at', 'desc')
        ->paginate(50);
        
        return response()->json($logs);
    }
    
    public function rolePermissionLogs($roleId)
    {
        $logs = PermissionChangeLog::with([
            'changedBy:id,name,email',
            'permission:id,name,display_name'
        ])
        ->where('change_type', 'role_permission')
        ->where('entity_id', $roleId)
        ->orderBy('created_at', 'desc')
        ->paginate(50);
        
        return response()->json($logs);
    }
}
