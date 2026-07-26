# Occupancy Monitoring — UI redesign (July 26 2026)

`resources/views/landlord/occupancy/index.blade.php`

## Why

A prototype was supplied for this page. Read against what shipped, the prototype is
mostly a *flatter subset* of the existing view — it drops the trend chart, the
expandable per-unit tiles, and the unit detail modal. So the job is not to build the
prototype; it is to take the two things it does better (flat, always-scannable
property rows; Recent Activity promoted into the right rail) and push past it.

## Decisions

1. **The donut is replaced by a segmented capacity bar.** A full-width "Portfolio
   mix" band sits directly under the stat rail: one stacked bar (Available /
   Reserved / Occupied / Maintenance) with an inline legend carrying count + share.
   Rationale: four categories summing to a known total is a part-to-whole
   comparison, and a bar beats a donut at that — angle is harder to judge than
   length. It also frees the right rail, and it gives the page **one** visual
   language for mix, because each property row gets the same bar in miniature.
   The occupancy-rate number it used to hold in its centre is already a stat card,
   so nothing is lost by removing it.
2. **Property rows keep expanding.** Rows read flat and scannable like the
   prototype (dot counts + unit total), but still expand to the unit tile grid, and
   a tile still opens the unit detail modal. The prototype's collapse-only rows
   would have cost in-place unit inspection for no gain.
3. **Status filter chips tint to their own status when active**, instead of all
   turning charcoal. A chip labelled "Occupied" going red-on-active is the status
   colour doing its job (§3: colour is reserved for status); four chips that all
   turn the same dark grey throw that away.

### Amendment — the Portfolio Mix band was deleted (same day)

Decision 1 shipped and was wrong. On screen, the band's legend read `20 (67%)`,
`3 (10%)`, `7 (23%)` — the same counts *and* percentages as the stat cards
directly above it, each of which already draws its own share bar. The donut had
restated one figure; the band restated all of them, and cost a full row of
vertical space doing it.

Resolved by deleting the band and adding a sixth stat card for **Maintenance** —
the only count the band alone carried. The stat rail becomes the single home for
portfolio-level numbers; the per-property mini bars stay, because those *are*
new information (the rail says nothing about any individual property).

**The general rule this produced:** before adding a summary panel, check whether
the stat rail above it already carries every number it would hold.

## Layout

```
page header ............................ title + property select + Export
stat rail .............................. 6 × <x-stat-card>, incl. Maintenance
grid lg:grid-cols-5
  left  (col-span-3) ................... Unit Status Overview + per-property mini bars
  right (col-span-2) ................... Occupancy Trend, then Recent Activity
```

## Scope

View-only. `Landlord\OccupancyController` already supplies every value the new
markup reads (`$availableUnits`/`$reservedUnits`/`$occupiedUnits`/`$maintenanceUnits`,
`$unitStatusOverview` per-property counts, `$trend`, `$recentActivities`) — no
controller, route, or query change.

### Amendment 2 — the trend chart was removed entirely

The empty chart prompted the right question: *who is this for?* A 30-day
occupancy line reports history the landlord already lived through and cannot
act on, and it assumes an audience that reads trend charts. The landlords on
this platform run a handful of properties and open this page to find out what
needs doing.

**Replaced by Vacancy Watch** in the same slot: idle monthly rent as the
headline figure, then the five longest-empty units with days-vacant pills,
per-unit lost rent, and a link into each unit's edit page. Days vacant reads
`vacated_at ?? created_at`, so a never-let unit counts from listing rather than
being excluded — those are the worst cases, not the absent ones. Tint escalates
at 30 and 60 days, reusing the admin catalogue's existing "stale" threshold.

Side effects: `OccupancySnapshotSeeder` deleted (it existed only to make the
removed chart look alive), and the Chart.js CDN `<script>` went with the chart —
the page no longer pulls a ~200KB bundle. **`occupancy:snapshot` deliberately
keeps running** with no reader; occupancy history can't be reconstructed after
the fact, so one query a night preserves the option of a future trend.

The section below is kept as the record of why the chart was empty.

## Occupancy Trend — why it was empty (historical)

Not a view bug. The chart reads `occupancy_snapshots`, filled by the
`occupancy:snapshot` command scheduled daily in `routes/console.php`. Laravel's
scheduler needs `schedule:run` invoked every minute (cron, or
`php artisan schedule:work` locally); on a dev box nothing does, so the table
had **zero rows** and the panel sat on "Trend is building" permanently.

Added `Database\Seeders\OccupancySnapshotSeeder` — 30 days per landlord, run
directly (`php artisan db:seed --class=OccupancySnapshotSeeder`), not wired into
`DatabaseSeeder` since it reads live unit statuses and must run after properties
exist. It **interpolates from a floor up to today's real occupied count** rather
than random-walking backwards: a downward-biased walk floors at zero within a
week on a 30-unit portfolio and flatlines, which was the first attempt and
produced a chart two-thirds dead. Today's point is pinned to the real figure so
the last plotted value can't disagree with the stat cards above the chart.

**Known inconsistency, not fixed here:** `SnapshotOccupancy` counts *all* units,
while `OccupancyRateCalculator` counts only `verification_status = 'Approved'`
ones. So a stored `occupancy_rate` won't equal `occupied_units / total_units`
whenever a landlord has unapproved units. Invisible on current seed data
(all 30 units are approved) but worth resolving before the trend is trusted.

## Conventions applied

- Status hexes are the locked tokens only (§7): `#22C55E` / `#FBBF24` / `#EF4444` /
  `#94A3B8`, darkening to `#15803D` / `#B45309` / `#DC2626` for text on white.
- The removed donut takes its Chart.js config with it; the trend line chart keeps
  its token colours (§7 Chart.js parity).
- Cards stay `<x-card>`-spec flat white; no new hexes, no gradients, no blur.
- The mix bar's segments carry `title` + `aria-label`, so the breakdown is not
  colour-only.
