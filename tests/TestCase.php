<?php

namespace Tests;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function createRole(string $name): Role
    {
        $levels = ['agente' => 1, 'supervisor' => 2, 'admin' => 3, 'usuario' => 0];

        return Role::firstOrCreate(['name' => $name], [
            'name'         => $name,
            'display_name' => ucfirst($name),
            'description'  => $name,
            'level'        => $levels[$name] ?? 1,
        ]);
    }

    protected function createUser(string $role = 'agente', array $overrides = []): User
    {
        $roleModel = $this->createRole($role);

        return User::factory()->create(array_merge([
            'role_id'   => $roleModel->id,
            'is_active' => true,
        ], $overrides));
    }

    protected function createAdmin(): User
    {
        return $this->createUser('admin');
    }

    protected function actingAsAdmin(): static
    {
        return $this->actingAs($this->createAdmin(), 'sanctum');
    }

    protected function grantPermission(User $user, string $permissionName): void
    {
        $module = Module::firstOrCreate(
            ['name' => 'test'],
            ['name' => 'test', 'display_name' => 'Test', 'is_active' => true]
        );

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['name' => $permissionName, 'display_name' => $permissionName, 'description' => '', 'module_id' => $module->id, 'action_type' => 'special']
        );

        \DB::table('user_permission')->insertOrIgnore([
            'user_id'       => $user->id,
            'permission_id' => $permission->id,
            'is_granted'    => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
