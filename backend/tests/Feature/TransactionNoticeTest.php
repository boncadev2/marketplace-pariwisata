<?php

namespace Tests\Feature;

use App\Mail\TransactionNotice;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionNoticeTest extends TestCase
{
    public static function noticeTypes(): array
    {
        return [
            'confirmation' => ['confirmation', 'Pesanan dikonfirmasi'],
            'awaiting payment' => ['awaiting_payment', 'Menunggu pembayaran'],
            'expired' => ['expired', 'Pesanan kedaluwarsa'],
            'voucher' => ['voucher', 'Voucher kunjungan tersedia'],
            'schedule changed' => ['schedule_changed', 'Jadwal perjalanan berubah'],
            'cancelled' => ['cancelled', 'Pesanan dibatalkan'],
            'refund' => ['refund', 'Pembaruan pengembalian dana'],
        ];
    }

    #[DataProvider('noticeTypes')]
    public function test_local_preview_renders_template_without_sending_email(string $type, string $title): void
    {
        Mail::fake();

        $this->get('/dev/notifications/'.$type)
            ->assertSee($title)
            ->assertSee('DEMO-PREVIEW-001')
            ->assertSee('PREVIEW')
            ->assertHeader('Cache-Control', 'no-store, private');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_template_escapes_customer_supplied_content(): void
    {
        $mail = new TransactionNotice('refund', ['order_id' => 'ORDER-1', 'name' => '<script>alert(1)</script>', 'detail' => '<img src=x onerror=alert(1)>']);

        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $mail->assertDontSeeInHtml('<script>', false);
        $mail->assertDontSeeInHtml('<img src=x', false);
    }

    public function test_production_preview_returns_404(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        Mail::fake();

        $this->get('/dev/notifications/voucher')->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_unknown_template_returns_404(): void
    {
        $this->get('/dev/notifications/unknown')->assertNotFound();
    }

    public function test_unknown_mail_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TransactionNotice('unknown', ['order_id' => 'ORDER-1', 'name' => 'Demo', 'detail' => 'Demo']);
    }
}
