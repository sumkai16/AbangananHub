<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreWalkInTenantRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Payment;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Mobile equivalent of Landlord\WalkInTenantController::store. Reuses the
 * exact same StoreWalkInTenantRequest — its rules are framework validation,
 * not view-coupled, so there's nothing web-specific to fork. See that
 * controller's docblock for why this is the second way a tenancy is born.
 */
class WalkInTenantController extends Controller
{
    public function store(StoreWalkInTenantRequest $request): JsonResponse
    {
        $data = $request->validated();
        $landlordId = auth()->id();

        $reservation = DB::transaction(function () use ($data, $landlordId) {
            $unit = PropertyUnit::whereKey($data['unit_id'])->lockForUpdate()->firstOrFail();
            $property = $unit->property;

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

            $unit->update(['availability_status' => 'Occupied']);

            if (! empty($data['initial_amount'])) {
                $this->recordInitialPayment($reservation, $data, $landlordId);
            }

            return $reservation;
        });

        return response()->json([
            'data' => new ReservationResource($reservation->load(['unit', 'property', 'tenant'])),
        ], 201);
    }

    private function resolveTenant(array $data, int $landlordId): User
    {
        if (! empty($data['existing_tenant_id'])) {
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
