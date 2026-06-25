<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @tags Autenticación
 */
class AuthController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login'    => 'required|string',
            'password' => 'required',
        ]);

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::whereRaw('LOWER(' . $loginField . ') = ?', [strtolower($credentials['login'])])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            AuthLog::record(event: 'login_failed', loginInput: $credentials['login']);
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if (!$user->is_active) {
            AuthLog::record(event: 'login_inactive', userId: $user->id, loginInput: $credentials['login']);
            return response()->json(['message' => 'Usuario inactivo'], 403);
        }

        // ── 2FA checks ─────────────────────────────────────────────────────
        $adminRoles = ['admin', 'supervisor'];
        $isAdmin    = in_array($user->role?->name, $adminRoles);

        if ($user->two_factor_enabled) {
            // Require TOTP verification
            $challengeToken = Str::uuid()->toString();
            Cache::put("2fa_challenge:{$challengeToken}", $user->id, now()->addMinutes(5));
            return response()->json([
                'requires_2fa'    => true,
                'challenge_token' => $challengeToken,
            ]);
        }

        if ($isAdmin) {
            // Admin/supervisor without 2FA → force setup (MSPI requirement)
            $setupToken = Str::uuid()->toString();
            Cache::put("2fa_setup:{$setupToken}", $user->id, now()->addMinutes(15));
            return response()->json([
                'requires_2fa_setup' => true,
                'setup_token'        => $setupToken,
            ]);
        }
        // ────────────────────────────────────────────────────────────────────

        $user->update(['last_login' => now()]);

        AuthLog::record(event: 'login_success', userId: $user->id);

        // Access token — short lived (60 min via sanctum config)
        $accessToken = $user->createToken('auth-token')->plainTextToken;

        // Refresh token — 7 days, delivered as httpOnly cookie
        $refresh = RefreshToken::generate($user->id);

        $refreshCookie = Cookie::make(
            name: self::REFRESH_COOKIE,
            value: $refresh->token,
            minutes: 60 * 24 * 7,
            path: '/api/auth',
            secure: true,
            httpOnly: true,
            sameSite: 'None',
        );

        return response()->json([
            'user'  => $user->load('role', 'area'),
            'token' => $accessToken,
        ])->withCookie($refreshCookie);
    }

    public function refresh(Request $request)
    {
        $rawToken = $request->cookie(self::REFRESH_COOKIE);

        if (!$rawToken) {
            return response()->json(['message' => 'Refresh token no encontrado'], 401);
        }

        $refreshToken = RefreshToken::where('token', $rawToken)
            ->with('user')
            ->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return response()->json(['message' => 'Refresh token inválido o expirado'], 401);
        }

        $user = $refreshToken->user;

        if (!$user->is_active) {
            return response()->json(['message' => 'Usuario inactivo'], 403);
        }

        // Rotate: revoke old, issue new
        $refreshToken->revoke();
        $newRefresh = RefreshToken::generate($user->id);

        // Revoke old access tokens and issue fresh one
        $user->tokens()->delete();
        $accessToken = $user->createToken('auth-token')->plainTextToken;

        AuthLog::record(event: 'login_success', userId: $user->id);

        $refreshCookie = Cookie::make(
            name: self::REFRESH_COOKIE,
            value: $newRefresh->token,
            minutes: 60 * 24 * 7,
            path: '/api/auth',
            secure: true,
            httpOnly: true,
            sameSite: 'None',
        );

        return response()->json([
            'user'  => $user->load('role', 'area'),
            'token' => $accessToken,
        ])->withCookie($refreshCookie);
    }

    public function logout(Request $request)
    {
        AuthLog::record(event: 'logout', userId: $request->user()->id);

        $request->user()->currentAccessToken()->delete();

        // Revoke refresh token if present
        $rawToken = $request->cookie(self::REFRESH_COOKIE);
        if ($rawToken) {
            RefreshToken::where('token', $rawToken)->first()?->revoke();
        }

        $expiredCookie = Cookie::forget(self::REFRESH_COOKIE);

        return response()->json(['message' => 'Sesión cerrada'])->withCookie($expiredCookie);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 422);
        }

        $policy = app(\App\Services\PasswordPolicyService::class);
        $errors = $policy->validate($user, $request->new_password);
        if (!empty($errors)) {
            return response()->json(['message' => implode('. ', $errors)], 422);
        }

        $hashed = Hash::make($request->new_password);
        $user->update(['password' => $hashed]);
        $policy->recordChange($user, $hashed);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('role', 'area');

        $effectivePermissions = $user->getAllPermissions()->pluck('name')->values();

        return response()->json(array_merge($user->toArray(), [
            'effective_permissions' => $effectivePermissions,
        ]));
    }
}
