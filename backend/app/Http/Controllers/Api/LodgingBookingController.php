<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LodgingBooking;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\LodgingReservationService;
use App\Support\CommerceMode;
use App\Support\ServiceManagementAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LodgingBookingController extends Controller
{
    public function rooms(Request $request): JsonResponse
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
            $query = RoomType::query()->fromSub($query->reorder(), 'catalog')->select('catalog.*')->orderBy('name')->orderBy('id');
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
        $rooms = $query->paginate(20);
        $rooms->getCollection()->transform(fn (RoomType $room): RoomType => $this->catalogRoom($room));

        return response()->json([
            'data' => $rooms,
            'meta' => ['sandbox_reservations_enabled' => CommerceMode::enabled(), 'price_basis' => 'lowest_available_nightly_rate'],
        ])->header('Cache-Control', 'no-store');
    }

    public function show(int $room): JsonResponse
    {
        $room = $this->catalogQuery()->whereKey($room)->firstOrFail();

        return response()->json(['data' => $this->catalogRoom($room), 'meta' => ['sandbox_reservations_enabled' => CommerceMode::enabled(), 'price_basis' => 'lowest_available_nightly_rate']])->header('Cache-Control', 'no-store');
    }

    private function catalogQuery(): Builder
    {
        $startingPrice = RoomRate::query()->selectRaw('MIN(room_rates.price)')
            ->join('room_inventories', function ($join): void {
                $join->on('room_inventories.room_type_id', '=', 'room_rates.room_type_id')->on('room_inventories.date', '=', 'room_rates.date');
            })->whereColumn('room_rates.room_type_id', 'room_types.id')->where('room_inventories.stock', '>', 0)
            ->where('room_rates.price', '>', 0)->whereBetween('room_rates.date', [now()->toDateString(), now()->addYear()->toDateString()]);

        return RoomType::query()->where('is_active', true)->select(['id', 'name', 'description', 'capacity', 'location', 'latitude', 'longitude', 'location_is_demo', 'exterior_image_url', 'interior_image_url', 'photos_are_illustrations', 'exterior_photo_path', 'interior_photo_path'])
            ->addSelect(['starting_price' => $startingPrice])->orderBy('name')->orderBy('id');
    }

    private function catalogRoom(RoomType $room): RoomType
    {
        foreach (['exterior_image_url', 'interior_image_url'] as $field) {
            $url = $room->getAttribute($field);
            if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'images.unsplash.com' || ! str_starts_with((string) parse_url($url, PHP_URL_PATH), '/photo-')) {
                $room->setAttribute($field, null);
            }
        }

        foreach (['exterior', 'interior'] as $kind) {
            if ($room->photoPath($kind)) {
                $room->setAttribute($kind.'_image_url', '/api/v1/lodging/rooms/'.$room->id.'/photos/'.$kind.'?v='.hash('sha256', $room->photoPath($kind)));
            }
        }

        return $room;
    }

    public function photo(Request $request, int $room, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['exterior', 'interior'], true), 404);
        $room = RoomType::query()->whereKey($room)->firstOrFail();
        $admin = ServiceManagementAccess::canManage($request->user(), $room->partner_id);
        abort_unless($room->is_active || $admin, 404);
        $path = $room->photoPath($kind);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function quote(Request $request, LodgingReservationService $service): JsonResponse
    {
        return response()->json(['data' => $service->quote($this->stayData($request))])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => LodgingBooking::query()->with('reservationPayment')->where('user_id', $request->user()->id)
            ->with('roomType:id,name')->latest('id')->paginate(20)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, LodgingReservationService $service): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Reservasi penginapan hanya tersedia dalam simulasi lokal.');
        $data = $this->stayData($request);
        $key = validator(['key' => $request->header('Idempotency-Key')], [
            'key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ])->validate()['key'];
        $expected = $request->validate(['expected_total_price' => ['required', 'string', 'regex:/^[0-9]{1,13}\.[0-9]{2}$/']]);
        $booking = $service->reserve($request->user(), $data, $key, $expected['expected_total_price']);

        return response()->json(['data' => $booking], $booking->wasRecentlyCreated ? 201 : 200)
            ->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, int $booking, LodgingReservationService $service): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Reservasi penginapan hanya tersedia dalam simulasi lokal.');

        return response()->json(['data' => $service->cancel($request->user(), $booking)])
            ->header('Cache-Control', 'private, no-store');
    }

    private function stayData(Request $request): array
    {
        return $request->validate([
            'room_type_id' => ['required', 'integer', 'min:1'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.now()->addYear()->addDays(30)->toDateString()],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'guests' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
