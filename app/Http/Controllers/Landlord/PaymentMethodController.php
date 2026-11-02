<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Support\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Settings > Payments: which ways tenants may pay this landlord (see PaymentMethods). */
class PaymentMethodController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'accepts_online' => ['required', 'boolean'],
            'accepts_qr' => ['required', 'boolean'],
            'accepts_cash' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $online = (bool) $validated['accepts_online'];
        $qr = (bool) $validated['accepts_qr'];
        $cash = (bool) $validated['accepts_cash'];

        if (! $qr && ! $cash && ! ($online && PaymentMethods::onlinePossibleFor($user))) {
            throw ValidationException::withMessages(['payment_methods' => PaymentMethods::NONE_LEFT])
                ->redirectTo(route('profile.edit').'#payments');
        }

        $user->forceFill(['accepts_qr_payments' => $qr, 'accepts_cash_payments' => $cash])->save();

        // The online choice lives on the payout account row, so it only exists once GCash is saved.
        if ($user->hasPayoutDestination()) {
            $user->setAcceptsOnlinePayments($online);
        }

        return redirect()->to(route('profile.edit').'#payments')->with('success', 'Payment methods saved.');
    }
}
