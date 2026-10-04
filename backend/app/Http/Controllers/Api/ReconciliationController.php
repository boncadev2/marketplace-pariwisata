<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveReconciliationAlertRequest;
use App\Jobs\ReconcilePaymentAttempt;
use App\Models\AuditLog;
use App\Models\PaymentAttempt;
use App\Models\ReconciliationAlert;
use App\Models\ReconciliationBatch;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function __construct(private ReconciliationService $reconciliationService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string|unique:reconciliation_batches,reference',
            'date' => 'required|date',
            'mutations' => 'required|array',
            'mutations.*.reference' => 'required|string',
            'mutations.*.amount' => 'required|integer|min:0',
            'mutations.*.status' => 'nullable|in:pending,unknown,succeeded,failed',
            'mutations.*.fee' => 'nullable|integer|min:0',
            'mutations.*.settlement_reference' => 'nullable|string|max:255',
        ]);

        $batch = $this->reconciliationService->reconcile(
            $validated['reference'],
            $validated['date'],
            $validated['mutations']
        );

        $batch->load('entries');
        $this->audit($request, 'reconciliation.manual_import', ReconciliationBatch::class, $batch->id, [
            'reference' => $batch->reference,
            'entries' => $batch->entries->count(),
        ]);

        return response()->json([
            'message' => 'Reconciliation completed',
            'data' => $batch,
        ], 201);
    }

    public function reports(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', 'in:manual,automatic,daily_report'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $reports = ReconciliationBatch::query()
            ->when($validated['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->withCount([
                'entries',
                'entries as discrepancy_count' => fn ($query) => $query->whereIn('status', ['mismatched', 'not_found']),
            ])
            ->latest('date')
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return response()->json($reports)->header('Cache-Control', 'private, no-store');
    }

    public function alerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,resolved'],
            'type' => ['nullable', 'string', 'max:48'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $alerts = ReconciliationAlert::query()
            ->when($validated['status'] ?? 'open', fn ($query, $status) => $query->where('status', $status))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->with(['order:id,public_id', 'partner:id,name'])
            ->latest('detected_at')
            ->paginate((int) ($validated['per_page'] ?? 50));

        return response()->json($alerts)->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, PaymentAttempt $paymentAttempt): JsonResponse
    {
        $paymentAttempt->update([
            'next_reconciliation_at' => now(),
            'reconciliation_error' => null,
        ]);
        ReconcilePaymentAttempt::dispatch($paymentAttempt->id);
        $this->audit($request, 'reconciliation.retry_requested', PaymentAttempt::class, $paymentAttempt->id, [
            'provider' => $paymentAttempt->provider,
        ]);

        return response()->json(['data' => ['queued' => true, 'payment_attempt_id' => $paymentAttempt->id]], 202);
    }

    public function resolve(ResolveReconciliationAlertRequest $request, ReconciliationAlert $reconciliationAlert): JsonResponse
    {
        $reconciliationAlert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'resolution_notes' => $request->validated('notes'),
        ]);
        $this->audit($request, 'reconciliation.alert_resolved', ReconciliationAlert::class, $reconciliationAlert->id, [
            'type' => $reconciliationAlert->type,
        ]);

        return response()->json(['data' => $reconciliationAlert->fresh()]);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, string $action, string $type, int $id, array $metadata): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'metadata' => $metadata,
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);
    }
}
