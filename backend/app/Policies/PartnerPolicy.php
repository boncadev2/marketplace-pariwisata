<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->platform_role === 'super_admin' || $user->partnerMemberships()->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Partner $partner): bool
    {
        return $this->canAccessPartner($user, $partner);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->platform_role, ['admin_region', 'super_admin'], true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Partner $partner): bool
    {
        return $this->canAccessPartner($user, $partner);
    }

    private function canAccessPartner(User $user, Partner $partner): bool
    {
        return $user->platform_role === 'super_admin' || $user->partnerMemberships()->where('partner_id', $partner->id)->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Partner $partner): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Partner $partner): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Partner $partner): bool
    {
        return false;
    }
}
