<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_for_guest_and_404_for_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);
        $uri = '/api/v1/account/orders/'.$order->public_id.'/support-tickets';

        $this->postJson($uri, ['category' => 'booking', 'message' => 'Tolong periksa jadwal saya.'])->assertUnauthorized();
        $this->actingAs($other)->postJson($uri, ['category' => 'booking', 'message' => 'Tolong periksa jadwal saya.'])->assertNotFound();
        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_valid_message_creates_ticket_for_owned_order_without_trusting_owner_fields(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson('/api/v1/account/orders/'.$order->public_id.'/support-tickets', [
            'category' => 'payment', 'message' => 'Pembayaran saya belum terlihat.', 'user_id' => $other->id, 'status' => 'closed',
        ])->assertCreated()->assertJsonPath('data.category', 'payment')->assertJsonPath('data.status', 'open')->assertJsonPath('data.messages.0.author', 'customer');

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame($owner->id, $ticket->user_id);
        $this->assertSame($order->id, $ticket->order_id);
        $this->assertDatabaseHas('support_ticket_messages', ['support_ticket_id' => $ticket->id, 'user_id' => $owner->id, 'body' => 'Pembayaran saya belum terlihat.']);
        $response->assertDontSee('guest_access_hash')->assertDontSee('customer_email');
    }

    public function test_invalid_category_short_message_and_unsafe_attachment_return_422_without_writes(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);
        $uri = '/api/v1/account/orders/'.$order->public_id.'/support-tickets';

        $this->actingAs($owner)->postJson($uri, ['category' => 'unknown', 'message' => 'short'])->assertUnprocessable()->assertJsonValidationErrors(['category', 'message']);
        $this->actingAs($owner)->withHeader('Accept', 'application/json')->post($uri, [
            'category' => 'other', 'message' => 'Berikut lampiran untuk pemeriksaan.',
            'attachment' => UploadedFile::fake()->createWithContent('attack.html', '<script>alert(1)</script>'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['attachment']);
        $this->actingAs($owner)->withHeader('Accept', 'application/json')->post($uri, [
            'category' => 'other', 'message' => 'Lampiran terlalu besar untuk diperiksa.',
            'attachment' => UploadedFile::fake()->create('too-large.pdf', 5121, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['attachment']);
        $this->assertDatabaseCount('support_tickets', 0);
        Storage::disk('local')->assertDirectoryEmpty('support-attachments');
    }

    public function test_private_attachment_can_only_be_downloaded_by_ticket_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);
        $uri = '/api/v1/account/orders/'.$order->public_id.'/support-tickets';
        $attachment = UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

        $response = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post($uri, [
            'category' => 'payment', 'message' => 'Saya lampirkan bukti pembayaran.', 'attachment' => $attachment,
        ])->assertCreated()->assertJsonPath('data.messages.0.attachments.0.mime_type', 'application/pdf');
        $ticketId = $response->json('data.id');
        $attachmentId = $response->json('data.messages.0.attachments.0.id');
        $this->assertNotNull($attachmentId);
        $response->assertDontSee('support-attachments/');
        Storage::disk('local')->assertExists(SupportTicketAttachment::findOrFail($attachmentId)->path);
        $path = '/api/v1/account/support-tickets/'.$ticketId.'/attachments/'.$attachmentId;

        $this->actingAs($other)->getJson($path)->assertNotFound();
        $this->actingAs($owner)->get($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload('lampiran-'.$attachmentId.'.pdf');
        $another = SupportTicket::factory()->create(['user_id' => $owner->id, 'order_id' => $order->id]);
        $this->actingAs($owner)->getJson('/api/v1/account/support-tickets/'.$another->id.'/attachments/'.$attachmentId)->assertNotFound();
    }

    public function test_list_detail_and_followup_are_scoped_and_closed_ticket_rejects_new_message(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownOrder = Order::factory()->create(['user_id' => $owner->id]);
        $foreignOrder = Order::factory()->create(['user_id' => $other->id]);
        $own = SupportTicket::factory()->create(['user_id' => $owner->id, 'order_id' => $ownOrder->id]);
        $foreign = SupportTicket::factory()->create(['user_id' => $other->id, 'order_id' => $foreignOrder->id]);
        $uri = '/api/v1/account/support-tickets/'.$own->id;

        $this->actingAs($owner)->getJson('/api/v1/account/support-tickets')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $own->id);
        $this->actingAs($owner)->getJson('/api/v1/account/support-tickets/'.$foreign->id)->assertNotFound();
        $this->actingAs($other)->getJson($uri)->assertNotFound();
        $this->actingAs($owner)->postJson($uri.'/messages', ['message' => 'Mohon kabari status bantuan ini.'])->assertCreated()->assertJsonPath('data.author', 'customer');
        $this->actingAs($owner)->getJson($uri)->assertOk()->assertJsonCount(1, 'data.messages');
        $own->update(['status' => 'closed']);
        $this->actingAs($owner)->postJson($uri.'/messages', ['message' => 'Pesan setelah tiket ditutup.'])->assertStatus(409);
        $this->assertDatabaseCount('support_ticket_messages', 1);

    }
}
