<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\TenantRequest;
use App\Models\LandlordTenant;
use App\Models\Reservation;
use App\Models\TenantDetail;
use App\Models\User;
use App\Support\TenantInvites;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The Tenants page: the landlord's own tenant list (landlord_tenants), so a tenant can be added before any
 * contract. Status is read from the tenant's reservations here (LandlordTenant::statusFrom), never stored.
 * Contracts live on the Contracts page. See plans/landlord-tenants-contracts-direct-payments.md, phase 3.
 */
class TenantController extends Controller
{
    /** Reservation statuses that stop a tenant being removed from the list: anything still in progress. */
    private const LIVE_STATUSES = [
        'Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Reserved', 'Occupied',
    ];

    public function index(Request $request)
    {
        $rows = $this->rows();

        $stats = [
            'total' => $rows->count(),
            'active' => $rows->where('status', 'Active')->count(),
            'pending' => $rows->where('status', 'Pending')->count(),
        ];

        $filtered = $rows
            ->when($request->input('status'), fn ($c, $s) => $c->where('status', ucfirst($s)))
            ->when($request->integer('property'), fn ($c, $id) => $c->filter(
                fn ($r) => $r['reservations']->contains('property_id', $id)))
            ->when(trim((string) $request->input('search')), function ($c, $search) {
                $needle = Str::lower($search);

                return $c->filter(fn ($r) => Str::contains(Str::lower($r['name'].' '.$r['tenant']->email.' '.$r['code']), $needle));
            })
            ->values();

        $properties = Auth::user()->properties()->where('verification_status', 'Approved')
            ->orderBy('title')->get(['property_id', 'title']);

        return view('landlord.tenants.index', ['rows' => $filtered, 'stats' => $stats, 'properties' => $properties]);
    }

