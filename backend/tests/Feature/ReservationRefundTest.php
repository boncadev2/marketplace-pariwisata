<?php

namespace Tests\Feature;

use App\Models\MealSlot;
use App\Models\ReservationPayment;
use App\Models\RoomInventory;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\UmkmOrder;
use App\Models\UmkmProduct;
use App\Models\User;
use App\Services\LodgingReservationService;
use App\Services\MealReservationService;
use App\Services\ReservationRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservationRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_waits_for_bank_then_returns_stock_once_and_blocks_fulfillment_while_pending(): void
    {
        config(['services.midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.refunds_enabled' => true]);
        Http::preventStrayRequests();
        $buyer = User::factory()->create();
        $product = UmkmProduct::factory()->create(['stock' => 3, 'price' => 50000, 'status' => 'published']);
        $order = $this->buy($buyer, $product);
        $payment = ReservationPayment::create(['kind' => 'umkm', 'booking_id' => $order->id, 'user_id' => $buyer->id, 'reference' => fake()->uuid(), 'amount' => 50000, 'status' => 'paid', 'expires_at' => now()->addMinutes(15)]);
        $refundId = $this->actingAs($buyer)->postJson('/api/v1/account/reservation-payments/'.$payment->reference.'/refund', ['reason' => 'Batal sebelum dikirim'])->assertOk()->assertJsonPath('data.status', 'requested')->json('data.id');
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $this->actingAs($admin);
        $this->patchJson('/api/v1/dashboard/umkm-orders/'.$order->public_id, ['status' => 'processing_sandbox'])->assertConflict();
        $statusUrl = 'https://api.sandbox.midtrans.com/v2/'.$payment->reference.'/status';
        $refundUrl = 'https://api.sandbox.midtrans.com/v2/'.$payment->reference.'/refund';
        $status = ['order_id' => $payment->reference, 'gross_amount' => '50000.00', 'transaction_status' => 'settlement'];
        Http::fake([$statusUrl => Http::sequence()->push($status)->push([...$status, 'transaction_status' => 'refund', 'refunds' => [['refund_key' => 'wisata-reservation-refund-'.$refundId, 'refund_amount' => '50000.00', 'refund_method' => 'online', 'bank_confirmed_at' => '2026-10-03 10:00:00', 'refund_chargeback_id' => 456]]]), $refundUrl => Http::response(['status_code' => '200'])]);
        $this->withHeaders($this->sensitiveHeaders($admin))->postJson('/api/v1/dashboard/reservation-refunds/'.$refundId.'/approve', ['notes' => 'Disetujui sebelum pengiriman'])->assertOk()->assertJsonPath('data.status', 'processing');
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame('paid', $payment->fresh()->status);
        Http::assertSent(fn ($request) => $request->url() === $refundUrl && $request['amount'] === 50000);
        $this->postJson('/api/v1/dashboard/refunds/reservation/'.$refundId.'/refresh')->assertOk()->assertJsonPath('data.status', 'succeeded');
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->postJson('/api/v1/dashboard/refunds/reservation/'.$refundId.'/refresh')->assertConflict();
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_other_accounts_duplicate_requests_and_unconfirmed_admin_cannot_change_refund(): void
    {
        Http::preventStrayRequests();
        $buyer = User::factory()->create();
        $product = UmkmProduct::factory()->create(['stock' => 3, 'price' => 50000, 'status' => 'published']);
        $order = $this->buy($buyer, $product);
        $payment = ReservationPayment::create(['kind' => 'umkm', 'booking_id' => $order->id, 'user_id' => $buyer->id, 'reference' => fake()->uuid(), 'amount' => 50000, 'status' => 'paid', 'expires_at' => now()->addMinutes(15)]);
        $url = '/api/v1/account/reservation-payments/'.$payment->reference.'/refund';
        $this->actingAs(User::factory()->create())->postJson($url, ['reason' => 'Akun lain'])->assertNotFound();
        $refund = $this->actingAs($buyer)->postJson($url, ['reason' => 'Batal kunjungan'])->assertOk()->json('data.id');
        $this->postJson($url, ['reason' => 'Duplikat'])->assertConflict();
        $this->getJson('/api/v1/dashboard/refunds')->assertForbidden();
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $this->actingAs($admin)->postJson('/api/v1/dashboard/reservation-refunds/'.$refund.'/approve', ['notes' => 'Tanpa konfirmasi'])->assertStatus(423);
        Http::assertNothingSent();
    }

    public function test_lodging_and_culinary_refunds_restore_their_own_inventory_after_bank_confirmation(): void
    {
        $this->freezeTime();
        config(['services.midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.refunds_enabled' => true]);
        Http::preventStrayRequests();
        $buyer = User::factory()->create();
        $room = RoomType::factory()->create(['is_active' => true]);
        $date = now('Asia/Jakarta')->addDays(2)->toDateString();
        RoomInventory::create(['room_type_id' => $room->id, 'date' => $date, 'stock' => 3]);
        RoomRate::create(['room_type_id' => $room->id, 'date' => $date, 'price' => '50000.00']);
        $lodging = app(LodgingReservationService::class)->reserve($buyer, ['room_type_id' => $room->id, 'check_in' => $date, 'check_out' => now('Asia/Jakarta')->addDays(3)->toDateString(), 'quantity' => 1, 'guests' => 1], fake()->uuid(), '50000.00');
        $slot = MealSlot::factory()->create(['price' => '50000.00', 'capacity' => 3, 'reserved' => 0, 'time_slot' => now()->addDays(2)]);
        $meal = app(MealReservationService::class)->reserve($buyer, $slot->id, 1, fake()->uuid(), '50000.00');
        foreach (['lodging' => $lodging, 'culinary' => $meal] as $kind => $booking) {
            $payment = ReservationPayment::create(['kind' => $kind, 'booking_id' => $booking->id, 'user_id' => $buyer->id, 'reference' => fake()->uuid(), 'amount' => 50000, 'status' => 'paid', 'expires_at' => now()->addMinutes(15)]);
            $service = app(ReservationRefundService::class);
            $refund = $service->request($payment, $buyer, 'Pembatalan sebelum layanan dimulai');
            $refund->update(['status' => 'approved']);
            Http::fake(['https://api.sandbox.midtrans.com/v2/'.$payment->reference.'/status' => Http::response(['order_id' => $payment->reference, 'gross_amount' => '50000.00', 'transaction_status' => 'refund', 'refunds' => [['refund_key' => 'wisata-reservation-refund-'.$refund->id, 'refund_amount' => '50000.00', 'refund_method' => 'online', 'bank_confirmed_at' => '2026-10-03 10:00:00', 'refund_chargeback_id' => 789]]])]);
            $this->assertSame('succeeded', $service->process($refund)->status);
            $this->assertSame('cancelled', $booking->fresh()->status);
            $this->assertSame('refunded', $payment->fresh()->status);
            Http::assertSentCount(1);
        }
        $this->assertSame(3, (int) RoomInventory::where('room_type_id', $room->id)->first()->stock);
        $this->assertSame(0, (int) $slot->fresh()->reserved);
    }

    private function buy(User $buyer, UmkmProduct $product): UmkmOrder
    {
        $publicId = $this->actingAs($buyer)->withHeader('Idempotency-Key', fake()->uuid())->postJson('/api/v1/account/umkm-orders', ['product_slug' => $product->slug, 'quantity' => 1, 'expected_price' => 50000, 'customer_name' => 'Pembeli Demo', 'customer_phone' => '081234567890'])->assertCreated()->json('data.order_id');

        return UmkmOrder::where('public_id', $publicId)->firstOrFail();
    }
}
