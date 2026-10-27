# Direct payments: the tenant pays the landlord, AbangananHub keeps the record

**Status:** implemented Oct 24 2026 on branch `axci`, pending manual verification in a browser (checklist at the end). Not built: tenant receipts for direct payments, mobile claim endpoints, dropping the payout tables.

Replaces "Phase 4" in `plans/stakeholder-feedback-pass.md`. Save a copy as
`plans/direct-payments-record-and-confirm.md`, and point phase 4 of the old plan at it.

## Context
- **Why:** today AbangananHub holds the money. The tenant pays through PayMongo into our account, a
  deposit sits as `Held`, and an admin later sends it to the landlord's GCash by hand. This creates
  four problems:
  - Holding other people's money may need a license from the BSP (the central bank).
  - The stakeholders asked "if someone hacks you, who pays me back?"
  - Every payout waits on one admin.
  - We have to store each landlord's GCash number.
- **Decided Oct 24 2026 with Axcee:**
  1. AbangananHub never holds money. The tenant pays the landlord however they already do (cash,
     GCash, Maya, bank) and files "I paid". The landlord confirms or rejects it. Only a confirmed
     claim counts as money.
  2. Payment is by QR only (QR Ph / InstaPay: GCash, Maya, GoTyme, bank apps), no cards. The
     landlord shares their QR in the chat with that tenant. We never ask for an account name or
     number. Cash is recorded by the landlord.
  3. PayMongo checkout stays in the code behind an **admin switch, off by default**.
  4. GCash payout fields: **hide now, drop the table later.**
  5. In-app payment straight to the landlord (PayMongo Platforms or Xendit) is a later **phase 5**,
     after talking to a provider.
- **What we give up:** no deposit hold protects a tenant from a landlord who takes the money and
  never hands over keys. The tenant is told this in plain words. What is left: verified landlords,
  reviews, the signed lease, and a dated record of every claim.

## 1. Admin switch: `online_payments_enabled`
- `config/rentals.php`: `online_payments_enabled => env('ONLINE_PAYMENTS_ENABLED', false)`.
- `app/Models/Setting.php`:
  - Add group `GROUP_PAYMENTS` and the key, with label "Online payments (PayMongo)".
  - Add a `'boolean'` type to `cast()` and `serialize()`. Only `integer` and `integer_list` exist
    today.
  - Store `'0'` rather than deleting the row, so "off" is not lost when the file default is on.
  - The admin settings form renders from `DEFINITIONS`. Add a checkbox branch to
    `admin/settings/edit.blade.php`.
- One helper, `App\Support\Payments::online(): bool`. Every gate reads it.
- **Off means:**
  - The "Pay now" and "Pay ₱X now" buttons are hidden. This covers `agreements/show.blade.php:225`,
    `tenant/tenancy/show.blade.php:173` and the chat-panel "Proceed to payment" link.
  - New checkouts are refused (409 with a plain message) in the web and API
    `Tenant\PaymentController::checkout`/`pay` and `payRent`.
- **Still runs, so money already in flight finishes:**
  - the webhook, `reconcile`, `ProcessMoveInDeadlines`, `Reservation::confirmMoveIn()`, admin
    release and admin payouts;
  - an existing `Held` reservation keeps its clocks and copy unchanged.

## 2. The landlord shares a payment QR in the chat (changed Oct 24 2026)
Payments are **QR only**: QR Ph / InstaPay, which GCash, Maya, GoTyme and every bank app can pay.
No cards. Cash is not claimed by the tenant; the landlord records it.

**The landlord shares the QR per conversation, in the chat**, not once in Account Settings. These
landlords already agree on payment in chat, the choice is made per tenant, and it allows different
accounts per property. We never ask for an account name or number: the QR carries both.

**We store the QR's contents, not the image.**
- In the browser, `jsqr` (npm, lazy-loaded only by the share form) reads the uploaded screenshot,
  for example a Maya "Receive money" QR.
