<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

/**
 * Builds and sends the rent-reminder notifications.
 *
 * Shared by the nightly ProcessRentReminders command and the landlord's manual
 * "Send reminder" button, so both word a reminder identically. The wording is
 * derived from the real gap to the due date, not from any milestone key — a
 * reminder that fires late still reads "N days overdue", never a stale
 * "due today".
 */
class RentReminderNotifier
{
    /**
     * Notify the landlord about a tenancy's rent. Always deliverable — this is
     * the only channel that reaches a walk-in tenancy.
     */
    public function sendToLandlord(Reservation $reservation, array $period, Carbon $today): void
    {
        $landlordId = $reservation->property?->landlord_id;
        if (! $landlordId) {
            return;
        }

        $tenant = $reservation->tenant;
        $name = $tenant ? trim($tenant->first_name . ' ' . $tenant->last_name) : 'A tenant';

        Notification::notify(
            $landlordId,
            'payment',
            $this->title($today->greaterThan($period['due_on'])),
            $name . "'s " . $period['label'] . ' rent (' . $this->peso($period['balance']) . ') '
                . $this->timing($period['due_on'], $today) . '.',
            route('landlord.tenancies.show', $reservation),
        );
    }

    /**
     * Notify the tenant, but only when they can actually receive it — a walk-in
     * is inactive and can never log in. Returns false when there is no
     * notifiable tenant, so callers can report why nothing was sent.
     */
    public function sendToTenant(Reservation $reservation, array $period, Carbon $today): bool
    {
        $tenant = $reservation->tenant;

        if (! $tenant || $tenant->is_walk_in || $tenant->account_status !== 'active') {
            return false;
        }

        Notification::notify(
            $tenant->user_id,
            'payment',
            $this->title($today->greaterThan($period['due_on'])),
            'Your ' . $period['label'] . ' rent (' . $this->peso($period['balance']) . ') '
                . $this->timing($period['due_on'], $today) . '. Please arrange payment with your landlord.',
            route('reservations.index'),
        );

        return true;
    }

    private function title(bool $overdue): string
    {
        return $overdue ? 'Rent overdue' : 'Rent due soon';
    }

    private function timing(Carbon $dueOn, Carbon $today): string
    {
        $days = $today->diffInDays($dueOn, false); // positive = due date is ahead

        return match (true) {
            $days > 1   => "is due in {$days} days",
            $days === 1 => 'is due tomorrow',
            $days === 0 => 'is due today',
            default     => 'is ' . abs($days) . ' ' . (abs($days) === 1 ? 'day' : 'days') . ' overdue',
        };
    }

    private function peso(float $amount): string
    {
        return '₱' . number_format(max(0, $amount), 2);
    }
}
