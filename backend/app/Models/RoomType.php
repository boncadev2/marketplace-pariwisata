<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = ['partner_id', 'name', 'description', 'capacity', 'is_active', 'exterior_image_url', 'interior_image_url', 'photos_are_illustrations', 'location', 'latitude', 'longitude', 'location_is_demo'];

    protected $hidden = ['exterior_photo_path', 'interior_photo_path', 'creation_key', 'creation_fingerprint'];

    public function photoPath(string $kind): ?string
    {
        $path = $this->getAttribute($kind.'_photo_path');

        return is_string($path) && preg_match('~^lodging/'.$this->id.'/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$~D', $path) ? $path : null;
    }

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean', 'photos_are_illustrations' => 'boolean', 'location_is_demo' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(RoomInventory::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }
}
