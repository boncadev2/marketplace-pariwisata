<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RoomType;
use App\Models\User;
use App\Support\CommerceMode;
use App\Support\ServiceManagementAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LodgingManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => 'nullable|integer|min:1', 'date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addYear()->toDateString()]);
        $rooms = ServiceManagementAccess::scope(RoomType::query(), $request->user())->orderBy('name')->orderBy('id')->paginate(12);
        $date = $request->string('date')->toString();
        $data = DB::transaction(function () use ($request, $rooms, $date): array {
            return $rooms->getCollection()->map(function (RoomType $room) use ($request, $date): array {
                $room = ServiceManagementAccess::scope(RoomType::query(), $request->user())->whereKey($room->id)->lockForUpdate()->firstOrFail();

                return $this->present($room, $date);
            })->all();
        });

        return response()->json(['data' => $data, 'meta' => ['page' => $rooms->currentPage(), 'last_page' => $rooms->lastPage(), 'partners' => ServiceManagementAccess::partners($request->user()), 'editing_available' => CommerceMode::enabled()]])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $room): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan penginapan masih tersedia untuk simulasi lokal.');
        $data = $this->validated($request);
        if ($request->isMethod('POST') || $request->exists('partner_id')) {
            $request->validate(['partner_id' => 'nullable|integer|min:1']);
            $data['partner_id'] = ServiceManagementAccess::assignment($request->user(), $request->input('partner_id'));
        }
        $data += $request->validate(['revision' => 'required|string|size:64']);
        $storedPaths = [];
        try {
            $result = DB::transaction(function () use ($request, $room, $data, &$storedPaths): array {
                $room = ServiceManagementAccess::scope(RoomType::query(), $request->user())->whereKey($room)->lockForUpdate()->firstOrFail();
                abort_if(array_key_exists('partner_id', $data) && $room->partner_id !== null && (int) $room->partner_id !== (int) $data['partner_id'], 422, 'Kepemilikan tempat yang sudah ditetapkan tidak dapat dipindahkan.');
                $current = $this->present($room, $data['date']);
                abort_unless(hash_equals($current['revision'], $data['revision']), 409, 'Kamar, tarif, atau stok berubah. Muat ulang sebelum menyimpan.');
                $this->applyPhotos($request, $room, $storedPaths);
                $room->fill(collect($data)->only(['partner_id', 'name', 'description', 'capacity', 'is_active', 'exterior_image_url', 'interior_image_url', 'location', 'latitude', 'longitude', 'location_is_demo', 'photos_are_illustrations'])->all());
                $room->save();
                $room->inventories()->updateOrCreate(['date' => $data['date']], ['stock' => $data['stock']]);
                $room->rates()->updateOrCreate(['date' => $data['date']], ['price' => $data['price']]);
                AuditLog::create(['user_id' => $request->user()->id, 'action' => 'lodging.room_updated', 'auditable_type' => RoomType::class, 'auditable_id' => $room->id,
                    'metadata' => ['date' => $data['date'], 'previous_stock' => $current['stock'], 'stock' => $data['stock'], 'previous_price' => $current['price'], 'price' => $data['price']]]);

                return $this->present($room, $data['date']);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan penginapan masih tersedia untuk simulasi lokal.');
        $data = $this->validated($request);
        if ($request->isMethod('POST') || $request->exists('partner_id')) {
            $request->validate(['partner_id' => 'nullable|integer|min:1']);
            $data['partner_id'] = ServiceManagementAccess::assignment($request->user(), $request->input('partner_id'));
        }
        $key = validator(['key' => $request->header('Idempotency-Key')], ['key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/']])->validate()['key'];
        $creationKey = hash('sha256', $request->user()->id.':'.$key);
        $fingerprintData = collect($data)->only(['partner_id', 'name', 'description', 'capacity', 'is_active', 'exterior_image_url', 'interior_image_url', 'location', 'latitude', 'longitude', 'location_is_demo', 'photos_are_illustrations', 'date', 'price', 'stock'])->all();
        foreach (['location', 'latitude', 'longitude'] as $field) {
            $fingerprintData[$field] = $data[$field] ?? null;
        }
        foreach (['latitude', 'longitude'] as $field) {
            $fingerprintData[$field] = $fingerprintData[$field] === null ? null : number_format((float) $fingerprintData[$field], 7, '.', '');
        }
        foreach (['capacity', 'price', 'stock'] as $field) {
            $fingerprintData[$field] = (int) $fingerprintData[$field];
        }
        foreach (['is_active', 'location_is_demo', 'photos_are_illustrations'] as $field) {
            $fingerprintData[$field] = $request->boolean($field);
        }
        foreach (['exterior', 'interior'] as $kind) {
            $fingerprintData[$kind.'_photo'] = $request->hasFile($kind.'_photo') ? hash_file('sha256', $request->file($kind.'_photo')->getRealPath()) : null;
        }
        $fingerprint = hash('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR));
        $storedPaths = [];
        $created = false;
        try {
            $result = DB::transaction(function () use ($request, $data, $creationKey, $fingerprint, &$storedPaths, &$created): array {
                User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $room = ServiceManagementAccess::scope(RoomType::query(), $request->user())->where('creation_key', $creationKey)->lockForUpdate()->first();
                if ($room) {
                    abort_unless(hash_equals($room->creation_fingerprint, $fingerprint), 409, 'Permintaan pembuatan kamar sudah digunakan untuk data berbeda.');

                    return $this->present($room, $data['date']);
                }
                $room = new RoomType(collect($data)->only(['partner_id', 'name', 'description', 'capacity', 'is_active', 'exterior_image_url', 'interior_image_url', 'location', 'latitude', 'longitude', 'location_is_demo', 'photos_are_illustrations'])->all());
                $room->creation_key = $creationKey;
                $room->creation_fingerprint = $fingerprint;
                $room->save();
                $this->applyPhotos($request, $room, $storedPaths);
                $room->save();
                $room->inventories()->create(['date' => $data['date'], 'stock' => $data['stock']]);
                $room->rates()->create(['date' => $data['date'], 'price' => $data['price']]);
                AuditLog::create(['user_id' => $request->user()->id, 'action' => 'lodging.room_created', 'auditable_type' => RoomType::class, 'auditable_id' => $room->id,
                    'metadata' => ['date' => $data['date'], 'stock' => $data['stock'], 'price' => $data['price']]]);
                $created = true;

                return $this->present($room, $data['date']);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json(['data' => $result], $created ? 201 : 200)->header('Cache-Control', 'private, no-store');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|min:2|max:255', 'description' => 'required|string|min:10|max:5000',
            'capacity' => 'required|integer|min:1|max:100', 'is_active' => 'required|boolean',
            'exterior_image_url' => ['present', 'nullable', 'string', 'max:1000', 'regex:~^https://images\.unsplash\.com/photo-[A-Za-z0-9_-]+(?:\?[^\s#]*)?$~D'],
            'interior_image_url' => ['present', 'nullable', 'string', 'max:1000', 'regex:~^https://images\.unsplash\.com/photo-[A-Za-z0-9_-]+(?:\?[^\s#]*)?$~D'],
            'location' => 'sometimes|nullable|string|max:500',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180|required_with:latitude',
            'location_is_demo' => 'sometimes|boolean',
            'photos_are_illustrations' => 'required|boolean',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addYear()->toDateString(),
            'price' => 'required|integer|min:1|max:1000000000', 'stock' => 'required|integer|min:0|max:1000000',
            'exterior_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:5120',
            'interior_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:5120',
            'remove_exterior_photo' => 'sometimes|boolean', 'remove_interior_photo' => 'sometimes|boolean',
        ]);
    }

    private function applyPhotos(Request $request, RoomType $room, array &$storedPaths): void
    {
        foreach (['exterior', 'interior'] as $kind) {
            if ($request->hasFile($kind.'_photo')) {
                $path = $request->file($kind.'_photo')->store('lodging/'.$room->id, 'local');
                abort_if($path === false, 500, 'Foto tidak dapat disimpan.');
                $storedPaths[] = $path;
                $room->setAttribute($kind.'_photo_path', $path);
            } elseif ($request->boolean('remove_'.$kind.'_photo')) {
                $room->setAttribute($kind.'_photo_path', null);
            }
        }
    }

    private function present(RoomType $room, string $date): array
    {
        $inventory = $room->inventories()->where('date', $date)->lockForUpdate()->first();
        $rate = $room->rates()->where('date', $date)->lockForUpdate()->first();
        $data = [...$room->only(['id', 'partner_id', 'name', 'description', 'capacity', 'is_active', 'exterior_image_url', 'interior_image_url', 'location', 'latitude', 'longitude', 'location_is_demo', 'photos_are_illustrations']),
            'date' => $date, 'stock' => $inventory?->stock, 'price' => $rate?->price];

        return [...$data, 'exterior_photo_url' => $room->photoPath('exterior') ? '/api/v1/lodging/rooms/'.$room->id.'/photos/exterior?v='.hash('sha256', $room->exterior_photo_path) : null,
            'interior_photo_url' => $room->photoPath('interior') ? '/api/v1/lodging/rooms/'.$room->id.'/photos/interior?v='.hash('sha256', $room->interior_photo_path) : null,
            'revision' => hash('sha256', json_encode([...$data, 'exterior_photo_path' => $room->exterior_photo_path, 'interior_photo_path' => $room->interior_photo_path], JSON_THROW_ON_ERROR))];
    }
}
