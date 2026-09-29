<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OperationalDispute;
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
            'reporter_id' => 'required|exists:users,id',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $dispute = OperationalDispute::create($validated);

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
