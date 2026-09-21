# Landlord Payout Mechanism

**Date:** 2026-07-26
**Status:** Implemented same day. `payout_status`/`paid_out_at`/`paid_out_by`/`payout_reference`
on `payments`, `gcash_number`/`gcash_account_name` on `users`,
`Admin\PayoutController` (queue + mark-paid-out) and `Landlord\PayoutController`
(read-only pending/history view) are all live. Verified via tinker with a
synthetic pending-payout payment through the full mark-paid-out flow —
grouping, status flip, audit log — then cleaned up.

## Problem

Every online payment — the initial-payment escrow and, as of QRPh (2026-07-26),
monthly rent too — settles into AbangananHub's own PayMongo sandbox merchant
balance, never the landlord's. Once a payment reaches `Released` (initial) or
`Paid` (monthly rent), the app treats the money as the landlord's, but nothing
in the code moves it there:

- `Reservation::confirmMoveIn()`, `ProcessMoveInDeadlines` (Clock 2 auto-expiry),
  and `Admin\PaymentController::release()` all write `payments.status = 'Released'`
  and stop. No PayMongo payout/transfer API call, no queue job, nothing external.
- Monthly rent (`PaymentController::rentSuccess()` /
  `PayMongoWebhookController::handleCheckoutPaid()`) writes `status = 'Paid'`
  with no payout tracking of any kind — it isn't even on the release path's radar
  today.
- `context/ARCHITECTURE.md` already names this: *"Escrow is simulated — PayMongo
  sandbox handles payment capture, but the escrow hold-and-release logic is
  application-layer simulation, not a real escrow service."*

A landlord who sees "Released" on their dashboard has no actual claim on
anything — there is no bank transfer, no GCash send, no record of who owes them
what outside the `payments` table's status column.

## Design

### Money model: one pooled balance, not per-landlord escrow

PayMongo doesn't offer per-merchant sub-accounts or marketplace-style split
payments on the sandbox tier used here. Every tenant payment — GCash or QRPh,
initial or monthly — lands in the same platform PayMongo balance regardless of
which unit or landlord it's for.

This is not a gap to engineer around. It's how most marketplaces actually work
(Airbnb, Shopee): the platform holds one pool, and the ledger — not a wallet
boundary — is what tells you who's owed what. `payments` already is that
ledger. Nothing about the pooled model changes what's built above; it only
changes what happens *after* a payment is released/paid.

### Payout is a manual transfer, tracked in-app

No automated disbursement API. The admin manually sends money — GCash to
GCash, matching how tenants already pay in — to each landlord's registered
number, then records that it happened. This mirrors the existing pattern for
landlord-recorded offline rent payments (`recorded_by`, `reference_no`,
`payment_notes` from the 2026-07-24 migration): the app doesn't move real
money for offline rent either, it records a human's assertion that money
moved.

A real disbursement API (PayMongo business-tier payouts, or a second PSP like
Xendit) is out of scope — it needs landlord KYC and merchant-tier access this
project doesn't have, and is a materially larger integration than anything
built so far. See Explicitly out of scope.

## Schema

**`users`** — two new nullable columns, landlord payout destination:

| Column | Type | Purpose |
|---|---|---|
| `gcash_number` | nullable string | Where payouts get sent |
| `gcash_account_name` | nullable string | Name on the GCash account, so admin can verify before sending |

Nullable because tenants share this table and never need it; a landlord simply
can't be paid out until both are filled in.

**`payments`** — one new column and its supporting audit trio, same shape as
the existing `released_at` / `released_by` / `release_reason`:

| Column | Type | Purpose |
|---|---|---|
| `payout_status` | enum(`Pending Payout`, `Paid Out`) nullable | Null until the payment is money the landlord is owed at all |
| `paid_out_at` | nullable timestamp | When the admin recorded the transfer |
| `paid_out_by` | nullable FK → `users.user_id` | Which admin sent it |
| `payout_reference` | nullable string | The GCash transaction reference, typed in by the admin after sending |

`payout_status` starts null, not `'Pending Payout'`, because most payments
never reach a payout-eligible state at all (`Pending`, `Held`, `Failed`) — a
non-null value should mean something, not just be the default everyone
ignores until release.

### When `payout_status` is set

`'Pending Payout'` is written at the exact moment a payment becomes money owed
to a landlord — piggybacking on writes that already exist, not a new trigger:

- Initial payment: alongside `status = 'Released'` in all three existing
  release paths (`Reservation::confirmMoveIn()`, `ProcessMoveInDeadlines`,
  `Admin\PaymentController::release()`).
- Monthly rent: alongside `status = 'Paid'` in
  `PayMongoWebhookController::handleCheckoutPaid()` and the
  `rentSuccess()`/`success()` poll-fallback in `Tenant\PaymentController`.

Landlord-recorded offline payments (`recorded_by` non-null) never get a
`payout_status` — the landlord already collected that money directly; there is
nothing for the platform to pay out.

## Admin payout workflow

New admin view: a payouts queue grouped by landlord, each row a running total
of `payout_status = 'Pending Payout'` payments.

1. Admin opens the queue: "Juan dela Cruz — ₱18,000 across 2 payments,
   pending payout."
2. Admin manually sends ₱18,000 via GCash to the `gcash_number` on file — this
   is the actual money movement, and it happens outside the app.
3. Admin marks the batch "Paid Out," entering the GCash transaction reference.
   This writes `payout_status = 'Paid Out'`, `paid_out_at = now()`,
   `paid_out_by = auth()->id()`, `payout_reference` on every payment in the
   batch, inside one locked transaction — same `lockForUpdate()` pattern as
   `Admin\PaymentController::release()`.

A landlord missing `gcash_number`/`gcash_account_name` is visibly flagged in
the queue rather than silently payable-to-nothing — the admin needs to chase
that detail before a payout can be recorded.

## Landlord-facing visibility

A "Payouts" section on the landlord dashboard: pending payout total, plus a
history of paid-out batches with references — so a landlord can check "did
AbangananHub actually send my money" against their own GCash app, the same way
they'd check any transfer.

## Explicitly out of scope

**Automated PSP disbursement.** Requires PayMongo business-tier access (or a
second provider) plus landlord KYC — a materially bigger integration than a
manual-transfer-with-audit-trail model, and not something this sandbox account
can do today regardless of app-side work.

**Per-landlord PayMongo sub-accounts / split payments.** Investigated and
ruled out for the same tier-access reason — not a "build it later" gap, a
"can't, on this account" one.

**Bank transfer as a payout destination.** GCash only, matching what tenants
already pay with and what a manual admin transfer can realistically do at
volume. A landlord who only banks (no GCash) is out of scope for now.

**Partial payouts.** A payout batch is all-or-nothing per landlord per run —
no splitting a landlord's pending total across multiple partial transfers.

## Open risks

- **Reconciliation has no automated check.** Nothing in this design verifies
  that AbangananHub's actual PayMongo balance can cover what's queued for
  payout, or that admin-entered payout totals don't silently drift from what
  PayMongo actually settled. For now this is an admin-diligence problem, same
  as offline-recorded payments already are.
- **A landlord with a typo'd `gcash_number` has no in-app recovery** — the
  admin would send money to the wrong account. Verifying `gcash_account_name`
  against the number before sending is a manual admin check, not something the
  app enforces.
- **Payout batching is per-run, not per-billing-period** — if an admin marks a
  batch "Paid Out" that includes a rent payment from a disputed tenancy, there
  is no automatic claw-back. This should stay rare given the small scale, but
  is worth flagging if payout volume grows.
