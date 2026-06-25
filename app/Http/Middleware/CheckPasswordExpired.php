<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->password_expires_at && $user->password_expires_at->isPast()) {
            return response()->json([
                'message'    => 'password_expired',
                'expires_at' => $user->password_expires_at,
            ], 423);
        }

        return $next($request);
    }
}
