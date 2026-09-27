<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    public function priceRules(): HasMany
    {
        return $this->hasMany(ProductPriceRule::class);
    }

    public function tourPackage(): HasOne
    {
        return $this->hasOne(TourPackage::class);
    }
}
