<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\OperationalDashboardService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalDashboardController extends Controller
{
    public function __construct(private OperationalDashboardService $dashboard) {}

    public function summary(DashboardFilterRequest $request): JsonResponse
    {
        return $this->privateJson($this->dashboard->summary($request->user(), $request->validated()));
    }

    public function transactions(DashboardFilterRequest $request): JsonResponse
    {
        return $this->privateJson($this->dashboard->transactions($request->user(), $request->validated()));
    }

    public function transaction(DashboardFilterRequest $request, Order $order): JsonResponse
    {
        return $this->privateJson(['data' => $this->dashboard->transaction($request->user(), $order, $request->validated())]);
    }

    public function exceptions(DashboardFilterRequest $request): JsonResponse
    {
        return $this->privateJson($this->dashboard->exceptions($request->user(), $request->validated()));
    }

    public function export(DashboardFilterRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $context = $this->dashboard->context($request->user(), $filters);
        $rows = $this->dashboard->exportRows($request->user(), $filters);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'partner_id' => $context['partner_id'],
            'action' => 'dashboard.transactions_exported',
            'metadata' => ['filters' => $filters, 'format' => 'csv'],
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip()) : null,
        ]);

        $filename = 'transaksi-dashboard-'.now($context['timezone'])->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $context): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['order_id', 'partner', 'status', 'payment_status', 'payout_status', 'currency', 'total', 'created_at']);

            foreach ($rows as $row) {
                fputcsv($output, array_map($this->dashboard->sanitizeCsvCell(...), [
                    $row->public_id,
                    $row->partner_name,
                    $row->status,
                    $row->payment_status,
                    $row->payout_status,
                    $row->currency,
                    $row->total,
                    $row->created_at?->setTimezone($context['timezone'])->toIso8601String(),
                ]));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function privateJson(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'private, no-store');
    }
}
