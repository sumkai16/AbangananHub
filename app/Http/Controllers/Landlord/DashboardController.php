<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\Review;
use App\Services\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /** Rows shown in the action queue before "and N more". */
    private const QUEUE_LIMIT = 6;

    public function index(Request $request)
    {
        $landlordId = Auth::id();
        $propertyIds = Property::where('landlord_id', $landlordId)->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();
        $totalUnits = $units->count();
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();
        $reservedUnits = $units->where('availability_status', 'Reserved')->count();

        // Portfolio health, kept apart from the rent/attention cards above it: what the rented units
        // bring in per month, what the vacant ones would, and how many inquiries are waiting.
        $rentRoll = (float) $units->where('availability_status', 'Occupied')->sum('rental_fee');
        $vacantRoll = (float) $units->where('availability_status', 'Available')->sum('rental_fee');
        $openInquiries = Reservation::whereIn('property_id', $propertyIds)->where('rental_status', 'Inquiry')->count();

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
            })
            // Emptiest first: the property that most needs a tenant is the one worth seeing.
            ->sortBy(fn ($p) => $p['total_units'] > 0 ? $p['occupied_units'] / $p['total_units'] : 0)
            ->values();

        [$rent, $overdueItems] = $this->rentThisMonth($landlordId);

        $pendingPayout = (float) Payment::whereHas(
            'reservation.property',
            fn ($q) => $q->where('landlord_id', $landlordId)
        )->where('payout_status', 'Pending Payout')->sum('amount');

        $actionQueue = $this->actionQueue($landlordId, $propertyIds, $overdueItems);

        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        $recentActivity = $this->recentActivity($landlordId, $propertyIds);
        $setupAlerts = $this->setupAlerts($landlordId);

        return view('landlord.dashboard.index', [
            'totalUnits' => $totalUnits,
            'occupiedUnits' => $occupiedUnits,
            'availableUnits' => $availableUnits,
            'reservedUnits' => $reservedUnits,
            'rentRoll' => $rentRoll,
            'vacantRoll' => $vacantRoll,
            'openInquiries' => $openInquiries,
            'recentActivity' => $recentActivity,
            'properties' => $properties,
            'rent' => $rent,
            'pendingPayout' => $pendingPayout,
            'actionQueue' => $actionQueue->take(self::QUEUE_LIMIT),
            'actionQueueOverflow' => max(0, $actionQueue->count() - self::QUEUE_LIMIT),
            'greeting' => $greeting,
            'setupAlerts' => $setupAlerts,
        ]);
    }

    /**
     * This month's rent across every running tenancy, via the same RentLedger
     * the payments page uses so the two screens can never disagree.
     *
     * @return array{0: array<string, mixed>, 1: Collection} totals, then the
     *         overdue tenancies (oldest first) for the action queue.
     */
    private function rentThisMonth(int $landlordId): array
    {
        $rows = Reservation::where('rental_status', 'Occupied')
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label,rental_fee', 'payments'])
            ->get()
            ->map(fn (Reservation $r) => ['reservation' => $r, 'summary' => RentLedger::for($r)->summary()]);

        $due = round($rows->sum(fn ($r) => $r['summary']['dueThisMonth']), 2);
        $collected = round($rows->sum(fn ($r) => $r['summary']['collectedThisMonth']), 2);

        $overdue = $rows
            ->filter(fn ($r) => $r['summary']['overdueAmount'] > 0)
            ->sortBy(fn ($r) => $r['summary']['oldestOverdue']['due_on']?->timestamp ?? PHP_INT_MAX)
            ->values();

        $rent = [
            'due' => $due,
            'collected' => $collected,
            'percent' => $due > 0 ? min(100, (int) round($collected / $due * 100)) : null,
            'overdueAmount' => round($overdue->sum(fn ($r) => $r['summary']['overdueAmount']), 2),
            'overdueCount' => $overdue->count(),
            'tenancies' => $rows->count(),
        ];

        return [$rent, $overdue];
    }

    /**
     * One list of everything waiting on this landlord, most urgent first.
     * Each item: level (danger|warn), title, meta, cta, url.
     */
    private function actionQueue(int $landlordId, Collection $propertyIds, Collection $overdueItems): Collection
    {
        $items = collect();

        foreach ($overdueItems as $row) {
            $r = $row['reservation'];
            $dueOn = $row['summary']['oldestOverdue']['due_on'] ?? null;
            $days = $dueOn ? (int) $dueOn->diffInDays(now()->startOfDay()) : null;

            $items->push([
                'level' => 'danger',
                'title' => "{$r->tenant->first_name} {$r->tenant->last_name} owes ₱" . number_format($row['summary']['overdueAmount']),
                'meta' => trim(($r->unit?->unit_label ? $r->unit->unit_label . ' · ' : '') . $r->property->title
                    . ($days ? " · {$days} " . ($days === 1 ? 'day' : 'days') . ' overdue' : ' · overdue')),
                'cta' => 'View ledger',
                'url' => route('landlord.tenancies.show', $r),
            ]);
        }

        // Signed agreement, deposit held, keys not yet handed over — Clock 1.
        Reservation::whereIn('property_id', $propertyIds)
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNull('keys_turned_over_at')
            ->whereNull('move_in_disputed_at')
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label'])
            ->get()
            ->sortBy(fn ($r) => $r->move_in_deadline_at?->timestamp ?? PHP_INT_MAX)
            ->each(function ($r) use ($items) {
                $late = $r->move_in_deadline_at?->isPast();
                $items->push([
                    'level' => $late ? 'danger' : 'warn',
                    'title' => 'Turn over the keys to ' . $r->tenant->first_name,
                    'meta' => trim(($r->unit?->unit_label ? $r->unit->unit_label . ' · ' : '') . $r->property->title
                        . ($r->move_in_deadline_at
                            ? ' · ' . ($late ? 'was due ' : 'due ') . $r->move_in_deadline_at->diffForHumans()
                            : '')),
                    'cta' => 'Schedule handover',
                    'url' => $r->conversation_id
                        ? route('conversations.index', ['active' => $r->conversation_id])
                        : route('landlord.reservations.index'),
                ]);
            });

        Reservation::whereIn('property_id', $propertyIds)
            ->where('rental_status', 'Inquiry')
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label'])
            ->oldest('created_at')
            ->get()
            ->each(function ($r) use ($items) {
                $items->push([
                    'level' => 'warn',
                    'title' => "Inquiry from {$r->tenant->first_name} {$r->tenant->last_name}",
                    'meta' => trim(($r->unit?->unit_label ? $r->unit->unit_label . ' · ' : '') . $r->property->title
                        . ' · ' . $r->created_at->diffForHumans()),
                    'cta' => 'Respond',
                    'url' => $r->conversation_id
                        ? route('conversations.index', ['active' => $r->conversation_id])
                        : route('landlord.reservations.index'),
                ]);
            });

        $unread = Message::whereHas('conversation', fn ($q) => $q->where('landlord_id', $landlordId))
            ->where('sender_id', '!=', $landlordId)
            ->where('is_read', false)
            ->count();

        if ($unread > 0) {
            $items->push([
                'level' => 'warn',
                'title' => $unread . ' unread ' . ($unread === 1 ? 'message' : 'messages'),
                'meta' => 'Waiting on your reply',
                'cta' => 'Open inbox',
                'url' => route('conversations.index'),
            ]);
        }

        return $items->sortBy(fn ($i) => $i['level'] === 'danger' ? 0 : 1)->values();
    }

    /**
     * Things blocking this landlord from earning: listings stuck in review,
     * rejected or unfinished, and a missing payout destination.
     * Each: level (danger|warn|info), text, cta, url.
     */
    private function setupAlerts(int $landlordId): Collection
    {
        $alerts = collect();
        $counts = ['rejected' => 0, 'suspended' => 0, 'draft' => 0, 'pending' => 0];

        Property::where('landlord_id', $landlordId)
            ->selectRaw('verification_status, publication_status, count(*) as c')
            ->groupBy('verification_status', 'publication_status')
            ->get()
            ->each(function ($row) use (&$counts) {
                $n = (int) $row->c;
                if ($row->publication_status === 'Draft') {
                    $counts['draft'] += $n;
                } elseif ($row->publication_status === 'Suspended') {
                    $counts['suspended'] += $n;
                } elseif ($row->verification_status === 'Rejected') {
                    $counts['rejected'] += $n;
                } elseif ($row->verification_status === 'Pending') {
                    $counts['pending'] += $n;
                }
            });

        $listings = fn (int $n) => $n . ' ' . ($n === 1 ? 'listing' : 'listings');

        if (! Auth::user()->hasPayoutDestination()) {
            $alerts->push([
                'level' => 'warn',
                'text' => 'Add your GCash details so AbangananHub can send your payouts.',
                'cta' => 'Add details',
                'url' => route('landlord.profile.edit'),
            ]);
        }
        if ($counts['rejected'] > 0) {
            $alerts->push([
                'level' => 'danger',
                'text' => $listings($counts['rejected']) . ' rejected by admin. Review and resubmit.',
                'cta' => 'Review',
                'url' => route('landlord.properties.index', ['status' => 'Rejected']),
            ]);
        }
        if ($counts['suspended'] > 0) {
            $alerts->push([
                'level' => 'danger',
                'text' => $listings($counts['suspended']) . ' suspended and hidden from tenants.',
                'cta' => 'View',
                'url' => route('landlord.properties.index'),
            ]);
        }
        if ($counts['draft'] > 0) {
            $alerts->push([
                'level' => 'warn',
                'text' => $listings($counts['draft']) . ' not finished yet, so tenants cannot see ' . ($counts['draft'] === 1 ? 'it' : 'them') . '.',
                'cta' => 'Continue setup',
                'url' => route('landlord.properties.index'),
            ]);
        }
        if ($counts['pending'] > 0) {
            $alerts->push([
                'level' => 'info',
                'text' => $listings($counts['pending']) . ' waiting for admin approval.',
                'cta' => 'View',
                'url' => route('landlord.properties.index', ['status' => 'Pending']),
            ]);
        }

        return $alerts;
    }

    /**
     * Payments, reservation changes and reviews merged into one newest-first
     * feed. Unit edits are deliberately left out: they say nothing a landlord
     * needs to act on.
     * Each: kind (payment|reservation|review), text, meta, timestamp, url.
     */
    private function recentActivity(int $landlordId, Collection $propertyIds): Collection
    {
        $titles = Property::where('landlord_id', $landlordId)->pluck('title', 'property_id');
        $where = fn ($r) => trim(($r->unit?->unit_label ? $r->unit->unit_label . ' · ' : '') . ($titles[$r->property_id] ?? ''));
        $name = fn ($u) => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: 'A tenant';

        $payments = Payment::whereHas('reservation.property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->whereIn('status', ['Paid', 'Held', 'Released'])
            ->where('amount', '>', 0)
            ->whereNull('voided_at')
            ->with(['reservation.tenant', 'reservation.unit:unit_id,unit_label'])
            ->latest('paid_at')
            ->limit(5)
            ->get()
            ->map(function ($p) use ($name, $where) {
                $r = $p->reservation;
                $amount = '₱' . number_format((float) $p->amount);
                $who = $name($r->tenant);
                $text = $p->status === 'Held'
                    ? "{$amount} held in escrow for {$who}"
                    : ($p->payment_type === 'Monthly'
                        ? "{$amount} rent received from {$who}"
                        : "{$amount} " . strtolower($p->payment_type) . " payment from {$who}");

                return [
                    'kind' => 'payment',
                    'text' => $text,
                    'meta' => $where($r),
                    'timestamp' => $p->paid_at ?? $p->created_at,
                    'url' => in_array($r->rental_status, ['Occupied', 'Completed'], true)
                        ? route('landlord.tenancies.show', $r)
                        : route('landlord.payments.index'),
                ];
            });

        $phrases = [
            'Inquiry' => 'sent an inquiry',
            'Negotiation' => 'is negotiating terms',
            'Rental Agreement Signed' => 'signed the rental agreement',
            'Occupied' => 'moved in',
            'Completed' => 'finished their tenancy',
            'Cancelled' => 'cancelled their reservation',
            'Rejected' => 'was declined',
        ];

        $reservations = Reservation::whereIn('property_id', $propertyIds)
            ->with(['tenant', 'unit:unit_id,unit_label'])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'kind' => 'reservation',
                'text' => $name($r->tenant) . ' ' . ($phrases[$r->rental_status] ?? 'is ' . strtolower($r->rental_status)),
                'meta' => $where($r),
                'timestamp' => $r->updated_at,
                'url' => $r->conversation_id
                    ? route('conversations.index', ['active' => $r->conversation_id])
                    : route('landlord.reservations.index'),
            ]);

        $reviews = Review::whereIn('property_id', $propertyIds)
            ->where('is_hidden', false)
            ->with('tenant')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($rv) => [
                'kind' => 'review',
                'text' => $name($rv->tenant) . ' left a ' . $rv->rating . '-star review',
                'meta' => $titles[$rv->property_id] ?? '',
                'timestamp' => $rv->created_at,
                'url' => route('landlord.reviews.index'),
            ]);

        return $payments->concat($reservations)->concat($reviews)
            ->sortByDesc('timestamp')
            ->take(5)
            ->values();
    }
}
