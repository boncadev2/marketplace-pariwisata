<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Services\Refund\RefundService;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{
    public function __construct(private RefundService $refundService) {}

    public function store(Request $request, Order $order)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $refundRequest = $this->refundService->requestRefund($order, $request->reason);

            return response()->json($refundRequest, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, RefundRequest $refundRequest)
    {
        $request->validate([
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            // In a real app, you would authorize and get the admin/partner user id
            $userId = $request->user()?->id ?? 1; // Fallback for tests if not authenticated

            $this->refundService->approve($refundRequest, $userId, $request->notes);

            return response()->json($refundRequest->fresh());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, RefundRequest $refundRequest)
    {
        $request->validate([
            'notes' => 'required|string|max:255',
        ]);

        try {
            $userId = $request->user()?->id ?? 1;

            $this->refundService->reject($refundRequest, $userId, $request->notes);

            return response()->json($refundRequest->fresh());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
