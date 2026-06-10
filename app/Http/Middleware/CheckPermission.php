<?php

namespace App\Http\Middleware;

use App\Models\AuthLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if (!$request->user()->hasPermissionAccess($permission)) {
            AuthLog::record(
                event: 'access_denied',
                userId: $request->user()->id,
                requiredPermission: $permission,
            );

            return response()->json([
                'message'             => 'No tienes permiso para realizar esta acción',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
