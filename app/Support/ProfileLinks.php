<?php

namespace App\Support;

use App\Models\LandlordTenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Where a person's name should lead for whoever is looking, or null when there is no page for them to see.
 *
 *   admin    -> anyone                      admin.users.show
 *   landlord -> one of their own tenants    landlord.tenants.profile
 *   anyone   -> a landlord with an open page landlord.profile.show
 *
 * Tenants never see other tenants, and a landlord never opens another landlord from a name, so those stay plain
 * text. Used by <x-person-link>; keep the rules here so every page agrees.
 */
class ProfileLinks
{
    public static function url(?User $subject, ?User $viewer = null): ?string
    {
        return self::resolve($subject, $viewer ?? Auth::user());
    }

    private static function resolve(?User $subject, ?User $viewer): ?string
    {
        if (! $subject || ! $subject->exists || ($viewer && $viewer->user_id === $subject->user_id)) {
            return null;
        }

        // Roles are loaded on purpose (one query each, once): a plain read would trip the lazy-loading guard.
        $viewer?->loadMissing('roles');

        if ($viewer?->hasRole('Admin')) {
            return route('admin.users.show', $subject->user_id);
        }

        if ($viewer?->hasRole('Landlord')) {
            $row = self::tenantRows($viewer)[$subject->user_id] ?? null;

            return $row ? route('landlord.tenants.profile', $row) : null;
        }

        return self::landlordPageIsOpen($subject) ? route('landlord.profile.show', $subject->user_id) : null;
    }

    /** The landlord's tenant list as [tenant user id => landlord_tenant_id], read once per request. */
    private static function tenantRows(User $landlord): array
    {
        $bag = request()->attributes;
        $key = 'profile_links.tenant_rows.'.$landlord->user_id;

        if (! $bag->has($key)) {
            $bag->set($key, LandlordTenant::where('landlord_id', $landlord->user_id)->pluck('landlord_tenant_id', 'tenant_id')->all());
        }

        return $bag->get($key);
    }

    /**
     * Mirrors Landlord\ProfileController::ensureVisible for a viewer who is not a landlord, so a link never ends in
     * a 404. Read once per landlord per request.
     */
    private static function landlordPageIsOpen(User $subject): bool
    {
        $bag = request()->attributes;
        $key = 'profile_links.open.'.$subject->user_id;

        if (! $bag->has($key)) {
            $subject->loadMissing('roles');
            $bag->set($key, $subject->hasRole('Landlord')
                && ($subject->hasPublicBusinessPage()
                    || ! in_array($subject->profile_visibility ?? 'private', ['private', 'landlords_only'], true)));
        }

        return $bag->get($key);
    }
}
