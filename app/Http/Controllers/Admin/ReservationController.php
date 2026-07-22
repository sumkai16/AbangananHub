<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    private const STATUSES = [
        'Inquiry',
        'Under Negotiation',
        'Pending Rental Agreement',
        'Rental Agreement Signed',
        'Occupied',
        'Cancelled',
        'Rejected',
    ];

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'all');

        $base = Reservation::with(['property.media', 'property.landlord', 'unit', 'tenant'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('tenant', fn($t) =>
                        $t->where('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name',  'like', "%{$search}%")
                          ->orWhere('email',       'like', "%{$search}%")
                    )->orWhereHas('property', fn($p) =>
                        $p->where('title',   'like', "%{$search}%")
                          ->orWhere('address', 'like', "%{$search}%")
                    );
                });
            });

        $counts = ['all' => (clone $base)->count()];
        foreach (self::STATUSES as $s) {
            $counts[$s] = (clone $base)->where('rental_status', $s)->count();
        }

        if (in_array($status, self::STATUSES, true)) {
            $base->where('rental_status', $status);
        }

        // Both entry points to review land in move_in_disputed_at: a tenant
        // reporting a missing turnover, and the nightly job timing one out.
        if ($request->query('filter') === 'disputed') {
            $base->whereNotNull('move_in_disputed_at');
        }

        $disputedCount = Reservation::whereNotNull('move_in_disputed_at')->count();

        $reservations = $base->latest()->paginate(15)->withQueryString();

        return view('admin.reservations.index', compact('reservations', 'counts', 'status', 'search', 'disputedCount'));
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'property.landlord',
            'property.media',
            'unit',
            'tenant',
            'payments',
            'conversation',
        ]);

        return view('admin.reservations.show', compact('reservation'));
    }

    public function forceCancel(Request $request, Reservation $reservation)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            abort_if(in_array($locked->rental_status, ['Occupied', 'Cancelled', 'Rejected'], true), 409, 'This reservation cannot be cancelled at its current status.');

            $locked->rental_status = 'Cancelled';
            if ($request->filled('admin_note')) {
                $locked->remarks = '[Admin] ' . $request->admin_note;
            }
            $locked->save();

            // Free the unit if it was reserved
            if ($locked->unit && in_array($locked->unit->availability_status, ['Reserved', 'Occupied'], true)) {
                $otherActive = Reservation::where('unit_id', $locked->unit_id)
                    ->where('reservation_id', '!=', $locked->reservation_id)
                    ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
                    ->exists();

                if (!$otherActive) {
                    $locked->unit->update(['availability_status' => 'Available']);
                }
            }
        });

        return back()->with('success', 'Reservation has been cancelled by admin.');
    }

    public function forceReject(Request $request, Reservation $reservation)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            abort_if(in_array($locked->rental_status, ['Occupied', 'Cancelled', 'Rejected'], true), 409, 'This reservation cannot be rejected at its current status.');

            $locked->rental_status    = 'Rejected';
            $locked->rejection_reason = $request->filled('admin_note')
                ? '[Admin] ' . $request->admin_note
                : '[Admin action]';
            $locked->save();

            if ($locked->unit && $locked->unit->availability_status === 'Reserved') {
                $otherActive = Reservation::where('unit_id', $locked->unit_id)
                    ->where('reservation_id', '!=', $locked->reservation_id)
                    ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
                    ->exists();

                if (!$otherActive) {
                    $locked->unit->update(['availability_status' => 'Available']);
                }
            }
        });

        return back()->with('success', 'Reservation has been rejected by admin.');
    }
}
