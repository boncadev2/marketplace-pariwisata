<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('app:send-test-email {email : Alamat email penerima uji coba}')]
#[Description('Kirim email uji coba untuk menguji koneksi dan konfigurasi SMTP server resmi')]
class SendTestEmail extends Command
{
    public function handle(): int
    {
        $recipient = (string) $this->argument('email');

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("Alamat email tidak valid: {$recipient}");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $host = config("mail.mailers.{$mailer}.host", '-');
        $port = config("mail.mailers.{$mailer}.port", '-');
        $from = config('mail.from.address');

        $this->info("Menyiapkan pengiriman email uji coba...");
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Mailer', $mailer],
                ['Host', (string) $host],
                ['Port', (string) $port],
                ['Pengirim (From)', (string) $from],
                ['Penerima (To)', $recipient],
            ]
        );

        $this->line("Menghubungi mail server...");

        try {
            Mail::raw(
                "Halo!\n\nIni adalah pesan uji coba dari sistem WisataDaerah (" . config('app.name') . ").\n\nJika Anda menerima email ini, konfigurasi SMTP server resmi Anda telah berhasil terhubung dan berfungsi dengan baik.\n\nWaktu pengiriman: " . now()->toDateTimeString() . "\nDomain: " . config('app.url'),
                function ($message) use ($recipient) {
                    $message->to($recipient)
                        ->subject('Uji Coba Email Resmi - ' . config('app.name'));
                }
            );

            $this->info("✓ Sukses! Email uji coba berhasil dikirim ke: {$recipient}");
            $this->line("Silakan periksa kotak masuk (Inbox) atau folder Spam pada email penerima.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error("✗ Gagal mengirim email!");
            $this->error("Pesan Kesalahan: " . $exception->getMessage());
            $this->newLine();
            $this->warn("Saran Pemecahan Masalah:");
            $this->line("- Periksa kembali kredensial MAIL_USERNAME dan MAIL_PASSWORD di backend/.env.");
            $this->line("- Pastikan MAIL_HOST dan MAIL_PORT sesuai dengan provider (587 untuk TLS, 465 untuk SSL).");
            $this->line("- Jika menggunakan port 465, pastikan MAIL_SCHEME=smtps.");
            $this->line("- Pastikan VPS dapat mengakses port keluar SMTP (outbound firewall / security group).");

            return self::FAILURE;
        }
    }
}
