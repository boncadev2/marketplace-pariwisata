<?php

namespace App\Models;

use Database\Factories\PilotControlFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PilotControl extends Model
{
    /** @use HasFactory<PilotControlFactory> */
    use HasFactory;

    protected $fillable = ['checkout_enabled', 'reason', 'changed_by'];

    protected function casts(): array
    {
        return ['checkout_enabled' => 'boolean'];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array{enabled: bool, reason: ?string, changed_at: ?string} */
    public static function checkoutStatus(): array
    {
        $control = self::query()->find(1);

        return [
            'enabled' => $control?->checkout_enabled ?? (bool) config('pilot.checkout_enabled_default'),
            'reason' => $control?->reason,
            'changed_at' => $control?->updated_at?->toIso8601String(),
        ];
    }
}
