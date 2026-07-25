<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreWalkInTenantRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The second way a tenancy comes into existence.
 *
 * Everything else in the app arrives through the inquiry pipeline
 * (Inquiry -> Under Negotiation -> Pending Rental Agreement -> Signed ->
 * Occupied) with a PayMongo escrow in the middle. A walk-in was agreed offline
 * and is being written down after the fact, so it lands directly on 'Occupied'
 * with no conversation, no agreement and no escrow — the money, if any, already
 * changed hands in person.
 *
 * Nothing here is platform-verified. The landlord asserted all of it, which is
 * why the tenant row carries is_walk_in and any payment carries recorded_by.
 */
class WalkInTenantController extends Controller
{
    public function create()
    {
        $landlordId = Auth::id();

        // Only units a tenant could actually be placed in: approved by an admin
        // and not currently spoken for. Showing anything else would just fail
        // the guard in store() after the landlord filled the whole form.
        $properties = Property::where('landlord_id', $landlordId)
            ->where('verification_status', 'Approved')
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

        return view('landlord.tenants.walk-in.create', compact('properties', 'existingTenants'));
    }

    public function store(StoreWalkInTenantRequest $request)
    {
        $data = $request->validated();
        $landlordId = Auth::id();

        $reservation = DB::transaction(function () use ($data, $landlordId) {
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
                'rental_status'        => 'Occupied',
                'remarks'              => $data['notes'] ?? null,
            ]);

            // Fires PropertyUnitObserver, which logs the occupancy activity.
            $unit->update(['availability_status' => 'Occupied']);

            if (! empty($data['initial_amount'])) {
                $this->recordInitialPayment($reservation, $data, $landlordId);
            }

            return $reservation;
        });

        return redirect()
            ->route('landlord.tenancies.show', $reservation)
            ->with('success', 'Walk-in tenant added and the unit is now marked occupied.');
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

    /**
     * Move-in money the landlord collected in person.
     *
     * Status 'Paid', never 'Held' — escrow protects a tenant who paid a
     * stranger through the platform before getting keys. This tenant handed
     * over cash at a door they were already standing in; there is nothing left
     * to hold and nothing for the platform to release.
     */
    private function recordInitialPayment(Reservation $reservation, array $data, int $landlordId): void
    {
        Payment::create([
            'reservation_id' => $reservation->reservation_id,
            'payment_type'   => $data['initial_type'] ?? 'Initial',
            'amount'         => $data['initial_amount'],
            'payment_method' => $data['payment_method'],
            'status'         => 'Paid',
            'paid_at'        => $data['payment_date'] ?? now(),
            'reference_no'   => $data['reference_no'] ?? null,
            'recorded_by'    => $landlordId,
        ]);
    }
}
