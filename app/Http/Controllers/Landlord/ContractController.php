<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\RenewContractRequest;
use App\Http\Requests\Landlord\StoreContractRequest;
use App\Models\AuditLog;
use App\Models\LandlordTenant;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\LeaseContractCreator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The Contracts page: every lease on the landlord's properties, online and added by hand, grouped by property.
 * A contract is a reservation from 'Rental Agreement Signed' on; the inquiry funnel before that stays on the
 * Reservations page. See plans/landlord-tenants-contracts-direct-payments.md.
 */
class ContractController extends Controller
{
    public const CONTRACT_STATUSES = ['Rental Agreement Signed', 'Reserved', 'Occupied', 'Completed'];

    /** The status filter: 'current' (default) hides ended contracts. */
    private const FILTERS = [
        'current' => ['Rental Agreement Signed', 'Reserved', 'Occupied'],
        'awaiting' => ['Rental Agreement Signed', 'Reserved'],
        'active' => ['Occupied'],
        'ended' => ['Completed'],
        'all' => self::CONTRACT_STATUSES,
    ];

    private function landlordContracts(): Builder
    {
        return Reservation::whereHas('property', fn ($q) => $q->where('landlord_id', Auth::id()))
            ->whereIn('rental_status', self::CONTRACT_STATUSES);
    }

    public function index(Request $request)
    {
        $filter = array_key_exists($request->input('status'), self::FILTERS) ? $request->input('status') : 'current';

        $contracts = $this->landlordContracts()
            ->whereIn('rental_status', self::FILTERS[$filter])
            ->when($request->integer('property'), fn ($q, $id) => $q->where('property_id', $id))
            ->when($request->input('search'), fn ($q, $search) => $q->whereHas('tenant', fn ($t) => $t
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->boolean('lease_missing'), fn ($q) => $q->whereNull('lease_file_path')->whereNull('agreed_at'))
            ->with(['tenant', 'unit', 'property', 'renewals'])
            ->orderBy('property_id')
            ->orderByRaw("FIELD(rental_status, 'Rental Agreement Signed', 'Reserved', 'Occupied', 'Completed')")
            ->orderBy('target_move_out_date')
            ->get();

        // Tenant IDs for every row in one query, keyed by tenant.
        $codes = LandlordTenant::where('landlord_id', Auth::id())
            ->whereIn('tenant_id', $contracts->pluck('tenant_id')->unique())
            ->get()
            ->mapWithKeys(fn (LandlordTenant $row) => [$row->tenant_id => $row->code()]);

        $live = $this->landlordContracts()->whereIn('rental_status', self::FILTERS['current']);
        $stats = [
            'active' => (clone $live)->where('rental_status', 'Occupied')->count(),
            'awaiting' => (clone $live)->whereIn('rental_status', self::FILTERS['awaiting'])->count(),
            'ending' => (clone $live)->where('rental_status', 'Occupied')
                ->whereNotNull('target_move_out_date')
                ->whereDate('target_move_out_date', '<=', now()->addDays((int) config('rentals.renewal_window_days', 60)))
                ->count(),
            'lease_missing' => (clone $live)->whereNull('lease_file_path')->whereNull('agreed_at')->count(),
        ];

        $properties = Auth::user()->properties()->where('verification_status', 'Approved')
            ->orderBy('title')->get(['property_id', 'title']);

        return view('landlord.contracts.index', [
            'groups' => $contracts->groupBy('property_id'),
            'codes' => $codes,
            'stats' => $stats,
            'filter' => $filter,
            'properties' => $properties,
            'tenantOptions' => $this->tenantOptions(),
            'unitOptions' => $this->unitOptions(),
        ]);
    }

    public function store(StoreContractRequest $request)
    {
        $data = $request->validated();
        $row = LandlordTenant::where('landlord_id', Auth::id())->visible()->findOrFail($data['landlord_tenant_id']);

        $reservation = LeaseContractCreator::create(Auth::id(), $row->tenant, $data, $request->file('lease_file'));

        return redirect()
            ->route('landlord.contracts.index', ['open' => $reservation->reservation_id])
            ->with('success', $reservation->rental_status === 'Occupied'
                ? 'Contract added. The unit is now occupied.'
                : 'Contract added. The unit is reserved until '.$row->tenant->first_name.' moves in.');
    }

    /** The contract's details, rendered for the view modal (fetched when it opens, not embedded per row). */
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);
        abort_unless(in_array($reservation->rental_status, self::CONTRACT_STATUSES, true), 404);

        $reservation->load(['tenant.tenantDetail', 'unit', 'property', 'renewals', 'payments', 'tenantRating']);

        $code = LandlordTenant::where('landlord_id', Auth::id())->where('tenant_id', $reservation->tenant_id)->first()?->code();

        return view('landlord.contracts.partials._details', [
            'reservation' => $reservation,
            'code' => $code,
            'showTenantDetails' => Gate::allows('viewTenantDetails', $reservation),
        ]);
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
                    'Your lease was renewed',
                    'Your lease now runs until '.$renewal->new_move_out_date->format('M j, Y').'. Rent from '
                        .$renewal->rent_effective_from->format('F Y').' is ₱'.number_format((float) $renewal->new_monthly_rent, 2).'.',
                    route('tenancy.show', $locked),
                );
            }

            return $renewal;
        });

        if (! $renewed) {
            return back()->with('error', 'This contract can no longer be renewed.');
        }

        return redirect()->route('landlord.contracts.index', ['open' => $reservation->reservation_id])
            ->with('success', 'Contract renewed until '.$renewed->new_move_out_date->format('M j, Y').'.');
    }

    /** Tenants on this landlord's list, for the Add contract picker: those without a live contract first. */
    private function tenantOptions()
    {
        return LandlordTenant::where('landlord_id', Auth::id())->visible()
            ->with('tenant:user_id,first_name,last_name')
            ->get()
            ->map(fn (LandlordTenant $row) => [
                'id' => $row->landlord_tenant_id,
                'label' => $row->code().' · '.trim($row->tenant?->first_name.' '.$row->tenant?->last_name),
            ])
            ->sortBy('label')
            ->values();
    }

    /** Units a contract can be added on: approved, available, and with no live reservation (LeaseContractCreator's rule). */
    private function unitOptions()
    {
        return Property::where('landlord_id', Auth::id())
            ->where('verification_status', 'Approved')
            ->with(['units' => fn ($q) => $q
                ->where('verification_status', 'Approved')
                ->where('availability_status', 'Available')
                ->whereDoesntHave('reservations', fn ($r) => $r->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES))
                ->orderBy('unit_label')])
            ->orderBy('title')
            ->get()
            ->filter(fn (Property $p) => $p->units->isNotEmpty())
            ->map(fn (Property $p) => [
                'id' => $p->property_id,
                'title' => $p->title,
                'terms' => $p->default_lease_terms,
                'units' => $p->units->map(fn ($u) => [
                    'id' => $u->unit_id,
                    'label' => $u->unit_label,
                    'rent' => (float) $u->rental_fee,
                    'deposit' => (float) ($u->security_deposit ?? 0),
                    'limit' => $u->occupancy_limit,
                ])->values(),
            ])
            ->values();
    }
}
