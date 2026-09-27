<?php

namespace App\Models;

use Database\Factories\PartnerMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerMember extends Model
{
    /** @use HasFactory<PartnerMemberFactory> */
    use HasFactory;

    protected $guarded = [];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
