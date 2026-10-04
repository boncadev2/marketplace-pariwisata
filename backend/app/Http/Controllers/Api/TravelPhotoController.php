<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Destination;
use App\Models\Product;
use App\Models\TravelPhoto;
use App\Support\ServiceManagementAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TravelPhotoController extends Controller
{
    public function index(Request $request, string $kind, int $id): JsonResponse
    {
        $item = ServiceManagementAccess::scope($this->query($kind), $request->user())->findOrFail($id);

        return $this->present($item);
    }

    public function store(Request $request, string $kind, int $id): JsonResponse
    {
        $data = $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:5120', 'caption' => 'required|string|max:160', 'is_illustration' => 'required|boolean', 'revision' => 'required|string|size:64']);
        $path = null;
        try {
            $item = DB::transaction(function () use ($request, $kind, $id, $data, &$path) {
                $item = ServiceManagementAccess::scope($this->query($kind), $request->user())->lockForUpdate()->findOrFail($id);
                abort_unless(hash_equals($this->revision($item), $data['revision']), 409, 'Galeri berubah. Muat ulang sebelum mengunggah.');
                abort_if($item->photos()->count() >= 12, 422, 'Maksimal 12 foto per galeri.');
                $path = $request->file('photo')->store('travel/'.$kind.'/'.$id, 'local');
                abort_unless(is_string($path), 500, 'Foto tidak dapat disimpan.');
                $item->photos()->create(['path' => $path, 'caption' => $data['caption'], 'is_illustration' => $data['is_illustration']]);
                $item->touch();
                AuditLog::create(['user_id' => $request->user()->id, 'action' => 'travel.photo_uploaded', 'auditable_type' => $item::class, 'auditable_id' => $id, 'metadata' => ['kind' => $kind]]);

                return $item;
            }, 3);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return $this->present($item);
    }

    public function destroy(Request $request, string $kind, int $id, int $photo): JsonResponse
    {
        $data = $request->validate(['revision' => 'required|string|size:64']);
        $item = DB::transaction(function () use ($request, $kind, $id, $photo, $data) {
            $item = ServiceManagementAccess::scope($this->query($kind), $request->user())->lockForUpdate()->findOrFail($id);
            abort_unless(hash_equals($this->revision($item), $data['revision']), 409, 'Galeri berubah. Muat ulang sebelum menghapus.');
            $item->photos()->findOrFail($photo)->delete();
            $item->touch();
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'travel.photo_removed', 'auditable_type' => $item::class, 'auditable_id' => $id, 'metadata' => ['photo_id' => $photo]]);

            return $item;
        }, 3);

        return $this->present($item);
    }

    public function show(TravelPhoto $photo): StreamedResponse
    {
        $item = $photo->destination ?? $photo->product;
        abort_unless($item && ($item instanceof Destination ? $item->publication_status === 'published' : ($item->status === 'published' && $item->tourPackage?->status === 'published')), 404);
        $path = $photo->safePath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function query(string $kind): Builder
    {
        return $kind === 'destinations' ? Destination::query() : Product::query()->where('type', 'package');
    }

    private function revision(Destination|Product $item): string
    {
        return hash('sha256', $item->photos()->orderBy('id')->get()->toJson());
    }

    private function present(Destination|Product $item): JsonResponse
    {
        return response()->json(['data' => $item->photos()->orderBy('id')->get(), 'revision' => $this->revision($item)])->header('Cache-Control', 'private, no-store');
    }
}
