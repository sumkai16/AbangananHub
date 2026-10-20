<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Support\LeaseTerms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The landlord's side of a tenancy's lease: the printable document (for a
 * walk-in to sign on paper, or to check what an online tenant signed) and
 * the signed copy attached to it. See plans/formal-lease-agreement.md.
 *
 * The file lives on the private `local` disk and is only ever served through
 * this policy-gated controller — same as property_documents.
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
            'isLandlordView'  => true,
        ]);
    }

    public function upload(Request $request, Reservation $reservation)
    {
        Gate::authorize('manageLease', $reservation);

        $request->validate([
            'lease_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'lease_file.required' => 'Choose the signed lease file to upload.',
            'lease_file.mimes'    => 'Upload the signed lease as a PDF, JPG or PNG.',
            'lease_file.max'      => 'The lease file must be 10 MB or smaller.',
        ]);

        $file = $request->file('lease_file');
        $path = $file->store("leases/{$reservation->property->landlord_id}", 'local');

        try {
            $oldPath = DB::transaction(function () use ($reservation, $path, $file) {
                $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
                $old = $locked->lease_file_path;

                $locked->update([
                    'lease_file_path'   => $path,
                    'lease_file_name'   => $file->getClientOriginalName(),
                    'lease_uploaded_at' => now(),
                    // A tenancy issued before snapshots existed gets one now,
                    // so the printable lease matches what was just attached.
                    'lease_snapshot'    => $locked->lease_snapshot ?: LeaseTerms::snapshot($locked),
                ]);

                return $old;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        // Only after the row points at the new file.
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', $oldPath ? 'Signed lease replaced.' : 'Signed lease uploaded.');
    }

    public function file(Reservation $reservation)
    {
        Gate::authorize('manageLease', $reservation);

        abort_unless($reservation->lease_file_path && Storage::disk('local')->exists($reservation->lease_file_path), 404);

        $disk = Storage::disk('local');

        // Inline, so a PDF or photo opens in the browser tab instead of
        // downloading — landlords mostly want to glance at it on a phone.
        return response()->file($disk->path($reservation->lease_file_path), [
            'Content-Type'        => $disk->mimeType($reservation->lease_file_path),
            'Content-Disposition' => 'inline; filename="' . addslashes($reservation->lease_file_name ?? 'lease') . '"',
        ]);
    }
}
