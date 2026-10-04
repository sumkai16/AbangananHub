<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DepositCharge;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Admin's oversight of deposit charges — the emman-branch feature shipped as
 * pure landlord/tenant self-service with no escalation path at all. A tenant
 * disputing a charge previously had nowhere to go; this gives admin the same
 * void() a landlord has on their own charges, as an override, via
 * ReservationPolicy::voidDepositCharge (now admin-reachable too).
 */
class DepositChargeController extends Controller
{
    private const STATUSES = ['Active', 'Voided', 'All'];

    public function index(Request $request)
    {
        $status = $request->query('status', 'Active');

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'Active';
        }

        $charges = DepositCharge::with(['reservation.tenant', 'reservation.property', 'reservation.unit', 'charger', 'voider'])
            ->when($status === 'Active', fn ($q) => $q->whereNull('voided_at'))
            ->when($status === 'Voided', fn ($q) => $q->whereNotNull('voided_at'))
            ->latest('charged_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'Active' => DepositCharge::whereNull('voided_at')->count(),
            'Voided' => DepositCharge::whereNotNull('voided_at')->count(),
        ];
        $counts['All'] = $counts['Active'] + $counts['Voided'];

        return view('admin.deposit-charges.index', [
            'charges' => $charges,
            'status'  => $status,
            'counts'  => $counts,
        ]);
    }

    /**
     * Same shape as Landlord\DepositChargeController::void() — the row keeps
     * its amount/category/description and gains who voided it, when, why.
     * Unlike the landlord's own self-void, this is an admin overriding
     * someone else's decision, so both the tenant AND the landlord are told.
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
                'voided_by'   => auth()->id(),
                'void_reason' => $data['void_reason'],
                'void_note'   => $data['void_note'] ?? null,
            ]);

            AuditLog::record(
                'deposit_charge.void',
                '₱' . number_format((float) $locked->amount, 2) . ' deposit charge voided by admin',
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

        $tenant = $reservation->tenant;
        if ($tenant && ! $tenant->is_walk_in) {
            Notification::notify(
                $tenant->user_id,
                'payment',
                'A deposit charge was reversed',
                sprintf(
                    'An administrator reversed a ₱%s security deposit charge (%s).',
                    number_format((float) $voided->amount, 2),
                    strtolower($voided->voidReasonLabel()),
                ),
                route('tenancy.show', $reservation),
            );
        }

        Notification::notify(
            $reservation->property?->landlord_id,
            'payment',
            'A deposit charge was reversed',
            sprintf(
                'An administrator reversed your ₱%s security deposit charge on %s (%s).',
                number_format((float) $voided->amount, 2),
                $reservation->property->title ?? 'a tenancy',
                strtolower($voided->voidReasonLabel()),
            ),
            route('landlord.tenancies.show', $reservation),
        );

        return back()->with('success', 'Deposit charge voided. The original entry stays on record.');
    }
}
