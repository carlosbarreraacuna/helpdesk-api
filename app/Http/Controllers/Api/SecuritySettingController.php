<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSetting;
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
}
