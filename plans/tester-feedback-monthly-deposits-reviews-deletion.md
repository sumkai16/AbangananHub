# Tester Feedback Pass — Monthly Rentals, Deposits, Reviews, Safe Deletion

## Context

Members tested the system and raised four issues. Investigation showed most are **not missing
features** — they are existing features that are unreachable, unenforced, or contradicted by their
own UI. Two are genuine defects.

| Reported | What's actually true |
|---|---|
| "Inquiry and Reserve do the same thing" | Correct. Both post to `reservations.store` and both produce `rental_status = 'Inquiry'`. The status pill reads "Inquiry" either way, so the distinction is invisible. |
| "Use monthly, not 5-day/weekly stays" | The app is **already** monthly-only — no `rent_frequency` column exists, every price label is "/month", `RentLedger` hardcodes `addMonthNoOverflow()`. The hole is `StoreReservationRequest.php:24`, which validates move-out as only `after:target_move_in_date` — a 1-day stay passes. |
| "1 month should always have a security deposit" | `property_units.security_deposit` is nullable, optional in every landlord form, display-only, and **never charged** — PayMongo checkout bills `rental_fee` alone. |
| "Where can a tenant review a landlord? Vice versa?" | **Both directions already exist** (`Review` tenant→property, `TenantRating` landlord→tenant). They're unfindable because of a real bug: both gates require `rental_status === 'Occupied'` *exactly*, so completing a tenancy **closes** the review window. Meanwhile `tenant/profile/show.blade.php:196` says "Reviews appear here once you've completed a stay." |
| "Block deletion if the account has an occupied place" | `ProfileController.php:76` is an unconditional `$user->delete()`. Every FK is `onDelete('cascade')`, so it silently destroys reservations, payments, rent history, conversations, and reviews with no DB error. This directly violates `context/RULES.md:56`, which already documents the rule and points at a working precedent. |

**Outcome wanted:** monthly duration made explicit and enforced; a deposit that always exists and is
always visible; reviews that open *after* a stay ends and are reachable from where users actually
are; and an account deletion that refuses to destroy live rental history.

**Constraint:** panel defense is September 2026 — roughly three weeks out. Everything below is
deliberately scoped away from the escrow/money path, which is already hardened and demo-critical.

---

## 1. Collapse Inquiry / Reserve into one CTA

Two buttons that produce identical results are overhead, not clarity.

- **`resources/views/properties/show.blade.php`** — remove the `mode` toggle from *both* surfaces:
  the desktop modal (~lines 1071–1082) and the mobile two-step sheet (~lines 1288–1301). One CTA,
  labelled **"Contact Landlord"**. The move-in date and duration fields move inside the sheet as
  optional inputs, always visible (drop the `x-show="mode === 'reserve'"` guards at ~1097 and
  ~1309/1319). Delete the `mode` key from `x-data` (~line 75) and the hidden `<input name="mode">`
  (~line 1092). Submit label collapses to a single string — no more `mode === 'reserve' ? … : …`
  ternaries at ~1146 and ~1344.
- **`app/Http/Requests/StoreReservationRequest.php`** — drop the `mode` rule, its two `messages()`
  entries, and the `required_if:mode,reserve` on `target_move_in_date`. **Delete
  `prepareForValidation()` entirely** — it existed only to null out dates abandoned on the other tab,
  which cannot happen once there are no tabs.
- **`context/DESIGN.md` §192–195** — that section currently argues the two modes are "a real
  distinction, not decoration." Rewrite it to record why the distinction was removed, and keep the
  existing §195 note about there being no separate "Message landlord" card.

---

## 2. Monthly-only duration — no schema change needed

The tenant picks **move-in date + number of months**; move-out is *computed*. Crucially,
`target_move_out_date` and `target_move_in_date` already exist, so `duration_months` is fully
derivable from them — **no migration required.**

- **`StoreReservationRequest.php`** — accept `duration_months` as
  `nullable|integer|in:1,3,6,12` (null = open-ended). Add a `withValidator`/`prepareForValidation`
  step that computes `target_move_out_date = move_in->copy()->addMonthsNoOverflow($n)` when both a
  move-in date and a duration are present, and leaves it null when duration is open-ended. Keep
  `target_move_in_date` as `nullable` (an inquiry with no date is still valid) but keep the existing
  `after_or_equal:today` / `before_or_equal:+1 year` bounds.
- **`resources/views/properties/show.blade.php`** — replace the free move-out `<x-date-picker>`
  (desktop ~1111–1121, mobile ~1319) with a duration `<select>` using the existing styled-select
  component. Options: 1 / 3 / 6 / 12 months, plus "Open-ended". Keep the move-in `<x-date-picker>` as
  is.
