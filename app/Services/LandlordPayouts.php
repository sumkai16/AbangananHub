<?php

namespace App\Services;

use App\Mail\GcashChangedMail;
use App\Models\LandlordVerification;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Support\NameMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Every rule for paying a landlord what the platform owes them (plans/online-and-direct-payments.md, phase 4).
 *
 * Owed money is `payments.payout_owed_at` set with `payout_id` null. `batch()` locks those rows, snapshots
 * the landlord's GCash details onto a new Payout and links the rows to it in one transaction, so two senders
 * at once can never pay the same payment twice. `sendFor()` is the automatic path (batch, then PayMongo);
 * the admin's "mark paid out" uses `batch()` with a `Sent` status. A payout sums `amount` only: the online
 * payment fee the tenant paid is never the landlord's.
 *
 * Money for a landlord waits (it stays owed, nothing is lost) while they have no GCash details, an admin has
 * paused them, their GCash changed in the last 48 hours, or a failed payout is waiting for an admin.
 */
class LandlordPayouts
{
    /** Hours payouts wait after a landlord changes their GCash details. */
    public const HOLD_HOURS = 48;

    /** Whether payouts go out through PayMongo by themselves. Off is manual mode: an admin pays by hand. */
    public static function automaticEnabled(): bool
    {
        return (bool) config('rentals.automatic_payouts_enabled', true);
    }

    /** Why this landlord's money must wait right now, or null when it can go out. */
    public static function blockedReason(User $landlord): ?string
    {
        if (! static::automaticEnabled()) {
            return 'automatic payouts are switched off (pay by hand, then press Mark Paid Out)';
        }

        $account = UserPayoutAccount::find($landlord->user_id);

        if (! $account || blank($account->gcash_number) || blank($account->gcash_account_name)) {
            return 'no GCash details on file';
        }

        if ($account->isPaused()) {
            return 'paused by an administrator';
        }

        if ($account->isOnHold()) {
            return 'GCash details changed; payouts resume '.$account->payouts_hold_until->format('M j, g:i A');
        }

        if (! $account->isApproved()) {
            return 'an administrator has not approved this GCash account yet';
        }

        if (static::hasUnresolvedFailure($landlord)) {
            return 'a failed payout is waiting for an administrator';
        }

        return null;
    }

    /**
     * The names this landlord is known by on record: the account name and the name read off their approved ID.
     *
     * @return list<string>
     */
    public static function identityNames(User $landlord): array
    {
        $idName = LandlordVerification::where('user_id', $landlord->user_id)
            ->where('verification_status', 'Approved')
            ->value('ocr_name');

        return array_values(array_filter([trim($landlord->first_name.' '.$landlord->last_name), $idName], fn ($n) => filled($n)));
    }

