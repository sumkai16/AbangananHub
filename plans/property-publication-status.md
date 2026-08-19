# Property publication status — separating "is it legitimate?" from "should it be live?"

## Context

`properties.verification_status` (`Pending`/`Approved`/`Rejected`) is currently doing two unrelated
jobs: recording the admin's verdict on whether a listing is legitimate, *and* acting as the only
switch controlling whether it appears to tenants. This is item 5 of
`plans/property-flow-verification-gaps.md`, the foundation the remaining analyst-doc items
(wizard/draft-save, submission checklist) depend on.

Two concrete problems in the code today prove the need:

1. **Moderation is silently reversible.** `Admin\ReportController::resolve()` (`:70`) handles the
   "delist property" outcome of a user report by setting `verification_status = 'Rejected'` — the
   same value that means "failed initial review". Because `PropertyController::update()` resets a
   property to `Pending` whenever details change, a landlord whose listing was delisted for a
   complaint can edit anything, get routinely re-approved, and be back online — the moderation
   action vanishes with no trace. There is effectively a "Suspended" state already hiding inside
   the approval enum, and it does not hold.
2. **Landlords cannot take a listing down without destroying it.** There is no unpublish control;
   `PropertyController::destroy()` (`:247`) is the only path, it hard-deletes, and it has no guard
   against active reservations. That contradicts `context/RULES.md:56`, which frames suspension as
   the reversible alternative to deletion precisely so history is preserved.

**Outcome:** a second, orthogonal `publication_status` column answers "should this be live right
now?", leaving `verification_status` to answer only "is this legitimate?". A suspended listing
stays suspended across edits and re-approvals; landlords get a non-destructive way to pause a
listing; and "approved but not currently listed" becomes representable.

Decisions taken: states are **Draft / Published / Unpublished / Suspended** (no `Archived` — it has
no producer, no consumer, and would need its own policy for reviews and reservations). Scope is the
foundation, the moderation fix, and a landlord publish/unpublish toggle.

## Design decisions

- **`verification_status` is not touched at all.** No new members, no data migration, no `Under
  Review` state (nothing in the app would set it). This is *additive*, not a split — every existing
  verification code path keeps working untouched, which removes most of the risk from a change that
  otherwise reaches ~50 read sites.
- **Properties only, not units.** `property_units.verification_status` has the same single-status
  shape, but a suspended property already hides its units transitively through the visibility gate,
  and per-unit publication (pause one room, keep the others live) is a genuinely separate feature.
  Doubling the surface here doubles the risk for no current requirement.
- **`Draft` is defined now but produced by nothing until the wizard ships.** Column default stays
  `Published`; the wizard (next roadmap item) will set `Draft` explicitly.
- Follows this codebase's conventions: Title Case enum members to match the `properties` table
  (not the lowercase `users.account_status` style), raw `ALTER`-style enum work per the
  `Maintenance` / `payment_method` precedents in `SCHEMA.md`'s migration log, and a single
  source-of-truth query scope in the spirit of `Reservation::TERMINAL_STATUSES`.

## Phase 1 — Funnel every visibility check through one scope (no behavior change)

**This phase must land before the column exists.** `context/ARCHITECTURE.md:266` records exactly the
failure mode to avoid here: `rental_status` "is enumerated across 20 files … and a missed one
silently drops reservations out of a tab." The same predicate is currently written inline in **three**
places, and a missed one after the column lands means either a suspended property still visible to
tenants (a moderation failure) or an approved one vanishing.

The three copies of "approved property with at least one available approved unit":
- `app/Models/Property.php:111` — `scopeBrowsable()`, the canonical one (used only by
  `Api\PropertyController:29`)
- `app/Http/Controllers/PropertyController.php:20-25` — web browse, duplicated inline
- `app/Providers/AppServiceProvider.php:49-51` — the cached `navAreas` header composer, a third copy

There are two distinct predicates to name, because they are not the same:

1. `scopeLive()` / `isLive()` — "this listing is publicly viewable at all": `verification_status =
   Approved`. Used by the `show()` 404 gates and the tenant reservation gate. Does **not** require an
   available unit (the public property page renders fine with none).
2. `scopeBrowsable()` — "this listing belongs in a browse list": `scopeLive()` **plus** at least one
   available approved unit, plus the existing `withMin`/`withAvg`/`withCount` aggregates. Rebuild it
   on top of `scopeLive()` rather than repeating the condition.

Then: web `PropertyController::index()` and the `navAreas` composer call the scopes instead of their
inline copies, and the four gate sites (`PropertyController::show():112`,
`Api\PropertyController:59`, `Tenant\ReservationController:63`, `Api\Tenant\ReservationController:76`)
call `isLive()` instead of comparing the string.

Phase 1 is independently shippable and verifiable: behavior must be **identical**. Confirm by
browsing, opening a property, and checking the header Areas menu before and after.

## Phase 2 — The column

**Migration `add_publication_status_to_properties_table`**
- `publication_status` ENUM('Draft','Published','Unpublished','Suspended') NOT NULL DEFAULT
  'Published', after `verification_status`.
- Backfill: every existing row → `Published`. Publication was never a concept, so nothing was ever
  deliberately unpublished.
- One caveat worth a comment in the migration: properties delisted through the report flow are
  currently indistinguishable in the data from genuinely-rejected ones — both are `Rejected`. In
  production they *could* be recovered from `AuditLog` (`report.resolve` entries), but the dev DB has
  none, so the backfill does not attempt it. Note it rather than silently ignoring it.

**Model (`app/Models/Property.php`)**
- Add `publication_status` to `$fillable`.
- `scopeLive()` gains `->where('publication_status', 'Published')`; `isLive()` matches. **This one
  edit is what makes every gate from Phase 1 publication-aware** — that is the entire point of doing
  Phase 1 first.
