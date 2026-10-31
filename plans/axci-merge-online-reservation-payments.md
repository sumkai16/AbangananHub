# Merge origin/axci into emman + online (PayMongo) reservation payments in emman's pipeline

## Context
Axcee wants the `axci` work in `emman` while keeping emman's newer Tenants, Contracts, Payments and the
normalized Reservations pipeline (the screenshot: Needs your reply / Discussing / Waiting on tenant /
Payment to confirm, stage tabs). On top of that, a tenant must be able to **reserve a unit by paying online
through PayMongo**, and that payment has to show up correctly in emman's pipeline.

What the branches actually contain (checked with git):
- `origin/axci` is only **4 commits** past the merge base `cac8b92` (already in emman via main): mobile
  layout polish (top-bar title, stat chips, one-row filters, `resources/css/app.css`) and **"Remind all
  overdue"** on the Payments page (`PaymentController::remindOverdue`, route `payments.remindOverdue`,
  `PaymentsPageRemindersTest`).
- **PayMongo is already in emman**, behind the admin switch *Online payments (PayMongo)*
  (`rentals.online_payments_enabled`, `App\Support\Payments::online()`, default off; `phpunit.xml` turns it on).
- The gap: emman's pipeline decides "paid" only from `reservations.move_in_paid_at`, which only the
  direct-pay path sets. A PayMongo payment (Initial row `Held`) never sets it, so today an online-paid
  reservation stays in *Awaiting payment*, says "{tenant} pays you ₱…", does **not hold its unit** (a
  competing tenant could also be confirmed), and the contract modal says "Waiting for move-in payment…".

Decisions (Axcee):
1. **Both payment methods, the admin switch decides.** On: tenants pay online, money held until move-in.
   Off: today's direct pay + landlord confirms. The pipeline must handle both, including rows paid under the other mode.
2. **Real `git merge origin/axci`**, emman wins for tenants/contracts/payments/reservations.
3. **Online checkout charges the full move-in amount** (`moveInDue()`: advance months incl. the first + deposit), not just one month.

## Step 0 — commit the pending work first
The working tree has uncommitted, unverified work (lease → rental agreement wording, Rate tenant in the
contract modal). A merge needs a clean tree. Ask Axcee to confirm, then commit it as its own commit
(author = Axcee only, **no Co-Authored-By trailer**, per CLAUDE.md). Do not push.

## Step 1 — merge origin/axci
`git fetch && git merge origin/axci` on `emman`. Expected conflicts and how to resolve:

| File | Resolution |
|---|---|
| `landlord/tenant-ratings/create.blade.php`, `landlord/tenants/walk-in/create.blade.php` | Modify/delete: **keep emman's deletion** (`git rm`). |
| `landlord/reservations/index.blade.php`, `landlord/tenants/index.blade.php` | Keep emman's markup; re-apply axci's class tweaks only (`grid-cols-2` filter grid, `stat-chips`, `filter-row`) since `app.css` styles them. |
| `landlord/payments/index.blade.php`, `Landlord/PaymentController.php`, `routes/web.php` | Keep emman's Payments page (ledger split); **add** axci's `remindOverdue` method, route (`throttle:3,1`) and its "Remind all overdue" button. |
| `layouts/landlord.blade.php` | Keep emman's nav (Tenants, Contracts, Payments); take axci's top-bar page title. |
| `conversations/index.blade.php`, `about.blade.php` | Combine both sides. |
| `context/CHANGELOG.md` | Keep both sets of entries, newest first. |

After resolving: `npm run build`, `php artisan test` (only the 3 known `intl` failures in `TenantConcernsTest`
allowed), then commit the merge. Check `PaymentsPageRemindersTest` passes against emman's Payments page.

## Step 2 — one definition of "move-in money settled"
Keep `move_in_paid_at` meaning **paid directly** (the direct-pay notices, `directPaymentNotice()` and
`ReservationObserver` rely on that). Add one shared notion that covers both channels in `app/Models/Reservation.php`:
- `scopeMoveInSettled($q)`: `move_in_paid_at IS NOT NULL` **or** `whereHas('payments', Initial in Held/Paid/Released)`.
- `moveInSettled(): bool` = existing `hasSettledInitialPayment()` (reuse it; keep the old name as the implementation).
- `paidOnline(): bool` / reuse `heldPayment()` (`Reservation.php:371`) for UI branching.

Replace the payment-blind checks with it:
| Site | Change |
|---|---|
| `Landlord/ReservationController::applyStage` (`$awaitingPayment`, `history`) and strip counts (~:90-96) | use the scope, so an online-paid row leaves *Awaiting payment* and is not counted in "Waiting on tenant" |
| `Reservation::scopeHoldingUnit()` | include settled-online, so `unitHeldByAnother()` / `PaymentClaims::assertMoveInOpen` block a competitor |
| `Reservation::contractStatus()` | Signed + settled → `Awaiting move-in` |
| `Reservation::requestStage()` (~:913) | Held → "Paid online, held until move-in" (no "pays you ₱") |
| `Reservation::markOccupied()` + `ReservationPolicy::confirmDirectMoveIn` | accept settled with **no Held row** (fixes the admin-release-without-keys stuck state: Released + Signed + `move_in_paid_at` null) |
| `ReservationPolicy::recordMoveInPayment` | refuse when settled online (today only the service refuses) |

