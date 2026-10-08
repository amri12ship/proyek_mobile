<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_and_returns_token(): void
    {
        $user = User::factory()->create(['email' => 'budi@absensi.test', 'password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@absensi.test',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['message', 'access_token', 'user' => ['id', 'name', 'email', 'role']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_never_leaks_password(): void
    {
        User::factory()->create(['email' => 'budi@absensi.test', 'password' => Hash::make('password123')]);

        $body = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@absensi.test',
            'password' => 'password123',
        ])->assertOk()->getContent();

        $this->assertStringNotContainsString('password', $body);
        $this->assertStringNotContainsString('$2y$', $body);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create(['email' => 'budi@absensi.test', 'password' => Hash::make('password123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@absensi.test',
            'password' => 'salahpassword',
        ])->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_unknown_email_returns_same_401(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'tidak@ada.test',
            'password' => 'password123',
        ])->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_inactive_account_login_returns_403(): void
    {
        User::factory()->inactive()->create([
            'email' => 'budi@absensi.test',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@absensi.test',
            'password' => 'password123',
        ])->assertForbidden()->assertJsonPath('code', 'ACCOUNT_INACTIVE');
    }

    public function test_login_validation_errors(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => '', 'password' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        $this->postJson('/api/v1/auth/login', ['email' => 'bukan-email', 'password' => 'rahasia'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'budi@absensi.test', 'password' => Hash::make('password123')]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'budi@absensi.test',
                'password' => 'passwordsalah',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@absensi.test',
            'password' => 'password123',
        ])->assertStatus(429);
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_returns_token_owner(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_ignores_client_supplied_user_id(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me?user_id='.$other->id)->assertJsonPath('data.id', $user->id);
    }

    public function test_inactive_user_token_is_rejected(): void
    {
        $user = User::factory()->inactive()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_INACTIVE');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('hp-budi');
        $tablet = $user->createToken('tablet-budi');

        $this->withToken($phone->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $phone->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tablet->accessToken->id]);
    }

    public function test_api_is_stateless_and_ignores_web_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('hp')->plainTextToken;

        $response = $this->withSession(['foo' => 'bar'])
            ->get('/api/v1/me', ['Authorization' => 'Bearer '.$token]);

        $response->assertOk();
        $this->assertArrayNotHasKey('Set-Cookie', $response->headers->all());
    }

    public function test_api_returns_json_401_even_when_browser_accepts_html(): void
    {
        $this->get('/api/v1/me', ['Accept' => 'text/html'])
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }
}
