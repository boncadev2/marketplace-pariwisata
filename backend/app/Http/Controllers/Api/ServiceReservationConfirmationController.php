<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CulinaryPlace;
use App\Models\LodgingBooking;
use App\Models\MealBooking;
use App\Models\MealSlot;
use App\Models\ReservationRefund;
use App\Models\RoomType;
use App\Services\LodgingReservationService;
use App\Services\MealReservationService;
use App\Support\CommerceMode;
use App\Support\ServiceManagementAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceReservationConfirmationController extends Controller
{
    public static function revision(LodgingBooking|MealBooking $booking): string
    {
        return hash('sha256', json_encode([$booking->id, $booking->status, $booking->confirmed_at?->toIso8601String(), ($booking instanceof LodgingBooking ? $booking->checked_in_at?->toIso8601String() : null), $booking->completed_at?->toIso8601String()], JSON_THROW_ON_ERROR));
    }

    public function __invoke(Request $request, string $type, int $booking, string $action = 'confirm'): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Konfirmasi reservasi masih tersedia untuk simulasi lokal.');
        $request->validate(['revision' => 'required|string|size:64', 'reason' => ($action === 'cancel' ? 'required' : 'nullable').'|string|min:5|max:1000']);
        $result = DB::transaction(function () use ($request, $type, $booking, $action): array {
            $model = $type === 'lodging' ? LodgingBooking::class : MealBooking::class;
            $candidate = $model::query()->findOrFail($booking);
            if ($type === 'lodging') {
                ServiceManagementAccess::scope(RoomType::query(), $request->user())->lockForUpdate()->findOrFail($candidate->room_type_id);
            } else {
                $slot = MealSlot::query()->findOrFail($candidate->meal_slot_id);
                ServiceManagementAccess::scope(CulinaryPlace::query(), $request->user())->lockForUpdate()->findOrFail($slot->culinary_place_id);
                MealSlot::query()->lockForUpdate()->findOrFail($slot->id);
            }
            $reservation = $model::query()->lockForUpdate()->findOrFail($booking);
            $payment = $reservation->reservationPayment()->first();
            abort_if($payment && ReservationRefund::where('reservation_payment_id', $payment->id)->whereIn('status', ['requested', 'approved', 'processing'])->exists(), 409, 'Reservasi sedang dalam pengajuan refund.');
            $field = match ($action) {
                'confirm' => 'confirmed_at',
                'check-in' => 'checked_in_at',
                'complete' => 'completed_at',
                'cancel' => null,
            };
            $alreadyApplied = $action === 'cancel' ? $reservation->status === 'cancelled' : ($field !== null && $reservation->getAttribute($field) !== null);
            if (! $alreadyApplied) {
                abort_unless(hash_equals(self::revision($reservation), $request->string('revision')->toString()), 409, 'Reservasi berubah. Muat ulang sebelum memperbarui layanan.');
                abort_unless(in_array($action, self::availableActions($reservation)), 409, 'Tindakan belum tersedia untuk status atau jadwal reservasi ini.');
                if ($action === 'cancel') {
                    $reservation = $type === 'lodging'
                        ? app(LodgingReservationService::class)->cancel($reservation->user, $reservation->id)
                        : app(MealReservationService::class)->cancel($reservation->user, $reservation->id);
                } else {
                    $reservation->setAttribute($field, now());
                    $reservation->save();
                }
                AuditLog::create(['user_id' => $request->user()->id, 'action' => match ($action) {
                    'confirm' => 'service.reservation_confirmed',
                    'check-in' => 'service.reservation_checked_in',
                    'complete' => 'service.reservation_completed',
                    'cancel' => 'service.reservation_cancelled',
                }, 'auditable_type' => $model, 'auditable_id' => $reservation->id, 'metadata' => ['type' => $type, 'mode' => 'sandbox', ...($action === 'cancel' ? ['reason' => $request->string('reason')->toString()] : [])]]);
            } else {
                abort_if($action !== 'cancel' && $reservation->status !== 'reserved_sandbox', 409, 'Reservasi dibatalkan tidak dapat diproses.');
            }

            return ['id' => $reservation->id, 'status' => $reservation->status, ...self::operationalData($reservation), 'revision' => self::revision($reservation)];
        }, 3);

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    public static function operationalData(LodgingBooking|MealBooking $booking): array
    {
        return ['payment_status' => $booking->reservationPayment?->status ?? 'unpaid', 'confirmed_at' => $booking->confirmed_at?->toIso8601String(), 'checked_in_at' => ($booking instanceof LodgingBooking ? $booking->checked_in_at?->toIso8601String() : null), 'completed_at' => $booking->completed_at?->toIso8601String(), 'available_actions' => self::availableActions($booking)];
    }

    public static function availableActions(LodgingBooking|MealBooking $booking): array
    {
        if (! CommerceMode::enabled() || $booking->status !== 'reserved_sandbox' || $booking->completed_at !== null) {
            return [];
        }
        $payment = $booking->reservationPayment;
        if ($payment && $payment->status !== 'paid') {
            return [];
        }
        $actions = [];
        if (! $payment && (! ($booking instanceof LodgingBooking) || $booking->checked_in_at === null)) {
            $actions[] = 'cancel';
        }
        if ($booking->confirmed_at === null) {
            $actions[] = 'confirm';
        } elseif ($booking instanceof LodgingBooking) {
            $today = now('Asia/Jakarta')->toDateString();
            if ($booking->checked_in_at === null && $today >= $booking->check_in->toDateString() && $today < $booking->check_out->toDateString()) {
                $actions[] = 'check-in';
            } elseif ($booking->checked_in_at !== null) {
                $actions[] = 'complete';
            }
        } elseif (now()->greaterThanOrEqualTo($booking->time_slot)) {
            $actions[] = 'complete';
        }

        return $actions;
    }
}
