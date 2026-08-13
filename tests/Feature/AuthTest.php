<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_player_cannot_claim_a_promo(): void
    {
        $this->postJson('/api/promo/claim', ['code' => 'WELCOME10'])
            ->assertUnauthorized();
    }

    public function test_unauthenticated_player_cannot_read_promo_history(): void
    {
        $this->getJson('/api/promo/history')->assertUnauthorized();
    }

    public function test_valid_login_returns_a_usable_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id);

        $this->withToken($login->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'demo@example.com');
    }

    public function test_invalid_login_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'demo@example.com']);

        $this->postJson('/api/login', [
            'email' => 'demo@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }
}
