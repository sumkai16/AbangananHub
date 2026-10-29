<?php

namespace App\Policies;

use App\Models\TenancyDocument;
use App\Models\User;

/**
 * Who may touch a tenant's uploaded document. The tenant who owns it and the landlord of that property, nobody else:
 * admins deliberately cannot open these (plans/required-tenant-documents.md).
 */
class TenancyDocumentPolicy
{
    public function view(User $user, TenancyDocument $document): bool
    {
        return $this->isTenant($user, $document) || $this->isLandlord($user, $document);
    }

    public function upload(User $user, TenancyDocument $document): bool
    {
        return $this->isTenant($user, $document);
    }

    public function review(User $user, TenancyDocument $document): bool
    {
        return $this->isLandlord($user, $document);
    }

    private function isTenant(User $user, TenancyDocument $document): bool
    {
        return $document->reservation?->tenant_id === $user->user_id;
    }

    private function isLandlord(User $user, TenancyDocument $document): bool
    {
        return $document->reservation?->property?->landlord_id === $user->user_id && $user->hasRole('Landlord');
    }
}
