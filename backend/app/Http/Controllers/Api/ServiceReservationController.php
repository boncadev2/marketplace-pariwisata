<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LodgingBooking;
use App\Models\MealBooking;
use App\Support\ServiceManagementAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceReservationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => 'required|in:lodging,culinary', 'operation' => 'nullable|in:waiting,confirmed,checked_in,completed', 'status' => 'nullable|in:reserved_sandbox,cancelled', 'date' => 'nullable|date_format:Y-m-d', 'id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1']);
        $lodging = $data['type'] === 'lodging';
        $query = $lodging ? LodgingBooking::query()->with(['user:id,name', 'roomType:id,name']) : MealBooking::query()->with(['user:id,name', 'mealSlot:id,culinary_place_id', 'mealSlot.culinaryPlace:id,name']);
        if (! ServiceManagementAccess::admin($request->user())) {
            $ids = ServiceManagementAccess::partnerIds($request->user());
            $query->whereHas($lodging ? 'roomType' : 'mealSlot.culinaryPlace', fn ($place) => $place->whereIn('partner_id', $ids));
        }
        if (! empty($data['operation'])) {
            $query->where('status', 'reserved_sandbox');
            if ($data['operation'] === 'waiting') {
                $query->whereNull('confirmed_at');
            } elseif ($data['operation'] === 'completed') {
                $query->whereNotNull('completed_at');
            } elseif ($data['operation'] === 'checked_in') {
                abort_unless($lodging, 422, 'Check-in hanya tersedia untuk penginapan.');
                $query->whereNotNull('checked_in_at')->whereNull('completed_at');
            } else {
                $query->whereNotNull('confirmed_at')->whereNull('completed_at');
                if ($lodging) {
                    $query->whereNull('checked_in_at');
                }
            }
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['id'])) {
            $query->whereKey($data['id']);
        }
        if (! empty($data['date'])) {
            if ($lodging) {
                $query->whereDate('check_in', $data['date']);
            } else {
                $start = CarbonImmutable::parse($data['date'], 'Asia/Jakarta')->startOfDay();
                $query->where('time_slot', '>=', $start->utc())->where('time_slot', '<', $start->addDay()->utc());
            }
        }
        $bookings = $query->with('reservationPayment')->latest('id')->paginate(20);
        $bookings->through(function (LodgingBooking|MealBooking $booking) use ($lodging): array {
            $common = ['id' => $booking->id, 'type' => $lodging ? 'lodging' : 'culinary', 'customer_name' => $booking->user?->name ?? 'Akun tidak tersedia', 'place_name' => $lodging ? $booking->roomType?->name : $booking->mealSlot?->culinaryPlace?->name, 'status' => $booking->status, ...ServiceReservationConfirmationController::operationalData($booking), 'revision' => ServiceReservationConfirmationController::revision($booking), 'quantity' => $booking->quantity, 'total_price' => $booking->total_price, 'created_at' => $booking->created_at->toIso8601String()];

            return [...$common, ...($lodging ? ['check_in' => $booking->check_in->toDateString(), 'check_out' => $booking->check_out->toDateString(), 'guests' => $booking->guests, 'nights' => (int) $booking->check_in->diffInDays($booking->check_out)] : ['package_name' => $booking->package_name, 'time_slot' => $booking->time_slot->toIso8601String(), 'unit_price' => $booking->unit_price])];
        });

        return response()->json(['data' => $bookings, 'meta' => ['timezone' => 'Asia/Jakarta', 'reservation_mode' => 'sandbox', 'confirmation_available' => app()->environment(['local', 'testing'])]])->header('Cache-Control', 'private, no-store');
    }
}
