# Landlord: delete the Occupancy page, fold its keepers into Analytics

**Status:** implemented Aug 30 2026 — pending manual verification
**Date:** 2026-08-30
**Follows:** `plans/units-occupancy-deduplication.md`, which moved the unit list off Occupancy
onto Units. This finishes the job: what was left of Occupancy mostly duplicated Analytics.

---

## The problem

After the unit list moved to Units, `landlord/occupancy` held six stat tiles and two panels.
Against `landlord/analytics`:

| Occupancy | Analytics equivalent |
|---|---|
| 5 status stat tiles | Occupancy Overview donut — same four statuses. **Duplicate** |
| Occupancy Rate tile | `stats['occupancyRate']` stat card. **Duplicate** |
| — | Occupancy by Property (Analytics carries more) |
| **Vacancy Watch** | nothing |
| **Recent Activity timeline** | nothing |

Two thirds of the page restated Analytics. But a straight delete would lose:

1. **Vacancy Watch** — idle monthly rent in pesos, the five longest-empty units, days-vacant
   escalation, an edit link per unit. The only panel on either page that names a cost and a
   next step.
2. **Recent Activity** — the sole web reader of `occupancy_activities`
   (`Landlord\OccupancyController:91`). Deleting it makes that table write-only, joining
   `occupancy_snapshots`. ARCHITECTURE.md records one deliberate write-only table; a second
   one by accident is a different thing.

## The decision

**Move both panels into Analytics, then delete Occupancy.** One fewer nav item, no duplicated
occupancy numbers, nothing lost.

The objection this invites — "Analytics is range-filtered, those panels are point-in-time" —
does not apply. Analytics' occupancy panels are **already** point-in-time: `$occupancyBreakdown`
(`AnalyticsController:76-83`) and `$perProperty['rate']` (`:95-110`) read current
`availability_status` with no date filter, while only revenue and reservations honour the range.
The page already mixes both clocks. Two more right-now panels add no new inconsistency — but
they do get an explicit **"as of today"** label, because the page footer says "Showing data for
&lt;range&gt;" and that sentence must not appear to cover them.

Accepted loss: Occupancy's `?property=` filter. Analytics has no property filter, so Vacancy
Watch and the activity feed become portfolio-wide. For a landlord with a handful of properties
that is the more useful default anyway — "which of my units is empty longest" is a portfolio
question.

---

## Changes

### 1. `AnalyticsController` — carry the two payloads

- `use App\Models\OccupancyActivity;`
- **`$vacancy`** — lifted from `OccupancyController`, scoped to `$properties` instead of the
  removed `$scopedProperties`. No extra query: `$units` is already a full-model
  `PropertyUnit::whereIn(...)->get()`, so `vacated_at` and `created_at` are in hand.
  Keeps the `vacated_at ?? created_at` rule (a never-let unit is the *longest* vacancy, not an
  absent one) and the top-5 slice.
- **`$recentActivities`** — `OccupancyActivity` with the same four eager loads, `latest('activity_id')`,
  `limit(8)`, no property filter.
- Both added to the `view(...)` array.

### 2. `analytics/index.blade.php` — host the two panels

- `$statusStyles` (`tile` / `dot` / `verb`) added to the view's `@php` block — the timeline's
  dot colours and its "was reserved" / "was occupied" verbs come from it.
- New third row after the existing two (inserted before the range footer):
  `<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mt-3">` holding Vacancy Watch and Recent
  Activity, each with an **"as of today"** chip beside its `<h2>`.
- Panels copied verbatim otherwise — tints, the 30/60-day thresholds, the green empty state,
  the timeline rule.
- **Remove the "View All" link** at line 182 (it pointed at the deleted page).

### 3. Delete

| Path | Note |
|---|---|
| `app/Http/Controllers/Landlord/OccupancyController.php` | web only |
| `resources/views/landlord/occupancy/` | whole directory |
| `routes/web.php:171-172` | `occupancy.index`, `occupancy.export` |
| `layouts/landlord.blade.php:219-235` | sidebar entry under MAIN |
| `layouts/app.blade.php:560` | public footer "Occupancy Monitoring" link |

### 4. Keep — deliberately

- **`Api\Landlord\OccupancyController`** and `routes/api.php:177`. The mobile app depends on it
  and lives outside this repo; deleting the web page does not touch it. It still returns
  `unit_status_overview`, which the web dropped — that divergence is noted, not resolved here.
- `OccupancyRateCalculator` — Analytics uses it.
- `occupancy:snapshot` and `PropertyUnitObserver` — unchanged. The snapshot stays write-only by
  the standing decision in ARCHITECTURE.md.
- `routes/console.php:16`'s comment claimed the snapshot "feeds the occupancy trend chart".
  That chart went in July. Comment corrected while passing.

---

## Files

| File | Change |
|---|---|
| `app/Http/Controllers/Landlord/AnalyticsController.php` | + `$vacancy`, + `$recentActivities` |
| `resources/views/landlord/analytics/index.blade.php` | + `$statusStyles`, + Vacancy Watch, + Recent Activity, − dead "View All" |
| `app/Http/Controllers/Landlord/OccupancyController.php` | deleted |
| `resources/views/landlord/occupancy/index.blade.php` | deleted |
| `routes/web.php` | − 2 routes |
| `routes/console.php` | stale comment |
| `resources/views/layouts/landlord.blade.php` | − sidebar entry |
| `resources/views/layouts/app.blade.php` | − footer link |

---

## Manual test checklist

Analytics (`/landlord/analytics`):

- [ ] Page loads; the four existing charts still render (Chart.js still loads).
- [ ] Vacancy Watch appears in a new bottom row: idle rent total, up to five units, day pills tinted grey / amber >=30 / red >=60.
- [ ] Each Vacancy Watch row links to that unit's edit page and the unit belongs to you.
- [ ] Zero vacant units renders the green "Every unit is reserved, occupied, or under maintenance" state.
- [ ] Recent Activity renders the timeline with correct dot colour and verb per status.
- [ ] Both new panels carry an "as of today" label and do **not** change when the range selector changes; revenue and reservation panels still do.
- [ ] Side by side at >=1024px, stacked at 375px.
- [ ] "Occupancy by Property" no longer shows a "View All" link.
- [ ] Analytics CSV export still works.

Navigation:

- [ ] Sidebar has no Occupancy entry; Units and Reservations sit adjacent with no gap.
- [ ] Public footer has no "Occupancy Monitoring" link.
- [ ] `/landlord/occupancy` returns 404.
- [ ] No page in the landlord area throws `Route [landlord.occupancy.index] not defined`. Walk: dashboard, properties, units, reservations, tenants, payments, payouts, analytics, complaints, profile, settings.

Mobile API:

- [ ] `GET /api/landlord/occupancy` still returns 200 with `unit_status_overview` intact.
