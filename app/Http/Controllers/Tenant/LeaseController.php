<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Support\LeaseTerms;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The tenant's read-only view of their own lease — what they e-signed, or
 * the printable document plus whatever signed copy the landlord attached.
 * Mirrors Landlord\LeaseController (same policy, same LeaseTerms snapshot),
 * but file() is gated by viewLease (tenant-or-landlord) rather than
 * manageLease (landlord-only) — uploading/replacing stays landlord-only.
 */
class LeaseController extends Controller
{
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewLease', $reservation);

        $reservation->load(['property.landlord', 'unit', 'tenant']);

        return view('leases.show', [
            'reservation'     => $reservation,
            'terms'           => LeaseTerms::for($reservation),
            'isLandlordView'  => false,
        ]);
    }

    public function file(Reservation $reservation)
    {
        Gate::authorize('viewLease', $reservation);

        abort_unless($reservation->lease_file_path && Storage::disk('local')->exists($reservation->lease_file_path), 404);

        $disk = Storage::disk('local');

        return response()->file($disk->path($reservation->lease_file_path), [
            'Content-Type'        => $disk->mimeType($reservation->lease_file_path),
            'Content-Disposition' => 'inline; filename="' . addslashes($reservation->lease_file_name ?? 'lease') . '"',
        ]);
    }
}
