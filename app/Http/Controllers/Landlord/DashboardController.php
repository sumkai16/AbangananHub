<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $landlordId = Auth::id();
        $propertyIds = Property::where('landlord_id', $landlordId)->pluck('property_id');

        $totalProperties = $propertyIds->count();

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();
        $totalUnits = $units->count();
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();
        $reservedUnits = $units->where('availability_status', 'Reserved')->count();

        $totalTenants = Reservation::whereIn('property_id', $propertyIds)
            ->where('rental_status', 'Occupied')
            ->distinct('tenant_id')
            ->count('tenant_id');

        // Per-property occupancy breakdown (approved listings only)
        $properties = Property::where('landlord_id', $landlordId)
            ->where('verification_status', 'Approved')
            ->with(['units', 'media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1)])
            ->get()
            ->map(function ($property) {
                $units = $property->units;
                return [
                    'title' => $property->title,
                    'property_id' => $property->property_id,
                    'property_type' => $property->property_type,
                    'address' => $property->address,
                    'total_units' => $units->count(),
                    'occupied_units' => $units->where('availability_status', 'Occupied')->count(),
                    'available_units' => $units->where('availability_status', 'Available')->count(),
                    'reserved_units' => $units->where('availability_status', 'Reserved')->count(),
                    'thumbnail' => optional($property->media->first())->media_url,
                ];
            });

        $pendingReservations = Reservation::whereIn('property_id', $propertyIds)
            ->where('rental_status', 'Inquiry')
            ->count();

        $unreadMessages = Message::whereHas('conversation', function ($q) use ($landlordId) {
            $q->where('landlord_id', $landlordId);
        })->where('sender_id', '!=', $landlordId)
            ->where('is_read', false)
            ->count();

        $newReviews = Review::whereIn('property_id', $propertyIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Reports this landlord FILED and that are still open — matching the
        // My Complaints page. It previously counted reports filed *against*
        // them (reported_user_id / their properties), which is moderation
        // state the admin owns: telling a landlord they have been reported
        // both leaks the queue and contradicts the confidentiality notice on
        // the complaints page.
        $openComplaints = Report::where('reporter_id', $landlordId)
            ->where('report_status', 'Pending')
            ->count();

        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        // Recent activity — derived from real unit and reservation timestamps, no fabricated data
        $recentUnitActivity = PropertyUnit::whereIn('property_id', $propertyIds)
            ->with('property')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function ($unit) {
                return [
                    'type' => 'unit',
                    'description' => "Unit \"{$unit->unit_label}\" in {$unit->property->title} was updated",
                    'status' => $unit->availability_status,
                    'timestamp' => $unit->updated_at,
                ];
            });

        $recentReservationActivity = Reservation::whereIn('property_id', $propertyIds)
            ->with(['property', 'unit', 'tenant'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function ($reservation) {
                return [
                    'type' => 'reservation',
                    'description' => "Reservation for {$reservation->property->title}"
                        . ($reservation->unit ? " ({$reservation->unit->unit_label})" : '')
                        . " by {$reservation->tenant->first_name} {$reservation->tenant->last_name} is {$reservation->rental_status}",
                    'status' => $reservation->rental_status,
                    'timestamp' => $reservation->updated_at,
                ];
            });

        $recentActivity = $recentUnitActivity
            ->concat($recentReservationActivity)
            ->sortByDesc('timestamp')
            ->take(5)
            ->values();

        return view('landlord.dashboard.index', [
            'totalProperties' => $totalProperties,
            'totalUnits' => $totalUnits,
            'occupiedUnits' => $occupiedUnits,
            'availableUnits' => $availableUnits,
            'reservedUnits' => $reservedUnits,
            'totalTenants' => $totalTenants,
            'recentActivity' => $recentActivity,
            'properties' => $properties,
            'pendingReservations' => $pendingReservations,
            'unreadMessages' => $unreadMessages,
            'newReviews' => $newReviews,
            'openComplaints' => $openComplaints,
            'greeting' => $greeting,
        ]);
    }
}
