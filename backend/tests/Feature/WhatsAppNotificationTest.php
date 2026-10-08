<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_service_formats_phone_numbers_correctly(): void
    {
        $service = new WhatsAppService;

        $this->assertSame('628123456789', $service->formatPhone('08123456789'));
        $this->assertSame('628123456789', $service->formatPhone('628123456789'));
        $this->assertSame('628123456789', $service->formatPhone('+62 812-3456-789'));
        $this->assertSame('6281234567890', $service->formatPhone('0812-3456-7890'));

        $this->assertNull($service->formatPhone('12345'));
        $this->assertNull($service->formatPhone('021123456')); // Landline Jakarta
        $this->assertNull($service->formatPhone(''));
    }

    public function test_whatsapp_service_builds_voucher_message(): void
    {
        $service = new WhatsAppService;
        $order = Order::factory()->create([
            'customer_name' => 'Budi Santoso',
            'status' => 'paid',
            'total' => 50000,
            'policy_snapshot' => ['visit_date' => '2026-10-15'],
        ]);
        $product = Product::create([
            'partner_id' => $order->partner_id,
            'name' => 'Tiket Masuk Pantai Belitung',
            'slug' => 'pantai-belitung',
            'type' => 'ticket',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 2,
            'unit_price' => 25000,
            'total' => 50000,
            'snapshot' => [],
        ]);

        $message = $service->buildVoucherMessage($order);

        $this->assertStringContainsString('Budi Santoso', $message);
        $this->assertStringContainsString('Tiket Masuk Pantai Belitung', $message);
        $this->assertStringContainsString('2 Pax / Tiket', $message);
        $this->assertStringContainsString('2026-10-15', $message);
        $this->assertStringContainsString($order->public_id, $message);
    }

    public function test_send_order_voucher_requires_paid_status_and_authorization(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $pendingOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending_payment',
        ]);

        $paidOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'paid',
            'policy_snapshot' => ['visit_date' => '2026-10-20'],
        ]);
        $product = Product::create([
            'partner_id' => $paidOrder->partner_id,
            'name' => 'Paket Wisata Pulau',
            'slug' => 'paket-pulau',
            'type' => 'ticket',
        ]);
        $paidOrder->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100000,
            'total' => 100000,
            'snapshot' => [],
        ]);

        // 1. Pending order rejected with 422
        $this->actingAs($user)->postJson("/api/v1/account/orders/{$pendingOrder->public_id}/whatsapp", [
            'phone' => '081234567890',
        ])->assertStatus(422);

        // 2. Stranger cannot access order (403)
        $this->actingAs($stranger)->postJson("/api/v1/account/orders/{$paidOrder->public_id}/whatsapp", [
            'phone' => '081234567890',
        ])->assertForbidden();

        // 3. Owner can send WhatsApp
        $response = $this->actingAs($user)->postJson("/api/v1/account/orders/{$paidOrder->public_id}/whatsapp", [
            'phone' => '081234567890',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.phone', '6281234567890')
            ->assertJsonStructure(['data' => ['phone', 'direct_url', 'message_preview']]);

        // Check snapshot log recorded
        $fresh = $paidOrder->fresh();
        $this->assertNotEmpty($fresh->policy_snapshot['whatsapp_deliveries']);
        $this->assertSame('6281234567890', $fresh->policy_snapshot['whatsapp_deliveries'][0]['phone']);

        // 4. Guest checkout order with matching email can also send via guest endpoint
        $guestOrder = Order::factory()->create([
            'customer_email' => 'tamu@example.test',
            'status' => 'paid',
            'policy_snapshot' => ['visit_date' => '2026-10-25'],
        ]);
        $this->postJson("/api/v1/guest/orders/{$guestOrder->public_id}/whatsapp", [
            'email' => 'tamu@example.test',
            'phone' => '081298765432',
        ])->assertOk()->assertJsonPath('data.phone', '6281298765432');
    }
}
