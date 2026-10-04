<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePilotCheckoutControlRequest;
use App\Models\AuditLog;
use App\Models\PilotControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PilotCheckoutControlController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => PilotControl::checkoutStatus()])
            ->header('Cache-Control', 'no-store');
    }

    public function update(UpdatePilotCheckoutControlRequest $request): JsonResponse
    {
        $data = $request->validated();

        $control = DB::transaction(function () use ($request, $data): PilotControl {
            $existing = PilotControl::query()->lockForUpdate()->find(1);
            $previousEnabled = $existing?->checkout_enabled ?? (bool) config('pilot.checkout_enabled_default');
            $control = PilotControl::query()->updateOrCreate(['id' => 1], [
                'checkout_enabled' => $data['enabled'],
                'reason' => $data['reason'],
                'changed_by' => $request->user()->id,
            ]);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => $data['enabled'] ? 'pilot.checkout_opened' : 'pilot.checkout_closed',
                'auditable_type' => PilotControl::class,
                'auditable_id' => $control->id,
                'metadata' => [
                    'previous_enabled' => $previousEnabled,
                    'current_enabled' => (bool) $data['enabled'],
                    'reason' => $data['reason'],
                ],
                'ip_hash' => hash('sha256', (string) $request->ip()),
            ]);

            return $control;
        });

        return response()->json(['data' => [
            'enabled' => $control->checkout_enabled,
            'reason' => $control->reason,
            'changed_at' => $control->updated_at?->toIso8601String(),
        ]])->header('Cache-Control', 'private, no-store');
    }
}
