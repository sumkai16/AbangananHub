<?php

namespace App\Support;

use App\Models\Reservation;
use App\Models\User;

/**
 * The "next step" of a chat's reservation, worded for whoever is looking.
 *
 * One home for two facts the chat panel used to derive in several places:
 * which of the six stages a reservation is in, and, for this viewer, what to
 * do or what is awaited. The folded next-step row in chat-panel reads
 * `for()`; the stage stepper reads `stageIndex()`. The full action bar in
 * chat-panel still renders the buttons themselves, and the row's button
 * points at the same link or form so there is one source of truth.
 *
 * `action` is null (no clear primary action) or one of:
 *   ['kind' => 'link',   'label', 'href']  the same href the action bar uses
 *   ['kind' => 'submit', 'label', 'form']  submits the real form by id
 *   ['kind' => 'modal',  'label']          opens the send-agreement modal
 *   ['kind' => 'panel',  'label']          needs the open panel (editor, review)
 */
class ChatDealStep
{
    public const STAGES = ['Inquiry', 'Negotiation', 'Agreement', 'Signed', 'Paid', 'Occupied'];

    /** Zero-based stage, or false when the reservation is not in a live stage. */
    public static function stageIndex(?Reservation $reservation): int|false
    {
        if (! $reservation) {
            return false;
        }

        // A direct payment is written as 'Paid' and stamps move_in_paid_at once the landlord confirms it; online money is Held/Released.
        $settled = $reservation->move_in_paid_at !== null || $reservation->payments->contains(
            fn ($p) => $p->payment_type === 'Initial' && in_array($p->status, ['Held', 'Paid', 'Released'], true)
        );

        return match ($reservation->rental_status) {
            'Inquiry' => 0,
            'Under Negotiation' => 1,
            'Pending Rental Agreement' => 2,
            'Rental Agreement Signed' => $settled ? 4 : 3,
            'Occupied' => 5,
            default => false,
        };
    }

    public static function formId(string $name, Reservation $reservation): string
    {
        return "deal-{$name}-{$reservation->reservation_id}";
    }

    /**
     * @return array{stage:string,step:int,total:int,yourTurn:bool,sentence:string,action:?array}|null
     */
    public static function for(Reservation $reservation, User $viewer, bool $isLandlord, string $them): ?array
    {
        $index = self::stageIndex($reservation);
        if ($index === false) {
            return null;
        }

        $base = [
            'stage' => self::STAGES[$index],
            'step' => $index + 1,
            'total' => count(self::STAGES),
        ];

        // A viewing request waiting on this viewer is easy to miss behind the fold, so it outranks the stage.
        $viewing = $reservation->latestViewing();
        if ($viewing?->isOpen() && $viewing->awaitsResponseFrom($viewer->user_id)) {
            $slot = $viewing->scheduled_at->format('D, M j \a\t g:i A');

            return $base + [
                'yourTurn' => true,
                'sentence' => $isLandlord ? "{$them} asked to view the unit on {$slot}" : "{$them} suggested a new viewing time: {$slot}",
                'action' => self::panel('Review request'),
            ];
        }

        [$yourTurn, $sentence, $action] = $isLandlord
            ? self::landlordStep($reservation, $them)
            : self::tenantStep($reservation, $them);

        return $base + ['yourTurn' => $yourTurn, 'sentence' => $sentence, 'action' => $yourTurn ? $action : null];
    }

