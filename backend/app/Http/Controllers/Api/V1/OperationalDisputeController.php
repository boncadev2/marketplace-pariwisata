<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OperationalDispute;
use App\Models\Order;
use Illuminate\Http\Request;

class OperationalDisputeController extends Controller
{
    public function index()
    {
        return response()->json(OperationalDispute::with(['order', 'reporter'])->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $order = Order::query()
            ->whereKey($validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $dispute = OperationalDispute::create([
            ...$validated,
            'order_id' => $order->id,
            'reporter_id' => $request->user()->id,
        ]);

        return response()->json($dispute, 201);
    }

    public function update(Request $request, OperationalDispute $operationalDispute)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,under_review,resolved,closed',
            'resolution' => 'nullable|string',
        ]);

        $operationalDispute->update($validated);

        return response()->json($operationalDispute);
    }
}
