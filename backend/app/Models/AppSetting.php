<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'app_settings:all';

    public static function defaults(): array
    {
        return [
            'app_name' => 'WisataDaerah',
            'app_tagline' => 'Jelajahi Keindahan & Pengalaman Lokal',
            'theme' => 'ocean',
            'primary_color' => '#0870ce',
            'accent_color' => '#ef7f1a',
            'hero_title' => "Liburan dekat.\nCerita hebat.",
            'hero_description' => 'Temukan tempat baru, nikmati pengalaman lokal, dan buat perjalanan Anda lebih berarti.',
            'hero_image_url' => 'https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=1800&q=90',
            'hero_kicker' => 'SAATNYA JELAJAHI DAERAH',
            'hero_badge_title' => 'Keindahan ada di sekitar kita',
            'hero_badge_subtitle' => 'Indonesia, penuh cerita.',
            'hero_cta_text' => 'Mulai petualangan',
            'hero_cta_url' => '/destinasi',
            'app_logo_url' => null,
            'app_favicon_url' => null,
            'address' => 'Jl. Malioboro No. 56, Sosromenduran, Gedong Tengen, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55271',
            'contact_email' => 'kontak@wisatadaerah.id',
            'contact_phone' => '+62 812-3456-7890',
            'contact_hours' => 'Senin – Minggu: 08:00 – 21:00 WIB',
            'emergency_phone' => '+62 811-9988-7766',
            'social_instagram' => 'https://instagram.com/wisatadaerah',
            'social_facebook' => 'https://facebook.com/wisatadaerah',
            'social_tiktok' => 'https://tiktok.com/@wisatadaerah',
            'social_youtube' => 'https://youtube.com/@wisatadaerah',
            'social_twitter' => 'https://x.com/wisatadaerah',
            'social_whatsapp' => 'https://wa.me/6281234567890',
        ];
    }

    public static function themePresets(): array
    {
        return [
            'ocean' => [
                'name' => 'Biru Bahari (Default)',
                'primary_color' => '#0870ce',
                'accent_color' => '#ef7f1a',
            ],
            'emerald' => [
                'name' => 'Hijau Alam',
                'primary_color' => '#059669',
                'accent_color' => '#d97706',
            ],
            'sunset' => [
                'name' => 'Jingga Senja',
                'primary_color' => '#e11d48',
                'accent_color' => '#f59e0b',
            ],
            'forest' => [
                'name' => 'Hutan Pinus',
                'primary_color' => '#15803d',
                'accent_color' => '#ca8a04',
            ],
            'purple' => [
                'name' => 'Pesona Ungu',
                'primary_color' => '#7c3aed',
                'accent_color' => '#f43f5e',
            ],
            'teal' => [
                'name' => 'Pesisir Pantai',
                'primary_color' => '#0f766e',
                'accent_color' => '#f97316',
            ],
            'custom' => [
                'name' => 'Kustom Mandiri',
                'primary_color' => '#0870ce',
                'accent_color' => '#ef7f1a',
            ],
        ];
    }

    /**
     * Get all settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $saved = self::query()->pluck('value', 'key')->all();
            $merged = self::defaults();

            foreach ($saved as $key => $val) {
                if ($val !== null && $val !== '') {
                    $merged[$key] = $val;
                }
            }

            return $merged;
        });
    }

    /**
     * Get single setting value with fallback.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        $all = self::getAll();

        return $all[$key] ?? $default ?? (self::defaults()[$key] ?? null);
    }

    /**
     * Save or update multiple settings and clear cache.
     *
     * @param array<string, mixed> $settings
     */
    public static function saveMany(array $settings): void
    {
        $allowedKeys = array_keys(self::defaults());

        foreach ($settings as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                continue;
            }

            self::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_string($value) ? trim($value) : $value]
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Reset all settings to defaults.
     */
    public static function resetToDefaults(): void
    {
        self::query()->truncate();
        Cache::forget(self::CACHE_KEY);
    }
}
