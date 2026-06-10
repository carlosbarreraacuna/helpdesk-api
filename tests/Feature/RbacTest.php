<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class RbacTest extends TestCase
{
    public function test_admin_can_access_user_management(): void
    {
        $admin = $this->createAdmin();
        $this->grantPermission($admin, 'users.view');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_agent_without_permission_gets_403(): void
    {
        $agent = $this->createUser('agente');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_403_is_recorded_in_auth_log(): void
    {
        $agent = $this->createUser('agente');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/users');

        $this->assertDatabaseHas('auth_logs', [
            'user_id'             => $agent->id,
            'event'               => 'access_denied',
            'required_permission' => 'users.view',
        ]);
    }

    public function test_user_override_can_grant_extra_permission(): void
    {
        $agent = $this->createUser('agente');
        $this->grantPermission($agent, 'users.view');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_admin_can_create_role(): void
    {
        $admin = $this->createAdmin();
        $this->grantPermission($admin, 'settings.update');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles', [
                'name'         => 'nuevo_rol',
                'display_name' => 'Nuevo Rol',
                'description'  => 'Rol de prueba',
                'level'        => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('role.name', 'nuevo_rol');
    }

    public function test_permissions_endpoint_returns_list(): void
    {
        $admin = $this->createAdmin();
        $this->grantPermission($admin, 'test.permission');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/permissions')
            ->assertOk()
            ->assertJsonStructure([['id', 'name']]);
    }
}
