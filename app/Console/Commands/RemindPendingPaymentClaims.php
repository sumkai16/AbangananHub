<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\PaymentClaim;
use App\Services\PaymentClaims;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily: a tenant said "I paid" and the landlord has not answered. The landlord is told once when the report is filed;
 * this nudges them again after each number of days in config rentals.claim_reminder_days (default 1 and 3), and on the
 * last nudge tells the tenant their landlord has not answered yet, so they are not left guessing.
 *
 * Idempotent: payment_claims.reminders_sent counts the nudges already sent, so a same-day re-run sends nothing.
 * Reports on a reservation that ended are already Withdrawn by the ReservationObserver, so only live ones are here.
 */
class RemindPendingPaymentClaims extends Command
{
    protected $signature = 'payment-claims:remind';

    protected $description = 'Remind landlords of payment reports they have not confirmed or rejected';

    public function handle(): int
    {
        $days = array_values(config('rentals.claim_reminder_days', [1, 3]));
        $sent = $failures = 0;

        $claims = PaymentClaim::pending()
            ->where('reminders_sent', '<', count($days))
            ->with(['reservation.property', 'reservation.tenant', 'reservation.unit'])
            ->get();

        foreach ($claims as $claim) {
            try {
                $due = $days[$claim->reminders_sent];

                if ($claim->created_at->gt(now()->subDays($due)) || ! $claim->reservation?->property) {
                    continue;
                }

                $this->remind($claim, $claim->reminders_sent + 1 === count($days));
                $sent++;
            } catch (\Throwable $e) {
                $failures++;
                Log::error('Payment report reminder failed for claim '.$claim->payment_claim_id, ['exception' => $e->getMessage()]);
            }
        }

        $this->info("Payment report reminders: {$sent} sent, {$failures} failed.");

        return self::SUCCESS;
    }

    private function remind(PaymentClaim $claim, bool $last): void
    {
        $reservation = $claim->reservation;
        $unit = $reservation->unit?->unit_label ?? 'the unit';
        $tenant = trim(($reservation->tenant->first_name ?? 'Your tenant').' '.($reservation->tenant->last_name ?? ''));
        $amount = '₱'.number_format((float) $claim->amount, 2);
        $what = $claim->coverageLabel($reservation);
        $filed = $claim->created_at->diffForHumans();

        Notification::notify(
            $reservation->property->landlord_id,
            'payment_to_confirm',
            'Payment still to confirm',
            "{$tenant} reported a {$amount} {$what} for {$unit} {$filed}. Check your account, then confirm or reject it.",
            PaymentClaims::decisionLink($reservation),
            $reservation->conversation_id,
            $claim,
        );

        if ($last) {
            Notification::notify(
                $reservation->tenant_id,
                'payment',
                'Your landlord has not answered yet',
                "Your {$amount} payment report for {$unit} is still waiting for your landlord. We reminded them. You can also message them directly.",
                $reservation->conversation_id
                    ? route('conversations.index', ['active' => $reservation->conversation_id])
                    : route('agreements.show', $reservation),
                $reservation->conversation_id,
            );
        }

        // Query builder on purpose: the report's own updated_at is not a clock anything reads, and no events fire.
        DB::table('payment_claims')->where('payment_claim_id', $claim->payment_claim_id)->increment('reminders_sent');
    }
}
