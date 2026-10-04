<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealBooking;
use App\Models\MealSlot;
use App\Services\MealReservationService;
use App\Support\CommerceMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealBookingController extends Controller
{
    public function slots(): JsonResponse
    {
        $slots = MealSlot::query()->where('is_active', true)->where('time_slot', '>', now())
            ->whereHas('culinaryPlace', fn ($query) => $query->where('is_active', true))
            ->with('culinaryPlace:id,name,description,location')->orderBy('time_slot')->orderBy('id')->paginate(20);
        $slots->through(fn (MealSlot $slot): array => [
            'id' => $slot->id, 'package_name' => $slot->package_name, 'time_slot' => $slot->time_slot->toIso8601String(),
            'price' => $slot->price, 'available' => max(0, $slot->capacity - $slot->reserved), 'place' => $slot->culinaryPlace,
        ]);

        return response()->json(['data' => $slots, 'meta' => ['sandbox_reservations_enabled' => CommerceMode::enabled()]])->header('Cache-Control', 'no-store');
    }

    public function quote(Request $request, MealReservationService $service): JsonResponse
    {
        $data = $this->mealData($request);

        return response()->json(['data' => $service->quote((int) $data['meal_slot_id'], (int) $data['quantity'])])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => MealBooking::query()->with('reservationPayment')->where('user_id', $request->user()->id)->latest('id')->paginate(20)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, MealReservationService $service): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Reservasi kuliner hanya tersedia dalam simulasi lokal.');
        $data = $this->mealData($request);
        $key = validator(['key' => $request->header('Idempotency-Key')], [
            'key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ])->validate()['key'];
        $expected = $request->validate(['expected_total_price' => ['required', 'string', 'regex:/^[0-9]{1,13}\.[0-9]{2}$/']]);
        $booking = $service->reserve($request->user(), (int) $data['meal_slot_id'], (int) $data['quantity'], $key, $expected['expected_total_price']);

        return response()->json(['data' => $booking], $booking->wasRecentlyCreated ? 201 : 200)->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, int $booking, MealReservationService $service): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Reservasi kuliner hanya tersedia dalam simulasi lokal.');

        return response()->json(['data' => $service->cancel($request->user(), $booking)])->header('Cache-Control', 'private, no-store');
    }

    private function mealData(Request $request): array
    {
        $data = $request->validate(['culinary_place_id' => ['sometimes', 'integer', 'min:1'], 'meal_slot_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        if (isset($data['culinary_place_id'])) {
            abort_unless(MealSlot::query()->whereKey($data['meal_slot_id'])->where('culinary_place_id', $data['culinary_place_id'])->exists(), 404, 'Slot bukan milik rumah makan yang dipilih.');
        }

        return $data;
    }
}
