<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CulinaryPlace;
use App\Models\MealSlot;
use App\Support\CommerceMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CulinaryPlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'sort' => 'nullable|in:name,price_asc,price_desc', 'min_price' => 'nullable|numeric|min:0|max:1000000000', 'max_price' => 'nullable|numeric|min:0|max:1000000000']);
        if (isset($data['min_price'], $data['max_price'])) {
            abort_unless((float) $data['max_price'] >= (float) $data['min_price'], 422, 'Harga maksimum harus sama atau lebih besar dari harga minimum.');
        }
        $query = $this->catalogQuery();
        $search = trim($data['q'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn (Builder $query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("location LIKE ? ESCAPE '!'", [$pattern]));
        }
        if (isset($data['min_price']) || isset($data['max_price'])) {
            $query = CulinaryPlace::query()->fromSub($query->reorder(), 'catalog')->select('catalog.*')->orderBy('name')->orderBy('id');
            if (isset($data['min_price'])) {
                $query->whereRaw('CAST(starting_price AS DECIMAL(15,2)) >= ?', [(float) $data['min_price']]);
            }
            if (isset($data['max_price'])) {
                $query->whereRaw('CAST(starting_price AS DECIMAL(15,2)) <= ?', [(float) $data['max_price']]);
            }
        }
        $sort = $data['sort'] ?? 'name';
        if (in_array($sort, ['price_asc', 'price_desc'], true)) {
            $query->reorder()->orderByRaw('starting_price IS NULL')->orderBy('starting_price', $sort === 'price_asc' ? 'asc' : 'desc')->orderBy('name')->orderBy('id');
        }
        $places = $query->paginate(12);
        $places->through(fn (CulinaryPlace $place): array => $this->present($place));

        return response()->json(['data' => $places, 'meta' => ['sandbox_reservations_enabled' => CommerceMode::enabled()]])->header('Cache-Control', 'no-store');
    }

    public function show(int $place): JsonResponse
    {
        $place = $this->catalogQuery()->whereKey($place)->firstOrFail();

        return response()->json(['data' => $this->present($place), 'meta' => ['sandbox_reservations_enabled' => CommerceMode::enabled()]])->header('Cache-Control', 'no-store');
    }

    public function slots(Request $request, int $place): JsonResponse
    {
        CulinaryPlace::query()->where('is_active', true)->whereKey($place)->firstOrFail();
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:'.now('Asia/Jakarta')->toDateString().'|before_or_equal:'.now('Asia/Jakarta')->addYear()->toDateString(), 'page' => 'nullable|integer|min:1']);
        $start = CarbonImmutable::parse($data['date'], 'Asia/Jakarta')->startOfDay();
        $slots = MealSlot::query()->where('culinary_place_id', $place)->where('is_active', true)->where('price', '>', 0)
            ->where('time_slot', '>', now())->where('time_slot', '>=', $start->utc())->where('time_slot', '<', $start->addDay()->utc())
            ->orderBy('time_slot')->orderBy('id')->paginate(20);
        $slots->through(fn (MealSlot $slot): array => ['id' => $slot->id, 'culinary_place_id' => $place, 'package_name' => $slot->package_name,
            'time_slot' => $slot->time_slot->toIso8601String(), 'price' => $slot->price, 'available' => max(0, $slot->capacity - $slot->reserved)]);

        return response()->json(['data' => $slots, 'meta' => ['date' => $data['date'], 'timezone' => 'Asia/Jakarta']])->header('Cache-Control', 'no-store');
    }

    private function catalogQuery(): Builder
    {
        $available = fn (): Builder => MealSlot::query()->whereColumn('culinary_place_id', 'culinary_places.id')->where('is_active', true)->where('time_slot', '>', now())->where('price', '>', 0)->whereColumn('capacity', '>', 'reserved');

        return CulinaryPlace::query()->where('is_active', true)->select(['id', 'name', 'description', 'location', 'latitude', 'longitude', 'location_is_demo', 'image_url', 'photos_are_illustrations', 'photo_path'])
            ->addSelect(['starting_price' => $available()->selectRaw('MIN(price)'), 'next_available_time' => $available()->selectRaw('MIN(time_slot)')])->orderBy('name')->orderBy('id');
    }

    private function present(CulinaryPlace $place): array
    {
        $url = $place->image_url;
        $trusted = is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === 'images.unsplash.com' && str_starts_with((string) parse_url($url, PHP_URL_PATH), '/photo-');

        return [...$place->only(['id', 'name', 'description', 'location', 'latitude', 'longitude', 'location_is_demo', 'photos_are_illustrations', 'starting_price']),
            'image_url' => $place->photoUrl() ?? ($trusted ? $url : null), 'next_available_time' => $place->next_available_time ? CarbonImmutable::parse($place->next_available_time, 'UTC')->toIso8601String() : null];
    }
}
