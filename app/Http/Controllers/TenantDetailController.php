<?php

namespace App\Http\Controllers;

use App\Models\TenantDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A tenant's own emergency contact and ID, edited from Account Settings. The landlord's view of the same
 * row is on their tenancy page (Landlord\TenancyController::updateTenantDetails covers walk-ins, who
 * cannot log in); signing an agreement also fills it in (Tenant\AgreementController::sign).
 */
class TenantDetailController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasRole('Tenant'), 403);

        $validated = $request->validate(TenantDetail::rules(false), TenantDetail::messages());

        if (TenantDetail::saveFor($user, $validated) === null) {
            return back()->with('warning', 'Nothing to save. Fill in at least one field.');
        }

        return redirect()
            ->to(route('profile.edit') . '#emergency-contact')
            ->with('success', 'Your emergency contact and ID were saved.');
    }
}
