<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Normalize Indonesian phone number to international format (628...).
     */
    public function formatPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Strip non-digit characters
        $digits = preg_replace('/[^\d]/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        // Valid Indonesian mobile number starts with 628 and has 10 to 15 digits
        if (! preg_match('/^628\d{8,12}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    /**
     * Build ticket voucher message for WhatsApp.
     */
    public function buildVoucherMessage(Order $order): string
    {
        $item = $order->items()->first();
        $productName = $item?->name ?? 'Tiket Wisata';
        $quantity = $item?->quantity ?? 1;
        $visitDate = $order->policy_snapshot['visit_date'] ?? '-';
        $totalFormatted = number_format($order->total, 0, ',', '.');
        $baseUrl = rtrim((string) (config('services.frontend_url') ?: config('app.url', 'https://wisatadaerah.id')), '/');
        $voucherUrl = "{$baseUrl}/voucher?ref={$order->public_id}";

        $message = "Halo *{$order->customer_name}*,\n\n";
        $message .= "Terima kasih telah memesan di *WisataDaerah*! 🎉\n";
        $message .= "Pembayaran Anda telah TERVERIFIKASI dan tiket Anda siap digunakan.\n\n";
        $message .= "📋 *Rincian Pesanan:*\n";
        $message .= "• ID Pesanan: `{$order->public_id}`\n";
        $message .= "• Layanan: *{$productName}*\n";
        $message .= "• Jumlah: *{$quantity} Pax / Tiket*\n";
        $message .= "• Tanggal Kunjungan: *{$visitDate}*\n";
        $message .= "• Total Pembayaran: *Rp {$totalFormatted}*\n\n";
        $message .= "🎟️ *Tautan E-Tiket & QR Code Masuk:*\n";
        $message .= "{$voucherUrl}\n\n";
        $message .= "Silakan tunjukkan QR Code pada tautan di atas kepada petugas tiket saat tiba di lokasi pintu masuk.\n\n";
        $message .= "Selamat berlibur dan menikmati keindahan alam nusantara! 🌴✨\n";
        $message .= "_Butuh bantuan? Kunjungi {$baseUrl}/bantuan_";

        return $message;
    }

    /**
     * Dispatch WhatsApp message via configured driver (gadstudio, fonnte, or mock).
     */
    public function send(string $phone, string $message, ?Order $order = null): array
    {
        $cleanPhone = $this->formatPhone($phone);
        if (! $cleanPhone) {
            throw new \InvalidArgumentException('Nomor WhatsApp tidak valid. Format yang didukung: 08xxxxxxxxxx atau 628xxxxxxxxxx.');
        }

        $driver = config('services.whatsapp.driver', env('WHATSAPP_GATEWAY_DRIVER', 'gadstudio'));
        $token = config('services.whatsapp.token', env('WHATSAPP_API_TOKEN', ''));
        $baseUrl = config('services.whatsapp.url', env('WHATSAPP_API_URL', 'https://wa.gadstudio.cloud'));

        if (in_array($driver, ['gadstudio', 'custom', 'generic', 'wagateway'], true) && ! empty($token)) {
            try {
                $endpoint = rtrim((string) $baseUrl, '/').'/api/send-text';
                $response = Http::timeout(10)->withHeaders([
                    'x-api-key' => $token,
                    'Content-Type' => 'application/json',
                ])->post($endpoint, [
                    'to' => $cleanPhone,
                    'message' => $message,
                ]);

                $data = $response->json();
                $isSuccess = $response->successful() && (($data['status'] ?? false) === true || ($data['success'] ?? false) === true);
                Log::info("WhatsApp sent via GADStudio Gateway to {$cleanPhone}", ['response' => $data]);

                return [
                    'success' => $isSuccess,
                    'driver' => 'gadstudio',
                    'phone' => $cleanPhone,
                    'response' => $data,
                ];
            } catch (\Throwable $e) {
                Log::error("Failed to send WhatsApp via GADStudio Gateway to {$cleanPhone}: {$e->getMessage()}");
            }
        }

        if ($driver === 'fonnte' && ! empty($token)) {
            try {
                $response = Http::timeout(10)->withHeaders([
                    'Authorization' => $token,
                ])->post('https://api.fonnte.com/send', [
                    'target' => $cleanPhone,
                    'message' => $message,
                ]);

                $data = $response->json();
                Log::info("WhatsApp sent via Fonnte to {$cleanPhone}", ['response' => $data]);

                return [
                    'success' => $response->successful(),
                    'driver' => 'fonnte',
                    'phone' => $cleanPhone,
                    'response' => $data,
                ];
            } catch (\Throwable $e) {
                Log::error("Failed to send WhatsApp via Fonnte to {$cleanPhone}: {$e->getMessage()}");
            }
        }

        // Fallback or testing/mock driver
        Log::info("WhatsApp Mock dispatched to {$cleanPhone}:\n{$message}");

        return [
            'success' => true,
            'driver' => 'mock',
            'phone' => $cleanPhone,
            'sent_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate direct WhatsApp click-to-chat URL.
     */
    public function getDirectShareUrl(string $phone, string $message): string
    {
        $cleanPhone = $this->formatPhone($phone) ?: $phone;

        return 'https://api.whatsapp.com/send?phone='.$cleanPhone.'&text='.rawurlencode($message);
    }
}
