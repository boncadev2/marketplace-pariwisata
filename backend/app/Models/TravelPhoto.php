<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelPhoto extends Model
{
    protected $guarded = [];

    protected $visible = ['id', 'caption', 'is_illustration', 'url'];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return ['is_illustration' => 'boolean'];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return route('travel.photos.show', ['photo' => $this->id], false);
    }

    public function safePath(): ?string
    {
        $directory = $this->destination_id ? 'destinations/'.$this->destination_id : 'packages/'.$this->product_id;

        return is_string($this->path) && preg_match('~^travel/'.$directory.'/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$~D', $this->path) ? $this->path : null;
    }
}
