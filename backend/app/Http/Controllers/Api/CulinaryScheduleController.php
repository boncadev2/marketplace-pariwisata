<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CulinaryPlace;
use App\Models\MealSlot;
use App\Support\CommerceMode;
use App\Support\ServiceManagementAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CulinaryScheduleController extends Controller
{
    public function index(Request $request, int $place): JsonResponse
    {
        ServiceManagementAccess::scope(CulinaryPlace::query(), $request->user())->findOrFail($place);
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'page' => 'nullable|integer|min:1']);
        $start = CarbonImmutable::parse($data['date'], 'Asia/Jakarta')->startOfDay();
        $slots = MealSlot::query()->where('culinary_place_id', $place)->where('time_slot', '>=', $start->utc())->where('time_slot', '<', $start->addDay()->utc())->orderBy('time_slot')->orderBy('id')->paginate(20);
        $slots->through(fn (MealSlot $slot): array => $this->present($slot));

        return response()->json(['data' => $slots, 'meta' => ['editing_available' => CommerceMode::enabled(), 'timezone' => 'Asia/Jakarta']])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, int $place): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan jadwal masih tersedia untuk simulasi lokal.');
        $data = $this->validated($request);
        $slot = DB::transaction(function () use ($request, $place, $data): MealSlot {
            ServiceManagementAccess::scope(CulinaryPlace::query(), $request->user())->lockForUpdate()->findOrFail($place);
            abort_if(MealSlot::query()->where('culinary_place_id', $place)->where('time_slot', $data['time_slot'])->where('package_name', $data['package_name'])->exists(), 409, 'Jadwal dan paket ini sudah tersedia. Muat ulang daftar jadwal.');
            $slot = MealSlot::query()->create([...$data, 'culinary_place_id' => $place, 'reserved' => 0]);
            $this->audit($request, $slot, 'culinary.schedule_created');

            return $slot;
        }, 3);

        return response()->json(['data' => $this->present($slot)], 201)->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $place, int $slot): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan jadwal masih tersedia untuk simulasi lokal.');
        $data = $this->validated($request);
        $request->validate(['revision' => 'required|string|size:64']);
        $result = DB::transaction(function () use ($request, $place, $slot, $data): MealSlot {
            ServiceManagementAccess::scope(CulinaryPlace::query(), $request->user())->lockForUpdate()->findOrFail($place);
            $slot = MealSlot::query()->where('culinary_place_id', $place)->lockForUpdate()->findOrFail($slot);
            abort_unless(hash_equals($this->present($slot)['revision'], $request->string('revision')->toString()), 409, 'Jadwal atau kuota berubah. Muat ulang sebelum menyimpan.');
            abort_if($data['capacity'] < $slot->reserved, 422, 'Kapasitas tidak boleh kurang dari peserta yang sudah dipesan.');
            abort_if($slot->reserved > 0 && ($slot->package_name !== $data['package_name'] || ! $slot->time_slot->equalTo($data['time_slot'])), 409, 'Tanggal, jam, dan nama paket dengan reservasi aktif tidak dapat diubah.');
            abort_if(MealSlot::query()->where('culinary_place_id', $place)->whereKeyNot($slot->id)->where('time_slot', $data['time_slot'])->where('package_name', $data['package_name'])->exists(), 409, 'Jadwal dan paket ini sudah tersedia.');
            $slot->update($data);
            $this->audit($request, $slot, 'culinary.schedule_updated');

            return $slot;
        }, 3);

        return response()->json(['data' => $this->present($result)])->header('Cache-Control', 'private, no-store');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:'.now('Asia/Jakarta')->toDateString().'|before_or_equal:'.now('Asia/Jakarta')->addYear()->toDateString(), 'time' => 'required|date_format:H:i', 'package_name' => 'required|string|min:2|max:255', 'price' => 'required|numeric|decimal:0,2|min:1|max:1000000000', 'capacity' => 'required|integer|min:0|max:1000000', 'is_active' => 'required|boolean']);
        $time = CarbonImmutable::parse($data['date'].' '.$data['time'], 'Asia/Jakarta')->utc();
        abort_unless($time->isFuture(), 422, 'Tanggal dan jam harus berada di masa depan.');

        return [...collect($data)->except(['date', 'time'])->all(), 'time_slot' => $time];
    }

    private function present(MealSlot $slot): array
    {
        $data = [...$slot->only(['id', 'culinary_place_id', 'package_name', 'price', 'capacity', 'reserved', 'is_active']), 'time_slot' => $slot->time_slot->toIso8601String(), 'available' => max(0, $slot->capacity - $slot->reserved)];

        return [...$data, 'revision' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))];
    }

    private function audit(Request $request, MealSlot $slot, string $action): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'auditable_type' => MealSlot::class, 'auditable_id' => $slot->id, 'metadata' => ['culinary_place_id' => $slot->culinary_place_id, 'capacity' => $slot->capacity, 'reserved' => $slot->reserved, 'price' => $slot->price]]);
    }
}
