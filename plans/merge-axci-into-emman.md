# Merge axci into emman

## Context
Axcee wants all of axci's work on the emman branch. The two branches split at `7a9c0f6`:

| Branch | New commits | Main work |
|---|---|---|
| emman | 4 feature commits + 27 uncommitted files | Contracts/tenants/invites; online move-in charges full `moveInDue()`; `Reservation::moveInSettled()`; online pay holds the unit; held-money view on Contracts |
| axci (`origin/axci`, `0da821e`) | 6 commits | Policies page, editable lease templates, tenant documents; online + direct payments side by side, refunds, automatic payouts, cash reports, disputes; redesigned agreement page; tenant receipt; one filter-bar style |

A dry run (`git merge-tree`) shows **18 conflicted files, about 33 conflict blocks**, mostly in the payment code, because both branches rebuilt online move-in payments separately.

Decisions already made:
1. Commit the 27 uncommitted files to emman first, then merge.
2. **Payments follow axci's flow, start to finish.** The tenant picks per payment (online PayMongo, the landlord's QR, or cash), with axci's move-in amount, holding rules, refunds, payouts and disputes. Emman's three payment commits (`46778bd`, `cc1027f`, `848fb20`) are replaced by axci's version, not combined with it.

## Steps

### 0. Housekeeping
- Copy this plan to `plans/merge-axci-into-emman.md` (per CLAUDE.md).
- Back up emman's database: `mysqldump abanganan_hub` to the scratchpad. The merge adds axci's 12 migrations to it.

### 1. Commit the 27 uncommitted files on emman
- Read `git diff` (including `context/CHANGELOG.md`) to write an accurate message, e.g. `feat(landlord): ...`.
- Author it as EmmanBas24 only, with **no Co-Authored-By trailer** (CLAUDE.md overrides the default).
- Run `php artisan test --filter "Contract"` first so a broken state isn't committed.

### 2. Merge
- `git merge origin/axci` (a merge commit, same as the earlier `702eb5c Merge origin/axci into emman`). No rebase, no force.

### 3. Resolve conflicts (axci's payment flow wins)

**Payment code: take axci's side** (`git checkout --theirs`, then review):
`Concerns/ReconcilesPaymongoCheckout.php`, `PayMongoWebhookController.php`, `Tenant/PaymentController.php`, `Api/Tenant/PaymentController.php`, the payment parts of `Reservation.php`, `tests/Feature/Payments/TenantCheckoutTest.php`.
Non-conflicting payment files that emman's payment commits touched (`RentLedger.php`, `PaymentClaims.php`, `_direct-pay.blade.php`, `RentLedgerInitialTest.php`, `DirectPaymentPagesTest.php`) also get checked, so no emman-only payment logic survives that contradicts axci's flow. For example, emman's `platformInitialSplit` in `RentLedger` stays only if axci's move-in row still needs it.

**`Reservation.php`.** axci's payment methods, plus emman's **non-payment** contract/tenant methods (from the step-1 commit). Emman's `moveInSettled()` / `paidOnline()` / `scopeMoveInSettled()` stay only as thin readers of axci's payment state, if emman's Contracts, Tenants or Reservations pages call them. They must not change what axci's flow does.

**Emman pages that show payment state** (Contracts held-money/keys step, Reservations "Awaiting payment" / Moved-in tab, `Landlord/ReservationController`): keep the pages, and rewire their status checks onto axci's payment fields and statuses so they show what axci's flow actually did.

**Not carried over unless axci already does it:** emman's "online payment holds the unit" and "refuse a unit another tenant holds" (`cc1027f`). If axci has no equivalent, I'll note it in the summary as a gap for Axcee to decide on, not add it.

**Views.** Keep both features: axci's look (filter bar, agreement redesign, lease editor) plus emman's content (Moved-in tab, contracts links, held-money / keys step):
`landlord/reservations/index` (6 blocks), `conversations/partials/chat-panel`, `leases/_document`, `agreements/show`, `properties/show`, `landlord/tenants/index`, `layouts/landlord` (both sets of sidebar links).

**Other.**
- `app/Support/LeaseTerms.php`: axci's editable template wording, plus emman's rental-agreement wording if it is still needed.
- `WalkInTenantController.php`: no conflict markers, so it's a structural conflict. Inspect it with `git status`.
- `context/CHANGELOG.md`, `ARCHITECTURE.md`: keep both entries, newest dates on top.
- Migrations: keep emman's `2026_10_30_000000_create_landlord_tenants_and_lease_renewals` and all 12 of axci's.

### 4. Build and migrate
- `composer install --ignore-platform-reqs` (axci adds a package), `npm install`, `npm run build`.
- `php artisan migrate` on `abanganan_hub` (axci's 12 migrations are additive), then `php artisan optimize:clear`.

### 5. Verify
- `php artisan test`: the full suite must pass (axci alone reported 909 passing). Fix failures caused by the merge. Report any that also fail on axci alone, rather than hiding them.
- `grep -rn '^<<<<<<<\|^>>>>>>>' app resources routes tests context`: no markers left.
- Restart the servers on 8000/8080, then check in the browser:
  1. Landlord: Contracts page, Tenants, Reservations (Moved-in tab + filter bar), Payments, Policies / lease editor.
  2. Tenant: agreement page with axci's two-card pay choice; online move-in checkout behaves exactly as on axci (amount, fee, held until move-in); Contracts then shows the money as held.
  3. Direct pay: "I paid", then the landlord confirms; the reservation moves out of "Awaiting payment".
- Commit the merge, authored by the user, with no AI trailer. **Don't push** unless Axcee asks.

### 6. Cleanup (ask first)
- Offer to stop the 8001 copy, then `git worktree remove ..\AbangananHub-axci`, `git branch -D axci-run`, and drop `abanganan_hub_axci`.

## Rollback
Before the merge commit: `git merge --abort`. After it: `git reset --hard <step-1 commit>` (only with Axcee's OK), then restore the DB from the step-0 dump.
