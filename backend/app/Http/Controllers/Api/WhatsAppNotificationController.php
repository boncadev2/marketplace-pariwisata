<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class WhatsAppNotificationController extends Controller
{
    public function sendOrderVoucher(Request $request, string $publicId, WhatsAppService $whatsAppService): JsonResponse
    {
        $order = Order::query()->where('public_id', $publicId)->firstOrFail();

        abort_unless($order->status === 'paid', 422, 'Voucher WhatsApp hanya dapat dikirim untuk pesanan yang telah dibayar.');
        abort_unless($order->user_id === $request->user()->id, 403, 'Akses tidak sah untuk pesanan ini.');

        return $this->dispatchWhatsAppNotice($request, $order, $whatsAppService);
    }

    public function sendGuestVoucher(Request $request, string $publicId, WhatsAppService $whatsAppService): JsonResponse
    {
        $order = Order::query()->where('public_id', $publicId)->firstOrFail();

        abort_unless($order->status === 'paid', 422, 'Voucher WhatsApp hanya dapat dikirim untuk pesanan yang telah dibayar.');

        $guestToken = $request->header('X-Guest-Access-Token');
        $isAuthorized = false;

        if ($guestToken && Hash::check($guestToken, $order->guest_access_hash)) {
            $isAuthorized = true;
        } elseif ($request->filled('email') && strcasecmp($request->input('email'), $order->customer_email) === 0) {
            $isAuthorized = true;
        }

        abort_unless($isAuthorized, 403, 'Akses tidak sah untuk pesanan ini.');

        return $this->dispatchWhatsAppNotice($request, $order, $whatsAppService);
    }

    private function dispatchWhatsAppNotice(Request $request, Order $order, WhatsAppService $whatsAppService): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $cleanPhone = $whatsAppService->formatPhone($validated['phone']);
        abort_unless($cleanPhone !== null, 422, 'Format nomor WhatsApp tidak valid. Gunakan awalan 08 atau 628.');

        $message = $whatsAppService->buildVoucherMessage($order);
        $result = $whatsAppService->send($cleanPhone, $message, $order);
        $directUrl = $whatsAppService->getDirectShareUrl($cleanPhone, $message);

        // Record in order snapshot
        $snapshot = $order->policy_snapshot ?? [];
        $waLogs = $snapshot['whatsapp_deliveries'] ?? [];
        $waLogs[] = [
            'phone' => $cleanPhone,
            'sent_at' => now()->toIso8601String(),
            'driver' => $result['driver'] ?? 'mock',
        ];
        $snapshot['whatsapp_deliveries'] = $waLogs;
        $order->update(['policy_snapshot' => $snapshot]);

        return response()->json([
            'message' => 'Tiket dan voucher berhasil dikirim ke WhatsApp.',
            'data' => [
                'phone' => $cleanPhone,
                'direct_url' => $directUrl,
                'message_preview' => $message,
            ],
        ]);
    }
}
