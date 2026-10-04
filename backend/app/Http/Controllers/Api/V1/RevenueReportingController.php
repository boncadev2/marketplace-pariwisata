<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RevenueReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueReportingController extends Controller
{
    public function __construct(private RevenueReportingService $reportingService) {}

    public function index(Request $request): JsonResponse
    {
        // Assuming admin can view all, or partner can view their own.
        // For now, allow filtering by partner_id
        $partnerId = $request->query('partner_id') ? (int) $request->query('partner_id') : null;
        
        $metrics = $this->reportingService->getMetrics($partnerId);
        
        return response()->json([
            'data' => $metrics,
        ]);
    }
}
