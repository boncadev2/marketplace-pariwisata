<?php

namespace Tests\Feature;

use App\Jobs\DataRetentionJob;
use App\Logging\RedactSensitiveData;
use App\Models\DataDeletionRequest;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class SecurityAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_include_security_headers_without_hsts_on_plain_http(): void
    {
        $response = $this->getJson('/api/v1/destinations');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-XSS-Protection', '0');
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_cors_only_allows_configured_credentialed_origin(): void
    {
        config()->set('cors.allowed_origins', ['https://app.example.test']);

        $this->withHeaders([
            'Origin' => 'https://app.example.test',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type,X-XSRF-TOKEN',
        ])->options('/api/v1/checkout')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.test')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $disallowed = $this->withHeaders([
            'Origin' => 'https://evil.example.test',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/checkout');
        $this->assertNotSame('https://evil.example.test', $disallowed->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_media_upload_is_limited_to_authorized_partner_and_safe_image_extensions(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $ownedPartner = Partner::factory()->create();
        $otherPartner = Partner::factory()->create();
        PartnerMember::factory()->create([
            'user_id' => $owner->id,
            'partner_id' => $ownedPartner->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($owner)->post('/api/v1/media', [
            'partner_id' => $otherPartner->id,
            'file' => UploadedFile::fake()->createWithContent('cross-tenant.png', $png),
        ], ['Accept' => 'application/json'])->assertNotFound();

        $response = $this->actingAs($owner)->post('/api/v1/media', [
            'partner_id' => $ownedPartner->id,
            'file' => UploadedFile::fake()->createWithContent('photo.png', $png),
            'alt_text' => 'Pemandangan desa',
        ], ['Accept' => 'application/json'])->assertCreated();

        $response->assertJsonMissingPath('data.path');
        $this->assertDatabaseHas('media', ['partner_id' => $ownedPartner->id, 'alt_text' => 'Pemandangan desa']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.uploaded', 'partner_id' => $ownedPartner->id]);

        $this->actingAs($owner)->post('/api/v1/media', [
            'partner_id' => $ownedPartner->id,
            'file' => UploadedFile::fake()->createWithContent('payload.php', $png),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_data_deletion_anonymizes_profile_but_retains_transaction_record(): void
    {
        $customer = User::factory()->create(['email' => 'customer@example.test']);
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $order = Order::factory()->create(['user_id' => $customer->id, 'customer_email' => 'customer@example.test']);

        $created = $this->actingAs($customer)->postJson('/api/v1/account/data-deletion-request', [
            'reason' => 'Tidak lagi menggunakan layanan.',
        ])->assertCreated();

        $requestId = $created->json('data.id');
        $this->actingAs($admin)->withHeaders($this->sensitiveHeaders($admin))
            ->postJson("/api/v1/privacy/data-deletion-requests/{$requestId}/process")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.retained_records.orders', 1);

        $customer->refresh();
        $this->assertSame("deleted+{$customer->id}@example.invalid", $customer->email);
        $this->assertNull($customer->email_verified_at);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $customer->id]);
        $this->assertDatabaseHas('data_deletion_requests', ['id' => $requestId, 'status' => 'completed', 'processed_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.data_deletion_completed', 'auditable_id' => $requestId]);
    }

    public function test_deletion_request_is_idempotent_and_admin_account_is_excluded(): void
    {
        $customer = User::factory()->create();

        $first = $this->actingAs($customer)->postJson('/api/v1/account/data-deletion-request')->assertCreated();
        $second = $this->postJson('/api/v1/account/data-deletion-request')->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, DataDeletionRequest::query()->count());

        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $this->actingAs($admin)->postJson('/api/v1/account/data-deletion-request')->assertConflict();
    }

    public function test_unverified_admin_cannot_access_platform_finance(): void
    {
        $admin = User::factory()->unverified()->create(['platform_role' => 'super_admin']);

        $this->actingAs($admin)->getJson('/api/v1/payouts/eligible')->assertForbidden();
    }

    public function test_retention_redacts_personal_notification_data_without_deleting_financial_record(): void
    {
        $old = NotificationDelivery::factory()->create(['created_at' => now()->subDays(91)]);
        $recent = NotificationDelivery::factory()->create(['created_at' => now()->subDays(10)]);

        (new DataRetentionJob)->handle();

        $old->refresh();
        $recent->refresh();
        $this->assertSame('[redacted]', $old->recipient);
        $this->assertSame([], $old->snapshot);
        $this->assertNotNull($old->personal_data_redacted_at);
        $this->assertSame('customer@example.test', $recent->recipient);
        $this->assertSame(2, NotificationDelivery::query()->count());
    }

    public function test_log_processor_redacts_tokens_emails_and_long_numbers(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Bearer secret-token customer@example.test 1234567890123456',
            context: ['password' => 'secret', 'account_number' => '1234567890', 'safe' => 'ok'],
        );

        $redacted = (new RedactSensitiveData)($record);

        $this->assertStringNotContainsString('secret-token', $redacted->message);
        $this->assertStringNotContainsString('customer@example.test', $redacted->message);
        $this->assertStringNotContainsString('1234567890123456', $redacted->message);
        $this->assertSame('[REDACTED]', $redacted->context['password']);
        $this->assertSame('[REDACTED]', $redacted->context['account_number']);
        $this->assertSame('ok', $redacted->context['safe']);
    }
}