    /**
     * Whether the name on the GCash account is the landlord's own, judged against their name and verified ID.
     * Null when there is nothing to compare. A mismatch is a flag for the admin approving the account, not a block:
     * a landlord may legitimately be paid into a spouse's or a business account.
     */
    public static function nameMatchesIdentity(User $landlord, ?string $accountName = null): ?bool
    {
        $accountName ??= UserPayoutAccount::find($landlord->user_id)?->gcash_account_name;
        $names = static::identityNames($landlord);

        if (blank($accountName) || $names === []) {
            return null;
        }

        foreach ($names as $name) {
            if (NameMatch::matches($accountName, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether saving these values would change an existing payout destination (a change, or clearing it). Callers
     * ask for the current password first: a hijacked session must not be able to redirect a landlord's money.
     * Setting details for the first time needs no password.
     */
    public static function changesDestination(User $landlord, ?string $number, ?string $name): bool
    {
        $current = UserPayoutAccount::find($landlord->user_id);

        if (! $current || (blank($current->gcash_number) && blank($current->gcash_account_name))) {
            return false;
        }

        return trim((string) $number) !== trim((string) $current->gcash_number)
            || trim((string) $name) !== trim((string) $current->gcash_account_name);
    }

    /** A Failed payout nobody has looked at yet. The sweep must not keep re-sending to a destination that fails. */
    public static function hasUnresolvedFailure(User $landlord): bool
    {
        return Payout::where('landlord_id', $landlord->user_id)
            ->where('status', 'Failed')
            ->whereNull('resolved_at')
            ->exists();
    }

    public static function hasMoneyOwed(User $landlord): bool
    {
        return Payment::pendingPayout()
            ->whereHas('reservation.property', fn ($q) => $q->where('landlord_id', $landlord->user_id))
            ->exists();
    }

    /** @return Collection<int, int> landlord ids with money waiting to be sent */
    public static function landlordIdsWithMoneyOwed(): Collection
    {
        return Payment::pendingPayout()
            ->join('reservations', 'reservations.reservation_id', '=', 'payments.reservation_id')
            ->join('properties', 'properties.property_id', '=', 'reservations.property_id')
            ->distinct()
            ->pluck('properties.landlord_id');
    }

    /**
     * Lock everything currently owed to one landlord and put it in a new payout. Returns null when nothing is owed.
     *
     * @param  array<string, mixed>  $attributes  extra columns for the payout row (an admin's reference, who sent it)
     */
    public static function batch(User $landlord, string $status = 'Processing', array $attributes = []): ?Payout
    {
        return DB::transaction(function () use ($landlord, $status, $attributes) {
            $payments = Payment::whereHas(
                'reservation.property',
                fn ($q) => $q->where('landlord_id', $landlord->user_id)
            )
                ->pendingPayout()
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                return null;
            }

            // Copied onto the payout: the record must keep saying where this transfer went even after the
            // landlord changes their payout account.
            $account = UserPayoutAccount::find($landlord->user_id);

            $payout = Payout::create(array_merge([
                'landlord_id' => $landlord->user_id,
                'amount' => $payments->sum('amount'),
                'status' => $status,
                'gcash_number' => $account?->gcash_number,
                'gcash_account_name' => $account?->gcash_account_name,
            ], $attributes));

            Payment::whereIn('payment_id', $payments->pluck('payment_id'))->update(['payout_id' => $payout->payout_id]);

            return $payout;
        });
    }

    /**
     * The automatic path: everything owed to this landlord goes out as one PayMongo transfer. Does nothing
     * (the money stays owed) when the landlord is blocked or PayMongo is not set up for payouts.
     */
    public static function sendFor(User $landlord): ?Payout
    {
        if (! static::hasMoneyOwed($landlord) || static::blockedReason($landlord) !== null) {
            return null;
        }

        if ($missing = PaymongoTransfers::missingConfig()) {
            static::alertAdmins(
                'payouts-config',
                'Automatic payouts need setting up',
                'Landlords are owed money, but payouts cannot be sent until these are set in .env: '.implode(', ', $missing).'. Until then payouts stay owed, or send them by hand on the Payouts page.',
            );

            return null;
        }

        $payout = static::batch($landlord);

        if (! $payout) {
            return null;
        }

        try {
            PaymongoTransfers::send($payout);
        } catch (Throwable $e) {
            // Anything unexpected after the money was linked: leave it Processing for an admin rather than
            // risk sending it twice, and say so.
            report($e);
            PaymongoTransfers::markUnknown($payout, 'Unexpected error while sending: '.$e->getMessage());
        }

        return $payout->fresh();
    }

    /**
     * The landlord changed where they get paid. Nothing is sent for 48 hours, so a hijacked account cannot
     * redirect money at once, and the landlord is told by app and by email in case it was not them.
     *
     * @param  bool  $firstTime  the landlord had no destination before: only worth a hold when money is already owed
     */
    public static function holdAfterGcashChange(User $landlord, bool $firstTime = false): void
    {
        if ($firstTime && ! static::hasMoneyOwed($landlord)) {
            return;
        }

        $until = now()->addHours(self::HOLD_HOURS);
        UserPayoutAccount::whereKey($landlord->user_id)->update(['payouts_hold_until' => $until]);

        Notification::notify(
            $landlord->user_id,
            'payment',
            'Your GCash details changed',
            'Payouts to you are paused for '.self::HOLD_HOURS.' hours as a safety check. If this was not you, change your password and tell us.',
            route('landlord.payouts.index'),
        );

        try {
            Mail::to($landlord->email)->send(new GcashChangedMail($landlord, $until));
        } catch (Throwable $e) {
            // A mail outage must not undo the hold or fail the save; the in-app notice is already written.
            report($e);
        }
    }

    /** Tell every admin something about payouts needs them, at most once per $key per day. */
    public static function alertAdmins(string $key, string $title, string $message, ?string $link = null): void
    {
        if (! Cache::add('payout-alert:'.$key, true, now()->addDay())) {
            return;
        }

        Log::warning('Payout alert: '.$title, ['message' => $message]);

        User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))
            ->pluck('user_id')
            ->each(fn ($adminId) => Notification::notify(
                $adminId,
                'payment',
                $title,
                $message,
                $link ?? route('admin.payouts.index'),
            ));
    }
}
