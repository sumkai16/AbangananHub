<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    /**
     * Current tenants (occupied reservations) with the page's search and
     * property filters applied. Shared by index() and export().
     */
    private function filteredQuery(Request $request)
    {
        $landlordId = Auth::user()->user_id;

        $query = Reservation::where('rental_status', 'Occupied')
            ->whereHas('property', fn($q) => $q->where('landlord_id', $landlordId));

        if ($search = $request->input('search')) {
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($propertyId = $request->input('property')) {
            $query->where('property_id', $propertyId);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $reservations = $this->filteredQuery($request)
            ->with(['tenant', 'property.media', 'unit', 'conversation', 'payments'])
            ->latest('reservation_date')->paginate(12)->withQueryString();

        // Rent standing per card, so each knows whether the tenant has anything
        // to be reminded about. The ledger runs in memory off the already-loaded
        // payments, and the page shows at most 12 tenancies.
        $ledger = $reservations->getCollection()->mapWithKeys(fn (Reservation $r) => [
            $r->reservation_id => RentLedger::for($r)->summary(),
        ]);

        $properties = Auth::user()->properties()
            ->where('verification_status', 'Approved')
            ->orderBy('title')
            ->get(['property_id', 'title']);

        return view('landlord.tenants.index', compact('reservations', 'properties', 'ledger'));
    }

    /**
     * CSV of the currently filtered tenants. Streamed and chunked.
     */
    public function export(Request $request)
    {
        $filename = 'abangananhub-tenants-' . now()->format('Y-m-d') . '.csv';

        $query = $this->filteredQuery($request)
            ->with(['tenant:user_id,first_name,last_name,email,contact_number,is_walk_in',
                'property:property_id,title', 'unit:unit_id,unit_label,rental_fee']);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Tenant', 'Type', 'Email', 'Contact', 'Property', 'Unit',
                'Monthly Rent', 'Move In', 'Move Out', 'Occupants',
            ]);

            $query->chunk(200, function ($reservations) use ($handle) {
                foreach ($reservations as $reservation) {
                    $tenant = $reservation->tenant;

                    fputcsv($handle, [
                        $tenant ? trim($tenant->first_name . ' ' . $tenant->last_name) : '',
                        $tenant?->is_walk_in ? 'Walk-in' : 'Platform',
                        $tenant->email ?? '',
                        $tenant->contact_number ?? '',
                        $reservation->property->title ?? '',
                        $reservation->unit->unit_label ?? '',
                        $reservation->unit->rental_fee ?? '',
                        optional($reservation->target_move_in_date)->format('Y-m-d'),
                        optional($reservation->target_move_out_date)->format('Y-m-d'),
                        $reservation->occupants_count ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}