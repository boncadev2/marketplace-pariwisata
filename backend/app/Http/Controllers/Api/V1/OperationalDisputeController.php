<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OperationalDispute;
use App\Models\Order;
use Illuminate\Http\Request;

class OperationalDisputeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = OperationalDispute::with(['order', 'reporter'])->latest();

        if ($user->platform_role !== 'super_admin') {
            $query->where('reporter_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $order = Order::query()
            ->where(function ($q) use ($validated) {
                $q->where('id', $validated['order_id'])
                    ->orWhere('public_id', $validated['order_id']);
            })
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $dispute = OperationalDispute::create([
            'order_id' => $order->id,
            'reporter_id' => $request->user()->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'open',
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
