<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily: nudge, then close, reservations that sit waiting on a person (config rentals.stale_reservations).
 *
 *   Inquiry                  the landlord has not answered     -> remind landlord, then cancel
 *   Under Negotiation        the chat went quiet               -> remind both, then cancel
 *   Pending Rental Agreement the tenant has not signed         -> remind tenant (landlord told), then cancel
 *
 * Idempotent: a reminder goes out once per quiet spell (reservations.stale_reminder_sent_at is compared with the
 * moment the spell began, so new activity re-arms it). The cancel goes through Reservation::cancel(), so anything
 * holding money or with an open checkout is left alone. Walk-in tenants are skipped: they cannot log in and the
 * landlord runs those leases by hand. One bad row never stops the batch.
 */
class ProcessStaleReservations extends Command
{
    protected $signature = 'reservations:process-stale';

    protected $description = 'Remind, then cancel, inquiries, negotiations and unsigned agreements that went quiet';

    /** status => key under config rentals.stale_reservations */
    private const STAGES = [
        'Inquiry' => 'inquiry',
        'Under Negotiation' => 'negotiation',
        'Pending Rental Agreement' => 'agreement',
    ];

    public function handle(): int
    {
        $reminded = $cancelled = $failures = 0;

        foreach (self::STAGES as $status => $key) {
            $rules = config("rentals.stale_reservations.$key");

            $rows = Reservation::where('rental_status', $status)
                ->whereHas('property')
                ->whereHas('tenant', fn ($q) => $q->where('is_walk_in', false))
                ->with(['property', 'tenant', 'unit'])
                ->get();

            foreach ($rows as $reservation) {
                try {
                    $quietSince = $this->quietSince($reservation);

                    if ($this->shouldCancel($quietSince, $rules) && $this->cancel($reservation, $key, $rules)) {
                        $cancelled++;
                    } elseif ($this->shouldRemind($reservation, $quietSince, $rules)) {
                        $this->remind($reservation, $key, $rules);
                        $reminded++;
                    }
                } catch (\Throwable $e) {
                    $failures++;
                    Log::error('Stale reservation check failed for '.$reservation->reservation_id, ['exception' => $e->getMessage()]);
                }
            }
        }

        $this->info("Stale reservations: {$reminded} reminded, {$cancelled} cancelled, {$failures} failed.");

        return self::SUCCESS;
    }

    /** The moment the current quiet spell began. */
    private function quietSince(Reservation $reservation): Carbon
    {
        // Created, for an Inquiry (nobody answered yet); otherwise the last status change, e.g. the agreement being sent.
        $since = $reservation->rental_status === 'Inquiry' ? $reservation->created_at : $reservation->updated_at;

        if ($reservation->rental_status === 'Under Negotiation' && $reservation->conversation_id) {
            $lastMessage = Message::where('conversation_id', $reservation->conversation_id)
                ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', 'system'))
                ->max('created_at');

            if ($lastMessage && Carbon::parse($lastMessage)->gt($since)) {
                $since = Carbon::parse($lastMessage);
            }
        }

        return $since;
    }

    private function shouldRemind(Reservation $reservation, Carbon $quietSince, array $rules): bool
    {
        if ($quietSince->gt(now()->subDays($rules['remind_days']))) {
            return false;
        }

        // Not yet reminded during this quiet spell.
        return $reservation->stale_reminder_sent_at === null || Carbon::parse($reservation->stale_reminder_sent_at)->lt($quietSince);
    }

    private function shouldCancel(Carbon $quietSince, array $rules): bool
    {
        return (bool) config('rentals.stale_reservations.auto_cancel') && $quietSince->lte(now()->subDays($rules['cancel_days']));
    }

    private function remind(Reservation $reservation, string $key, array $rules): void
    {
        $left = max(1, $rules['cancel_days'] - $rules['remind_days']);
        $unit = $reservation->unit?->unit_label ?? 'the unit';
        $name = trim($reservation->tenant->first_name.' '.$reservation->tenant->last_name);
        $landlordId = $reservation->property->landlord_id;
        $tenantId = $reservation->tenant_id;
        $thread = route('conversations.index', ['active' => $reservation->conversation_id]);
        $closing = config('rentals.stale_reservations.auto_cancel')
            ? " It will be cancelled in about {$left} ".($left === 1 ? 'day' : 'days').' if nothing happens.'
            : '';

        match ($key) {
            'inquiry' => Notification::notify($landlordId, 'reservation', 'Inquiry waiting for you',
                "{$name} is waiting for your reply about {$unit}. Accept or decline it.".$closing, $thread, $reservation->conversation_id),
            'negotiation' => $this->both($reservation, $landlordId, $tenantId, 'Talks have gone quiet',
                "The conversation about {$unit} has had no new message for a while. Pick it up before the reservation is closed.".$closing, $thread),
            'agreement' => $this->agreementReminder($reservation, $landlordId, $tenantId, $unit, $name, $closing, $thread),
        };

        // Query builder on purpose: no model events, and updated_at (the quiet-spell clock) stays put.
        DB::table('reservations')->where('reservation_id', $reservation->reservation_id)
            ->update(['stale_reminder_sent_at' => now()]);
    }

    private function both(Reservation $reservation, int $landlordId, int $tenantId, string $title, string $text, string $thread): void
    {
        Notification::notify($landlordId, 'reservation', $title, $text, $thread, $reservation->conversation_id);
        Notification::notify($tenantId, 'reservation', $title, $text, $thread, $reservation->conversation_id);
    }

    private function agreementReminder(Reservation $reservation, int $landlordId, int $tenantId, string $unit, string $name, string $closing, string $thread): void
    {
        Notification::notify($tenantId, 'agreement', 'Please sign your rental agreement',
            "Your rental agreement for {$unit} is still waiting for your signature. The unit is held for you until then.".$closing,
            route('agreements.show', $reservation), $reservation->conversation_id);
        Notification::notify($landlordId, 'agreement', 'Agreement still unsigned',
            "{$name} has not signed the rental agreement for {$unit} yet. We reminded them.".$closing, $thread, $reservation->conversation_id);
    }

    /** True when the reservation was actually cancelled (false when money is held or a checkout is open). */
    private function cancel(Reservation $reservation, string $key, array $rules): bool
    {
        $days = $rules['cancel_days'];
        $reservation->systemCancelReason = match ($key) {
            'inquiry' => "No reply for {$days} days, so the inquiry was closed.",
            'negotiation' => "No new message for {$days} days, so the reservation was closed.",
            'agreement' => "The agreement was not signed within {$days} days, so the reservation was closed and the unit is free again.",
        };

        if (! $reservation->cancel()) {
            $reservation->systemCancelReason = null;

            return false;
        }

        $reason = $reservation->systemCancelReason;
        $unit = $reservation->unit?->unit_label ?? 'the unit';
        $name = trim($reservation->tenant->first_name.' '.$reservation->tenant->last_name);
        $thread = route('conversations.index', ['active' => $reservation->conversation_id]);

        // Sent here, not by the observer, which stays quiet for these cancels and skips reservations with no chat.
        Notification::notify($reservation->tenant_id, 'reservation', 'Reservation closed', "{$unit}: {$reason}", $thread, $reservation->conversation_id);
        Notification::notify($reservation->property->landlord_id, 'reservation', 'Reservation closed', "{$name}, {$unit}: {$reason}", $thread, $reservation->conversation_id);
        $reservation->postSystemMessage($reason);

        return true;
    }
}
