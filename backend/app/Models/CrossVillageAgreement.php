<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrossVillageAgreement extends Model
{
    protected $fillable = ['tour_package_id', 'partner_id', 'revision', 'configuration_version', 'decision', 'decided_by', 'reason', 'decided_at'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'decided_at' => 'immutable_datetime'];
    }
}
