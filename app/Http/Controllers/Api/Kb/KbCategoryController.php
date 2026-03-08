<?php

namespace App\Http\Controllers\Api\Kb;

use App\Http\Controllers\Controller;
use App\Models\KbCategory;
use App\Models\KbSubcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KbCategoryController extends Controller
{
    public function index()
    {
        $categories = KbCategory::with(['subcategories' => function ($q) {
            $q->where('is_active', true)->orderBy('order_index');
        }])
        ->where('is_active', true)
        ->orderBy('order_index')
        ->get();

        return response()->json($categories);
    }

    public function indexAll()
    {
        $categories = KbCategory::with('subcategories')->orderBy('order_index')->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'icon'        => 'nullable|string|max:60',
            'color'       => 'nullable|string|max:10',
            'order_index' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = KbCategory::create($validated);

        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = KbCategory::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'icon'        => 'nullable|string|max:60',
            'color'       => 'nullable|string|max:10',
            'order_index' => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = KbCategory::findOrFail($id);
        $category->delete();
        return response()->json(['message' => 'Categoría eliminada']);
    }

    // Subcategories
    public function subcategories($categoryId)
    {
        $category = KbCategory::findOrFail($categoryId);
        $subs = $category->subcategories()->where('is_active', true)->orderBy('order_index')->get();
        return response()->json($subs);
    }

    public function storeSubcategory(Request $request, $categoryId)
    {
        KbCategory::findOrFail($categoryId);

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'order_index' => 'nullable|integer',
        ]);

        $validated['category_id'] = $categoryId;
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);

        $sub = KbSubcategory::create($validated);

        return response()->json($sub, 201);
    }

    public function updateSubcategory(Request $request, $id)
    {
        $sub = KbSubcategory::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'order_index' => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        }

        $sub->update($validated);

        return response()->json($sub);
    }

    public function destroySubcategory($id)
    {
        $sub = KbSubcategory::findOrFail($id);
        $sub->delete();
        return response()->json(['message' => 'Subcategoría eliminada']);
    }
}
