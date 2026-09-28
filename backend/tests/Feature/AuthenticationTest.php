<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_profile(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_valid_login_authenticates_session_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.test', 'password' => 'staff-password-123']);
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/login']);

        $this->postJson('/api/v1/login', ['email' => 'staff@example.test', 'password' => 'staff-password-123'])->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_registration_requires_strong_confirmed_password(): void
    {
        $this->postJson('/api/v1/register', ['name' => 'Demo', 'email' => 'demo@example.test', 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable();
    }

    public function test_registration_normalizes_email_before_uniqueness_check(): void
    {
        User::factory()->create(['email' => 'member@example.test']);
        $this->postJson('/api/v1/register', ['name' => 'Demo', 'email' => 'MEMBER@example.test', 'password' => 'strong-password-123', 'password_confirmation' => 'strong-password-123'])->assertUnprocessable();
    }

    public function test_login_returns_generic_error_for_invalid_password(): void
    {
        User::factory()->create(['email' => 'member@example.test', 'password' => 'password-yang-salah']);
        $this->postJson('/api/v1/login', ['email' => 'member@example.test', 'password' => 'wrong-password'])->assertUnprocessable()->assertJson(['message' => 'Kredensial tidak valid.']);
    }
}
