<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CulinaryPlace;
use App\Models\User;
use App\Support\CommerceMode;
use App\Support\ServiceManagementAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CulinaryManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => 'nullable|integer|min:1']);
        $places = ServiceManagementAccess::scope(CulinaryPlace::query(), $request->user())->orderBy('name')->orderBy('id')->paginate(12);

        return response()->json(['data' => $places->getCollection()->map(fn (CulinaryPlace $place): array => $this->present($place))->all(), 'meta' => ['page' => $places->currentPage(), 'last_page' => $places->lastPage(), 'partners' => ServiceManagementAccess::partners($request->user()), 'editing_available' => CommerceMode::enabled()]])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $place): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan rumah makan masih tersedia untuk simulasi lokal.');
        $data = $this->validated($request);
        if ($request->isMethod('POST') || $request->exists('partner_id')) {
            $request->validate(['partner_id' => 'nullable|integer|min:1']);
            $data['partner_id'] = ServiceManagementAccess::assignment($request->user(), $request->input('partner_id'));
        }
        $request->validate(['revision' => 'required|string|size:64']);
        $data['revision'] = $request->string('revision')->toString();
        $result = DB::transaction(function () use ($request, $place, $data): array {
            $place = ServiceManagementAccess::scope(CulinaryPlace::query(), $request->user())->whereKey($place)->lockForUpdate()->firstOrFail();
            abort_if(array_key_exists('partner_id', $data) && $place->partner_id !== null && (int) $place->partner_id !== (int) $data['partner_id'], 422, 'Kepemilikan tempat yang sudah ditetapkan tidak dapat dipindahkan.');
            abort_unless(hash_equals($this->present($place)['revision'], $data['revision']), 409, 'Informasi rumah makan berubah. Muat ulang sebelum menyimpan.');
            $place->update(collect($data)->except('revision')->all());
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'culinary.place_updated', 'auditable_type' => CulinaryPlace::class, 'auditable_id' => $place->id, 'metadata' => ['fields' => array_keys(collect($data)->except('revision')->all())]]);

            return $this->present($place);
        }, 3);

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan rumah makan masih tersedia untuk simulasi lokal.');
        $data = $this->validated($request);
        if ($request->isMethod('POST') || $request->exists('partner_id')) {
            $request->validate(['partner_id' => 'nullable|integer|min:1']);
            $data['partner_id'] = ServiceManagementAccess::assignment($request->user(), $request->input('partner_id'));
        }
        $key = validator(['key' => $request->header('Idempotency-Key')], ['key' => 'required|uuid'])->validate()['key'];
        $creationKey = hash('sha256', $request->user()->id.':'.strtolower($key));
        $normalized = $data;
        foreach (['latitude', 'longitude'] as $field) {
            $normalized[$field] = $data[$field] === null ? null : number_format((float) $data[$field], 7, '.', '');
        }
        foreach (['is_active', 'location_is_demo', 'photos_are_illustrations'] as $field) {
            $normalized[$field] = (bool) $data[$field];
        }
        $fingerprint = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
        $place = DB::transaction(function () use ($request, $data, $creationKey, $fingerprint): CulinaryPlace {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $existing = ServiceManagementAccess::scope(CulinaryPlace::query(), $request->user())->where('creation_key', $creationKey)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->creation_fingerprint, $fingerprint), 409, 'Permintaan tambah rumah makan sudah dipakai untuk informasi berbeda.');

                return $existing;
            }
            $place = new CulinaryPlace($data);
            $place->creation_key = $creationKey;
            $place->creation_fingerprint = $fingerprint;
            $place->save();
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'culinary.place_created', 'auditable_type' => CulinaryPlace::class, 'auditable_id' => $place->id, 'metadata' => ['is_active' => $place->is_active]]);

            return $place;
        }, 3);

        return response()->json(['data' => $this->present($place)], $place->wasRecentlyCreated ? 201 : 200)->header('Cache-Control', 'private, no-store');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => 'required|string|min:2|max:255', 'description' => 'required|string|min:10|max:5000', 'location' => 'present|nullable|string|max:255',
            'latitude' => 'present|nullable|numeric|between:-90,90|required_with:longitude', 'longitude' => 'present|nullable|numeric|between:-180,180|required_with:latitude',
            'location_is_demo' => 'required|boolean', 'photos_are_illustrations' => 'required|boolean', 'is_active' => 'required|boolean',
            'image_url' => ['present', 'nullable', 'string', 'max:1000', 'regex:~^https://images\.unsplash\.com/photo-[A-Za-z0-9_-]+(?:\?[^\s#]*)?$~D']]);
    }

    public function present(CulinaryPlace $place): array
    {
        $data = $place->only(['id', 'partner_id', 'name', 'description', 'location', 'latitude', 'longitude', 'location_is_demo', 'image_url', 'photos_are_illustrations', 'is_active']);

        return [...$data, 'uploaded_photo_url' => $place->photoUrl(), 'revision' => hash('sha256', json_encode([...$data, 'photo_path' => $place->photo_path], JSON_THROW_ON_ERROR))];
    }
}
