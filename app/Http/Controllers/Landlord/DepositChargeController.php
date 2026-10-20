<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DepositCharge;
use App\Models\Notification;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Claims a landlord makes against a tenant's held security deposit — damage,
 * cleaning, a missing item, an unpaid utility. Never touches the `payments`
 * row that funded the deposit; it is a separate ledger of what has been
 * claimed against money already held, capped so it can never claim more than
 * is actually there. Refunding what is left over at move-out is a separate,
 * larger workflow and is not this controller's job.
 */
class DepositChargeController extends Controller
{
    /**
     * Record a charge against this tenancy's held deposit.
     *
     * Deliberately not restricted to 'Occupied' — see
     * ReservationPolicy::recordDepositCharge() — so the cap check below is
     * the only thing standing between a landlord and an over-claim.
     */
    public function store(Request $request, Reservation $reservation)
    {
        Gate::authorize('recordDepositCharge', $reservation);

        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:1'],
            'category'    => ['required', Rule::in(array_keys(DepositCharge::CATEGORIES))],
            'description' => ['required', 'string', 'max:500'],
            'charged_at'  => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'charged_at.before_or_equal' => 'A charge cannot be dated in the future.',
        ]);

        $charge = DB::transaction(function () use ($data, $reservation) {
            // Re-read under a lock and recompute the remaining deposit inside
            // it: two concurrent charges could otherwise both read the same
            // "remaining" figure and together over-claim the deposit.
            $locked = Reservation::whereKey($reservation->getKey())
                ->with(['tenant', 'property', 'unit', 'depositCharges'])
                ->lockForUpdate()
                ->firstOrFail();

            $remaining = RentLedger::for($locked)->summary()['depositRemaining'];

            abort_if(
                (float) $data['amount'] > $remaining,
                422,
                'This would charge more than the ₱' . number_format($remaining, 2) . ' still held on this deposit.'
            );

            $charge = DepositCharge::create([
                'reservation_id' => $locked->reservation_id,
                'amount'         => $data['amount'],
                'category'       => $data['category'],
                'description'    => $data['description'],
                'charged_at'     => $data['charged_at'] ?? now()->toDateString(),
                'charged_by'     => Auth::id(),
            ]);

            AuditLog::record(
                'deposit_charge.create',
                '₱' . number_format((float) $charge->amount, 2) . ' deposit charge recorded on '
                    . ($locked->property->title ?? 'a tenancy'),
                $charge,
                $data['description'],
                [
                    'reservation_id' => $locked->reservation_id,
                    'amount'         => (float) $charge->amount,
                    'category'       => $charge->category,
                ],
            );

            return $charge;
        });

        $this->notifyTenant($reservation, 'Security deposit charge', sprintf(
            '₱%s was charged to your security deposit for %s: %s',
            number_format((float) $charge->amount, 2),
            $reservation->unit->unit_label ?? $reservation->property->title ?? 'your unit',
            $charge->description,
        ));

        return back()->with('success', 'Deposit charge recorded.');
    }

    /**
     * Strike a wrongly-entered charge without deleting it — same shape as
     * PaymentController::void(): the row keeps its amount, category and
     * description, and gains who voided it, when, and why.
     */
    public function void(Request $request, DepositCharge $depositCharge)
    {
        $depositCharge->loadMissing(['reservation.property', 'reservation.tenant', 'reservation.unit']);
        $reservation = $depositCharge->reservation;

        abort_unless($reservation !== null, 404);
        Gate::authorize('voidDepositCharge', $reservation);

        $data = $request->validate([
            'void_reason' => ['required', Rule::in(array_keys(DepositCharge::VOID_REASONS))],
            'void_note'   => ['required_if:void_reason,other', 'nullable', 'string', 'max:255'],
        ], [
            'void_note.required_if' => 'Say what went wrong with this charge.',
        ]);

        $voided = DB::transaction(function () use ($depositCharge, $data) {
            $locked = DepositCharge::whereKey($depositCharge->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($locked->voided_at === null, 409, 'This charge has already been voided.');

            $locked->update([
                'voided_at'   => now(),
                'voided_by'   => Auth::id(),
                'void_reason' => $data['void_reason'],
                'void_note'   => $data['void_note'] ?? null,
            ]);

            AuditLog::record(
                'deposit_charge.void',
                '₱' . number_format((float) $locked->amount, 2) . ' deposit charge voided',
                $locked,
                DepositCharge::VOID_REASONS[$data['void_reason']]
                    . (! empty($data['void_note']) ? ' — ' . $data['void_note'] : ''),
                [
                    'reservation_id' => $locked->reservation_id,
                    'amount'         => (float) $locked->amount,
                    'category'       => $locked->category,
                ],
            );

            return $locked;
        });

        $this->notifyTenant($reservation, 'A deposit charge was reversed', sprintf(
            'Your landlord reversed a ₱%s security deposit charge (%s).',
            number_format((float) $voided->amount, 2),
            strtolower($voided->voidReasonLabel()),
        ));

        return back()->with('success', 'Deposit charge voided. The original entry stays on record.');
    }

    /**
     * A walk-in has no account to tell — same limitation every other
     * tenancy notification in this app lives with.
     */
    private function notifyTenant(Reservation $reservation, string $title, string $message): void
    {
        $tenant = $reservation->tenant;

        if (! $tenant || $tenant->is_walk_in) {
            return;
        }

        Notification::notify(
            $tenant->user_id,
            'payment',
            $title,
            $message,
            route('tenancy.show', $reservation),
        );
    }
}
