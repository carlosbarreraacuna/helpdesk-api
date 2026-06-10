<?php

namespace App\Http\Controllers\Api\Assets;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;

/**
 * @tags Inventario de Activos
 */
class ServiceProviderController extends Controller
{
    public function index()
    {
        return response()->json(ServiceProvider::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:150',
            'phone'        => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:150',
            'service_type' => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);
        return response()->json(ServiceProvider::create($data), 201);
    }

    public function update(Request $request, ServiceProvider $serviceProvider)
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:150',
            'phone'        => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:150',
            'service_type' => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
            'is_active'    => 'sometimes|boolean',
        ]);
        $serviceProvider->update($data);
        return response()->json($serviceProvider);
    }

    public function destroy(ServiceProvider $serviceProvider)
    {
        $serviceProvider->delete();
        return response()->json(null, 204);
    }
}
