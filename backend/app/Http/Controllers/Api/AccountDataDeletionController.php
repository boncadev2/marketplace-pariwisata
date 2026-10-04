<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DataDeletionRequest;
use App\Services\DataDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountDataDeletionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $deletionRequest = DataDeletionRequest::query()->where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $deletionRequest ? $this->summary($deletionRequest) : null])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->platform_role === 'super_admin', 409, 'Akun administrator memerlukan proses offboarding terpisah.');
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $deletionRequest = DataDeletionRequest::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['status' => 'requested', 'reason' => $validated['reason'] ?? null, 'requested_at' => now()],
        );

        if ($deletionRequest->wasRecentlyCreated) {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'privacy.data_deletion_requested',
                'auditable_type' => DataDeletionRequest::class,
                'auditable_id' => $deletionRequest->id,
                'metadata' => ['status' => 'requested'],
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            ]);
        }

        return response()->json(['data' => $this->summary($deletionRequest)], $deletionRequest->wasRecentlyCreated ? 201 : 200)
            ->header('Cache-Control', 'private, no-store');
    }

    public function process(Request $request, DataDeletionRequest $dataDeletionRequest, DataDeletionService $service): JsonResponse
    {
        $processed = $service->process($dataDeletionRequest, $request->user(), $request->ip());

        return response()->json(['data' => $this->summary($processed)])
            ->header('Cache-Control', 'private, no-store');
    }

    private function summary(DataDeletionRequest $deletionRequest): array
    {
        return [
            'id' => $deletionRequest->id,
            'status' => $deletionRequest->status,
            'requested_at' => $deletionRequest->requested_at?->toIso8601String(),
            'processed_at' => $deletionRequest->processed_at?->toIso8601String(),
            'retained_records' => $deletionRequest->retained_records,
        ];
    }
}
