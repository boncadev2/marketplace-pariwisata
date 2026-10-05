<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_public_can_read_application_settings(): void
    {
        $response = $this->getJson('/api/v1/lookup/settings');

        $response->assertOk()
            ->assertJsonPath('data.app_name', 'WisataDaerah')
            ->assertJsonPath('data.theme', 'ocean')
            ->assertJsonStructure([
                'data' => [
                    'app_name',
                    'theme',
                    'primary_color',
                    'accent_color',
                    'hero_title',
                    'hero_description',
                    'hero_image_url',
                    'hero_cta_text',
                    'hero_cta_url',
                    'address',
                    'contact_hours',
                    'emergency_phone',
                    'social_instagram',
                ],
                'presets',
            ]);
    }

    public function test_unauthenticated_or_non_admin_cannot_access_or_update_settings(): void
    {
        $this->getJson('/api/v1/dashboard/settings')->assertUnauthorized();
        $this->putJson('/api/v1/dashboard/settings', ['app_name' => 'Hacker'])->assertUnauthorized();

        $user = User::factory()->create(['platform_role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($user)->getJson('/api/v1/dashboard/settings')->assertForbidden();
        $this->actingAs($user)->putJson('/api/v1/dashboard/settings', ['app_name' => 'Hacker'])->assertForbidden();
    }

    public function test_admin_can_update_settings_and_read_them(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        $payload = [
            'app_name' => 'PesonaNusantara',
            'app_tagline' => 'Portal Wisata Terbaik',
            'app_logo_url' => 'https://example.com/logo.png',
            'app_favicon_url' => 'https://example.com/favicon.ico',
            'theme' => 'emerald',
            'primary_color' => '#059669',
            'accent_color' => '#d97706',
            'hero_title' => "Eksplor Alam Asri.\nKeindahan Tiada Tara.",
            'hero_description' => 'Temukan paket wisata dan UMKM khas daerah langsung dari pengelola lokal.',
            'hero_image_url' => 'https://example.com/custom-hero.jpg',
            'hero_kicker' => 'PESONA WISATA INDONESIA',
            'hero_badge_title' => 'Destinasi Terverifikasi',
            'hero_badge_subtitle' => 'Nyaman dan Terpercaya',
            'hero_cta_text' => 'Lihat Semua Paket',
            'hero_cta_url' => '/paket',
            'address' => 'Jl. Sudirman Kav. 21, Jakarta Pusat',
            'contact_email' => 'halo@pesonanusantara.id',
            'contact_phone' => '+62 811-2233-4455',
            'contact_hours' => 'Senin – Sabtu: 09:00 – 18:00 WIB',
            'emergency_phone' => '112 / +62 811-0000-1111',
            'social_instagram' => 'https://instagram.com/pesonanusantara',
            'social_facebook' => 'https://facebook.com/pesonanusantara',
            'social_tiktok' => 'https://tiktok.com/@pesonanusantara',
            'social_youtube' => 'https://youtube.com/@pesonanusantara',
            'social_twitter' => 'https://x.com/pesonanusantara',
            'social_whatsapp' => 'https://wa.me/6281122334455',
        ];

        $response = $this->actingAs($admin)->putJson('/api/v1/dashboard/settings', $payload);

        $response->assertOk()
            ->assertJsonPath('data.app_name', 'PesonaNusantara')
            ->assertJsonPath('data.theme', 'emerald')
            ->assertJsonPath('data.primary_color', '#059669')
            ->assertJsonPath('data.hero_title', "Eksplor Alam Asri.\nKeindahan Tiada Tara.")
            ->assertJsonPath('data.hero_cta_text', 'Lihat Semua Paket')
            ->assertJsonPath('data.hero_cta_url', '/paket')
            ->assertJsonPath('data.contact_hours', 'Senin – Sabtu: 09:00 – 18:00 WIB')
            ->assertJsonPath('data.address', 'Jl. Sudirman Kav. 21, Jakarta Pusat');

        // Check public endpoint reflects changes
        $public = $this->getJson('/api/v1/lookup/settings');
        $public->assertOk()
            ->assertJsonPath('data.app_name', 'PesonaNusantara')
            ->assertJsonPath('data.theme', 'emerald')
            ->assertJsonPath('data.hero_cta_text', 'Lihat Semua Paket');
    }

    public function test_admin_can_upload_setting_assets(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'custom_hero.png',
            file_get_contents(base_path('tests/Fixtures/umkm-photo.png'))
        );

        $response = $this->actingAs($admin)->postJson('/api/v1/dashboard/settings/upload', [
            'file' => $file,
            'type' => 'hero',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'url', 'path', 'type'])
            ->assertJsonPath('type', 'hero');

        $path = $response->json('path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_can_reset_settings_to_defaults(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        AppSetting::saveMany(['app_name' => 'CustomizedApp']);
        $this->assertEquals('CustomizedApp', AppSetting::getValue('app_name'));

        $response = $this->actingAs($admin)->postJson('/api/v1/dashboard/settings/reset');

        $response->assertOk()
            ->assertJsonPath('data.app_name', 'WisataDaerah');

        $this->assertEquals('WisataDaerah', AppSetting::getValue('app_name'));
    }
}