- Add `isSuspended()` alongside the existing `isApproved()`/`isPending()`/`isRejected()` helpers
  (`:80-93`).

**Deliberately NOT filtered by publication status:** every landlord- and admin-facing query
(landlord dashboards, unit lists, the walk-in tenant unit picker, admin catalogue and approval
queues). A landlord must still see and operate on their own unpublished listing — unpublishing
controls public visibility, not whether the business can be run. Only the tenant-facing gates from
Phase 1 change.

**API:** add `publication_status` to `app/Http/Resources/PropertyResource.php` beside
`verification_status` (`:21`), so mobile clients can distinguish the two.

## Phase 3 — Fix the moderation hole

- `Admin\ReportController::resolve()` (`:70`): the `delist_property` branch sets
  `publication_status = 'Suspended'` instead of `verification_status = 'Rejected'`. The audit-log
  entry and `$actionLabel` already exist and stay as they are.
- **Editing must not clear a suspension.** `PropertyController::update()` and
  `Api\Landlord\PropertyWriteController::update()` reset `verification_status` to `Pending` on a
  material change — that behavior is correct and stays, but neither may touch `publication_status`.
  This is the headline win: a suspended listing that is edited and re-approved is still suspended,
  where today the moderation action is silently undone.
- Admin needs an **unsuspend** action (`Suspended` → `Published`) in `Admin\ListingController`,
  routed under the existing `admin.listings.*` group and surfaced on
  `resources/views/admin/listings/approval.blade.php`. Follow the `approve()`/`reject()` shape
  already there: `DB::transaction` + `lockForUpdate()` + `abort_if` on the current state +
  `AuditLog::record()` + `Notification::notify()` — the pattern `RULES.md:37-49` names as required
  for consequential admin state transitions.

## Phase 4 — Landlord publish/unpublish toggle

- Two routes beside the existing landlord property resource routes (`routes/web.php:72`), e.g.
  `POST /properties/{property}/publish` and `/unpublish` on `PropertyController`, both asserting
  `landlord_id === Auth::user()->user_id` like the sibling actions do.
- **Guards, in the controller, not just the UI:**
  - Landlords may only move `Published ↔ Unpublished`. Any attempt to publish out of `Suspended`
    must be refused — otherwise the moderation fix in Phase 3 is toothless.
  - `Draft` is not landlord-togglable yet (nothing produces it until the wizard).
- UI on `resources/views/landlord/properties/index.blade.php` (which already derives a status badge
  at `:139`) and the landlord property show page: a publication badge beside the existing
  verification badge, and the toggle. When `Suspended`, show that state plainly with **no toggle** —
  a landlord must not be able to self-clear an admin action, and a disabled control that silently
  does nothing is worse than an explained state.
- Add a `<x-publication-status-badge>` sibling to the existing
  `resources/views/components/verification-status-badge.blade.php`, matching its shape.

## Files

**New:** one migration; `resources/views/components/publication-status-badge.blade.php`.

**Modified:** `app/Models/Property.php` (scopes + helpers + fillable),
`app/Http/Controllers/PropertyController.php` (index via scope, show gate, publish/unpublish),
`app/Providers/AppServiceProvider.php` (navAreas via scope),
`app/Http/Controllers/Api/PropertyController.php` and both
`Tenant\ReservationController`s (gates via `isLive()`),
`app/Http/Controllers/Admin/ReportController.php` (delist → Suspended),
`app/Http/Controllers/Admin/ListingController.php` (+ unsuspend),
`app/Http/Resources/PropertyResource.php`, `routes/web.php`,
`resources/views/landlord/properties/index.blade.php`, the landlord property show view, and
`resources/views/admin/listings/approval.blade.php`.

**Docs to update as part of the work** (per `CLAUDE.md`): `context/SCHEMA.md` — the new column on
`properties`, the migration log entry, and the `verification_status` note clarifying it no longer
governs visibility alone; `context/ARCHITECTURE.md` — a log entry recording why publication was
split out (the delist conflation) and why it is stored rather than derived, since the file's
`"Paid (held)" is derived` entry argues the opposite for a genuinely derivable fact and the
distinction matters; `plans/property-flow-verification-gaps.md` — mark item 5 done.

## Verification

Run the app (`npm run build`, `php artisan serve`). Existing seeded data covers all of this; no
fixtures needed.

**Phase 1 (must be a no-op):** browse `/properties`, open a property page, check the header Areas
menu — identical before and after the refactor. This is the whole safety argument for the phased
split, so confirm it deliberately rather than assuming.

**The moderation fix — the case that is broken today:** as a tenant, report a listing. As admin,
resolve the report with "delist property". Confirm the property disappears from browse and its
public page 404s, **and** that the landlord still sees it in their own list marked Suspended. Then,
as the landlord, edit that property (which resets verification to Pending); as admin, approve it.
**It must still be hidden from tenants.** On `main` today the equivalent sequence puts it back
online — that contrast is the point of the change.

**Landlord toggle:** unpublish a live listing → gone from browse, public page 404s, still listed in
the landlord's own properties and still operable (walk-in tenant picker still offers its units).
Republish → back in browse. On a Suspended listing, confirm no toggle is rendered and that a
hand-crafted POST to the publish route is refused.

**Regression sweep:** an approved+published property with no available units still renders its
public page (it should — `isLive()` deliberately does not require an available unit) but stays out
of browse lists and the Areas menu.

**Automated:** the suite has 10 pre-existing failures on this branch (stock Breeze auth scaffolding,
stale routes) — confirm that count is unchanged rather than assuming a green run. Note that
`ProfileTest` uses `RefreshDatabase` against the real dev database, so **re-seed after any full test
run** (`php artisan migrate:fresh --seed`).
