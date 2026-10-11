<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestEmailTest extends TestCase
{
    public function test_rejects_invalid_email_format(): void
    {
        $this->artisan('app:send-test-email', ['email' => 'invalid-email-address'])
            ->expectsOutputToContain('Alamat email tidak valid')
            ->assertFailed();
    }

    public function test_sends_test_email_successfully_with_faked_mail(): void
    {
        Mail::fake();

        $this->artisan('app:send-test-email', ['email' => 'testing@gadstudio.cloud'])
            ->expectsOutputToContain('Sukses! Email uji coba berhasil dikirim')
            ->assertSuccessful();

        Mail::assertSent(function (\Illuminate\Mail\Mailable|\Symfony\Component\Mime\Email $mail) {
            return true;
        });
    }
}
