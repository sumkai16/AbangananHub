<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\RecordsMoveInPayments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreWalkInTenantRequest;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Support\LeaseTerms;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The second way a tenancy comes into existence.
 *
 * Everything else in the app arrives through the inquiry pipeline
 * (Inquiry -> Under Negotiation -> Pending Rental Agreement -> Signed ->
 * Occupied) with a PayMongo escrow in the middle. A walk-in was agreed offline
 * and is being written down after the fact, with no conversation, no
 * agreement and no escrow — the money, if any, already changed hands in
 * person. It lands on 'Occupied' immediately if the move-in date is today or
 * earlier, or 'Reserved' if it's still ahead — see createWalkIn() — with
 * Reservation::confirmWalkInMoveIn() flipping a Reserved one to Occupied
 * later, no key-turnover ceremony involved.
 *
 * Nothing here is platform-verified. The landlord asserted all of it, which is
 * why the tenant row carries is_walk_in and any payment carries recorded_by.
 */
class WalkInTenantController extends Controller
{
    use RecordsMoveInPayments;

    public function create(Request $request)
    {
        $landlordId = Auth::id();

        // Arriving from a specific property's "Add Walk-in Tenant" action
        // narrows the gallery to that property alone, instead of making the
        // landlord re-find it among everything they own. Just a query param,
        // not a route-bound model — the query below still scopes by
        // landlord_id, so a tampered id simply yields no match rather than
        // leaking another landlord's property.
        $scopedPropertyId = $request->integer('property') ?: null;

        // Only units a tenant could actually be placed in: approved by an admin
        // and not currently spoken for. Showing anything else would just fail
        // the guard in store() after the landlord filled the whole form.
        $properties = Property::where('landlord_id', $landlordId)
            ->where('verification_status', 'Approved')
            ->when($scopedPropertyId, fn ($q) => $q->where('property_id', $scopedPropertyId))
            ->with([
                // property.media is the per-unit photo fallback in the picker.
                'media',
                'units' => fn ($q) => $q
                    ->where('verification_status', 'Approved')
                    ->where('availability_status', 'Available')
                    ->whereDoesntHave('reservations', fn ($r) => $r
                        ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES))
                    ->with('media')
                    ->orderBy('unit_label'),
            ])
            ->orderBy('title')
            ->get()
            ->filter(fn (Property $p) => $p->units->isNotEmpty())
            ->values();

        // Someone this landlord already recorded — a tenant changing units or
        // renewing shouldn't become a second person in the database.
        $existingTenants = Auth::user()->walkInTenants()
            ->orderBy('first_name')
            ->get(['user_id', 'first_name', 'last_name', 'email', 'contact_number']);

