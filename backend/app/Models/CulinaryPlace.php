<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CulinaryPlace extends Model
{
    use HasFactory;

    protected $hidden = ['creation_key', 'creation_fingerprint', 'photo_path'];

    protected $fillable = ['partner_id', 'name', 'description', 'location', 'is_active', 'latitude', 'longitude', 'location_is_demo', 'image_url', 'photos_are_illustrations'];

    public function photoPath(): ?string
    {
        $path = $this->photo_path;

        return is_string($path) && preg_match('~^culinary/'.$this->id.'/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$~D', $path) ? $path : null;
    }

    public function photoUrl(): ?string
    {
        return $this->photoPath() ? '/api/v1/culinary/places/'.$this->id.'/photo?v='.hash('sha256', $this->photo_path) : null;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'location_is_demo' => 'boolean', 'photos_are_illustrations' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function mealSlots(): HasMany
    {
        return $this->hasMany(MealSlot::class);
    }
}
