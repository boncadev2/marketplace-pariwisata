<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Services\Refund\RefundService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{
    public function __construct(private RefundService $refundService) {}

    public function store(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $refundRequest = $this->refundService->requestRefund($order, $request->reason);

            return response()->json($refundRequest, 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, RefundRequest $refundRequest): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $this->refundService->approve($refundRequest, $request->user()->id, $request->notes);

            return response()->json($refundRequest->fresh());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, RefundRequest $refundRequest): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|max:255',
        ]);

        try {
            $this->refundService->reject($refundRequest, $request->user()->id, $request->notes);

            return response()->json($refundRequest->fresh());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
