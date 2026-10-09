<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RevenueReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RevenueReportingController extends Controller
{
    public function __construct(private RevenueReportingService $reportingService) {}

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id') ? (int) $request->query('partner_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $report = $this->reportingService->getFullReport($partnerId, $startDate, $endDate);

        return response()->json([
            'data' => $report,
        ]);
    }

    public function export(Request $request): Response
    {
        $partnerId = $request->query('partner_id') ? (int) $request->query('partner_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $csv = $this->reportingService->exportCsv($partnerId, $startDate, $endDate);
        $filename = 'laporan-pendapatan-'.now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
