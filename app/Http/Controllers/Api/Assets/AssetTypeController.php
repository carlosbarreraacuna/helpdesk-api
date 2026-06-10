<?php

namespace App\Http\Controllers\Api\Assets;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\AssetTypeField;
use Illuminate\Http\Request;

/**
 * @tags Inventario de Activos
 */
class AssetTypeController extends Controller
{
    public function index()
    {
        return response()->json(AssetType::withCount('assets')->orderBy('display_name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:80|unique:asset_types',
            'display_name' => 'required|string|max:100',
            'icon'         => 'nullable|string|max:60',
        ]);
        return response()->json(AssetType::create($data), 201);
    }

    public function update(Request $request, AssetType $assetType)
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:80|unique:asset_types,name,' . $assetType->id,
            'display_name' => 'sometimes|string|max:100',
            'icon'         => 'nullable|string|max:60',
            'is_active'    => 'sometimes|boolean',
        ]);
        $assetType->update($data);
        return response()->json($assetType);
    }

    public function destroy(AssetType $assetType)
    {
        if ($assetType->is_system) {
            return response()->json(['message' => 'No se pueden eliminar tipos del sistema.'], 403);
        }
        $assetType->delete();
        return response()->json(null, 204);
    }

    // Fields
    public function indexFields(AssetType $assetType)
    {
        return response()->json($assetType->fields);
    }

    public function storeField(Request $request, AssetType $assetType)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:80',
            'display_name'  => 'required|string|max:100',
            'field_type'    => 'required|in:text,number,date,select,boolean',
            'options'       => 'nullable|array',
            'is_required'   => 'boolean',
            'is_identifier' => 'boolean',
            'order_index'   => 'integer',
        ]);
        $field = $assetType->fields()->create($data);
        return response()->json($field, 201);
    }

    public function updateField(Request $request, AssetType $assetType, AssetTypeField $field)
    {
        $data = $request->validate([
            'name'          => 'sometimes|string|max:80',
            'display_name'  => 'sometimes|string|max:100',
            'field_type'    => 'sometimes|in:text,number,date,select,boolean',
            'options'       => 'nullable|array',
            'is_required'   => 'sometimes|boolean',
            'is_identifier' => 'sometimes|boolean',
            'order_index'   => 'sometimes|integer',
        ]);
        $field->update($data);
        return response()->json($field);
    }

    public function destroyField(AssetType $assetType, AssetTypeField $field)
    {
        $field->delete();
        return response()->json(null, 204);
    }
}
