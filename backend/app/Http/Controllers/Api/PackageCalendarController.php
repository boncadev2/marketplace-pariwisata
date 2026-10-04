<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryBucket;
use App\Models\Product;
use App\Support\ServiceManagementAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageCalendarController extends Controller
{
    public function index(Request $request, int $product): JsonResponse
    {
        $dates = $this->dates($request);
        $data = DB::transaction(function () use ($request, $product, $dates): array {
            $item = ServiceManagementAccess::scope(Product::query()->where('type', 'package'), $request->user())->lockForUpdate()->findOrFail($product);

            return $this->snapshot($item, $dates);
        }, 3);

        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $product): JsonResponse
    {
        $dates = $this->dates($request);
        $data = $request->validate(['capacity' => 'required|integer|min:0|max:1000000', 'is_closed' => 'required|boolean', 'revision' => 'required|string|size:64']);
        $result = DB::transaction(function () use ($request, $product, $dates, $data): array {
            $item = ServiceManagementAccess::scope(Product::query()->where('type', 'package'), $request->user())->lockForUpdate()->findOrFail($product);
            $before = $this->snapshot($item, $dates);
            abort_unless(hash_equals($before['revision'], $data['revision']), 409, 'Kuota atau pesanan berubah. Muat ulang kalender sebelum menyimpan.');
            foreach ($before['days'] as $day) {
                if ($data['capacity'] < $day['held'] + $day['confirmed']) {
                    throw ValidationException::withMessages(['capacity' => 'Kuota tidak boleh lebih kecil dari jumlah peserta yang ditahan dan sudah dibayar.']);
                }
            }
            foreach ($dates as $date) {
                $bucket = InventoryBucket::query()->where('product_id', $item->id)->where('session_key', 'default')->whereDate('service_date', $date)->lockForUpdate()->first();
                $bucket ??= new InventoryBucket(['product_id' => $item->id, 'service_date' => $date, 'session_key' => 'default']);
                $bucket->fill(['capacity' => $data['capacity'], 'is_closed' => $data['is_closed']])->save();
            }
            AuditLog::create(['user_id' => $request->user()->id, 'partner_id' => $item->partner_id, 'action' => 'package.calendar_updated', 'auditable_type' => Product::class, 'auditable_id' => $item->id, 'metadata' => ['start_date' => $dates[0], 'end_date' => end($dates), 'capacity' => $data['capacity'], 'is_closed' => $data['is_closed']]]);

            return $this->snapshot($item, $dates);
        }, 3);

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    private function dates(Request $request): array
    {
        $today = CarbonImmutable::today('Asia/Jakarta');
        $data = $request->validate(['start_date' => 'required|date_format:Y-m-d|after_or_equal:'.$today->toDateString().'|before_or_equal:'.$today->addMonths(3)->toDateString(), 'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:'.$today->addMonths(3)->toDateString()]);
        $dates = [];
        for ($date = CarbonImmutable::parse($data['start_date']); $date->toDateString() <= $data['end_date']; $date = $date->addDay()) {
            $dates[] = $date->toDateString();
            if (count($dates) > 90) {
                throw ValidationException::withMessages(['end_date' => 'Pilih maksimal 90 tanggal dalam satu penyimpanan.']);
            }
        }

        return $dates;
    }

    private function snapshot(Product $product, array $dates): array
    {
        $buckets = InventoryBucket::query()->where('product_id', $product->id)->where('session_key', 'default')->whereDate('service_date', '>=', $dates[0])->whereDate('service_date', '<=', end($dates))->orderBy('service_date')->lockForUpdate()->get()->keyBy(fn ($bucket) => $bucket->service_date->toDateString());
        $days = array_map(function (string $date) use ($buckets): array {
            $bucket = $buckets->get($date);

            return ['date' => $date, 'capacity' => $bucket?->capacity, 'held' => (int) ($bucket?->held ?? 0), 'confirmed' => (int) ($bucket?->confirmed ?? 0), 'available' => $bucket ? ($bucket->is_closed ? 0 : $bucket->available()) : 0, 'is_closed' => $bucket ? $bucket->is_closed : true];
        }, $dates);
        $data = ['product_id' => $product->id, 'name' => $product->name, 'start_date' => $dates[0], 'end_date' => end($dates), 'days' => $days];

        return [...$data, 'revision' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))];
    }
}
