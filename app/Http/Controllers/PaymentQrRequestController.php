<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\PaymentQrShare;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * "Ask {landlord} to share her QR": the tenant's one-tap request on the pay-directly card. It tells the landlord
 * through the system (a notification and a line in the chat) instead of the tenant having to write a message.
 * The landlord answers by sharing a QR (PaymentQrShareController), which closes the request.
 */
class PaymentQrRequestController extends Controller
{
    /** How long a request stays "open" before the tenant may ask again. */
    public const COOLDOWN_MINUTES = 60;

    public function store(Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->tenant_id === Auth::id(), 403);

        $reservation->loadMissing(['property.landlord', 'unit', 'tenant', 'conversation', 'tenancyThread']);
        $landlord = $reservation->property?->landlord;
        $conversation = $reservation->paymentChat(open: true);

        // A walk-in tenancy has no chat, and a finished or cancelled one has nothing left to pay.
        abort_unless($landlord && $conversation && ! in_array($reservation->rental_status, Reservation::TERMINAL_STATUSES, true), 404);

        if (PaymentQrShare::latestFor($reservation)) {
            return back()->with('warning', "{$landlord->first_name} has already shared a payment QR. It is on this page.");
        }

        $open = $reservation->qr_requested_at
            && $reservation->qr_requested_at->gt(now()->subMinutes(self::COOLDOWN_MINUTES));

        if ($open) {
            return back()->with('warning', "You already asked {$landlord->first_name} ".$reservation->qr_requested_at->diffForHumans().'. You can ask again in an hour.');
        }

        $reservation->forceFill(['qr_requested_at' => now()])->save();

        $tenantName = $reservation->tenant->first_name ?? 'The tenant';
        $place = trim(($reservation->property?->title ?? '').($reservation->unit ? ' · '.$reservation->unit->unit_label : ''));

        $conversation->postSystemMessage("{$tenantName} asked {$landlord->first_name} to share a payment QR.");

        Notification::notify(
            $landlord->user_id,
            'payment',
            "{$tenantName} asks for your payment QR",
            'Share it in your chat so they can pay you directly'.($place !== '' ? " for {$place}." : '.'),
            route('conversations.index', array_filter([
                'box' => $conversation->isTenancy() ? 'tenancy' : null,
                'active' => $conversation->conversation_id,
            ])),
            $conversation->conversation_id,
            $reservation,
        );

        return back()->with('success', "{$landlord->first_name} was asked to share a payment QR. We will tell you when it arrives.");
    }
}
