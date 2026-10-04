<?php

namespace App\Models;

use Database\Factories\UmkmProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UmkmProduct extends Model
{
    /** @use HasFactory<UmkmProductFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weight_grams' => 'integer', 'stock' => 'integer', 'price' => 'integer', 'is_demo' => 'boolean', 'delivery_available' => 'boolean', 'shipping_fee' => 'integer'];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    public function photoUrl(bool $managed = false): ?string
    {
        $photo = $this->photo;
        if (! $photo || ! $photo->is_public || $photo->disk !== 'public' || $photo->partner_id !== $this->partner_id || ! preg_match('/^media\/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$/D', $photo->path)) {
            return null;
        }

        return '/api/v1/'.($managed ? 'dashboard/' : '').'umkm-products/'.$this->slug.'/photo';
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
