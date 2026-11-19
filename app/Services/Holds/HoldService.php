<?php

namespace App\Services\Holds;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\TenantHold;
use App\Models\User;
use App\Services\RentLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a tenant hold goes through here, so each one is transactional, audited and announced.
 * account_status is never touched: a held tenant can always sign in and pay. See plans/tenant-payment-holds.md.
 */
class HoldService
{
    /** Where the notices send the tenant. A plain path: the page itself is built in a later step. */
    private const TENANT_LINK = '/account/on-hold';

    /** A landlord reports a tenant who has overdue rent on one of their tenancies. Waits for an admin. */
    public function flag(User $landlord, Reservation $reservation, string $reason): TenantHold
    {
        $reason = trim($reason);
        $min = (int) config('security.hold_match.min_reason_length', 10);

        if (mb_strlen($reason) < $min) {
            throw ValidationException::withMessages(['reason' => "Please give a reason of at least {$min} characters."]);
        }

        $reservation->loadMissing(['property', 'tenant']);

        if (! $reservation->tenant_id || (int) $reservation->property?->landlord_id !== (int) $landlord->user_id) {
            throw ValidationException::withMessages(['reservation' => 'You can only report tenants of your own properties.']);
        }

        $overdue = RentLedger::for($reservation)->summary();
        if ((int) $overdue['overdueCount'] < 1) {
            throw ValidationException::withMessages(['reservation' => 'This tenant has no overdue rent to report.']);
        }

        return DB::transaction(function () use ($landlord, $reservation, $reason, $overdue) {
            // Lock the tenant row so two simultaneous reports cannot both pass the one-open-hold check.
            User::whereKey($reservation->tenant_id)->lockForUpdate()->first();

            $exists = TenantHold::open()
                ->where('tenant_id', $reservation->tenant_id)
                ->where('reservation_id', $reservation->reservation_id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['reservation' => 'This tenant has already been reported for this tenancy.']);
            }

            $hold = TenantHold::create([
                'tenant_id'      => $reservation->tenant_id,
                'landlord_id'    => $landlord->user_id,
                'reservation_id' => $reservation->reservation_id,
                'amount_owed'    => round((float) $overdue['overdueAmount'], 2),
                'reason'         => $reason,
                'status'         => TenantHold::PENDING,
                'flagged_at'     => now(),
            ]);

            AuditLog::record(
                'hold.flagged',
                "Reported {$this->name($hold->tenant)} for unpaid rent (₱".number_format((float) $hold->amount_owed, 2).').',
                $hold,
                $reason,
                ['tenant_id' => $hold->tenant_id, 'landlord_id' => $landlord->user_id, 'reservation_id' => $hold->reservation_id],
            );

            Notification::notify(
                $hold->tenant_id,
                'account',
                'Your landlord reported unpaid rent',
                'Your landlord reported unpaid rent on your tenancy. An admin will review it. Pay now to clear it.',
                self::TENANT_LINK,
                null,
                $hold,
            );
            $this->notifyAdmins('Unpaid-rent report to review', "{$this->name($landlord)} reported {$this->name($hold->tenant)} for unpaid rent.");

            return $hold;
        });
    }

    /** An admin confirms the report: the hold is now in force. */
    public function confirm(TenantHold $hold, User $admin): TenantHold
    {
        return DB::transaction(function () use ($hold, $admin) {
            $hold = $this->lockedIn($hold, [TenantHold::PENDING], 'Only a hold waiting for review can be confirmed.');

            $hold->update(['status' => TenantHold::ACTIVE, 'confirmed_by' => $admin->user_id, 'confirmed_at' => now()]);

            AuditLog::record('hold.confirmed', "Confirmed the hold on {$this->name($hold->tenant)}.", $hold, null, ['tenant_id' => $hold->tenant_id]);

            Notification::notify(
                $hold->tenant_id,
                'account',
                'Your account is on hold',
                'An admin confirmed your landlord\'s report. You can still pay your rent and message your landlord. Pay what is overdue to lift the hold.',
                self::TENANT_LINK,
                null,
                $hold,
            );
            Notification::notify($hold->landlord_id, 'account', 'Hold confirmed', "An admin confirmed your report on {$this->name($hold->tenant)}. It lifts when the rent is paid.", null, null, $hold);

            return $hold;
        });
    }

    /** An admin turns the report down. The note is required and goes to the landlord. */
    public function reject(TenantHold $hold, User $admin, string $note): TenantHold
    {
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Please say why the report was rejected.']);
        }

        return DB::transaction(function () use ($hold, $admin, $note) {
            $hold = $this->lockedIn($hold, [TenantHold::PENDING], 'Only a hold waiting for review can be rejected.');

            $hold->update(['status' => TenantHold::REJECTED, 'rejected_note' => $note, 'released_by' => $admin->user_id, 'released_at' => now()]);

            AuditLog::record('hold.rejected', "Rejected the report on {$this->name($hold->tenant)}.", $hold, $note, ['tenant_id' => $hold->tenant_id]);

            Notification::notify($hold->landlord_id, 'account', 'Report not accepted', "An admin did not accept your report on {$this->name($hold->tenant)}: {$note}", null, null, $hold);

            return $hold;
        });
    }

    /**
     * Ends a pending or active hold, and every copy made from it. $reason is a key of TenantHold::RELEASE_REASONS.
     * $by is null when the hold lifted by itself.
     */
    public function release(TenantHold $hold, ?User $by, string $reason): TenantHold
    {
        if (! array_key_exists($reason, TenantHold::RELEASE_REASONS)) {
            throw new \InvalidArgumentException("Unknown release reason: {$reason}");
        }

        return DB::transaction(function () use ($hold, $by, $reason) {
            $hold = $this->lockedIn($hold, [TenantHold::PENDING, TenantHold::ACTIVE], 'This hold has already ended.');

            $this->end($hold, $by, $reason);

            // A copy lives only as long as the hold it was copied from.
            foreach ($hold->copies()->open()->get() as $copy) {
                $this->end($copy, $by, 'source_released');
            }

            return $hold;
        });
    }

    /** The tenant's side of the story. Saved on the hold and shown to the admins. */
    public function dispute(TenantHold $hold, string $note): TenantHold
    {
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Please write your note.']);
        }

        return DB::transaction(function () use ($hold, $note) {
            $hold = $this->lockedIn($hold, [TenantHold::PENDING, TenantHold::ACTIVE], 'This hold has already ended.');

            $hold->update(['tenant_note' => mb_substr($note, 0, 2000)]);

            $this->notifyAdmins('Tenant replied to a hold', "{$this->name($hold->tenant)} added a note to their hold.");

            return $hold;
        });
    }

    /**
     * Lifts the hold when its debt is gone: the overdue rent was paid, or the tenancy was cancelled or rejected.
     * A Completed tenancy that still owes rent stays held, because that debt is still owed. Holds with no
     * reservation (an admin's copy of another hold) lift with their source, never on their own.
     */
    public function releaseIfPaid(TenantHold $hold): bool
    {
        if (! $hold->isOpen() || ! $hold->reservation_id) {
            return false;
        }

        $reservation = $hold->reservation;
        if (! $reservation) {
            return false;
        }

        if (in_array($reservation->rental_status, ['Cancelled', 'Rejected'], true)) {
            $reason = 'debt_dropped';
        } elseif ((int) RentLedger::for($reservation)->summary()['overdueCount'] === 0) {
            $reason = 'paid';
        } else {
            return false;
        }

        try {
            $this->release($hold, null, $reason);
        } catch (ValidationException) {
            return false; // someone else ended it first
        }

        return true;
    }

    private function end(TenantHold $hold, ?User $by, string $reason): void
    {
        $hold->update(['status' => TenantHold::RELEASED, 'released_by' => $by?->user_id, 'released_at' => now(), 'release_reason' => $reason]);

        AuditLog::record(
            'hold.released',
            "Released the hold on {$this->name($hold->tenant)}: ".TenantHold::RELEASE_REASONS[$reason].'.',
            $hold,
            null,
            ['tenant_id' => $hold->tenant_id, 'release_reason' => $reason],
        );

        Notification::notify($hold->tenant_id, 'account', 'Your hold was lifted', 'The hold on your account is over. You can use AbangananHub as usual.', null, null, $hold);
    }

    /** Re-reads the hold under a row lock and refuses unless it is in one of $statuses. */
    private function lockedIn(TenantHold $hold, array $statuses, string $message): TenantHold
    {
        $fresh = TenantHold::whereKey($hold->getKey())->lockForUpdate()->firstOrFail();

        if (! in_array($fresh->status, $statuses, true)) {
            throw ValidationException::withMessages(['hold' => $message]);
        }

        return $fresh->load('tenant');
    }

    private function notifyAdmins(string $title, string $message): void
    {
        $adminIds = User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))->pluck('user_id');
        foreach ($adminIds as $adminId) {
            Notification::notify((int) $adminId, 'account', $title, $message, null);
        }
    }

    private function name(?User $user): string
    {
        return $user ? trim($user->first_name.' '.$user->last_name) : 'a tenant';
    }
}