## Step 3 — PayMongo settles = the unit is reserved
In the one place both settle paths share (`PayMongoWebhookController::handleCheckoutPaid` and
`ReconcilesPaymongoCheckout::reconcilePayment` → extract a small shared method), after Initial → `Held`:
call `holdUnit()`. In both checkout guards (`Tenant/PaymentController::createCheckoutSession`,
`Api/Tenant/PaymentController::pay`) refuse when `unitHeldByAnother()`, so two tenants cannot both pay for one unit.

## Step 4 — charge the full move-in amount
- Both checkouts: `amount = moveInDue()` instead of `monthlyRent()`; PayMongo line item labelled
  "Move-in payment (first month, advance, deposit)". Reuse `MoveInPaymentBreakdown` for the label text.
- `RentLedger::isPlatformInitial()` today treats a platform Initial row as paying exactly month one. Change
  it to spread the row with `MoveInPaymentBreakdown::make(amount, rent, deposit, moveIn)`: its rent rows
  settle the advance months, its deposit part counts toward *Deposit held* the same way a direct move-in's
  Deposit row does. Older Initial rows (amount = one month) must read exactly as before.
- Tenant-facing text that says "first month" for online pay (`properties/show.blade.php:1578, 1806`,
  `components/payment-consent.blade.php`, `agreements/show.blade.php` Pay Now box): show `moveInDue()` and its breakdown.

## Step 5 — landlord screens for an online-paid reservation
- **Reservations row / modal** (`_row-actions.blade.php`, `index.blade.php`): it leaves the pipeline once
  settled (like a confirmed direct payment). Fix the stale "switched-off checkout" comment near the
  cancel-blocked text.
- **Contract modal** (`contracts/partials/_details.blade.php`): new branch for Signed + Held:
  "₱X paid online · held by AbangananHub until {tenant} confirms move-in", buttons **Open ledger**,
  **Mark keys turned over** (existing `markTurnedOver`, policy `ReservationPolicy::markTurnedOver`) and
  Open chat. No "Confirm move-in" (escrow is released by the tenant, the deadline job or admin).
- Dashboard "Turn over the keys" task (`DashboardController.php:162-195`): list only Signed + settled rows.

## Tests
- New `tests/Feature/Payments/OnlineReservationPipelineTest.php` (switch on): Held reservation → not in
  `stage=payment`, in `history`; strip counts; pill "Paid online…"; contract modal shows the held text and
  Mark keys turned over, not "Waiting for … move-in payment"; unit is `Reserved` after the webhook and after
  reconcile; a second tenant's checkout on that unit is refused; admin release without keys → landlord can
  confirm move-in (no stuck state).
- `TenantCheckoutTest`: amount in centavos is now `moveInDue()`; update its expectations.
- `RentLedgerInitialTest`: a full move-in Initial row settles the advance months and shows the deposit held;
  an old one-month Initial row is unchanged.
- Existing direct-pay suites (`PaymentClaimTest`, `DirectPaymentPagesTest`, `ReservationsPipelineTest`,
  `ContractsPageTest`) must stay green, proving the switch-off path is untouched.

## Verification
1. `php artisan test` after Step 1 and again at the end (3 known `intl` failures only).
2. `npm run build`.
3. Browser, switch **on** (Admin → System Settings), PayMongo **test** keys in `.env`:
   tenant inquires → landlord sends agreement → tenant signs → Pay now (PayMongo test card) → landlord
   Reservations: row gone from Open/Awaiting payment; Contracts: "Awaiting move-in" with the held text;
   unit shows Reserved; Mark keys turned over → tenant confirms move-in → Occupied, payment Released.
   The webhook does not reach `127.0.0.1`, so the return page's reconcile poll settles it; that path is tested too.
4. Switch **off**: the direct flow (Pay your landlord → Payment to confirm → Confirm) works as before.
5. Phone width (375px) for the Reservations page, contract modal and tenant agreement page.

## Notes
- Composer can't reinstall here (lock needs PHP 8.4, local is 8.2.12); if axci changes `composer.lock`, flag it.
- Save this plan to `plans/axci-merge-online-reservation-payments.md`; add a `context/CHANGELOG.md` entry
  and a SCHEMA/ARCHITECTURE note that "settled" = paid directly **or** paid online.
- Implementation order: Step 0 → 1 (commit) → 2 → 3 → 4 → 5, one commit per step so each can be reverted alone.
