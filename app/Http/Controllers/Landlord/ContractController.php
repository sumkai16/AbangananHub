<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\RenewContractRequest;
use App\Http\Requests\Landlord\StoreContractRequest;
use App\Models\AuditLog;
use App\Models\LandlordTenant;
use App\Models\Notification;
use App\Models\Reservation;
use App\Services\LeaseContractCreator;
use App\Support\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Contracts: add, view, renew. The list itself lives on the Tenants page (one row per tenant with their current
 * contract); the old Contracts list URL redirects there. A contract is a reservation from 'Rental Agreement Signed'
 * on; the inquiry funnel before that stays on the Reservations page.
 * See plans/landlord-tenants-contracts-direct-payments.md and plans/merge-tenants-contracts-policies.md.
 */
class ContractController extends Controller
{
    public const CONTRACT_STATUSES = ['Rental Agreement Signed', 'Reserved', 'Occupied', 'Completed'];

    /** Old status filters on the Contracts list, mapped to the Tenants page's stage chips. */
    private const OLD_FILTERS = ['active' => 'living', 'awaiting' => 'moving', 'ended' => 'former'];

    /** The Contracts list moved onto the Tenants page; old links and bookmarks keep their filters and open modal. */
    public function index(Request $request)
    {
        $status = $request->boolean('lease_missing') ? 'no-agreement' : (self::OLD_FILTERS[$request->input('status')] ?? null);

        return redirect()->route('landlord.tenants.index', array_filter(
            $request->only(['open', 'rate', 'new', 'tenant', 'search', 'property']) + ['status' => $status],
            fn ($v) => $v !== null && $v !== '',
        ));
    }

    public function store(StoreContractRequest $request)
    {
        $data = $request->validated();
        $row = LandlordTenant::where('landlord_id', Auth::id())->visible()->findOrFail($data['landlord_tenant_id']);

        $reservation = LeaseContractCreator::create(Auth::id(), $row->tenant, $data, $request->file('lease_file'));

        return redirect()
            ->route('landlord.tenants.index', ['open' => $reservation->reservation_id])
            ->with('success', $reservation->rental_status === 'Occupied'
                ? 'Contract added. The unit is now occupied.'
                : 'Contract added. The unit is reserved until '.$row->tenant->first_name.' moves in.');
    }

    /** The contract's details, rendered for the view modal (fetched when it opens, not embedded per row). */
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);
        abort_unless(in_array($reservation->rental_status, self::CONTRACT_STATUSES, true), 404);

        return view('landlord.contracts.partials._details', self::detailsData($reservation));
    }

    /**
     * Everything the contract modal needs. Shared with TenantController::show, which opens the same modal for a
     * tenant's current contract. The caller has already authorised the landlord for this reservation.
     */
    public static function detailsData(Reservation $reservation): array
    {
        $reservation->load(['tenant.tenantDetail', 'unit', 'property', 'renewals', 'payments', 'tenantRating']);

        $row = LandlordTenant::where('landlord_id', Auth::id())->where('tenant_id', $reservation->tenant_id)->first();

        $others = Reservation::where('tenant_id', $reservation->tenant_id)
            ->whereKeyNot($reservation->getKey())
            ->whereIn('rental_status', self::CONTRACT_STATUSES)
            ->whereHas('property', fn ($q) => $q->where('landlord_id', Auth::id()))
            ->with(['unit:unit_id,unit_label', 'property:property_id,title'])
            ->latest('reservation_date')
            ->get();

        return [
            'reservation' => $reservation,
            'code' => $row?->code(),
            'showTenantDetails' => Gate::allows('viewTenantDetails', $reservation),
            'canInvite' => $row && Gate::allows('invite', $row),
            'inviteUrl' => $row ? route('landlord.tenants.invite', $row) : null,
            'others' => $others,
            'photo' => Images::resize($reservation->unit?->media()->where('media_type', 'Image')->orderBy('media_id')->value('media_url'), 320),
            // ?rate=1 opens the modal on its rating form (the "Rate your tenant" notification, or a failed submit).
            'startRating' => request()->boolean('rate'),
            // ?renew=1 opens it on the Renew form (the Renew button on an ending-soon row).
            'startRenewing' => request()->boolean('renew'),
        ];
    }

    /**
     * Renew: a later end date and the rent from a chosen month on. Locked and re-checked, so two submits cannot
     * write two renewals. The tenant is told when they have an account to be told on.
     */
    public function renew(RenewContractRequest $request, Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);
        $data = $request->validated();

        $renewed = DB::transaction(function () use ($reservation, $data, $request) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->rental_status, ['Reserved', 'Occupied'], true)
                || ($locked->target_move_out_date && ! Carbon::parse($data['new_move_out_date'])->gt($locked->target_move_out_date))) {
                return null;
            }

            $renewal = $locked->renewals()->create([
                'new_move_out_date' => $data['new_move_out_date'],
                'new_monthly_rent' => $data['new_monthly_rent'],
                'rent_effective_from' => Carbon::createFromFormat('Y-m', $data['rent_effective_from'])->startOfMonth(),
                'renewed_by' => $request->user()->user_id,
            ]);

            $locked->update(['target_move_out_date' => $data['new_move_out_date']]);

            AuditLog::record(
                'contract.renew',
                'Contract renewed to '.Carbon::parse($data['new_move_out_date'])->format('M j, Y').' on '.($locked->property?->title ?? 'a property'),
                $locked,
                null,
                ['new_monthly_rent' => (float) $data['new_monthly_rent'], 'rent_effective_from' => $data['rent_effective_from']],
            );

            if (! $locked->tenant?->is_walk_in) {
                Notification::notify(
                    $locked->tenant_id,
                    'reservation',
                    'Your contract was renewed',
                    'Your contract now runs until '.$renewal->new_move_out_date->format('M j, Y').'. Rent from '
                        .$renewal->rent_effective_from->format('F Y').' is ₱'.number_format((float) $renewal->new_monthly_rent, 2).'.',
                    route('tenancy.show', $locked),
                );
            }

            return $renewal;
        });

        if (! $renewed) {
            return back()->with('error', 'This contract can no longer be renewed.');
        }

        return redirect()->route('landlord.tenants.index', ['open' => $reservation->reservation_id])
            ->with('success', 'Contract renewed until '.$renewed->new_move_out_date->format('M j, Y').'.');
    }
}
