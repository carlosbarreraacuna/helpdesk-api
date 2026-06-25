<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function __construct(private TotpService $totp) {}

    // ── Flujo de configuración desde settings (usuario ya autenticado) ──────

    /** Genera secret y devuelve URI para el QR code */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->two_factor_enabled) {
            return response()->json(['message' => '2FA ya está activado'], 422);
        }

        $secret = $this->totp->generateSecret();
        $user->update(['two_factor_secret' => $secret]);

        return response()->json([
            'secret' => $secret,
            'qr_uri' => $this->totp->getOtpAuthUri($secret, $user->email),
        ]);
    }

    /** Confirma con el primer código y activa 2FA */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Inicie la configuración primero'], 422);
        }

        if (!$this->totp->verify($user->two_factor_secret, $request->code)) {
            return response()->json(['message' => 'Código inválido'], 422);
        }

        $user->update(['two_factor_enabled' => true, 'two_factor_confirmed_at' => now()]);
        return response()->json(['message' => '2FA activado correctamente']);
    }

    /** Desactiva 2FA con contraseña + código como confirmación */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'code'     => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Contraseña incorrecta'], 422);
        }

        if (!$this->totp->verify($user->two_factor_secret, $request->code)) {
            return response()->json(['message' => 'Código 2FA inválido'], 422);
        }

        $user->update([
            'two_factor_secret'       => null,
            'two_factor_enabled'      => false,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['message' => '2FA desactivado']);
    }

    // ── Flujo forzado en login (admin/supervisor sin 2FA configurado) ────────

    /** Genera secret para la configuración forzada post-login */
    public function forceSetup(Request $request): JsonResponse
    {
        $request->validate(['setup_token' => 'required|string']);

        $userId = Cache::get("2fa_setup:{$request->setup_token}");
        if (!$userId) {
            return response()->json(['message' => 'Token de configuración expirado'], 401);
        }

        $user   = User::findOrFail($userId);
        $secret = $this->totp->generateSecret();
        $user->update(['two_factor_secret' => $secret]);

        return response()->json([
            'secret'      => $secret,
            'qr_uri'      => $this->totp->getOtpAuthUri($secret, $user->email),
            'setup_token' => $request->setup_token,
        ]);
    }

    /** Confirma configuración forzada y emite tokens de acceso */
    public function forceConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'setup_token' => 'required|string',
            'code'        => 'required|string|size:6',
        ]);

        $userId = Cache::pull("2fa_setup:{$request->setup_token}");
        if (!$userId) {
            return response()->json(['message' => 'Token de configuración expirado'], 401);
        }

        $user = User::findOrFail($userId);

        if (!$this->totp->verify($user->two_factor_secret, $request->code)) {
            Cache::put("2fa_setup:{$request->setup_token}", $userId, now()->addMinutes(15));
            return response()->json(['message' => 'Código inválido'], 422);
        }

        $user->update(['two_factor_enabled' => true, 'two_factor_confirmed_at' => now()]);

        return $this->issueTokens($user);
    }

    // ── Verificación TOTP durante el login ───────────────────────────────────

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code'            => 'required|string|size:6',
        ]);

        $userId = Cache::get("2fa_challenge:{$request->challenge_token}");
        if (!$userId) {
            return response()->json(['message' => 'Sesión de verificación expirada'], 401);
        }

        $user = User::findOrFail($userId);

        if (!$this->totp->verify($user->two_factor_secret, $request->code)) {
            return response()->json(['message' => 'Código inválido'], 422);
        }

        Cache::forget("2fa_challenge:{$request->challenge_token}");
        return $this->issueTokens($user);
    }

    // ── Estado del 2FA del usuario autenticado ───────────────────────────────

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'enabled'      => $user->two_factor_enabled,
            'confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────

    private function issueTokens(User $user): JsonResponse
    {
        $user->update(['last_login' => now()]);
        AuthLog::record(event: 'login_success', userId: $user->id);

        $accessToken   = $user->createToken('auth-token')->plainTextToken;
        $refresh       = RefreshToken::generate($user->id);
        $refreshCookie = Cookie::make(
            name: 'refresh_token',
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
}