        return view('landlord.tenants.walk-in.create', compact('properties', 'existingTenants', 'scopedPropertyId'));
    }

    public function store(StoreWalkInTenantRequest $request)
    {
        $data = $request->validated();
        $landlordId = Auth::id();

        // Stored before the transaction (a file write can't roll back), and
        // deleted again below if the transaction fails, so a refused walk-in
        // never leaves an orphaned lease on disk.
        $leaseFile = $request->file('lease_file');
        $leasePath = $leaseFile?->store("leases/{$landlordId}", 'local');

        try {
            $reservation = $this->createWalkIn($data, $landlordId, $leasePath, $leaseFile?->getClientOriginalName());
        } catch (\Throwable $e) {
            if ($leasePath) {
                Storage::disk('local')->delete($leasePath);
            }
            throw $e;
        }

        // Reflects whatever createWalkIn() actually decided, rather than
        // re-deriving the date comparison here and risking the two drifting.
        $movedInNow = $reservation->rental_status === 'Occupied';

        return redirect()
            ->route('landlord.tenancies.show', $reservation)
            ->with('success', $movedInNow
                ? ($leasePath
                    ? 'Walk-in tenant added with their signed lease. The unit is now marked occupied.'
                    : 'Walk-in tenant added and the unit is now marked occupied. Upload the signed lease from this page when you have it.')
                : ($leasePath
                    ? 'Walk-in tenant added with their signed lease. The unit is reserved until they move in.'
                    : 'Walk-in tenant added and the unit is reserved until they move in. Upload the signed lease from this page when you have it.'));
    }

    private function createWalkIn(array $data, int $landlordId, ?string $leasePath, ?string $leaseName): Reservation
    {
        return DB::transaction(function () use ($data, $landlordId, $leasePath, $leaseName) {
            // Locked, not just checked: two tabs submitting the same unit could
            // otherwise both pass the availability check before either wrote a
            // reservation, placing two tenants in one unit.
            $unit = PropertyUnit::whereKey($data['unit_id'])->lockForUpdate()->firstOrFail();
            $property = $unit->property;

            // Route-model-bound-adjacent: unit_id came from a form field, so
            // this is the IDOR surface. Being a landlord is not authorisation
            // to place a tenant in someone else's unit.
            abort_unless($property && $property->landlord_id === $landlordId, 403);

            abort_unless(
                $unit->verification_status === 'Approved',
                422,
                'This unit is still awaiting admin approval.'
            );

            abort_unless(
                $unit->availability_status === 'Available',
                409,
                'This unit is no longer available.'
            );

            // The guard that actually matters. availability_status can read
            // 'Available' while a platform reservation is mid-pipeline against
            // the same unit — placing a walk-in on top would double-book it and
            // strand a tenant who is already paying into escrow.
            abort_if(
                Reservation::where('unit_id', $unit->unit_id)
                    ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
                    ->exists(),
                409,
                'This unit already has an active reservation.'
            );

            $tenant = $this->resolveTenant($data, $landlordId);

            // A walk-in agreed offline can be backdated (moved in already) or
            // forward-dated (reserved now, moving in later) — only the former
            // should occupy the unit immediately. Same <= today comparison
            // StoreWalkInTenantRequest uses to decide whether initial_amount
            // is required, so the two can't drift apart.
            $movesInNow = Carbon::parse($data['move_in_date'])->lte(Carbon::today());
            $status = $movesInNow ? 'Occupied' : 'Reserved';

            $reservation = Reservation::create([
                'property_id'          => $property->property_id,
                'unit_id'              => $unit->unit_id,
                'tenant_id'            => $tenant->user_id,
                // No thread: nobody negotiated this on the platform. The
                // reservation observers key off conversation_id and correctly
                // stay silent, so a walk-in raises no chat notifications.
                'conversation_id'      => null,
                'reservation_date'     => now(),
                'target_move_in_date'  => $data['move_in_date'],
                'target_move_out_date' => $data['move_out_date'] ?? null,
                'occupants_count'      => $data['occupants_count'] ?? null,
                'agreed_monthly_rent'  => $data['agreed_monthly_rent'] ?? $unit->rental_fee,
                'rent_due_day'         => $data['rent_due_day'] ?? null,
                'rental_status'        => $status,
                'remarks'              => $data['notes'] ?? null,
            ]);

            // The lease this tenancy runs on, frozen now (it needs the new
            // row's id for its reference), plus the signed copy if attached.
            $reservation->update([
                'lease_snapshot'    => LeaseTerms::snapshot($reservation),
                'lease_file_path'   => $leasePath,
                'lease_file_name'   => $leasePath ? $leaseName : null,
                'lease_uploaded_at' => $leasePath ? now() : null,
            ]);

            // Fires PropertyUnitObserver, which logs the occupancy activity.
            $unit->update(['availability_status' => $status]);

            if (! empty($data['initial_amount'])) {
                $this->recordMoveInPayments($reservation, $unit, $data, $landlordId);
            }

            return $reservation;
        });
    }

    /**
     * An existing walk-in of this landlord's, or a new lightweight account.
     *
     * The new account is a real `users` row so `reservations.tenant_id` stays
     * NOT NULL and every tenant-facing view keeps working — but with an
     * unknowable random password and an 'inactive' status, so it can never be
     * logged into and the walk-in can never post a review or rating they did
     * not earn the standing for.
     */
    private function resolveTenant(array $data, int $landlordId): User
    {
        if (! empty($data['existing_tenant_id'])) {
            // Re-scoped rather than trusted: the request rule checked ownership
            // at validation time, and this is the query that acts on it.
            return User::where('user_id', $data['existing_tenant_id'])
                ->where('is_walk_in', true)
                ->where('created_by_landlord_id', $landlordId)
                ->firstOrFail();
        }

        $tenant = User::create([
            'first_name'             => $data['first_name'],
            'last_name'              => $data['last_name'],
            'email'                  => $data['email'] ?? null,
            'password'               => Hash::make(Str::random(40)),
            'contact_number'         => $data['contact_number'] ?? null,
            'account_status'         => 'inactive',
            'is_walk_in'             => true,
            'created_by_landlord_id' => $landlordId,
        ]);

        $tenant->assignRole('Tenant');

        return $tenant;
    }
}
