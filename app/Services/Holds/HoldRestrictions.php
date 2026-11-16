<?php

namespace App\Services\Holds;

use App\Models\Conversation;
use App\Models\TenantHold;
use App\Models\User;
use Closure;

/**
 * What a tenant on hold may not do. One place for the rule so the middleware and the chat controllers (web and API)
 * agree. Only an `active` hold restricts anything: a hold still waiting for an admin changes nothing for the tenant.
 * A hold never touches paying rent, which is why enforcement is a short list of "starts something new" routes
 * rather than a lock on the whole tenant area.
 */
class HoldRestrictions
{
    public static function isRestricted(User $user): bool
    {
        return ! $user->hasRole('Admin') && $user->isOnHold();
    }

    /**
     * May this user talk to this landlord? A restricted tenant may only talk to a landlord who has a pending or
     * active hold on them. A hold an admin made on a duplicate account has no landlord, so it allows no chat.
     */
    public static function chatAllowed(User $user, int $landlordId): bool
    {
        if (! self::isRestricted($user)) {
            return true;
        }

        return in_array($landlordId, self::chatLandlordIds($user), true);
    }

    /** Conversation-level check: the landlord side of a conversation is never restricted. */
    public static function canUseConversation(User $user, Conversation $conversation): bool
    {
        if ((int) $conversation->landlord_id === (int) $user->user_id) {
            return true;
        }

        return self::chatAllowed($user, (int) $conversation->landlord_id);
    }

    /** @return list<int> Landlords a restricted tenant may still chat with. */
    public static function chatLandlordIds(User $user): array
    {
        return $user->holds()->open()->whereNotNull('landlord_id')
            ->pluck('landlord_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * The `where(...)` closure for "conversations this user is a party to". For everyone but a restricted tenant it
     * is the usual tenant-or-landlord test; a restricted tenant only gets their conversations with allowed landlords.
     */
    public static function partyScope(User $user): Closure
    {
        $userId = $user->user_id;

        if (! self::isRestricted($user)) {
            return fn ($q) => $q->where('tenant_id', $userId)->orWhere('landlord_id', $userId);
        }

        $allowed = self::chatLandlordIds($user);

        return fn ($q) => $q->where('landlord_id', $userId)
            ->orWhere(fn ($own) => $own->where('tenant_id', $userId)->whereIn('landlord_id', $allowed));
    }

    public static function deniedMessage(): string
    {
        return 'Your account is on hold for unpaid rent. You can still pay and message the landlord you owe.';
    }
}
