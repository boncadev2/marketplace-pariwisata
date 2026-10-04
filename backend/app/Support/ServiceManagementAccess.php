<?php

namespace App\Support;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ServiceManagementAccess
{
    public static function partnerIds(?User $user): array
    {
        if (! $user || ! $user->hasVerifiedEmail()) {
            return [];
        }

        return $user->partnerMemberships()->where('is_active', true)->whereIn('role', ['owner', 'manager'])->whereHas('partner', fn (Builder $query) => $query->where('status', 'approved'))->pluck('partner_id')->all();
    }

    public static function admin(?User $user): bool
    {
        return $user && $user->hasVerifiedEmail() && $user->platform_role === 'super_admin';
    }

    public static function scope(Builder $query, ?User $user): Builder
    {
        return self::admin($user) ? $query : $query->whereIn('partner_id', self::partnerIds($user));
    }

    public static function canManage(?User $user, ?int $partnerId): bool
    {
        return self::admin($user) || ($partnerId !== null && in_array($partnerId, self::partnerIds($user)));
    }

    public static function partners(User $user): array
    {
        return self::scopePartners($user)->get(['id', 'name'])->toArray();
    }

    private static function scopePartners(User $user): Builder
    {
        $query = Partner::query()->where('status', 'approved');

        return self::admin($user) ? $query : $query->whereIn('id', self::partnerIds($user));
    }

    public static function assignment(User $user, mixed $partnerId): ?int
    {
        if ($partnerId === null || $partnerId === '') {
            abort_unless(self::admin($user), 422, 'Pilih mitra pengelola tempat.');

            return null;
        }

        return (int) self::scopePartners($user)->whereKey($partnerId)->firstOrFail()->id;
    }
}
