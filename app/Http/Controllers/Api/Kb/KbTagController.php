<?php

namespace App\Http\Controllers\Api\Kb;

use App\Http\Controllers\Controller;
use App\Models\KbTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KbTagController extends Controller
{
    public function index()
    {
        $tags = KbTag::orderBy('name')->get();
        return response()->json($tags);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60|unique:kb_tags,name',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $tag = KbTag::create($validated);

        return response()->json($tag, 201);
    }

    public function destroy($id)
    {
        $tag = KbTag::findOrFail($id);
        $tag->delete();
        return response()->json(['message' => 'Etiqueta eliminada']);
    }
}
