<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Services\Refund\RefundService;
use App\Services\ReservationRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => 'nullable|integer|min:1', 'kind' => 'nullable|in:order,reservation']);
        $reservation = $request->input('kind', 'reservation') === 'reservation';
        $query = $reservation ? ReservationRefund::query()->with('payment') : RefundRequest::query()->with('order');
        $items = $query->latest('id')->paginate(12);
        $data = $items->getCollection()->map(fn ($refund) => ['id' => $refund->id, 'kind' => $reservation ? 'reservation' : 'order', 'reference' => $reservation ? $refund->payment->reference : $refund->order->public_id, 'service' => $reservation ? $refund->payment->kind : 'ticket/package', 'status' => $refund->status, 'reason' => $refund->reason, 'refundable_amount' => (int) $refund->refundable_amount, 'decision_notes' => $refund->decision_notes, 'failure_reason' => $refund->failure_reason]);

        return response()->json(['data' => $data, 'meta' => ['page' => $items->currentPage(), 'last_page' => $items->lastPage()]])->header('Cache-Control', 'private, no-store');
    }

    public function reservation(Request $request, string $reference, ReservationRefundService $service): JsonResponse
    {
        $payment = ReservationPayment::where('reference', $reference)->where('user_id', $request->user()->id)->firstOrFail();
        if ($request->isMethod('post')) {
            $data = $request->validate(['reason' => 'required|string|min:5|max:255']);
            $refund = $service->request($payment, $request->user(), $data['reason']);
        } else {
            $refund = ReservationRefund::where('reservation_payment_id', $payment->id)->first();
        }

        return response()->json(['data' => $refund ? $refund->only(['id', 'status', 'reason', 'refundable_amount', 'decision_notes', 'failure_reason']) : null])->header('Cache-Control', 'private, no-store');
    }

    public function order(Request $request, string $publicId, RefundService $service): JsonResponse
    {
        $order = Order::where('public_id', $publicId)->where('user_id', $request->user()->id)->firstOrFail();
        if ($request->isMethod('post')) {
            $data = $request->validate(['reason' => 'required|string|min:5|max:255']);
            try {
                $refund = $service->requestRefund($order, $data['reason']);
            } catch (\DomainException $exception) {
                abort(422, $exception->getMessage());
            }
        } else {
            $refund = RefundRequest::where('order_id', $order->id)->first();
        }

        return response()->json(['data' => $refund ? $refund->only(['id', 'status', 'reason', 'refundable_amount', 'decision_notes', 'failure_reason']) : null])->header('Cache-Control', 'private, no-store');
    }

    public function decide(Request $request, int $refund, string $action, ReservationRefundService $service): JsonResponse
    {
        $data = $request->validate(['notes' => 'required|string|min:5|max:255']);
        $refund = ReservationRefund::findOrFail($refund);

        return response()->json(['data' => $service->decide($refund, $request->user(), $action, $data['notes'])])->header('Cache-Control', 'private, no-store');
    }

    public function refresh(Request $request, string $kind, int $refund, RefundService $orders, ReservationRefundService $reservations): JsonResponse
    {
        if ($kind === 'reservation') {
            $item = ReservationRefund::findOrFail($refund);
            abort_unless($item->status === 'processing', 409, 'Refund tidak sedang menunggu provider.');
            $reservations->process($item);
        } else {
            $item = RefundRequest::findOrFail($refund);
            abort_unless($item->status === 'processing', 409, 'Refund tidak sedang menunggu provider.');
            $orders->process($item);
        }

        return response()->json(['data' => ['status' => $item->fresh()->status]])->header('Cache-Control', 'private, no-store');
    }
}
