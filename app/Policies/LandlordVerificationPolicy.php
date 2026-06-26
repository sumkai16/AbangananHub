<?php

namespace App\Policies;

use App\Models\LandlordVerification;
use App\Models\User;

class LandlordVerificationPolicy
{
    /**
     * Owner can view their own application document; Admin can view any.
     */
    public function view(User $user, LandlordVerification $verification): bool
    {
        return $user->user_id === $verification->user_id || $user->hasRole('Admin');
    }
}