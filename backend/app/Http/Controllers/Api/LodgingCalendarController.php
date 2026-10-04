<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RoomType;
use App\Support\CommerceMode;
use App\Support\ServiceManagementAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LodgingCalendarController extends Controller
{
    public function index(Request $request, int $room): JsonResponse
    {
        $dates = $this->dates($request);
        $result = DB::transaction(function () use ($request, $room, $dates): array {
            $room = ServiceManagementAccess::scope(RoomType::query(), $request->user())->whereKey($room)->lockForUpdate()->firstOrFail();

            return $this->snapshot($room, $dates);
        }, 3);

        return response()->json(['data' => $result, 'meta' => ['editing_available' => CommerceMode::enabled()]])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $room): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengaturan tarif masih tersedia untuk simulasi lokal.');
        $dates = $this->dates($request);
        $data = $request->validate(['price' => 'required|integer|min:1|max:1000000000', 'initial_stock' => 'nullable|integer|min:0|max:1000000', 'revision' => 'required|string|size:64']);
        $result = DB::transaction(function () use ($request, $room, $dates, $data): array {
            $room = ServiceManagementAccess::scope(RoomType::query(), $request->user())->whereKey($room)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($room, $dates);
            abort_unless(hash_equals($before['revision'], $data['revision']), 409, 'Tarif atau stok dalam kalender berubah. Tampilkan kalender kembali sebelum menyimpan.');
            $initialized = 0;
            foreach ($before['days'] as $day) {
                $room->rates()->updateOrCreate(['date' => $day['date']], ['price' => $data['price']]);
                if ($day['stock'] === null && isset($data['initial_stock'])) {
                    $room->inventories()->create(['date' => $day['date'], 'stock' => $data['initial_stock']]);
                    $initialized++;
                }
            }
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'lodging.rates_updated', 'auditable_type' => RoomType::class, 'auditable_id' => $room->id,
                'metadata' => ['start_date' => $dates[0], 'end_date' => $dates[count($dates) - 1], 'price' => $data['price'], 'initial_stock' => $data['initial_stock'] ?? null, 'initialized_dates' => $initialized, 'previous_days' => $before['days']]]);

            return [...$this->snapshot($room, $dates), 'initialized_dates' => $initialized];
        }, 3);

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    private function dates(Request $request): array
    {
        $data = $request->validate([
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addYear()->toDateString(),
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:'.now()->addYear()->toDateString(),
        ]);
        $dates = [];
        $end = CarbonImmutable::parse($data['end_date'])->startOfDay();
        for ($date = CarbonImmutable::parse($data['start_date'])->startOfDay(); $date->lte($end); $date = $date->addDay()) {
            $dates[] = $date->toDateString();
            if (count($dates) > 90) {
                throw ValidationException::withMessages(['end_date' => 'Pilih rentang maksimal 90 tanggal termasuk tanggal awal dan akhir.']);
            }
        }

        return $dates;
    }

    private function snapshot(RoomType $room, array $dates): array
    {
        $inventories = $room->inventories()->whereIn('date', $dates)->orderBy('date')->lockForUpdate()->get()->keyBy('date');
        $rates = $room->rates()->whereIn('date', $dates)->orderBy('date')->lockForUpdate()->get()->keyBy('date');
        $days = array_map(fn (string $date): array => ['date' => $date, 'price' => isset($rates[$date]) ? number_format((float) $rates[$date]->price, 2, '.', '') : null,
            'stock' => isset($inventories[$date]) ? (int) $inventories[$date]->stock : null], $dates);
        $data = ['room_id' => $room->id, 'start_date' => $dates[0], 'end_date' => $dates[count($dates) - 1], 'days' => $days];

        return [...$data, 'revision' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))];
    }
}
