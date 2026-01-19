<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if (!$request->user()->hasPermissionAccess($permission)) {
            return response()->json([
                'message' => 'No tienes permiso para realizar esta acción',
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
