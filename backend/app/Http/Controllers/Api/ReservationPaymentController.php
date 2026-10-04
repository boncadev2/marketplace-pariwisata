<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReservationPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationPaymentController extends Controller
{
    public function __invoke(Request $request, string $kind, string $booking, string $action, ReservationPaymentService $service): JsonResponse
    {
        $subject = $service->owned($kind, $booking, $request->user());
        if ($action === 'checkout') {
            $payment = $service->checkout($kind, $subject, $request->user());
        } else {
            $payment = $subject->reservationPayment()->where('user_id', $request->user()->id)->firstOrFail();
            $payment = $action === 'cancel' ? $service->cancel($payment) : $service->refresh($payment);
        }

        return response()->json(['data' => $payment])->header('Cache-Control', 'private, no-store');
    }
}
