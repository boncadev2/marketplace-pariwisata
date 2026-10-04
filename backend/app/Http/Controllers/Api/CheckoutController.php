<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InventoryUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\PilotControl;
use App\Models\Product;
use App\Payments\MidtransSandboxGateway;
use App\Payments\PaymentGatewayManager;
use App\Services\CheckoutService;
use App\Services\PaymentAttemptService;
use App\Support\CommerceMode;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request, CheckoutService $checkoutService, PaymentAttemptService $paymentAttemptService): JsonResponse
    {
        abort_if(app()->isProduction() && ! CommerceMode::enabled(), 503, 'Pemesanan produksi belum diaktifkan.');
        $checkoutStatus = PilotControl::checkoutStatus();
        if (! $checkoutStatus['enabled']) {
            return response()->json([
                'message' => 'Checkout sedang ditutup oleh operator pilot.',
                'error' => [
                    'code' => 'CHECKOUT_CLOSED',
                    'message' => $checkoutStatus['reason'] ?: 'Checkout sementara tidak menerima pesanan baru.',
                ],
            ], 503)->header('Cache-Control', 'no-store');
        }

        if (in_array(app(PaymentGatewayManager::class)->driver(), ['midtrans_sandbox', 'midtrans_production'], true)) {
            app(MidtransSandboxGateway::class)->assertConfigured();
        }

        $data = $request->validate(['product_slug' => ['required', 'string'], 'visit_date' => ['required', 'date_format:Y-m-d'], 'quantity' => ['required', 'integer', 'min:1', 'max:100'], 'customer_name' => ['required', 'string', 'max:120'], 'customer_email' => ['required', 'email:rfc', 'max:255'], 'coupon_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'], 'expected_total' => ['required_with:coupon_code,cross_village_version', 'nullable', 'integer', 'min:1'], 'cross_village_version' => ['nullable', 'string', 'size:64']]);
        $couponCode = isset($data['coupon_code']) ? strtoupper(trim($data['coupon_code'])) : null;
        $user = $request->user('sanctum');
        if ($couponCode !== null) {
            abort_unless(app()->environment(['local', 'testing']), 503, 'Promo hanya tersedia untuk checkout sandbox lokal.');
            abort_unless($user !== null, 401, 'Masuk sebelum menggunakan kupon.');
            abort_unless($user->hasVerifiedEmail() && strcasecmp($user->email, $data['customer_email']) === 0, 403, 'Gunakan email akun yang telah diverifikasi untuk kupon.');
            abort_unless(CarbonImmutable::parse($data['visit_date'])->greaterThanOrEqualTo(CarbonImmutable::today()), 422, 'Tanggal kunjungan kupon tidak boleh lampau.');
        }
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && strlen($key) >= 16, 422, 'Idempotency-Key wajib diisi.');
        $product = Product::query()->where('slug', $data['product_slug'])->where('status', 'published')->firstOrFail();
        try {
            [$order, $guestToken] = $checkoutService->create($product, CarbonImmutable::parse($data['visit_date']), $data['quantity'], $data['customer_name'], $data['customer_email'], $key, $couponCode, $user, isset($data['expected_total']) ? (int) $data['expected_total'] : null, $data['cross_village_version'] ?? null);
        } catch (IdempotencyConflictException $exception) {
            return response()->json(['error' => ['code' => 'IDEMPOTENCY_CONFLICT', 'message' => $exception->getMessage()]], 409)
                ->header('Cache-Control', 'no-store');
        } catch (InventoryUnavailableException $exception) {
            return response()->json(['error' => ['code' => 'INVENTORY_UNAVAILABLE', 'message' => $exception->getMessage()]], 409)
                ->header('Cache-Control', 'no-store');
        }

        $paymentAttempt = $paymentAttemptService->create($order);
        $order->refresh();

        return response()->json(['data' => [
            'order_id' => $order->public_id,
            'status' => $order->status,
            'total' => $order->total,
            'currency' => $order->currency,
            'guest_access_token' => $guestToken,
            'payment_status' => $paymentAttempt->status,
            'payment_provider' => $paymentAttempt->provider,
            'checkout_url' => $paymentAttempt->status === 'failed' ? null : $paymentAttempt->checkout_url,
            'promotion' => $order->policy_snapshot['promotion'] ?? null,
            'cross_village' => $order->cross_village_snapshot === null ? null : ['simulation_only' => true, 'revision' => $order->cross_village_snapshot['revision'], 'basis' => $order->cross_village_snapshot['basis']],
        ]], $guestToken ? 201 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
