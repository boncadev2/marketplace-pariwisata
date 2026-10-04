<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductionReadinessService;
use Illuminate\Http\JsonResponse;

class ProductionReadinessController extends Controller
{
    public function __invoke(ProductionReadinessService $service): JsonResponse
    {
        return response()->json(['data' => $service->report()])->header('Cache-Control', 'private, no-store');
    }
}
