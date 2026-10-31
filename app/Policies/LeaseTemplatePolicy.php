<?php

namespace App\Policies;

use App\Models\LeaseTemplate;
use App\Models\User;

class LeaseTemplatePolicy
{
    /** Only the landlord who wrote a lease template may see, change, assign or delete it. */
    public function manage(User $user, LeaseTemplate $template): bool
    {
        return $template->landlord_id === $user->user_id;
    }
}
