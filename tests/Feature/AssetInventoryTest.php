<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use Tests\TestCase;

class AssetInventoryTest extends TestCase
{
    private function makeAssetType(): AssetType
    {
        return AssetType::factory()->create();
    }

    public function test_agent_with_permission_can_list_assets(): void
    {
        $agent = $this->createUser('agente');
        $this->grantPermission($agent, 'assets.view');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/assets')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_agent_without_permission_cannot_list_assets(): void
    {
        $agent = $this->createUser('agente');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/assets')
            ->assertForbidden();
    }

    public function test_agent_with_permission_can_create_asset(): void
    {
        $agent = $this->createUser('agente');
        $this->grantPermission($agent, 'assets.create');
        $type = $this->makeAssetType();

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/assets', [
                'name'          => 'Laptop Dell XPS',
                'serial_number' => 'SN-2026-001',
                'asset_type_id' => $type->id,
                'status'        => 'available',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('assets', ['serial_number' => 'SN-2026-001']);
    }

    public function test_duplicate_serial_number_is_rejected(): void
    {
        $agent = $this->createUser('agente');
        $this->grantPermission($agent, 'assets.create');
        $type  = $this->makeAssetType();

        Asset::factory()->create(['serial_number' => 'DUP-001', 'asset_type_id' => $type->id]);

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/assets', [
                'name'          => 'Otro equipo',
                'serial_number' => 'DUP-001',
                'asset_type_id' => $type->id,
                'status'        => 'available',
            ])
            ->assertUnprocessable();
    }

    public function test_agent_can_assign_asset_to_user(): void
    {
        $agent     = $this->createUser('agente');
        $targetUser = $this->createUser('usuario');
        $this->grantPermission($agent, 'assets.assign');
        $type  = $this->makeAssetType();

        $asset = Asset::factory()->create([
            'asset_type_id' => $type->id,
            'status'        => 'available',
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/assets/{$asset->id}/assign", [
                'user_id' => $targetUser->id,
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('asset_assignments', [
            'asset_id'  => $asset->id,
            'user_id'   => $targetUser->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('assets', [
            'id'              => $asset->id,
            'current_user_id' => $targetUser->id,
            'status'          => 'assigned',
        ]);
    }

    public function test_asset_return_creates_assignment_record(): void
    {
        $agent      = $this->createUser('agente');
        $targetUser = $this->createUser('usuario');
        $this->grantPermission($agent, 'assets.assign');
        $type = $this->makeAssetType();

        $asset = Asset::factory()->create([
            'asset_type_id'   => $type->id,
            'status'          => 'assigned',
            'current_user_id' => $targetUser->id,
        ]);

        \DB::table('asset_assignments')->insert([
            'asset_id'    => $asset->id,
            'user_id'     => $targetUser->id,
            'assigned_by' => $agent->id,
            'assigned_at' => now()->subDays(30),
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/assets/{$asset->id}/return", [
                'notes' => 'Equipo devuelto en buen estado.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('assets', [
            'id'     => $asset->id,
            'status' => 'available',
        ]);
    }

    public function test_asset_events_are_recorded_on_assignment(): void
    {
        $agent     = $this->createUser('agente');
        $targetUser = $this->createUser('usuario');
        $this->grantPermission($agent, 'assets.assign');
        $type = $this->makeAssetType();

        $asset = Asset::factory()->create([
            'asset_type_id' => $type->id,
            'status'        => 'available',
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/assets/{$asset->id}/assign", [
                'user_id' => $targetUser->id,
            ]);

        $this->assertDatabaseHas('asset_events', [
            'asset_id'   => $asset->id,
            'event_type' => 'assignment',
        ]);
    }
}