    private static function landlordStep(Reservation $reservation, string $them): array
    {
        $payments = $reservation->payments;
        $held = $payments->firstWhere('status', 'Held');
        $pending = $payments->firstWhere('status', 'Pending');

        switch ($reservation->rental_status) {
            case 'Inquiry':
                if (! $reservation->target_move_in_date) {
                    return self::turn("{$them} sent an inquiry with no move-in date. Accept to talk it through.", [
                        'kind' => 'submit',
                        'label' => 'Accept & negotiate',
                        'form' => self::formId('advance-negotiation', $reservation),
                    ]);
                }

                return self::turn("{$them} wants to move in on {$reservation->target_move_in_date->format('M j')}. Review and accept.", self::panel('Review & accept'));

            case 'Under Negotiation':
                return self::turn("Agree the terms with {$them}, then send the agreement", ['kind' => 'modal', 'label' => 'Send agreement']);

            case 'Pending Rental Agreement':
                return self::wait("Waiting for {$them} to sign the agreement");

            case 'Rental Agreement Signed':
                if ($held) {
                    $clock = $reservation->moveInClockState();
                    if ($clock['disputed'] ?? false) {
                        return self::wait('An administrator is reviewing the move-in issue');
                    }
                    if (($clock['active_clock'] ?? null) === 'turnover') {
                        return self::turn("{$them}'s payment is held. Agree a time to hand over the keys.", self::panel('Schedule handover'));
                    }

                    return self::wait("Keys handed over. Waiting for {$them} to confirm the move-in");
                }
                if ($pending) {
                    return self::wait("{$them}'s payment is processing");
                }
                if ($reservation->move_in_paid_at) {
                    return self::turn("Payment confirmed. Hand over the keys, then confirm the move-in.", [
                        'kind' => 'submit',
                        'label' => 'Confirm move-in',
                        'form' => self::formId('confirm-direct-move-in', $reservation),
                    ]);
                }
                if ($reservation->paymentClaims->where('status', 'Pending')->where('claim_type', 'MoveIn')->isNotEmpty()) {
                    return self::turn("{$them} says they paid. Check it and confirm.", self::panel('Review payment'));
                }

                return self::wait("Agreement signed. Waiting for {$them} to pay");

            case 'Occupied':
                return self::wait("{$them} is occupying this unit");
        }

        return self::wait('');
    }

    private static function tenantStep(Reservation $reservation, string $them): array
    {
        $payments = $reservation->payments;
        $held = $payments->firstWhere('status', 'Held');
        $pending = $payments->firstWhere('status', 'Pending');
        $agreement = route('agreements.show', $reservation);

        switch ($reservation->rental_status) {
            case 'Inquiry':
                return self::wait("Waiting for {$them} to respond");

            case 'Under Negotiation':
                return self::wait("Discuss the terms with {$them}");

            case 'Pending Rental Agreement':
                return self::turn('Read the agreement and sign it', ['kind' => 'link', 'label' => 'View & sign agreement', 'href' => $agreement]);

            case 'Rental Agreement Signed':
                if ($held) {
                    if ($reservation->move_in_disputed_at) {
                        return self::wait('An administrator is reviewing the move-in issue');
                    }

                    return self::turn('Your payment is held. Confirm move-in once you have the keys.', ['kind' => 'link', 'label' => 'Confirm move-in', 'href' => $agreement]);
                }
                if ($pending) {
                    return self::wait('Your payment is processing. This updates by itself once it clears.');
                }
                if ($reservation->move_in_paid_at) {
                    return self::turn('Payment confirmed. Confirm your move-in.', ['kind' => 'link', 'label' => 'Confirm move-in', 'href' => $agreement]);
                }

                $waitingClaim = $reservation->paymentClaims->firstWhere('status', 'Pending');
                if (! Payments::onlineFor($reservation) || $waitingClaim) {
                    if ($waitingClaim) {
                        return self::wait("Waiting for {$them} to confirm your payment");
                    }

                    return self::turn("Pay {$them} using the QR in this chat, then report it", [
                        'kind' => 'link',
                        'label' => "I've paid",
                        'href' => route('agreements.show', ['reservation' => $reservation, 'pay' => 1]).'#pay',
                    ]);
                }

                return self::turn('Agreement signed. Pay to secure the unit.', ['kind' => 'link', 'label' => 'Proceed to payment', 'href' => $agreement]);

            case 'Occupied':
                return self::wait('You are occupying this unit');
        }

        return self::wait('');
    }

    private static function panel(string $label): array
    {
        return ['kind' => 'panel', 'label' => $label];
    }

    private static function turn(string $sentence, array $action): array
    {
        return [true, $sentence, $action];
    }

    private static function wait(string $sentence): array
    {
        return [false, $sentence, null];
    }
}
