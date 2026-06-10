<?php

namespace Tests\Feature;

use App\Models\AuthLog;
use App\Models\RefreshToken;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_login_sets_refresh_token_cookie(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertCookie('refresh_token');
    }

    public function test_login_records_success_in_auth_log(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event'   => 'login_success',
        ]);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Credenciales inválidas']);
    }

    public function test_failed_login_records_failed_event_in_auth_log(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('auth_logs', [
            'event'       => 'login_failed',
            'login_input' => $user->email,
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->createUser(overrides: ['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event'   => 'login_inactive',
        ]);
    }

    public function test_login_with_username_works(): void
    {
        $user = $this->createUser(overrides: ['username' => 'testuser']);

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'testuser',
            'password' => 'password',
        ]);

        $response->assertOk();
    }

    public function test_logout_revokes_access_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        // Reset guard cache to simulate a fresh HTTP request (in production each
        // request creates new guards; in tests the guard caches the authenticated user)
        app('auth')->forgetGuards();

        // Token should now be invalid
        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_records_event_in_auth_log(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/logout');

        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event'   => 'logout',
        ]);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonStructure(['id', 'name', 'email', 'effective_permissions']);
    }

    public function test_refresh_token_issues_new_access_token(): void
    {
        $user = $this->createUser();
        $refresh = RefreshToken::generate($user->id);

        // postJson doesn't send cookies by default — withCredentials enables cookie sending
        // API routes don't use EncryptCookies so the token must be sent unencrypted
        $response = $this->withCredentials()
            ->withUnencryptedCookie('refresh_token', $refresh->token)
            ->postJson('/api/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);

        // Old refresh token should be revoked
        $this->assertNotNull($refresh->fresh()->revoked_at);

        // A new refresh token should exist
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id'    => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_refresh_fails_with_invalid_token(): void
    {
        $this->withCookie('refresh_token', 'invalid-token')
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_refresh_fails_with_expired_token(): void
    {
        $user  = $this->createUser();
        $token = RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => str_repeat('x', 80),
            'expires_at' => now()->subHour(),
        ]);

        $this->withCookie('refresh_token', $token->token)
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_login_rate_limit_blocks_after_five_attempts(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'login'    => $user->email,
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }
}
