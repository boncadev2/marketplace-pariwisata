<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_articles_list_returns_only_published_articles(): void
    {
        $published = Article::factory()->published()->create([
            'title' => 'Panduan Pantai Pasir Putih',
            'category' => 'Panduan Wisata',
        ]);
        $draft = Article::factory()->draft()->create([
            'title' => 'Draf Rahasia Wisata',
        ]);

        $response = $this->getJson('/api/v1/articles');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $published->slug)
            ->assertJsonPath('data.0.title', $published->title)
            ->assertDontSee($draft->title);
    }

    public function test_public_articles_can_be_filtered_by_category_and_search(): void
    {
        $art1 = Article::factory()->published()->create([
            'title' => 'Kuliner Nusantara Mantap',
            'category' => 'Kuliner Lokal',
        ]);
        $art2 = Article::factory()->published()->create([
            'title' => 'Tips Liburan Hemat Desa',
            'category' => 'Tips Liburan',
        ]);

        $resCategory = $this->getJson('/api/v1/articles?category=Kuliner+Lokal');
        $resCategory->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $art1->slug);

        $resSearch = $this->getJson('/api/v1/articles?search=Hemat');
        $resSearch->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $art2->slug);
    }

    public function test_public_article_detail_returns_data_or_404_if_draft(): void
    {
        $published = Article::factory()->published()->create([
            'slug' => 'artikel-terbit-publik',
            'body' => 'Konten lengkap artikel terbit.',
        ]);
        $draft = Article::factory()->draft()->create([
            'slug' => 'artikel-draf-tersembunyi',
        ]);

        $this->getJson('/api/v1/articles/'.$published->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $published->slug)
            ->assertJsonPath('data.body', 'Konten lengkap artikel terbit.');

        $this->getJson('/api/v1/articles/'.$draft->slug)
            ->assertNotFound();
    }

    public function test_admin_article_endpoints_require_platform_admin(): void
    {
        $customer = User::factory()->create(['platform_role' => 'customer']);
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        $this->getJson('/api/v1/dashboard/articles')->assertUnauthorized();
        $this->actingAs($customer)->getJson('/api/v1/dashboard/articles')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/dashboard/articles')->assertOk();
    }

    public function test_admin_can_create_update_and_delete_article(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        // Create
        $payload = [
            'title' => 'Pesona Air Terjun Tersembunyi',
            'slug' => 'pesona-air-terjun-tersembunyi',
            'category' => 'Panduan Wisata',
            'excerpt' => 'Ringkasan artikel air terjun.',
            'body' => 'Deskripsi panjang keindahan air terjun.',
            'status' => 'published',
            'meta_title' => 'Air Terjun Tersembunyi',
            'meta_description' => 'Eksplorasi air terjun perawan di daerah.',
        ];

        $resCreate = $this->actingAs($admin)->postJson('/api/v1/dashboard/articles', $payload);
        $resCreate->assertCreated()
            ->assertJsonPath('data.slug', 'pesona-air-terjun-tersembunyi');

        $articleId = $resCreate->json('data.id');
        $this->assertDatabaseHas('articles', ['id' => $articleId, 'slug' => 'pesona-air-terjun-tersembunyi']);

        // Update
        $updatePayload = [
            'title' => 'Pesona Air Terjun Tersembunyi (Updated)',
            'status' => 'draft',
        ];
        $this->actingAs($admin)->putJson("/api/v1/dashboard/articles/{$articleId}", $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.title', 'Pesona Air Terjun Tersembunyi (Updated)')
            ->assertJsonPath('data.status', 'draft');

        // Delete
        $this->actingAs($admin)->deleteJson("/api/v1/dashboard/articles/{$articleId}")
            ->assertOk();

        $this->assertSoftDeleted('articles', ['id' => $articleId]);
    }
}