- The browser posts only the decoded text (`qr_payload`). The server validates it as a QR Ph code
  in `App\Support\QrPh::parse()`:
  - EMVCo tag-length-value format, starting `000201`;
  - the CRC in tag `63` matches;
  - it has a merchant account block (tags 26–51);
  - it is not a web link.
  It also reads the account name (tag 59) and city (tag 60).
- The server draws the QR again as SVG from the validated text (`bacon/bacon-qr-code`, which needs
  no GD). What a tenant scans is therefore always exactly the validated code, never an image a
  landlord could swap. This also stops "QR to a fake site" scams, and no image is stored.
- **Why not read the image on the server:** PHP here has no GD or Imagick, and it would also mean
  storing the image.

**New table `payment_qr_shares`:**

| Column | Type | Note |
|---|---|---|
| payment_qr_share_id | PK | |
| conversation_id | FK conversations, cascade | index `(conversation_id, created_at)` |
| shared_by | FK users, cascade | must be the conversation's landlord |
| payload | text, `encrypted` cast | holds the account details |
| account_name | varchar(100) | from tag 59, shown as "Pay to AXCEE CABUSAS" |
| note | varchar(300), null | e.g. "Pay before the 5th" |
| replaced_at | timestamp, null | set on the older share when a new one is posted |
| timestamps | | |

- A chat message with `kind = 'payment_qr'` and a new nullable `messages.payment_qr_share_id`
  (FK, null on delete), the same pattern as `concern_id`.
  - The chat shows a "Payment QR from Maria (landlord)" box: name, the QR, the note, and
    "Replaced" once a newer one exists.
  - Pushed live through the existing `MessageSent` event.
- Who can post: only the conversation's landlord (`ConversationPolicy::sharePaymentQr`).
- Who can see the QR: only the two people in that conversation.
- Posting a new QR notifies the tenant: "Maria updated how to pay you."
- **Prefill:** the share form offers "Use the QR you shared last time", from that landlord's latest
  share. That saves retyping for every tenant.
- **Latest QR for a reservation:** `PaymentQrShare::latestFor(Reservation)` is the newest
  unreplaced share in any conversation between that tenant and landlord for that property. Both
  the inquiry thread and the tenancy thread count, because the tenancy thread only opens at
  Occupied.
- **Saving the QR on a phone:** the tenant can't scan their own screen, so the enlarged QR has a
  "Save QR image" button. It draws the SVG to a canvas and downloads a PNG, so the tenant can
  upload it in GCash, Maya or GoTyme.
