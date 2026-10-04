<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\LandlordBlockedDate;
use App\Models\LandlordViewingHour;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\ViewingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The Viewings tab of the landlord's Reservations page: every viewing on a
 * month calendar, the days the landlord blocked, and viewings the landlord
 * logs for visitors who arranged them offline (phone, Facebook, walk-in).
 *
 * Actions on online viewings (approve, decline, reschedule, cancel) post to
 * App\Http\Controllers\ViewingController, the same routes the chat uses.
 */
class ViewingController extends Controller
{
    public function index(Request $request)
    {
        $landlordId = Auth::id();

        $month = $this->month($request->query('month'));
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $with = ['tenant', 'property', 'unit', 'reservation'];

        $monthViewings = ViewingRequest::where('landlord_id', $landlordId)
            ->whereBetween('scheduled_at', [$gridStart, $gridEnd])
            ->whereIn('status', ViewingRequest::ACTIVE_STATUSES)
            ->with($with)
            ->orderBy('scheduled_at')
            ->get();

        $upcoming = ViewingRequest::where('landlord_id', $landlordId)
            ->active()
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->with($with)
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        // Same definition as the badge on the Reservations tab.
        $pendingCount = $upcoming
            ->filter(fn (ViewingRequest $v) => $v->isOpen() && $v->awaitsResponseFrom($landlordId))
            ->count();

        // One list, each viewing drawn once: the calendar filters it by day,
        // and with no day picked it shows what's coming up.
        $upcomingIds = $upcoming->pluck('viewing_id')->flip();
        $rows = $upcoming->concat($monthViewings)
            ->unique('viewing_id')
            ->sortBy('scheduled_at')
            ->values()
            ->map(function (ViewingRequest $v) use ($upcomingIds) {
                $v->setAttribute('is_upcoming', $upcomingIds->has($v->viewing_id));

                return $v;
            });

        $blockedDates = LandlordBlockedDate::where('landlord_id', $landlordId)
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()
            ->keyBy(fn ($b) => $b->date->toDateString());

        // Every future blocked day, for the date pickers.
        $blockedIsos = ViewingRequest::blockedIsosFor($landlordId);

        $properties = Property::where('landlord_id', $landlordId)
            ->where('verification_status', 'Approved')
            ->with(['units' => fn ($q) => $q->where('verification_status', 'Approved')->orderBy('unit_label')])
            ->orderBy('title')
            ->get(['property_id', 'title']);

        return view('landlord.viewings.index', [
            'month'         => $month,
            'gridStart'     => $gridStart,
            'gridEnd'       => $gridEnd,
            'byDay'         => $monthViewings->groupBy(fn ($v) => $v->scheduled_at->toDateString()),
            'rows'          => $rows,
            'upcomingCount' => $upcoming->count(),
            'pendingCount'  => $pendingCount,
            'blockedDates'  => $blockedDates,
            'blockedIsos'   => $blockedIsos,
            'properties'    => $properties,
            // Rows as saved (empty = never set), so the form can tell
            // "every day by default" apart from "the landlord chose this".
            'hoursSet'      => LandlordViewingHour::where('landlord_id', $landlordId)->exists(),
            'week'          => LandlordViewingHour::weekFor($landlordId),
            'hoursSummary'  => LandlordViewingHour::summaryFor($landlordId),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateOffline($request);
        $slot = Carbon::parse($data['scheduled_at']);
        $landlordId = Auth::id();

        if ($problem = ViewingRequest::slotProblem($slot, $landlordId, weeklyHours: false)) {
            return back()->withInput()->with('error', $problem);
        }

        ViewingRequest::create([
            'source'        => 'Offline',
            'property_id'   => $data['property_id'],
            'unit_id'       => $data['unit_id'] ?? null,
            'visitor_name'  => $data['visitor_name'],
            'visitor_phone' => $data['visitor_phone'] ?? null,
            'landlord_id'   => $landlordId,
            'scheduled_at'  => $slot,
            // Agreed already, by phone or in person — nobody else to confirm.
            'status'        => 'Confirmed',
            'proposed_by'   => $landlordId,
            'note'          => $data['note'] ?? null,
            'responded_at'  => now(),
        ]);

        return redirect()
            ->route('landlord.viewings.index', ['month' => $slot->format('Y-m')])
            ->with('success', "Viewing added for {$data['visitor_name']} on {$slot->format('M j \a\t g:i A')}.");
    }

    public function update(Request $request, ViewingRequest $viewing)
    {
        Gate::authorize('manageOffline', $viewing);

        $data = $this->validateOffline($request);
        $slot = Carbon::parse($data['scheduled_at']);

        // Keeping the same time is always allowed, even if that day has since
        // been blocked — only a new time has to pass the rules.
        if (! $slot->equalTo($viewing->scheduled_at)
            && ($problem = ViewingRequest::slotProblem($slot, $viewing->landlord_id, weeklyHours: false))) {
            return back()->withInput()->with('error', $problem);
        }

        $updated = DB::transaction(function () use ($viewing, $data, $slot) {
            $locked = ViewingRequest::whereKey($viewing->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                return false;
            }

            $locked->update([
                'property_id'   => $data['property_id'],
                'unit_id'       => $data['unit_id'] ?? null,
                'visitor_name'  => $data['visitor_name'],
                'visitor_phone' => $data['visitor_phone'] ?? null,
                'scheduled_at'  => $slot,
                'note'          => $data['note'] ?? null,
            ]);

            return true;
        });

        return $updated
            ? back()->with('success', 'Viewing updated.')
            : back()->with('error', 'This viewing is no longer active.');
    }

    public function destroy(ViewingRequest $viewing)
    {
        Gate::authorize('manageOffline', $viewing);

        // Kept as Cancelled rather than deleted, same as online viewings.
        $viewing->update(['status' => 'Cancelled', 'responded_at' => now()]);

        return back()->with('success', 'Viewing cancelled.');
    }

    /**
     * Replace the landlord's weekly viewing hours. Only affects times picked
     * from now on — viewings already booked outside the new hours stay, so
     * nobody's confirmed visit disappears because the landlord tidied up.
     */
    public function saveHours(Request $request)
    {
        $earliest = config('rentals.viewing_hours_earliest');
        $latest = config('rentals.viewing_hours_latest');

        $data = $request->validate([
            'days'          => ['required', 'array'],
            'days.*.on'     => ['nullable', 'boolean'],
            'days.*.start'  => ['required_if_accepted:days.*.on', 'nullable', 'integer', "between:{$earliest},{$latest}"],
            'days.*.end'    => ['required_if_accepted:days.*.on', 'nullable', 'integer', "between:{$earliest},{$latest}"],
        ]);

        $week = collect($data['days'])
            ->only(range(0, 6))
            ->filter(fn ($d) => ! empty($d['on']));

        if ($week->isEmpty()) {
            return back()->with('error', 'Pick at least one viewing day. To pause viewings for a while, block the days on the calendar instead.');
        }

        $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($week as $day => $d) {
            if ((int) $d['end'] <= (int) $d['start']) {
                return back()->with('error', "On {$names[$day]}, the end time has to be after the start time.");
            }
        }

        $landlordId = Auth::id();

        DB::transaction(function () use ($landlordId, $week) {
            LandlordViewingHour::where('landlord_id', $landlordId)->delete();

            foreach ($week as $day => $d) {
                LandlordViewingHour::create([
                    'landlord_id' => $landlordId,
                    'day_of_week' => $day,
                    'start_hour'  => (int) $d['start'],
                    'end_hour'    => (int) $d['end'],
                ]);
            }
        });

        return back()->with('success', 'Viewing hours saved. Tenants can only pick times inside them.');
    }

    public function block(Request $request)
    {
        $data = $request->validate([
            'date'   => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'date.after_or_equal' => 'You can only block today or a future day.',
        ]);

        $landlordId = Auth::id();
        $date = Carbon::parse($data['date'])->toDateString();

        // A confirmed visitor must never be stranded by a blocked day, so the
        // landlord deals with those viewings first.
        $onThatDay = ViewingRequest::where('landlord_id', $landlordId)
            ->active()
            ->whereDate('scheduled_at', $date)
            ->count();

        if ($onThatDay > 0) {
            $word = $onThatDay === 1 ? 'viewing' : 'viewings';

            return back()->with('error', "Reschedule or cancel the {$onThatDay} {$word} on this day first.");
        }

        LandlordBlockedDate::firstOrCreate(
            ['landlord_id' => $landlordId, 'date' => $date],
            ['reason' => $data['reason'] ?? null],
        );

        return back()->with('success', Carbon::parse($date)->format('l, M j') . ' is now blocked. Tenants can\'t pick it.');
    }

    public function unblock(LandlordBlockedDate $blockedDate)
    {
        abort_unless($blockedDate->landlord_id === Auth::id(), 403);

        $label = $blockedDate->date->format('l, M j');
        $blockedDate->delete();

        return back()->with('success', "{$label} is open for viewings again.");
    }

    private function validateOffline(Request $request): array
    {
        $data = $request->validate([
            'visitor_name'  => ['required', 'string', 'max:120'],
            'visitor_phone' => ['nullable', 'string', 'max:30'],
            'property_id'   => ['required', 'integer'],
            'unit_id'       => ['nullable', 'integer'],
            'scheduled_at'  => ['required', 'date'],
            'note'          => ['nullable', 'string', 'max:500'],
        ], [
            'visitor_name.required' => 'Enter the visitor\'s name.',
            'property_id.required'  => 'Choose which property they\'ll view.',
            'scheduled_at.required' => 'Pick a date and time for the viewing.',
        ]);

        // Ids come from form fields, so ownership is checked here: a tampered
        // id must not log a viewing against another landlord's property.
        $owns = Property::whereKey($data['property_id'])->where('landlord_id', Auth::id())->exists();
        abort_unless($owns, 403);

        if (! empty($data['unit_id'])) {
            $unitMatches = PropertyUnit::whereKey($data['unit_id'])
                ->where('property_id', $data['property_id'])
                ->exists();
            abort_unless($unitMatches, 422, 'That unit is not part of the chosen property.');
        }

        return $data;
    }

    private function month(?string $value): Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value . '-01')->startOfDay();
            } catch (\Throwable) {
                // Fall through to this month.
            }
        }

        return now()->startOfMonth();
    }
}
