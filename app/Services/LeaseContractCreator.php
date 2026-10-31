<?php

namespace App\Services;

use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Support\LeaseTerms;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A contract the landlord writes down for a tenant they already know: the Contracts page's
 * "Add contract" and the mobile walk-in endpoint all end here. No conversation, no online agreement: the landlord
 * asserts all of it, which is why the tenant row carries is_walk_in and any payment carries recorded_by.
 *
 * Lands on Occupied when the move-in date is today or earlier, or Reserved when it is still ahead; a Reserved one
 * becomes Occupied through Reservation::confirmWalkInMoveIn().
 *
 * $data keys: unit_id, move_in_date, move_out_date, occupants_count, agreed_monthly_rent, rent_due_day, notes,
 * and optionally initial_amount, payment_method, payment_date, reference_no (see MoveInPaymentRecorder).
 */
class LeaseContractCreator
{
    public static function create(int $landlordId, User $tenant, array $data, ?UploadedFile $leaseFile = null): Reservation
    {
        // Stored before the transaction (a file write can't roll back), and deleted again if the transaction
        // fails, so a refused contract never leaves an orphaned lease on disk.
        $leasePath = $leaseFile?->store("leases/{$landlordId}", 'local');

        try {
            return DB::transaction(fn () => self::write($landlordId, $tenant, $data, $leasePath, $leaseFile?->getClientOriginalName()));
        } catch (\Throwable $e) {
            if ($leasePath) {
                Storage::disk('local')->delete($leasePath);
            }

            throw $e;
        }
    }

    private static function write(int $landlordId, User $tenant, array $data, ?string $leasePath, ?string $leaseName): Reservation
    {
        // Locked, not just checked: two tabs submitting the same unit could otherwise both pass the availability
        // check before either wrote a reservation, placing two tenants in one unit.
        $unit = PropertyUnit::whereKey($data['unit_id'])->lockForUpdate()->firstOrFail();
        $property = $unit->property;

        // unit_id came from a form field, so this is the IDOR surface. Being a landlord is not authorisation to
        // place a tenant in someone else's unit.
        abort_unless($property && $property->landlord_id === $landlordId, 403);

        abort_unless($unit->verification_status === 'Approved', 422, 'This unit is still awaiting admin approval.');

        abort_unless($unit->availability_status === 'Available', 409, 'This unit is no longer available.');

        // The guard that actually matters. availability_status can read 'Available' while an online reservation
        // is mid-pipeline against the same unit; a contract on top would double-book it.
        abort_if(
            Reservation::where('unit_id', $unit->unit_id)
                ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
                ->exists(),
            409,
            'This unit already has an active reservation.'
        );

        // Same <= today comparison the form requests use to decide whether initial_amount is required.
        $status = Carbon::parse($data['move_in_date'])->lte(Carbon::today()) ? 'Occupied' : 'Reserved';

        $reservation = Reservation::create([
            'property_id' => $property->property_id,
            'unit_id' => $unit->unit_id,
            'tenant_id' => $tenant->user_id,
            // No thread: nobody negotiated this on the platform, so the observer raises no chat notifications.
            'conversation_id' => null,
            'reservation_date' => now(),
            'target_move_in_date' => $data['move_in_date'],
            'target_move_out_date' => $data['move_out_date'] ?? null,
            'occupants_count' => $data['occupants_count'] ?? null,
            'agreed_monthly_rent' => $data['agreed_monthly_rent'] ?? $unit->rental_fee,
            'rent_due_day' => $data['rent_due_day'] ?? null,
            'rental_status' => $status,
            'remarks' => $data['notes'] ?? null,
            // Extra lease terms (Add contract prefills the property's standard terms); frozen into the snapshot below.
            'agreement_terms_notes' => $data['agreement_terms_notes'] ?? null,
        ]);

        // The lease this tenancy runs on, frozen now (it needs the new row's id for its reference).
        $reservation->update([
            'lease_snapshot' => LeaseTerms::snapshot($reservation),
            'lease_file_path' => $leasePath,
            'lease_file_name' => $leasePath ? $leaseName : null,
            'lease_uploaded_at' => $leasePath ? now() : null,
        ]);

        // Fires PropertyUnitObserver, which logs the occupancy activity.
        $unit->update(['availability_status' => $status]);

        if (! empty($data['initial_amount'])) {
            MoveInPaymentRecorder::record($reservation, $unit, $data, $landlordId);
        }

        return $reservation;
    }
}
