<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\PartnerMember;
use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UmkmProductPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function payload(UmkmProduct $product, ?int $photoId, string $revision): array
    {
        return [...$product->only(['name', 'description', 'location', 'price', 'unit', 'stock', 'status']), 'photo_media_id' => $photoId, 'revision' => $revision];
    }

    private function owner(UmkmProduct $product): User
    {
        $user = User::factory()->create();
        PartnerMember::create(['partner_id' => $product->partner_id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true]);

        return $user;
    }

    public function test_photo_upload_attach_publish_and_detach_preserves_file_and_inventory(): void
    {
        Storage::fake('public');
        $product = UmkmProduct::factory()->create(['status' => 'draft', 'stock' => 10]);
        $this->actingAs($this->owner($product));
        $upload = $this->postJson('/api/v1/media', ['partner_id' => $product->partner_id, 'file' => UploadedFile::fake()->createWithContent('product.png', file_get_contents(__DIR__.'/../Fixtures/umkm-photo.png')), 'alt_text' => 'Foto produk demo'])->assertCreated();
        $mediaId = $upload->json('data.id');
        $media = Media::findOrFail($mediaId);
        Storage::disk('public')->assertExists($media->path);
        $revision = $this->getJson('/api/v1/dashboard/umkm-products')->json('data.0.revision');
        $attached = $this->patchJson('/api/v1/dashboard/umkm-products/'.$product->slug, $this->payload($product, $mediaId, $revision))->assertOk()->assertJsonPath('data.photo_media_id', $mediaId);
        $this->get('/api/v1/umkm-products/'.$product->slug.'/photo')->assertNotFound();
        $this->get('/api/v1/dashboard/umkm-products/'.$product->slug.'/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $public = $this->patchJson('/api/v1/dashboard/umkm-products/'.$product->slug, [...$this->payload($product, $mediaId, $attached->json('data.revision')), 'status' => 'published'])->assertOk();
        $this->getJson('/api/v1/umkm-products/'.$product->slug)->assertOk()->assertJsonPath('data.photo_url', '/api/v1/umkm-products/'.$product->slug.'/photo')->assertJsonPath('data.photo_alt', 'Foto produk demo');
        $this->get('/api/v1/umkm-products/'.$product->slug.'/photo')->assertOk();
        $this->patchJson('/api/v1/dashboard/umkm-products/'.$product->slug, $this->payload($product->fresh(), null, $public->json('data.revision')))->assertOk()->assertJsonPath('data.photo_url', null);
        $this->get('/api/v1/umkm-products/'.$product->slug.'/photo')->assertNotFound();
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_other_partner_private_missing_and_unsafe_media_cannot_be_attached(): void
    {
        Storage::fake('public');
        $product = UmkmProduct::factory()->create();
        $other = UmkmProduct::factory()->create();
        $this->actingAs($this->owner($product));
        $revision = $this->getJson('/api/v1/dashboard/umkm-products')->json('data.0.revision');
        Storage::disk('public')->put('media/test.jpg', 'image-placeholder');
        foreach ([['partner_id' => $other->partner_id, 'path' => 'media/test.jpg', 'is_public' => true], ['partner_id' => $product->partner_id, 'path' => 'media/test.jpg', 'is_public' => false], ['partner_id' => $product->partner_id, 'path' => 'media/missing.jpg', 'is_public' => true], ['partner_id' => $product->partner_id, 'path' => '../private/secret.jpg', 'is_public' => true]] as $attributes) {
            $media = Media::create([...$attributes, 'disk' => 'public']);
            $this->patchJson('/api/v1/dashboard/umkm-products/'.$product->slug, $this->payload($product, $media->id, $revision))->assertUnprocessable();
        }
        $this->assertNull($product->fresh()->photo_media_id);
    }

    public function test_upload_rejects_svg_nonimage_and_oversized_and_other_partner(): void
    {
        Storage::fake('public');
        $product = UmkmProduct::factory()->create();
        $other = UmkmProduct::factory()->create();
        $this->actingAs($this->owner($product));
        foreach ([UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'), UploadedFile::fake()->create('bad.jpg', 1, 'text/plain'), UploadedFile::fake()->createWithContent('big.png', file_get_contents(__DIR__.'/../Fixtures/umkm-photo.png'))->size(5121)] as $file) {
            $this->postJson('/api/v1/media', ['partner_id' => $product->partner_id, 'file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->postJson('/api/v1/media', ['partner_id' => $other->partner_id, 'file' => UploadedFile::fake()->createWithContent('valid.png', file_get_contents(__DIR__.'/../Fixtures/umkm-photo.png'))])->assertNotFound();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_draft_photo_preview_is_scoped_and_missing_file_returns_404(): void
    {
        Storage::fake('public');
        $product = UmkmProduct::factory()->create(['status' => 'draft']);
        $media = Media::create(['partner_id' => $product->partner_id, 'disk' => 'public', 'path' => 'media/test.png', 'is_public' => true]);
        $product->update(['photo_media_id' => $media->id]);
        Storage::disk('public')->put($media->path, 'image-placeholder');
        $this->get('/api/v1/dashboard/umkm-products/'.$product->slug.'/photo', ['Accept' => 'application/json'])->assertUnauthorized();
        $other = UmkmProduct::factory()->create();
        $this->actingAs($this->owner($other))->get('/api/v1/dashboard/umkm-products/'.$product->slug.'/photo')->assertNotFound();
        $this->actingAs($this->owner($product))->get('/api/v1/dashboard/umkm-products/'.$product->slug.'/photo')->assertOk();
        Storage::disk('public')->delete($media->path);
        $this->get('/api/v1/dashboard/umkm-products/'.$product->slug.'/photo')->assertNotFound();
    }
}
