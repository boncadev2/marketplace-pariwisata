<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_customer_can_request_link_and_verify_only_their_own_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson('/api/v1/email/verification-notification')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertNotSentTo($other, VerifyEmail::class);

        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);
        $this->actingAs($other)->get($link)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user)->get($link)->assertRedirect(config('services.frontend_url').'/akun');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);

        $this->actingAs($user)->get($link.'&altered=1')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_guest_can_verify_email_with_valid_signed_link(): void
    {
        $user = User::factory()->unverified()->create();
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);

        $response = $this->get($link);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