- **`app/Models/Reservation.php`** — add a `durationOfStay` **accessor**. `duration_of_stay` is a
  dead `VARCHAR` column that only `ReservationSeeder.php:28` and `BuildsEscrowFixtures.php:139` ever
  write to; an Eloquent accessor takes precedence over the raw column, so defining
  `getDurationOfStayAttribute()` to derive `"6 months"` / `"Open-ended"` from the two date columns
  makes **all eight existing read-only display sites work unchanged** — `tenant/reservations/index`
  (×3), `landlord/reservations/index` (×3), `admin/reservations/show` (×2), and
  `ReservationResource.php:18`. No view edits, no dropped column, single source of truth.
  Per `context/RULES.md:131`, Carbon 3's `diffInMonths()` returns a float — `round()` it.
- **`app/Http/Requests/Landlord/StoreWalkInTenantRequest.php:43`** — same treatment so a landlord
  can't record a 3-day walk-in tenancy: `move_out_date` becomes
  `nullable|date|after_or_equal:<move_in + 1 month>`.
- Update the two fixture writers to set dates rather than the dead string.

---

## 3. Security deposit — always set, always visible, not charged

Scope decision: **listing-level requirement only.** The deposit appears everywhere a tenant makes a
decision, but the PayMongo checkout amount is untouched — `Tenant\PaymentController::createCheckoutSession`
and its API twin keep billing `rental_fee` alone, so the hardened escrow path is not disturbed
before defense. Deposit is collected offline, exactly as walk-in money already is.

- **Migration** (`add_security_deposit_default_to_property_units`): backfill `NULL` →
  `rental_fee` (one month, the standard PH convention) so no existing listing shows a blank deposit.
  Leave the column nullable at DB level — the requirement is enforced in application code, matching
  how every other business rule in this app works.
  *Flagging explicitly:* this writes a value on behalf of landlords who never entered one. If you'd
  rather they be prompted instead, say so and I'll leave nulls and render "Ask the landlord".
- **Validation** — `nullable` → `required` in all four places, which are currently identical:
  `Landlord\PropertyUnitController.php:49` (store) and `:165` (update), and
  `Api\Landlord\UnitWriteController.php:40` / `:130`. Rule becomes
  `required|numeric|min:0|max:999999.99`.
- **Forms** — `resources/views/landlord/units/create.blade.php:208-212` and
  `edit.blade.php:199-203`: add `required`, drop any "optional" label affordance, and prefill new
  units with the unit's rent so the landlord confirms rather than invents a number.
- **Tenant-facing visibility** — the deposit already renders conditionally
  (`properties/show.blade.php:63-64`, `agreements/show.blade.php:100-105`). Now that it always
  exists, promote it out of the `@if` and show it beside the rent in the inquiry sheet, so the
  tenant sees total move-in cost before contacting anyone.

---

## 4. Reviews — fix the window, then surface both directions

The feature exists; it is gated shut at the exact moment it should open.

**The bug (highest value item in this plan):**

- **`app/Models/Review.php:50-62`** — `canReview()` requires `rental_status === 'Occupied'`. Change
  to `whereIn('rental_status', ['Occupied', 'Completed'])`. A tenant should be able to review
  *after* moving out; today `endTenancy()` silently revokes the right.
- **`Landlord\TenantRatingController::create/store`** and **`Api\Landlord\TenantRatingController::show/store`**
  — same fix, same reason: a landlord currently loses the ability to rate a tenant the instant the
  tenancy ends.
- The eligibility check is **duplicated verbatim** in three places (`Review::canReview()`,
  `Tenant\ReviewController::store:28`, `Api\Tenant\ReviewController::store:31`). Collapse the two
  controllers onto the model helper so the rule lives once.

**Discoverability — why nobody could find it:**

- **`app/Models/Reservation.php:587-604`** (`endTenancy()`) is the sole `Completed` trigger and
  currently dispatches nothing. Both callers (`Landlord\TenancyController.php:58-74` and the API
  twin) already wrap it in `DB::transaction` + `lockForUpdate()`. Inside that lock, create two
  `Notification` rows — the codebase uses plain `App\Models\Notification::create(...)`, there is no
  `app/Notifications/` directory — one to the tenant ("Your stay ended — leave a review") and one to
  the landlord ("Rate your tenant"). This also closes an existing gap: `TenantRating` fires **no
  notification at all** today, so a rated tenant is never told.
- **`resources/views/tenant/reservations/index.blade.php`** and
  **`resources/views/tenant/tenancy/show.blade.php`** — add a "Leave a review" CTA on
  Occupied/Completed rows, deep-linking to the property page's existing review form. Right now the
  form exists *only* on the public listing page (`properties/show.blade.php:747-798`), which a
  tenant has no reason to revisit after moving in.
- **`resources/views/landlord/reservations/index.blade.php`** — surface the already-routed
  `landlord.reservations.rateTenant` link on Occupied/Completed rows. The route and form exist;
  nothing links to them prominently.
- **`resources/views/tenant/profile/show.blade.php:196`** — the empty-state copy promises reviews
  appear "once you've completed a stay," which was false. After the gate fix it becomes true; leave
  the copy and let the behaviour catch up to it.

Not doing: merging `Review` and `TenantRating` into one model. They are documented as separate in
`context/SCHEMA.md:486-488`, the admin ratings dashboard reads both, and unifying them is a schema
migration with no user-visible payoff.