- The pay sections carry the line "Only pay to a QR your landlord shared here."
- **Not doing:** payment links (a phishing route we can't check), and storing the uploaded image.

## 3. New table `payment_claims`
Kept apart from `payments` on purpose. An unconfirmed claim can never be counted as money:
`RentLedger::SETTLED_STATUSES`, the analytics whitelists and `hasSettledInitialPayment` stay
untouched by claims.

| Column | Type | Note |
|---|---|---|
| payment_claim_id | PK | |
| reservation_id | FK reservations, cascade | index `(reservation_id, status)` |
| claim_type | enum('MoveIn','Monthly') | |
| billing_period | date, null | required for Monthly (first of month) |
| amount | decimal(10,2) | MoveIn: defaults to rent + deposit. Monthly: defaults to the period balance, late fee included. Editable, 1 to 1,000,000 |
| payment_method | enum('GCash','Maya','GoTyme','Bank Transfer','Other') | the app the tenant paid from; "Bank Transfer" is labelled "Bank app". `GoTyme` is also added to `payments.payment_method` (MySQL enum change) |
| reference_no | varchar(100) | **required**: every QR payment has one, and the landlord checks it in their own app |
| paid_on | date | ≤ today; becomes `paid_at` on the rows, so the late fee is judged by when the tenant paid, not when the landlord confirmed |
| note | varchar(500), null | |
| status | enum('Pending','Confirmed','Rejected','Withdrawn') | default Pending |
| filed_by | FK users, set null | |
| decided_by | FK users, set null | |
| decided_at | timestamp, null | |
| rejection_reason | varchar(255), null | required on reject |
| timestamps | | |

- **New column `payments.payment_claim_id`** (FK, set null). It goes in the Payment fillable list,
  with a `claim()` relation.
- **New column `reservations.move_in_paid_at`** (timestamp, null). This is the one signal that the
  direct move-in money is settled. Without it, every view would have to work it out from row
  shapes.

## 4. Services (one place for every money rule)
- **`App\Services\MoveInPaymentRecorder::record(Reservation, float $amount, string $method,
  $paidAt, ?string $ref, int $recordedBy, ?int $claimId)`.**
  - Move the body of the `Concerns\RecordsMoveInPayments` trait here: split with
    `MoveInPaymentBreakdown` into Deposit, first month and advance rows, `status = Paid`.
  - The two walk-in controllers call this service. Delete the trait.
  - Because these are `Monthly` rows with a `billing_period`, `RentLedger` settles month one with
    **no ledger change**. The `isPlatformInitial()` edge from the old plan goes away.
- **`RentPaymentRecorder::record()`** accepts an optional `payment_claim_id` in `$data`.
- **`App\Services\PaymentClaims`.** Each method runs in a transaction with
  `lockForUpdate()` on the reservation, re-checks state under that lock, and calls
  `AuditLog::record()` and `Notification::notify()`.

  | Method | Rules |
  |---|---|
  | `file()` | Tenant only. MoveIn needs `Rental Agreement Signed` and `move_in_paid_at` null. Monthly needs `Occupied`. Only one Pending claim per (reservation, type, period). Refused while a fresh PayMongo checkout is Pending for the same thing (only possible when the switch is on). |
  | `confirm()` | Owning landlord only. The claim must still be Pending (so a double click writes one set of rows). MoveIn: `MoveInPaymentRecorder` with the claim id, then set `move_in_paid_at`. Monthly: `RentPaymentRecorder` (spreads a lump over months like a typed payment), `recorded_by` = landlord. Then the claim becomes Confirmed. Audit action `payment_claim.confirm`. |
  | `reject()` | Owning landlord, reason required. Audit action `payment_claim.reject`. The tenant can file again. |
  | `withdraw()` | Tenant, Pending claims only. |

- **Landlord records move-in money themselves** (cash at the door, no claim):
  `Landlord\MoveInPaymentController::store`.
  - Status must be `Rental Agreement Signed`.
  - It calls the same `MoveInPaymentRecorder` and sets `move_in_paid_at`.
  - It withdraws any Pending MoveIn claim, so the same money isn't counted twice.
  - Reuse the amount and live-split fields from the walk-in form, as a shared partial.

## 5. Move-in without escrow
- **`Reservation::markOccupied()`** already exists with no callers. Reuse it rather than add a new
  method.
  - Add guards: status `Rental Agreement Signed`, `move_in_paid_at` set, no `Held` payment.
  - If the tenant pressed it, also set `tenant_confirmed_move_in_at`.
  - `ReservationObserver` then opens the tenancy thread as usual.
- **Either party can tap "Confirm move-in".** No money rides on it, the same reasoning as
  `confirmWalkInMoveIn()`.
  - Tenant: `agreements.confirmDirectMoveIn`.
  - Landlord: `landlord.reservations.confirmDirectMoveIn`, in the chat panel and the reservations
    list.
  - Gate: `ReservationPolicy::confirmDirectMoveIn`.
- **`hasSettledInitialPayment()`** also returns true when `move_in_paid_at` is set. Its in-memory
  copy in `tenantNextStep()` (line 882) gets the same change.
- **Void while still Signed:** `Landlord\PaymentController::void` clears `move_in_paid_at`.
  - This is safe because the record form only allows `Occupied`, so any recorded row on a Signed
    reservation is move-in money.
- **Terminal statuses:** when a reservation reaches Cancelled, Rejected or Completed, the
  `ReservationObserver` withdraws its Pending claims. This covers cancel, reject and ending a
  tenancy.
- **Cancel confirm text** when `move_in_paid_at` is set: "₱X was paid directly to {landlord}.
  AbangananHub doesn't hold it and can't return it. Settle it with each other."
  `cancellationBlockedReason()` is unchanged, because nothing is held.

## 6. Screens (375px first)
Two shared components so the screens can't drift:
- `<x-payment-claim-form :reservation :type>`: the tenant's bottom sheet.
- `<x-payment-claim-review :claim>`: the landlord's Confirm and Reject card, with a reject-reason
  modal. It copies the `record-payment-modal` structure, per DESIGN.md §6q.

| Where | Tenant | Landlord |
|---|---|---|
| `agreements/show.blade.php`, Signed, switch off | New rail state "Pay your landlord to move in": first month ₱A + deposit ₱B = ₱C, the latest QR shared in their chat with "Pay to {name}" and the note (tap to enlarge, "Save QR image"; "Ask your landlord to share their payment QR" when there is none), notice "This goes straight to {landlord}. AbangananHub doesn't hold or refund it.", "I've paid" button. Then: waiting (with Withdraw), rejected (reason + "Submit again"), confirmed + "I've moved in" | — |
| `chat-panel.blade.php` (any stage from Under Negotiation) | The shared-QR box in the thread; at Signed a one-line status + link to the agreement page | "Share payment QR" in the composer menu; at Signed the claim review card, or "Record move-in payment", then "Confirm move-in" |
| `landlord/reservations/index.blade.php` | — | "Payment to confirm" badge + "Confirm move-in" action (table and card views) |
| `tenant/tenancy/show.blade.php` | "Pay your landlord" card with the latest shared QR and "I paid" in place of Pay now, plus a "Your payment reports" list (Pending, Rejected with reason) | — |
| `landlord/tenancies/show.blade.php` | — | "Payments to confirm" card above Unpaid periods |
| Landlord dashboard + `landlord/payments/index` | — | "N payments waiting for you to confirm" alert, linking to each tenancy |
| Receipt | Tenant can open the receipt for their own confirmed payments (extend the `payments.receipt` gate) | unchanged |

**Escrow copy becomes conditional** on `Payments::online()`, through one
`<x-payment-consent :role>` component:
- Today's text when the switch is on.
- Off: "Payments go directly to the landlord. AbangananHub records them but doesn't hold or refund
  money."

Places that change:
- `agreements/show.blade.php:173`
- `chat-panel.blade.php:225`
- `landlord/reservations/index.blade.php:962` and the turnover confirm copy at 258–260
- the payout help text in `update-landlord-business-form.blade.php:11`
- `RentReminderNotifier` tenant text, when off: "Pay your landlord, then tap 'I paid' on your
  tenancy page."

`_move-in-clock` only renders when there is a `Held` payment, so it stays silent. Verify, don't
change.

**Payout UI hidden when off:**
- The `#payout` section and its sidebar link in `profile/edit.blade.php:154`.
- The landlord Payouts nav link, unless that landlord has payout rows.
- The admin payouts queue stays, for money already in flight.
- `LandlordSeeder:87` stops seeding GCash payout data.
- Dropping `user_payout_accounts` and `payouts` is a later cleanup.

**Notifications** (type `payment`):
- Claim filed → landlord: "Ana says she paid ₱3,500 by GCash (ref 1234) on Oct 3. Check, then
  confirm." The link goes to the decision page.
- Confirmed or rejected → tenant.

## 7. Mobile API (kept small)
- The checkout endpoints refuse when the switch is off (section 1).
- `Api/Tenant/TenancyController` and the agreement show endpoint add `online_payments_enabled` and
  `payment_qr` (account name, note, raw payload so the app can draw the QR, shared date).
- The message payload carries `kind = payment_qr` and the share, so the app's chat can render it later.
- Claim endpoints are a follow-up. Add them to `plans/mobile-app.md`.

## 8. Account deletion
`ProfileController::activeObligation()`: also refuse deletion while the user has a Pending claim,
either as the tenant or as the landlord who has to decide it.

## 9. Phase 5 (later, not built now): in-app payment straight to the landlord
- PayMongo Platforms or Xendit xenPlatform sub-accounts. The provider checks the landlord's ID and
  holds their bank details. We store only the landlord's provider account ID.
- The webhook writes a `Paid` row directly, with no claim step.
- It reuses the switch from section 1 and the existing checkout code.
- Before building: ask the provider about fees, the documents we need, and whether a deposit can be
  delayed.

## Tests (new: `PaymentClaimTest`, `DirectMoveInTest`, `PaymentQrShareTest`, `QrPhTest`)
- **MoveIn:** file → landlord confirms → Deposit + Monthly rows with the claim id → month one Paid
  in `RentLedger` → `move_in_paid_at` set → "Pay now" gone → confirm move-in by the tenant, and
  separately by the landlord → Occupied, tenancy thread opens.
- **Monthly:**
  - a claim spreads a lump over two months;
  - a claim with `paid_on` inside the grace period carries no late fee even when confirmed after
    the deadline;
  - confirmed rows never get `payout_owed_at`.
- **Rules:**
  - reject (reason required) and file again;
  - withdraw;
  - a double confirm writes one set of rows;
  - a second Pending claim for the same period is refused;
  - another tenant or a stranger landlord gets 403;
  - claims are refused on the wrong status;
  - Pending claims are withdrawn on cancel and on ending the tenancy;
  - landlord-recorded move-in withdraws a Pending claim;
  - voiding move-in money while Signed clears `move_in_paid_at`.
- **Switch:**
  - off: web and API checkout give 409 and the buttons are hidden;
  - on: today's flow is unchanged (existing `TenantCheckoutTest` and the escrow tests still pass);
  - an in-flight `Held` payment still releases through `confirmMoveIn`;
  - the `Setting` boolean round-trips.
- **QR (`QrPhTest`, `PaymentQrShareTest`):**
  - `QrPh::parse` accepts a real InstaPay payload and reads the name; rejects a bad CRC, a URL, plain text, and a payload with no account block;
  - only the conversation's landlord can share; the tenant and strangers get 403;
  - a new share marks the older one replaced and notifies the tenant;
  - `latestFor` finds the share in the inquiry thread for a Signed reservation;
  - the payload column is encrypted in the database;
  - a claim with no reference number is refused.
- **Other:** account deletion is blocked by a Pending claim; walk-in tests still pass after the
  trait moves to a service.

## Docs
- `context/SCHEMA.md`: `payment_claims`, `payment_qr_shares`, `messages.payment_qr_share_id`, `payments.payment_claim_id`,
  `reservations.move_in_paid_at`.
- `context/ARCHITECTURE.md` decision log: we don't hold money; claims are kept apart from
  `payments`; the QR is private; PayMongo sits behind a switch.
- `context/PRD.md` §3: Direct payments. Escrow becomes "only when online payments are on".
- `context/CHANGELOG.md`: one entry.
- `plans/stakeholder-feedback-pass.md`: phase 4 points here.
- Demo seed: one shared payment QR in a demo chat, one Pending move-in claim, one Pending monthly
  claim.

## Verification
1. `php artisan test`: the whole suite is green, and the new tests above pass.
2. `php artisan migrate:rollback --step=1` then `migrate`: the migration rolls back cleanly.
3. `npm run build`, then a browser pass at 375px first, then desktop, light and dark:
   1. Landlord shares `~/Pictures/mayaqr.jpg` in the chat → the box shows "Pay to AXCEE CABUSAS" → the tenant sees it on the agreement page after signing → "Save QR image" gives a PNG that Maya/GCash can read → a random photo or a website QR is refused.
   2. Tenant signs → "I've paid" ₱C by GCash → the landlord is notified → confirms in chat → both
      see the payment → the tenant taps "I've moved in" → Occupied, tenancy chat opens.
   3. Same, but the landlord taps "Record move-in payment" for cash, then "Confirm move-in".
   4. Monthly claim → the landlord rejects it with a reason → the tenant sees the reason → files
      again → the landlord confirms → the ledger shows Paid, and the receipt opens for both.
   5. Admin turns online payments on → "Pay now" is back; turned off → it's gone and the API
      returns 409.
   6. Cancel after a direct move-in payment → the warning text shows; the Pending claim is withdrawn.
