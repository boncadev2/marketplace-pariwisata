<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppSettingController extends Controller
{
    /**
     * Public settings viewable by all users and guests.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $settings = AppSetting::getAll();
        $presets = AppSetting::themePresets();

        return response()->json([
            'data' => $settings,
            'presets' => $presets,
        ]);
    }

    /**
     * Admin settings viewable by platform admin.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json([
            'data' => AppSetting::getAll(),
            'defaults' => AppSetting::defaults(),
            'presets' => AppSetting::themePresets(),
        ]);
    }

    /**
     * Upload an asset image (hero, logo, or favicon) for app settings.
     */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg,ico', 'max:5120'],
            'type' => ['required', 'string', 'in:hero,logo,favicon'],
        ]);

        $folder = 'settings/'.$data['type'];
        $path = $data['file']->store($folder, 'public');
        abort_if($path === false, 500, 'Gagal menyimpan file.');

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'message' => 'File berhasil diunggah.',
            'url' => $url,
            'path' => $path,
            'type' => $data['type'],
        ]);
    }

    /**
     * Update application settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'app_tagline' => ['nullable', 'string', 'max:255'],
            'app_logo_url' => ['nullable', 'string', 'max:2048'],
            'app_favicon_url' => ['nullable', 'string', 'max:2048'],
            'theme' => ['required', 'string', 'in:ocean,emerald,sunset,forest,purple,teal,custom'],
            'primary_color' => ['nullable', 'string', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'hero_title' => ['required', 'string', 'max:500'],
            'hero_description' => ['nullable', 'string', 'max:1000'],
            'hero_image_url' => ['nullable', 'string', 'max:2048'],
            'hero_kicker' => ['nullable', 'string', 'max:100'],
            'hero_badge_title' => ['nullable', 'string', 'max:150'],
            'hero_badge_subtitle' => ['nullable', 'string', 'max:150'],
            'hero_cta_text' => ['nullable', 'string', 'max:100'],
            'hero_cta_url' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_hours' => ['nullable', 'string', 'max:255'],
            'emergency_phone' => ['nullable', 'string', 'max:50'],
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'social_tiktok' => ['nullable', 'string', 'max:255'],
            'social_youtube' => ['nullable', 'string', 'max:255'],
            'social_twitter' => ['nullable', 'string', 'max:255'],
            'social_whatsapp' => ['nullable', 'string', 'max:255'],
        ]);

        // If theme is not custom and primary_color/accent_color are empty, apply preset colors
        $presets = AppSetting::themePresets();
        if ($validated['theme'] !== 'custom' && isset($presets[$validated['theme']])) {
            $preset = $presets[$validated['theme']];
            if (empty($validated['primary_color'])) {
                $validated['primary_color'] = $preset['primary_color'];
            }
            if (empty($validated['accent_color'])) {
                $validated['accent_color'] = $preset['accent_color'];
            }
        }

        AppSetting::saveMany($validated);

        return response()->json([
            'message' => 'Pengaturan aplikasi berhasil disimpan.',
            'data' => AppSetting::getAll(),
        ]);
    }

    /**
     * Reset settings to original defaults.
     */
    public function reset(Request $request): JsonResponse
    {
        AppSetting::resetToDefaults();

        return response()->json([
            'message' => 'Pengaturan aplikasi berhasil dikembalikan ke default.',
            'data' => AppSetting::getAll(),
        ]);
    }
}
