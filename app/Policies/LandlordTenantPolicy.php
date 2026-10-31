<?php

namespace App\Policies;

use App\Models\LandlordTenant;
use App\Models\User;
use App\Support\TenantInvites;

/** A row on a landlord's tenant list belongs to that landlord only. */
class LandlordTenantPolicy
{
    public function view(User $user, LandlordTenant $row): bool
    {
        return $row->landlord_id === $user->user_id;
    }

    /**
     * Only a tenant this landlord added by hand and who has not set up their own login. Someone with their own
     * account owns their name, email and number.
     */
    public function update(User $user, LandlordTenant $row): bool
    {
        $tenant = $row->tenant;

        return $this->view($user, $row)
            && $tenant
            && $tenant->is_walk_in
            && $tenant->created_by_landlord_id === $user->user_id;
    }

    public function invite(User $user, LandlordTenant $row): bool
    {
        return $this->update($user, $row) && TenantInvites::canInvite($row->tenant);
    }

    /** Whether removal is allowed right now (no live contract) is decided in the controller under a lock. */
    public function delete(User $user, LandlordTenant $row): bool
    {
        return $this->view($user, $row);
    }
}
