<?php

namespace App\Policies;

use App\Models\PropertyDocument;
use App\Models\User;

class PropertyDocumentPolicy
{
    /**
     * Owning landlord can view their property's document; Admin can view any.
     */
    public function view(User $user, PropertyDocument $document): bool
    {
        return $user->user_id === $document->property->landlord_id || $user->hasRole('Admin');
    }
}
