<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Property;
use Illuminate\Support\Facades\Auth;

/**
 * A landlord may only act on their own property. Shared by every
 * Landlord/Property* controller (web and API) so the check can't drift
 * or get missed when a new controller needs it.
 */
trait AuthorizesPropertyOwnership
{
    private function authorizeProperty(Property $property): void
    {
        abort_if($property->landlord_id !== Auth::id(), 403);
    }
}