    /** Add Tenant: a placeholder account the tenant can claim through an email invite. */
    public function store(TenantRequest $request)
    {
        $data = $request->validated();
        $landlord = $request->user();

        $row = DB::transaction(function () use ($data, $landlord) {
            $tenant = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                // Unknowable until the tenant accepts an invite and chooses their own.
                'password' => Hash::make(Str::random(40)),
                'contact_number' => $data['contact_number'] ?? null,
                'account_status' => 'inactive',
                'is_walk_in' => true,
                'created_by_landlord_id' => $landlord->user_id,
            ]);
            $tenant->assignRole('Tenant');

            TenantDetail::saveFor($tenant, $data);

            return LandlordTenant::ensureFor($landlord->user_id, $tenant->user_id);
        });

        $invited = TenantInvites::send($row->tenant, $landlord);

        return redirect()->route('landlord.tenants.index')->with('success', $row->code().' added as Pending.'
            .($invited ? ' We emailed them an invite to set a password.' : ' Add their email later to invite them.'));
    }

    /** The view modal's body, fetched when it opens so emergency contacts are not embedded in the list. */
    public function show(LandlordTenant $landlordTenant)
    {
        Gate::authorize('view', $landlordTenant);

        $landlordTenant->load('tenant.tenantDetail');
        $reservations = $landlordTenant->reservations()->with(['unit', 'property'])->latest('reservation_date')->get();

        return view('landlord.tenants.partials._details', [
            'row' => $landlordTenant,
            'tenant' => $landlordTenant->tenant,
            'reservations' => $reservations,
            'status' => LandlordTenant::statusFrom($reservations->pluck('rental_status')),
            'canEdit' => Gate::allows('update', $landlordTenant),
            'canInvite' => Gate::allows('invite', $landlordTenant),
        ]);
    }

    public function update(TenantRequest $request, LandlordTenant $landlordTenant)
    {
        Gate::authorize('update', $landlordTenant);

        $data = $request->validated();
        $tenant = $landlordTenant->tenant;
        $emailChanged = ($data['email'] ?? null) !== $tenant->email;

        $tenant->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'contact_number' => $data['contact_number'] ?? null,
        ]);
        TenantDetail::saveFor($tenant, $data);

        $invited = $emailChanged && TenantInvites::send($tenant, $request->user());

        return redirect()->route('landlord.tenants.index')
            ->with('success', 'Tenant details saved.'.($invited ? ' We emailed an invite to the new address.' : ''));
    }

    public function invite(Request $request, LandlordTenant $landlordTenant)
    {
        Gate::authorize('invite', $landlordTenant);

        TenantInvites::send($landlordTenant->tenant, $request->user());

        return back()->with('success', 'Invite sent to '.$landlordTenant->tenant->email.'.');
    }

    /**
     * Refused while anything is still in progress with this tenant. A tenant this landlord added by hand who never
     * had a single reservation (with anyone) is deleted outright; anyone else is only taken off the list, because
     * deleting the account would cascade away payments and rent history (RULES.md).
     */
    public function destroy(LandlordTenant $landlordTenant)
    {
        Gate::authorize('delete', $landlordTenant);

        $result = DB::transaction(function () use ($landlordTenant) {
            $row = LandlordTenant::whereKey($landlordTenant->getKey())->lockForUpdate()->firstOrFail();
            $tenant = $row->tenant;

            if ($row->reservations()->whereIn('rental_status', self::LIVE_STATUSES)->exists()) {
                return 'live';
            }

            $ownPlaceholder = $tenant->is_walk_in && $tenant->created_by_landlord_id === $row->landlord_id;

            if ($ownPlaceholder && ! Reservation::where('tenant_id', $tenant->user_id)->exists()) {
                $tenant->delete();

                return 'deleted';
            }

            $row->update(['archived_at' => now()]);

            return 'archived';
        });

        return match ($result) {
            'live' => back()->with('error', 'This tenant has a contract or booking in progress. End or cancel it on the Contracts page first.'),
            'deleted' => back()->with('success', 'Tenant deleted.'),
            default => back()->with('success', 'Tenant removed from your list. Their past contracts and payments stay in your records.'),
        };
    }

    /** CSV of the landlord's whole list (Tenant ID, status, contact, current unit). */
    public function export()
    {
        $rows = $this->rows();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tenant ID', 'Tenant', 'Status', 'Email', 'Contact', 'Property', 'Unit', 'Has own login']);

            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['code'], $r['name'], $r['status'], $r['tenant']->email ?? '', $r['tenant']->contact_number ?? '',
                    $r['current']?->property?->title ?? '', $r['current']?->unit?->unit_label ?? '',
                    $r['tenant']->is_walk_in ? 'No' : 'Yes',
                ]);
            }

            fclose($handle);
        }, 'abangananhub-tenants-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Every visible row on this landlord's list with what the page needs: status, the current contract, the chat
     * to open and the open-concern count. Two queries in all; a landlord's list is tens of rows, not thousands.
     */
    private function rows(): Collection
    {
        $landlordId = Auth::id();

        $list = LandlordTenant::where('landlord_id', $landlordId)->visible()
            ->with('tenant')->orderBy('tenant_number')->get();

        $reservations = Reservation::whereIn('tenant_id', $list->pluck('tenant_id'))
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->with(['unit:unit_id,unit_label', 'property:property_id,title'])
            ->withCount(['concerns as open_concerns_count' => fn ($q) => $q->unresolved()])
            ->latest('reservation_date')
            ->get()
            ->groupBy('tenant_id');

        return $list->filter(fn (LandlordTenant $row) => $row->tenant)->map(function (LandlordTenant $row) use ($reservations) {
            $own = $reservations->get($row->tenant_id, collect());
            $current = $own->first(fn ($r) => in_array($r->rental_status, LandlordTenant::ACTIVE_STATUSES, true));
            $chatWith = $row->tenant->is_walk_in ? null
                : $own->first(fn ($r) => in_array($r->rental_status, ['Occupied', 'Completed'], true));

            return [
                'row' => $row,
                'tenant' => $row->tenant,
                'code' => $row->code(),
                'name' => trim($row->tenant->first_name.' '.$row->tenant->last_name),
                'status' => LandlordTenant::statusFrom($own->pluck('rental_status')),
                'reservations' => $own,
                'current' => $current,
                'chat' => $chatWith,
                'openConcerns' => (int) $own->sum('open_concerns_count'),
                'canEdit' => Gate::allows('update', $row),
            ];
        })->values();
    }
}
