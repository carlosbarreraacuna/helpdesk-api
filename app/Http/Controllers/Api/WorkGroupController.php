<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkGroup;
use App\Models\User;
use App\Models\TicketCategory;
use Illuminate\Http\Request;

/**
 * @tags Configuración
 */
class WorkGroupController extends Controller
{
    public function index()
    {
        $groups = WorkGroup::with(['agents:id,name,email', 'categories:id,name'])
            ->orderBy('priority_order')
            ->get();

        return response()->json($groups);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'description'    => 'nullable|string|max:255',
            'priority_order' => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
            'is_default'     => 'nullable|boolean',
        ]);

        $group = WorkGroup::create($validated);
        return response()->json($group->load(['agents:id,name,email', 'categories:id,name']), 201);
    }

    public function update(Request $request, $id)
    {
        $group = WorkGroup::findOrFail($id);

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:100',
            'description'    => 'nullable|string|max:255',
            'priority_order' => 'nullable|integer|min:0',
            'is_active'      => 'sometimes|boolean',
            'is_default'     => 'sometimes|boolean',
        ]);

        $group->update($validated);
        return response()->json($group->load(['agents:id,name,email', 'categories:id,name']));
    }

    public function destroy($id)
    {
        $group = WorkGroup::findOrFail($id);

        if ($group->tickets()->whereNotIn('status_id', function ($q) {
            $q->select('id')->from('ticket_statuses')->whereIn('name', ['cerrado', 'resuelto']);
        })->exists()) {
            return response()->json(['message' => 'El grupo tiene tickets activos. Reasigna antes de eliminarlo.'], 422);
        }

        $group->delete();
        return response()->json(['message' => 'Grupo eliminado.']);
    }

    // ── Agents ───────────────────────────────────────

    public function syncAgents(Request $request, $id)
    {
        $group = WorkGroup::findOrFail($id);

        $request->validate([
            'agent_ids'   => 'required|array',
            'agent_ids.*' => 'exists:users,id',
        ]);

        $group->agents()->sync($request->agent_ids);
        return response()->json($group->load('agents:id,name,email'));
    }

    public function addAgent(Request $request, $id)
    {
        $group = WorkGroup::findOrFail($id);

        $request->validate(['agent_id' => 'required|exists:users,id']);

        $group->agents()->syncWithoutDetaching([$request->agent_id]);
        return response()->json(['message' => 'Agente agregado.']);
    }

    public function removeAgent(Request $request, $id, $agentId)
    {
        $group = WorkGroup::findOrFail($id);
        $group->agents()->detach($agentId);
        return response()->json(['message' => 'Agente removido.']);
    }

    // ── Categories (rules) ────────────────────────────

    public function syncCategories(Request $request, $id)
    {
        $group = WorkGroup::findOrFail($id);

        $request->validate([
            'category_ids'   => 'required|array',
            'category_ids.*' => 'exists:ticket_categories,id',
        ]);

        $group->categories()->sync($request->category_ids);
        return response()->json($group->load('categories:id,name'));
    }

    public function addCategory(Request $request, $id)
    {
        $group = WorkGroup::findOrFail($id);
        $request->validate(['category_id' => 'required|exists:ticket_categories,id']);
        $group->categories()->syncWithoutDetaching([$request->category_id]);
        return response()->json(['message' => 'Categoría agregada.']);
    }

    public function removeCategory($id, $categoryId)
    {
        $group = WorkGroup::findOrFail($id);
        $group->categories()->detach($categoryId);
        return response()->json(['message' => 'Categoría removida.']);
    }

    // ── Available agents (for dropdown) ──────────────

    public function availableAgents()
    {
        $agents = User::whereHas('role', fn($q) => $q->where('name', 'agente'))
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($agents);
    }
}
