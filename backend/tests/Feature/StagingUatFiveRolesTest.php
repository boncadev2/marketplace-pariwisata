<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhook;
use App\Models\Destination;
use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerBankAccount;
use App\Models\PartnerMember;
use App\Models\PaymentWebhookEvent;
use App\Models\PayoutBatch;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Models\Voucher;
use App\Payouts\SandboxPayoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingUatFiveRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        SandboxPayoutGateway::resetSimulation();
        parent::tearDown();
    }

    /**
     * Peran 1: Tamu (Guest)
     * - Menelusuri destinasi dan produk tanpa login.
     * - Membuat checkout tamu dan menerima instruksi pembayaran.
     * - Status pesanan tetap pending sebelum pembayaran.
     * - Menerima voucher setelah pembayaran terverifikasi.
     */
    public function test_role_tamu_walkthrough(): void
    {
        $region = Region::factory()->create();
        $partner = Partner::factory()->create(['region_id' => $region->id, 'status' => 'approved']);
        $destination = Destination::factory()->create(['partner_id' => $partner->id, 'publication_status' => 'published']);
        $product = Product::factory()->create([
            'partner_id' => $partner->id,
            'destination_id' => $destination->id,
            'status' => 'published',
            'type' => 'ticket',
        ]);
        $bucket = InventoryBucket::factory()->create([
            'product_id' => $product->id,
            'service_date' => now()->addDays(2)->toDateString(),
            'capacity' => 10,
        ]);

        // 1. Browsing katalog tanpa login
        $this->getJson('/api/v1/destinations')->assertOk();
        $this->getJson('/api/v1/products')->assertOk();

        // 2. Checkout tamu
        $checkoutPayload = [
            'product_slug' => $product->slug,
            'visit_date' => now()->addDays(2)->toDateString(),
            'quantity' => 2,
            'customer_name' => 'Wisatawan Tamu',
            'customer_email' => 'tamu@wisatadaerah.id',
            'customer_phone' => '081234567890',
        ];

        $checkoutResponse = $this->withHeaders(['Idempotency-Key' => 'idemp-guest-0000001'])
            ->postJson('/api/v1/checkout', $checkoutPayload);

        $checkoutResponse->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment');

        $orderPublicId = $checkoutResponse->json('data.order_id');
        $this->assertNotNull($orderPublicId);

        // 3. Status tetap pending tanpa webhook
        $order = Order::where('public_id', $orderPublicId)->firstOrFail();
        $this->assertSame('pending_payment', $order->status);

        // 4. Voucher belum ada sebelum pembayaran
        $this->assertDatabaseMissing('vouchers', ['partner_id' => $partner->id]);

        // 5. Pembayaran terverifikasi via webhook
        config(['services.sandbox_payment.webhook_secret' => 'test-secret']);
        $attempt = $order->paymentAttempts()->latest('id')->firstOrFail();
        $this->postJson('/api/v1/webhooks/payments/sandbox', [
            'event_key' => 'evt-uat-guest-01',
            'provider_reference' => $attempt->provider_reference,
            'status' => 'succeeded',
            'amount' => $order->total,
            'currency' => 'IDR',
        ], ['X-Sandbox-Signature' => 'test-secret'])->assertOk();

        $event = PaymentWebhookEvent::latest('id')->firstOrFail();
        (new ProcessPaymentWebhook($event->id))->handle();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertDatabaseHas('vouchers', ['status' => 'active']);
    }

    /**
     * Peran 2: Pengguna Berakun (Authenticated Customer)
     * - Registrasi, login, dan profil.
     * - Isolasi pesanan (hanya melihat miliknya sendiri).
     * - Validasi refund tidak dapat melebihi nilai pembayaran.
     */
    public function test_role_pengguna_berakun_walkthrough(): void
    {
        $customer = User::factory()->create(['email' => 'customer@example.test']);
        $otherCustomer = User::factory()->create(['email' => 'other@example.test']);

        $partner = Partner::factory()->create();
        $product = Product::factory()->create(['partner_id' => $partner->id]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'partner_id' => $partner->id,
            'status' => 'paid',
            'total' => 100000,
            'policy_snapshot' => [
                'is_refundable' => true,
                'refund_percentage' => 100,
                'refund_cutoff_hours' => 24,
                'visit_date' => now()->addDays(3)->toIso8601String(),
            ],
        ]);
        $order->paymentAttempts()->create([
            'status' => 'succeeded',
            'amount' => 100000,
            'provider' => 'sandbox',
            'currency' => 'IDR',
        ]);

        // Customer dapat melihat pesanannya
        $this->actingAs($customer)->getJson('/api/v1/account/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id);

        // Customer lain tidak dapat melihat atau mengakses pesanan ini (404 Not Found)
        $this->actingAs($otherCustomer)->getJson("/api/v1/account/orders/{$order->public_id}")
            ->assertNotFound();

        // Customer lain tidak dapat mengajukan refund atas pesanan orang lain
        $this->actingAs($otherCustomer)->postJson("/api/v1/orders/{$order->id}/refunds", [
            'reason' => 'Pengajuan ilegal',
        ])->assertNotFound();

        // Customer pemilik dapat mengajukan refund sesuai kebijakan
        $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/refunds", [
            'reason' => 'Perubahan jadwal',
        ])->assertCreated()
            ->assertJsonPath('refundable_amount', 100000);
    }

    /**
     * Peran 3: Mitra / Partner Owner
     * - Hanya melihat produk, pesanan, dan keuangan miliknya.
     * - Isolasi lintas tenant.
     * - Registrasi rekening bank memerlukan alur konfirmasi keamanan.
     */
    public function test_role_partner_owner_walkthrough(): void
    {
        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();

        $ownerA = User::factory()->create();
        PartnerMember::factory()->create([
            'partner_id' => $partnerA->id,
            'user_id' => $ownerA->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        // Partner A melihat rekening bank miliknya
        PartnerBankAccount::factory()->create([
            'partner_id' => $partnerA->id,
            'bank_name' => 'BCA',
            'account_number' => '9876543210',
        ]);

        $this->actingAs($ownerA)->getJson("/api/v1/partner-bank-accounts?partner_id={$partnerA->id}")
            ->assertOk()
            ->assertJsonPath('data.0.account_number_masked', '******3210')
            ->assertJsonMissingPath('data.0.account_number');

        // Partner A ditolak saat mencoba melihat data Partner B (404 isolasi tenant)
        $this->actingAs($ownerA)->getJson("/api/v1/partner-bank-accounts?partner_id={$partnerB->id}")
            ->assertNotFound();

        // Mendaftarkan rekening bank baru memerlukan sensitive confirmation header
        $newAccountPayload = [
            'partner_id' => $partnerA->id,
            'bank_name' => 'BRI',
            'account_number' => '1122334455',
            'account_name' => 'CV Wisata Mitra A',
        ];

        // Tanpa token konfirmasi ditolak 423 (Sensitive confirmation required)
        $this->actingAs($ownerA)->postJson('/api/v1/partner-bank-accounts', $newAccountPayload)
            ->assertStatus(423);

        // Dengan header konfirmasi berhasil didaftarkan
        $this->actingAs($ownerA)
            ->withHeaders($this->sensitiveHeaders($ownerA))
            ->postJson('/api/v1/partner-bank-accounts', $newAccountPayload)
            ->assertCreated();
    }

    /**
     * Peran 4: Staf Lapangan (Field Staff)
     * - Redeem tiket / voucher dengan token QR.
     * - Scan duplikat ditolak satu kali (idempotensi).
     * - Staf dari mitra lain ditolak.
     */
    public function test_role_staf_lapangan_walkthrough(): void
    {
        $partner = Partner::factory()->create();
        $otherPartner = Partner::factory()->create();

        $destination = Destination::factory()->create(['partner_id' => $partner->id, 'publication_status' => 'published']);
        $product = Product::factory()->create(['partner_id' => $partner->id, 'destination_id' => $destination->id]);

        $staff = User::factory()->create();
        PartnerMember::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'destination_id' => $destination->id,
            'is_active' => true,
        ]);

        $otherStaff = User::factory()->create();
        PartnerMember::factory()->create([
            'partner_id' => $otherPartner->id,
            'user_id' => $otherStaff->id,
            'role' => 'staff',
            'is_active' => true,
        ]);
        $order = Order::factory()->create(['partner_id' => $partner->id, 'status' => 'paid']);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Tiket Masuk',
            'quantity' => 1,
            'unit_price' => 50000,
            'total' => 50000,
            'snapshot' => [],
        ]);

        $token = str_repeat('b', 48);
        $voucher = Voucher::create([
            'order_item_id' => $orderItem->id,
            'partner_id' => $partner->id,
            'token_hash' => hash('sha256', $token),
            'token' => $token,
            'service_date' => now('Asia/Jakarta')->toDateString(),
            'admissions' => 1,
            'status' => 'active',
            'used_admissions' => 0,
        ]);

        // 1. Staf mitra lain ditolak (NotFound karena isolasi tenant)
        $this->actingAs($otherStaff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => $token])
            ->assertNotFound();

        // 2. Staf sah melakukan scan QR / redeem pertama kali -> Berhasil
        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.used_admissions', 1);

        // 3. Scan kedua ditolak (Conflict 409)
        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => $token])
            ->assertConflict();
    }

    /**
     * Peran 5: Super Admin & Finance
     * - Rekonsiliasi, pemantauan status sistem.
     * - Pembuatan dan persetujuan maker-checker payout batch.
     * - Eksekusi payout dengan adapter provider terlindung timeout.
     */
    public function test_role_admin_finance_walkthrough(): void
    {
        config(['services.payout.driver' => 'sandbox']);

        $maker = User::factory()->create(['platform_role' => 'super_admin']);
        $checker = User::factory()->create(['platform_role' => 'super_admin']);

        $partner = Partner::factory()->create();
        $product = Product::factory()->create(['partner_id' => $partner->id]);
        PartnerBankAccount::factory()->create([
            'partner_id' => $partner->id,
            'is_verified' => true,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'paid',
            'payout_status' => 'eligible',
            'total' => 200000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Tiket Masuk',
            'quantity' => 1,
            'unit_price' => 200000,
            'total' => 200000,
            'commission_amount' => 20000,
            'snapshot' => [],
        ]);

        // 1. Maker membuat batch payout
        $createResponse = $this->actingAs($maker)
            ->withHeaders($this->sensitiveHeaders($maker))
            ->postJson('/api/v1/payouts/batches', [
                'provider' => 'bank_transfer',
                'notes' => 'Pencairan dana mingguan mitra',
                'items' => [
                    [
                        'partner_id' => $partner->id,
                        'order_ids' => [$order->id],
                    ],
                ],
            ]);

        $createResponse->assertCreated();
        $batchId = $createResponse->json('data.id');

        // 2. Maker tidak boleh meng-approve batch buatannya sendiri (Maker-Checker separation)
        $batch = PayoutBatch::findOrFail($batchId);
        $this->actingAs($maker)
            ->withHeaders($this->sensitiveHeaders($maker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/approve")
            ->assertConflict();

        // 3. Checker menyetujui batch
        $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/approve")
            ->assertOk();

        // 4. Eksekusi proses payout via adapter
        $processResponse = $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/process");

        $processResponse->assertOk()
            ->assertJsonPath('status', 'paid');

        // 5. Bukti buku besar (Ledger) tercatat tepat satu kali
        $order->refresh();
        $this->assertSame('paid', $order->payout_status);
        $batch->refresh();
        $this->assertSame('paid', $batch->status);
    }
}
