<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CulinaryPlace;
use App\Support\CommerceMode;
use App\Support\ServiceManagementAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CulinaryPhotoController extends Controller
{
    public function store(Request $request, int $place, CulinaryManagementController $management): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Unggah foto masih tersedia untuk simulasi lokal.');
        $data = $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:5120', 'revision' => 'required|string|size:64', 'photos_are_illustrations' => 'required|boolean']);
        $path = null;
        try {
            $result = DB::transaction(function () use ($request, $place, $management, $data, &$path): array {
                $place = ServiceManagementAccess::scope(CulinaryPlace::query(), $request->user())->lockForUpdate()->findOrFail($place);
                abort_unless(hash_equals($management->present($place)['revision'], $data['revision']), 409, 'Informasi atau foto berubah. Pilih kembali rumah makan untuk memuat data terbaru.');
                $path = $request->file('photo')->store('culinary/'.$place->id, 'local');
                abort_if($path === false, 500, 'Foto tidak dapat disimpan.');
                $place->photo_path = $path;
                $place->photos_are_illustrations = (bool) $data['photos_are_illustrations'];
                $place->save();
                AuditLog::create(['user_id' => $request->user()->id, 'action' => 'culinary.photo_uploaded', 'auditable_type' => CulinaryPlace::class, 'auditable_id' => $place->id, 'metadata' => ['photos_are_illustrations' => $place->photos_are_illustrations]]);

                return $management->present($place);
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }throw $exception;
        }

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $place): StreamedResponse
    {
        $place = CulinaryPlace::query()->findOrFail($place);
        $admin = ServiceManagementAccess::canManage($request->user(), $place->partner_id);
        abort_unless($place->is_active || $admin, 404);
        $path = $place->photoPath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
