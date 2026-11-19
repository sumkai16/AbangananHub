<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\PropertyActivity;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The money lines of a property's history: rent and other payments that landed, and payments voided.
 *
 * Money that is still pending is not a line. A payment spread over several months is one line ("Recorded
 * ₱15,000 rent from Ana Reyes (3 months)"): the rows of one recording (same claim or same minute) are folded
 * together, and so are the rows of one online checkout.
 *
 * It does not run after the commit, unlike PaymentObserver: the line is written inside the transaction that
 * writes the money, so a rollback leaves neither.
 */
class PaymentActivityObserver
{
    public function created(Payment $payment): void
    {
        if (in_array($payment->status, ['Paid', 'Held'], true)) {
            $this->recordLanded($payment);
        }
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('voided_at') && $payment->voided_at !== null) {
            $this->recordVoid($payment);

            return;
        }

        // Pending -> Paid / Held: an online checkout settled.
        if ($payment->wasChanged('status') && in_array($payment->status, ['Paid', 'Held'], true)) {
            $this->recordLanded($payment);
        }
    }

    private function recordLanded(Payment $payment): void
    {
        PropertyActivity::safely(function () use ($payment) {
            if ((float) $payment->amount <= 0 || ! ($reservation = $this->reservation($payment))) {
                return;
            }

            $recorder = $payment->recorded_by
                ? User::query()->whereKey($payment->recorded_by)->first(['user_id', 'first_name', 'last_name'])
                : null;
            $tenant = PropertyActivity::nameOf($reservation->tenant_id);
            $online = $recorder === null;

            $key = $online
                ? 'paid:'.($payment->paymongo_checkout_session_id ?: 'p'.$payment->payment_id)
                : 'rec:'.$payment->reservation_id.':'.$payment->recorded_by.':'.($payment->payment_claim_id ?: 'x');

            $options = [
                'unit_id' => $reservation->unit_id,
                // Online money is the tenant's whoever's session settled it (a webhook has no one signed in).
                'actor'   => $online ? User::query()->whereKey($reservation->tenant_id)->first(['user_id', 'first_name', 'last_name']) : $recorder,
                'role'    => $online ? 'tenant' : null,
            ];
            if ($options['role'] === null) {
                unset($options['role']);
            }

            PropertyActivity::recordMerged(
                $reservation->property_id,
                $online ? 'payment.paid' : 'payment.recorded',
                $key,
                fn (array $previous) => $this->landedLine($previous, $payment, $tenant, $online, $reservation),
                $options,
            );
        });
    }

    private function recordVoid(Payment $payment): void
    {
        PropertyActivity::safely(function () use ($payment) {
            if (! ($reservation = $this->reservation($payment))) {
                return;
            }

            $tenant = PropertyActivity::nameOf($reservation->tenant_id);

            PropertyActivity::recordMerged(
                $reservation->property_id,
                'payment.voided',
                'void:'.$payment->reservation_id.':'.(Auth::id() ?? 0),
                function (array $previous) use ($payment, $tenant, $reservation) {
                    $amount = round(($previous['amount'] ?? 0) + (float) $payment->amount, 2);
                    $count = ($previous['count'] ?? 0) + 1;

                    $money = $this->peso($amount);

                    return [
                        $count > 1
                            ? "Voided {$money} of payments from {$tenant} ({$count} entries)"
                            : "Voided a {$money} payment from {$tenant}",
                        ['amount' => $amount, 'count' => $count, 'reservation_id' => $reservation->reservation_id],
                    ];
                },
                ['unit_id' => $reservation->unit_id],
            );
        });
    }

    /** @return array{0: string, 1: array} summary, metadata */
    private function landedLine(array $previous, Payment $payment, string $tenant, bool $online, Reservation $reservation): array
    {
        $amount = round(($previous['amount'] ?? 0) + (float) $payment->amount, 2);
        $months = ($previous['months'] ?? 0) + ($payment->payment_type === 'Monthly' ? 1 : 0);
        $types = array_values(array_unique(array_merge($previous['types'] ?? [], [$this->typeWords($payment->payment_type)])));

        $what = $types === ['rent'] ? 'rent' : implode(' and ', $types);
        $span = $months > 1 && $types === ['rent'] ? " ({$months} months)" : '';
        $money = $this->peso($amount);

        $summary = $online
            ? "{$tenant} paid {$money} {$what} online{$span}".($payment->status === 'Held' ? ', held by AbangananHub' : '')
            : "Recorded {$money} {$what} from {$tenant}{$span}";

        return [$summary, [
            'amount'         => $amount,
            'months'         => $months,
            'types'          => $types,
            'reservation_id' => $reservation->reservation_id,
        ]];
    }

    private function typeWords(?string $type): string
    {
        return match ($type) {
            'Monthly' => 'rent',
            'Initial' => 'move-in payment',
            'Deposit' => 'deposit',
            default   => strtolower((string) $type) ?: 'payment',
        };
    }

    private function peso(float $amount): string
    {
        return '₱'.number_format($amount, floor($amount) === $amount ? 0 : 2);
    }

    private function reservation(Payment $payment): ?Reservation
    {
        return Reservation::query()->whereKey($payment->reservation_id)
            ->first(['reservation_id', 'property_id', 'unit_id', 'tenant_id']);
    }
}
