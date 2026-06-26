<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecuritySettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'two_factor_required' => (bool) HelpdeskSetting::get('two_factor_required', '0'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'two_factor_required' => 'required|boolean',
        ]);

        HelpdeskSetting::set('two_factor_required', $request->boolean('two_factor_required') ? '1' : '0');

        return response()->json([
            'message'             => 'Configuración actualizada',
            'two_factor_required' => $request->boolean('two_factor_required'),
        ]);
    }

    public function disableUserTwoFactor(Request $request, int $userId): JsonResponse
    {
        $target = User::findOrFail($userId);

        if (!$target->two_factor_enabled) {
            return response()->json(['message' => 'El usuario no tiene 2FA activo'], 422);
        }

        // Prevent admin from disabling their own 2FA through this endpoint
        if ($request->user()->id === $target->id) {
            return response()->json(['message' => 'Usa tu propia configuración de seguridad para desactivar tu 2FA'], 422);
        }

        $target->update([
            'two_factor_secret'       => null,
            'two_factor_enabled'      => false,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['message' => '2FA desactivado para ' . $target->name]);
    }
}
