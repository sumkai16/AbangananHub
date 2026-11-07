<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\ViewingRequest;
use App\Services\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /** Rows shown in the action queue before "and N more". */
    private const QUEUE_LIMIT = 6;

    /** Notifications listed on the dashboard before "View all". */
    private const NOTIFICATION_LIMIT = 6;

    /** Days the dashboard's viewings card counts as "soon", today included. */
    private const VIEWING_DAYS = 14;

    /** Months the dashboard's viewing calendar holds: this one and the next two (the full calendar has the rest). */
    private const VIEWING_MONTHS = 3;

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
        )->pendingPayout()->sum('amount');

        $actionQueue = $this->actionQueue($landlordId, $propertyIds, $overdueItems);

        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        $notifications = $this->latestNotifications($landlordId);
        $setupAlerts = $this->setupAlerts($landlordId);

        return view('landlord.dashboard.index', [
            'totalUnits' => $totalUnits,
            'occupiedUnits' => $occupiedUnits,
            'availableUnits' => $availableUnits,
            'reservedUnits' => $reservedUnits,
            'rentRoll' => $rentRoll,
            'vacantRoll' => $vacantRoll,
            'openInquiries' => $openInquiries,
            'notifications' => $notifications,
            'properties' => $properties,
            'rent' => $rent,
            'pendingPayout' => $pendingPayout,
            'actionQueue' => $actionQueue->take(self::QUEUE_LIMIT),
            'actionQueueOverflow' => max(0, $actionQueue->count() - self::QUEUE_LIMIT),
            'greeting' => $greeting,
            'setupAlerts' => $setupAlerts,
            'viewings' => $this->viewingSchedule($landlordId),
        ]);
    }

    /**
     * This month's rent across every running tenancy, via the same RentLedger
     * the payments page uses so the two screens can never disagree.
     *
     * @return array{0: array<string, mixed>, 1: Collection} totals, then the
     *                                                       overdue tenancies (oldest first) for the action queue.
     */
    private function rentThisMonth(int $landlordId): array
    {
        $rows = Reservation::where('rental_status', 'Occupied')
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label,rental_fee', 'payments', 'depositCharges', 'lateFeeWaivers', 'renewals'])
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
                'url' => route('landlord.payments.ledger', $r),
                'panel' => 'Dashboard', // opens over the dashboard instead of leaving it (page panel)
            ]);
        }

        // Signed agreement, deposit held, keys not yet handed over — Clock 1.
        $handovers = Reservation::whereIn('property_id', $propertyIds)
            ->where('rental_status', 'Rental Agreement Signed')
            // Only money paid online and held: an unsigned-for or directly-paid move-in has no handover clock.
            ->whereHas('payments', fn ($p) => $p->where('status', 'Held'))
            ->whereNull('keys_turned_over_at')
            ->whereNull('move_in_disputed_at')
            ->with(['property:property_id,title', 'unit:unit_id,unit_label'])
            ->get();

        $inquiries = Reservation::whereIn('property_id', $propertyIds)
            ->where('rental_status', 'Inquiry')
            ->with(['property:property_id,title', 'unit:unit_id,unit_label'])
            ->oldest('created_at')
            ->get();

        // Both lists name the tenant, so load them together: one users query
        // instead of one per list.
        $handovers->concat($inquiries)->load('tenant');

        $handovers
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

        $inquiries
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

        // Chats, by the same rule as the Messages badge, so the queue and the sidebar say the same number.
        $unread = Conversation::unreadCountFor($landlordId);

        if ($unread > 0) {
            $items->push([
                'level' => 'warn',
                'title' => $unread . ' unread ' . ($unread === 1 ? 'chat' : 'chats'),
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

        $toConfirm = PaymentClaim::pending()
            ->whereHas('reservation.property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->count();

        if ($toConfirm > 0) {
            $alerts->push([
                'level' => 'danger',
                'text' => $toConfirm.' '.($toConfirm === 1 ? 'payment is' : 'payments are').' waiting for you to confirm.',
                'cta' => 'Review',
                'url' => route('landlord.reservations.index'),
            ]);
        }

        if (Auth::user()->showsPayoutTools() && ! Auth::user()->hasPayoutDestination()) {
            $alerts->push([
                'level' => 'warn',
                'text' => 'Add your GCash details so AbangananHub can send your payouts.',
                'cta' => 'Add details',
                'url' => route('landlord.payouts.index').'#payout-account',
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
     * The latest notifications, the same rows as the bell. The old "Recent activity" feed was built from record
     * timestamps, so an edit read as something new and every row opened Reservations. A notification is something
     * that happened to this landlord and links to the page that answers it.
     */
    private function latestNotifications(int $landlordId): Collection
    {
        return Notification::where('user_id', $landlordId)
            ->where('type', '!=', 'message')
            // Several can land in the same second; the id breaks the tie so the newest is always first.
            ->latest()->latest('notification_id')
            ->limit(self::NOTIFICATION_LIMIT)
            ->get();
    }

    /**
     * The dashboard's viewings card and its calendar modal: every active (pending or confirmed) viewing from the
     * start of this month to the end of the month after next, grouped by day. The card shows the next one, how many
     * fall in the next two weeks and how many wait on the landlord's answer; the modal draws the months as a calendar.
     * Blocked days, offline viewings and answering stay on Reservations > Viewings (Landlord\ViewingController).
     *
     * @return array{months: Collection, byDay: Collection, next: ?ViewingRequest, soon: int, toAnswer: int, needsAnswerDays: array<int, string>}
     */
    private function viewingSchedule(int $landlordId): array
    {
        $today = now()->startOfDay();
        $firstMonth = $today->copy()->startOfMonth();
        $months = collect(range(0, self::VIEWING_MONTHS - 1))->map(fn ($i) => $firstMonth->copy()->addMonthsNoOverflow($i));

        $viewings = ViewingRequest::where('landlord_id', $landlordId)
            ->active()
            ->whereBetween('scheduled_at', [$firstMonth, $months->last()->copy()->endOfMonth()])
            ->with(['tenant:user_id,first_name,last_name', 'property:property_id,title', 'unit:unit_id,unit_label'])
            ->orderBy('scheduled_at')
            ->get();

        $awaiting = $viewings->filter(fn (ViewingRequest $v) => $v->isOpen() && $v->awaitsResponseFrom($landlordId));
        $soonEnd = $today->copy()->addDays(self::VIEWING_DAYS);

        return [
            'months' => $months,
            'byDay' => $viewings->groupBy(fn (ViewingRequest $v) => $v->scheduled_at->toDateString()),
            'next' => $viewings->first(fn (ViewingRequest $v) => $v->scheduled_at->gte(now())),
            'soon' => $viewings->filter(fn (ViewingRequest $v) => $v->scheduled_at->gte($today) && $v->scheduled_at->lt($soonEnd))->count(),
            'toAnswer' => $awaiting->count(),
            'needsAnswerDays' => $awaiting->map(fn (ViewingRequest $v) => $v->scheduled_at->toDateString())->unique()->values()->all(),
        ];
    }
}
