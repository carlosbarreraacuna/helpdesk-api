<?php

namespace App\Http\Controllers\Api\Assets;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        return response()->json(
            Location::with('area')->where('is_active', true)->orderBy('name')->get()
        );
    }

    public function indexByArea(Area $area)
    {
        return response()->json($area->locations()->where('is_active', true)->orderBy('name')->get());
    }

    public function store(Request $request, Area $area)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);
        $location = $area->locations()->create($data);
        return response()->json($location->load('area'), 201);
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);
        $location->update($data);
        return response()->json($location->load('area'));
    }

    public function destroy(Location $location)
    {
        $location->update(['is_active' => false]);
        return response()->json(null, 204);
    }
}
