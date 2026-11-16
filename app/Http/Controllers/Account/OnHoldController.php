<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\TenantHold;
use App\Services\Holds\HoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where a tenant on an unpaid-rent hold lands: what is owed and to whom, how to pay, and a note to the admins.
 * EnsureNotOnHold sends blocked requests here. A hold lifts itself once the rent is paid, so every visit checks that
 * first. Never mentions duplicate accounts: a hold an admin made on a duplicate shows only a neutral message.
 */
class OnHoldController extends Controller
{
    public function show(Request $request, HoldService $holds): View|RedirectResponse
    {
        $user = $request->user();

        $this->openHolds($user)->each(fn (TenantHold $hold) => $holds->releaseIfPaid($hold));

        $open = $this->openHolds($user);

        if ($open->isEmpty()) {
            return redirect($user->homeRoute())->with('success', 'Your account is not on hold.');
        }

        return view('account.on-hold', ['holds' => $open]);
    }

    public function dispute(Request $request, TenantHold $hold, HoldService $holds): RedirectResponse
    {
        abort_unless((int) $hold->tenant_id === (int) $request->user()->user_id && $hold->isOpen(), 403);

        $data = $request->validate(['note' => ['bail', 'required', 'string', 'max:1000']]);

        $holds->dispute($hold, $data['note']);

        return back()->with('success', 'Your note was sent to the AbangananHub team.');
    }

    private function openHolds($user)
    {
        return $user->holds()->open()
            ->with(['landlord', 'reservation.property', 'reservation.unit'])
            ->latest('flagged_at')
            ->get();
    }
}
