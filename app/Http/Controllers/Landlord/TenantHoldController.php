<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\TenantHold;
use App\Services\Holds\HoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A landlord reports a tenant who has overdue rent (an admin confirms it) or lifts a hold they raised.
 * All rules live in HoldService; this only checks ownership and turns a refusal into a message.
 */
class TenantHoldController extends Controller
{
    public function store(Request $request, Reservation $reservation, HoldService $holds): RedirectResponse
    {
        Gate::authorize('viewTenancy', $reservation);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $holds->flag($request->user(), $reservation, $data['reason']);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Reported. An admin will review it before anything changes for the tenant.');
    }

    public function release(Request $request, TenantHold $hold, HoldService $holds): RedirectResponse
    {
        // Only the landlord who raised the hold can lift it (an admin lifts any hold from the admin page).
        abort_unless((int) $hold->landlord_id === (int) $request->user()->user_id, 403);

        try {
            $holds->release($hold, $request->user(), 'landlord');
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Hold lifted.');
    }
}
