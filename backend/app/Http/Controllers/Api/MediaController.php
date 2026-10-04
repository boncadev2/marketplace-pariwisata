<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        if ($user->platform_role !== 'super_admin') {
            $authorized = $user->partnerMemberships()
                ->where('partner_id', $data['partner_id'])
                ->where('is_active', true)
                ->whereIn('role', ['owner', 'manager'])
                ->exists();
            abort_unless($authorized, 404);
        }

        $path = $data['file']->store('media', 'public');
        abort_if($path === false, 500, 'Media tidak dapat disimpan.');
        $media = Media::create([
            'partner_id' => $data['partner_id'],
            'disk' => 'public',
            'path' => $path,
            'alt_text' => $data['alt_text'] ?? null,
            'is_public' => true,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'partner_id' => $data['partner_id'],
            'action' => 'media.uploaded',
            'auditable_type' => Media::class,
            'auditable_id' => $media->id,
            'metadata' => ['mime_type' => $data['file']->getMimeType(), 'size_bytes' => $data['file']->getSize()],
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);

        return response()->json(['data' => [
            'id' => $media->id,
            'partner_id' => $media->partner_id,
            'url' => Storage::disk('public')->url($media->path),
            'alt_text' => $media->alt_text,
        ]], 201);
    }
}
