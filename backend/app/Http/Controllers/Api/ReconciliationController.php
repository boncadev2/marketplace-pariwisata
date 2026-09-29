<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReconciliationController extends Controller
{
    public function __construct(private ReconciliationService $reconciliationService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string|unique:reconciliation_batches,reference',
            'date' => 'required|date',
            'mutations' => 'required|array',
            'mutations.*.reference' => 'required|string',
            'mutations.*.amount' => 'required|integer|min:0',
        ]);

        $batch = $this->reconciliationService->reconcile(
            $validated['reference'],
            $validated['date'],
            $validated['mutations']
        );

        $batch->load('entries');

        return response()->json([
            'message' => 'Reconciliation completed',
            'data' => $batch,
        ], 201);
    }
}