---

## 5. Account deletion — block on active obligations

Port the guard that already exists on the admin path (`Admin\UserController.php:231-269`) to the
self-service path, which never got it.

- **`app/Http/Controllers/ProfileController.php:66-82`** — after the existing `current_password`
  check and **before** `Auth::logout()`, gather blockers:
  - live tenancy — `$user->reservations()->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)->exists()`.
    Reuse the exact query shape already in `Tenant\ProfileController.php:24-28`.
  - owned properties — `$user->properties()->exists()` (matches the admin guard).
  - reviews written — `$user->reviews()->exists()` (matches the admin guard; preserves history).
  - outstanding rent — sum `RentLedger::for($reservation)->summary()['outstanding']` across the
    user's Occupied reservations.

  If any hit, `return back()->with('error', '<specific sentence>')` — the dominant blocking pattern
  in this codebase, and it renders through the global flash modal per `context/RULES.md:29`. The
  message must **name what is blocking** ("You have an occupied tenancy and ₱4,500 in unpaid rent"),
  not a generic refusal. A genuinely clean account still deletes.
- **`resources/views/profile/partials/delete-user-form.blade.php`** — this modal predates the
  `data-confirm` convention and uses a bespoke `x-modal` + `$dispatch('open-modal')`. Leave the
  password-confirm mechanism (it is the right control here), but rewrite the body copy to state what
  will be permanently destroyed, and show the blocking reasons inline when present rather than only
  after a failed submit.
- **`context/RULES.md:56`** — extend the existing "Hard deletes on `User` cascade" rule to state that
  *both* delete paths are now guarded, so the next person doesn't reintroduce the gap on a third one.

---

## Files touched (summary)

**Requests/validation:** `StoreReservationRequest.php`, `StoreWalkInTenantRequest.php`,
`Landlord\PropertyUnitController.php`, `Api\Landlord\UnitWriteController.php`
**Models:** `Reservation.php` (duration accessor), `Review.php` (`canReview` gate)
**Controllers:** `ProfileController.php`, `Landlord\TenancyController.php` + API twin,
`Tenant\ReviewController.php` + API twin, `Landlord\TenantRatingController.php` + API twin
**Views:** `properties/show.blade.php` (largest edit — both modal surfaces),
`landlord/units/{create,edit}.blade.php`, `tenant/reservations/index.blade.php`,
`tenant/tenancy/show.blade.php`, `landlord/reservations/index.blade.php`,
`profile/partials/delete-user-form.blade.php`
**Migration:** one — security deposit backfill
**Docs:** `context/DESIGN.md`, `context/RULES.md`, `context/SCHEMA.md`, `context/PRD.md`
**Also:** copy this plan to `plans/tester-feedback-monthly-deposits-reviews-deletion.md` per
`CLAUDE.md`

## Build order

Per `context/RULES.md` Build Order, and sequenced so the riskiest UI edit lands last:

1. Migration + `$fillable` audit
2. Model changes (`Review::canReview`, `Reservation` duration accessor)
3. Request/validation changes
4. Controller changes (deletion guard, completion notifications, review gates)
5. Views — deletion modal, review CTAs, unit forms, then `properties/show.blade.php`
6. Context docs

## Verification

No automated suite — per `context/RULES.md:136` and how you work, this is manual, so the deliverable
is fixtures + a checklist rather than tests. I'll extend the existing
`app/Console/Commands/RatingScenarios.php` pattern (additive, tagged, `--clean` teardown, prints
credentials) to seed the states below.

Then `npm run build` + `php artisan serve` + `php artisan reverb:start`, and check:

1. **Inquiry** — property page at 375px and desktop: one CTA, no toggle. Send with no date → lands in
   conversation. Send with move-in + "6 months" → reservation shows "6 months" on the tenant list,
   landlord list, and admin detail (all three read the new accessor).
2. **Duration floor** — try to submit a walk-in with a move-out 3 days after move-in → inline
   validation error beside the field, not a flash modal.
3. **Deposit** — create a unit leaving deposit blank → blocked. Existing seeded units show a
   backfilled deposit. Deposit visible on listing + inquiry sheet. Confirm the PayMongo checkout
   amount is **still rent only** (this is the regression to watch).
4. **Reviews** — take a tenancy Occupied → end it → confirm *both* parties get a notification, the
   tenant's review CTA still works **after** completion (this is the bug being fixed — it fails
   today), and the landlord can still rate the tenant.
5. **Deletion** — with an occupied tenancy, attempt delete → blocked with a message naming the
   tenancy and any unpaid rent. Complete the tenancy, clear rent, retry → still blocked if they own
   properties or wrote reviews, with that reason named. A fresh account with no activity → deletes.
6. `php artisan migrate:fresh --seed` + `route:list` as the standard checkpoint.

I'll stop before committing so you can verify in-browser, then hand you the file list and commit
messages (split per concern: migration, models/requests, controllers, views, docs).
