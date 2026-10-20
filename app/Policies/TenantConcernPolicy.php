<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\TenantConcern;
use App\Models\User;

class TenantConcernPolicy
{
    /** Only the tenant, in their own tenancy chat, and only while the tenancy is running. */
    public function create(User $user, Conversation $conversation): bool
    {
        return $conversation->isTenancy()
            && $conversation->tenant_id === $user->user_id
            && $this->tenancyRunning($conversation);
    }

    /**
     * The landlord moves a concern to In Progress or Fixed. The tenant can only
     * send a Fixed one back to Open. Nobody changes anything once the tenancy ended.
     */
    public function updateStatus(User $user, TenantConcern $concern, string $to): bool
    {
        $conversation = $concern->conversation;

        if (! $this->tenancyRunning($conversation) || ! in_array($to, TenantConcern::STATUSES, true) || $to === $concern->status) {
            return false;
        }

        if ($user->user_id === $conversation->landlord_id) {
            return in_array($to, [TenantConcern::IN_PROGRESS, TenantConcern::FIXED], true);
        }

        return $user->user_id === $concern->tenant_id
            && $concern->isFixed()
            && $to === TenantConcern::OPEN;
    }

    private function tenancyRunning(Conversation $conversation): bool
    {
        return $conversation->status === 'Open'
            && $conversation->reservation?->rental_status === 'Occupied';
    }
}
