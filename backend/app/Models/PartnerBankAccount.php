<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id', 'bank_name', 'account_number', 'account_name',
        'is_verified', 'verified_by', 'verified_at', 'is_active',
    ];

    protected $hidden = ['account_number'];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'account_number' => 'encrypted',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
