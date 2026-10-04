<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TransactionNotice extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public const TEMPLATES = [
        'confirmation' => ['Pesanan dikonfirmasi', 'Pembayaran telah dikonfirmasi dan kuota kunjungan telah dijamin.'],
        'awaiting_payment' => ['Menunggu pembayaran', 'Pesanan menunggu pembayaran. Periksa batas waktu sebelum melanjutkan pembayaran.'],
        'expired' => ['Pesanan kedaluwarsa', 'Batas waktu pembayaran telah berakhir. Jangan membayar pesanan ini; buat pesanan baru untuk memeriksa ketersediaan.'],
        'voucher' => ['Voucher kunjungan tersedia', 'Buka halaman voucher dengan nomor pesanan dan kode akses Anda. Jangan membagikan kode akses kepada pihak lain.'],
        'schedule_changed' => ['Jadwal perjalanan berubah', 'Jadwal perjalanan diperbarui. Periksa rincian perubahan dan hubungi pengelola jika membutuhkan bantuan.'],
        'cancelled' => ['Pesanan dibatalkan', 'Pesanan dibatalkan. Refund, jika berlaku, mengikuti kebijakan dan status terpisah.'],
        'refund' => ['Pembaruan pengembalian dana', 'Periksa status refund berikut. Waktu dana masuk dapat berbeda menurut metode pembayaran.'],
    ];

    /** @param array{order_id:string, name:string, detail:string} $snapshot */
    public function __construct(public string $type, public array $snapshot, public ?string $messageKey = null)
    {
        if (! array_key_exists($type, self::TEMPLATES)) {
            throw new \InvalidArgumentException('Jenis notifikasi tidak dikenal.');
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: self::TEMPLATES[$this->type][0],
        );
    }

    public function headers(): Headers
    {
        return new Headers(messageId: $this->messageKey ? $this->messageKey.'@notifications.wisata.test' : null);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.transaction-notice',
            with: ['title' => self::TEMPLATES[$this->type][0], 'description' => self::TEMPLATES[$this->type][1]],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
