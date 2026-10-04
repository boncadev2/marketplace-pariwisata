<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Payments\MidtransSandboxGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;

class PaymentGatewayStatusController extends Controller
{
    public function __invoke(PaymentGatewayManager $manager): JsonResponse
    {
        $driver = $manager->driver();
        $ready = match ($driver) {
            'sandbox' => true,
            'midtrans_sandbox', 'midtrans_production' => app(MidtransSandboxGateway::class)->isConfigured(),
            default => false,
        };

        return response()->json(['data' => ['provider' => $driver, 'mode' => $driver === 'midtrans_production' ? 'production' : 'sandbox', 'ready' => $ready]])->header('Cache-Control', 'no-store');
    }
}
