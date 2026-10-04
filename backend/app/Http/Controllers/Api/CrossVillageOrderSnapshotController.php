<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CrossVillageOrderSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrossVillageOrderSnapshotController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 503);

        return response()->json(['data' => $order->cross_village_snapshot, 'order_status' => $order->status])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Order $order, CrossVillageOrderSnapshotService $snapshots): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'string', 'size:64'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $snapshot = $snapshots->create($order, $request->user(), $data['version'], $data['reason'], (string) $request->ip());

        return response()->json(['data' => $snapshot])->header('Cache-Control', 'private, no-store');
    }
}
