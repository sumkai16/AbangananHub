# Stakeholder feedback pass: direct payments, late fees, tenant records, lease terms

**Status:** Phases 1 and 2 implemented Oct 18 2026 and phase 3 on Oct 20 2026, all on branch `axci`, pending manual verification (checklist below). Phase 4 not started.
**Source:** 41-minute Q&A with two landlords (a couple, both nurses, who own a multi-floor dormitory
near St. Cecilia's). The project manager ran the demo. Transcript notes: see "Source notes" at the end.

## What this plan does

| # | Change | Size | Stakeholder ask |
|---|---|---|---|
| 1 | Tenant can pay the landlord directly (GCash QR or cash), for the move-in payment and for rent; the landlord confirms it | Large | "How reliable is your site? If someone gets into it, who pays me back?" The PM offered paying outside the system as the alternative |
| 2 | Per-property rent grace days and an optional one-time late fee | Medium | "3 days grace, then we add a percentage on the 4th" |
| 3 | Emergency contact and ID on file for every tenant | Small | "We ask for ID and an emergency contact, in case something happens" |
| 4 | No-deposit units read correctly; landlords can type extra lease terms on the web | Small | "We only ask for the first month"; notarized contracts with their own rules |

Build order: **4 → 3 → 2 → 1**. Smallest and safest first; 1 last because it reads the late fee from
2 (a direct rent claim defaults to the period's balance, fee included).

**One agent, in sequence. No forks.** All four touch the same few files (`chat-panel.blade.php`,
`LeaseTerms`, `_document.blade.php`, the tenancy pages, `RentLedger`), so parallel agents would collide.

---

## Phase 1 — No-deposit units and extra lease terms (item 4)

### What is already there
- `property_units.security_deposit` accepts 0 (`PropertyUnitController` validates `min:0`).
- The platform only ever charges the first month at move-in. There is no advance charge to remove.
- `reservations.agreement_terms_notes` (max 2000) is accepted by the web controller, stored, frozen into
  `lease_snapshot['extra_terms']`, and printed on the lease (`leases/_document.blade.php:149`).
  **But no web form has the field.** Only the mobile API can send it. Web landlords cannot add terms.

### Changes
1. **Lease summary shows "None" for a ₱0 deposit.** `_document.blade.php:59` checks `!== null`, so 0
   prints "₱0.00". Change it to `> 0`, matching line 99.
2. **Unit form hint** under Security deposit: "Enter 0 if you don't collect a deposit."
   Check `properties/show.blade.php` around line 521 shows "No deposit" for a ₱0 unit (it already
   filters on `> 0`, so verify only).
3. **New column `properties.default_lease_terms`** (text, null). Edited on the property edit page as
   "Standard lease terms (optional)", with help text: "Added to every agreement you send for this
   property. You can still edit them per tenant."
4. **"Additional terms" textarea in both web Send-agreement forms**, prefilled from
   `default_lease_terms`, max 2000, posted as `agreement_terms_notes`:
   - `conversations/partials/chat-panel.blade.php` (the form near line 211)
   - `landlord/reservations/index.blade.php` (the `openAgreement()` modal)
   One shared partial, `landlord/reservations/_terms-field.blade.php`, so the two can't drift.

### Tests
- `LeaseTermsTest` (extend or new): a ₱0 deposit renders "None"; extra terms land in the snapshot.
- `SendAgreementTermsTest`: the web form posts terms; the textarea is prefilled from the property.

---

## Phase 2 — Emergency contact and ID on file (item 3)

### Design
A new table, **`tenant_details`**, one row per user, kept off `users`. This follows the precedent of
`user_payout_accounts`: sensitive data never rides along in a nested user payload.

| Column | Type | Note |
|---|---|---|
| user_id | PK, FK → users, cascade | |
| emergency_contact_name | varchar(150), null | |
| emergency_contact_relationship | varchar(50), null | e.g. Parent, Sibling |
| emergency_contact_number | varchar(20), null | |
| id_type | varchar(50), null | Pick list: PhilSys, Driver's License, Passport, UMID, Postal, Student ID, Other |
| id_number | text, null | **`encrypted` cast.** Text because the ciphertext is longer than the plain value |
| timestamps | | |

Model `TenantDetail`, `User::tenantDetail()` hasOne, `TenantDetail::maskedIdNumber()` (last 4), the
same approach as `LandlordVerification::maskedIdNumber()`.

### Who sees what

| Viewer | Emergency contact | ID number |
|---|---|---|
| The tenant | Full, editable | Full, editable |
| Landlord with a reservation for this tenant at `Rental Agreement Signed`, `Occupied` or `Completed` | Full | Full on the landlord tenancy page only; masked elsewhere |
| Admin | Full | Masked |
| CSV exports, API list payloads, `lease_snapshot` | Never the ID number | Never |

`lease_snapshot` is plain JSON, so writing the ID into it would undo the encryption. Keep it out.
Rule: `ReservationPolicy::viewTenantDetails()` and `editWalkInDetails()`, scoped to the reservation (landlord of that property, status Reserved, Occupied or Completed) instead of a separate `TenantDetailPolicy`. Admin screens do not display the details yet.

### Where it is entered
1. **Account Settings** (`/profile/settings`): a new "Emergency contact & ID" section (tenants only).
2. **Agreement page, before signing** (`agreements/show.blade.php`): if the tenant has no details,
   show the four fields above the Sign button and require name, number, ID type and ID number. Saved
   to `tenant_details`, then the agreement is signed. Already filled → one line,
   "Emergency contact: Ana Cruz (Mother) · Edit".
   Enforced in `Tenant\AgreementController` (sign), not only in the view.
3. **Walk-in form** (`StoreWalkInTenantRequest`): the same fields, optional. The landlord can also
   edit them later on the walk-in's tenancy page, because a walk-in can't log in to do it.

### Shown on
- Landlord tenancy page (`landlord/tenancies/show.blade.php`): an "Emergency contact & ID" card.
- Tenant tenancy page: read-only, with an Edit link to Account Settings.

### Tests
- `TenantDetailsTest`: the encrypted value round-trips and the raw DB value is not the plain number;
  signing is refused without details; landlord visibility by status; a stranger landlord gets 403;
  CSV export has no ID column; walk-in edit by the owning landlord only.

---

## Phase 3 — Grace days and late fee (item 2)

### Design
Per property, frozen into each lease when it is issued, read by the ledger from the lease.

New columns on `properties`:

| Column | Type | Note |
|---|---|---|
| rent_grace_days | tinyint unsigned, null | null = platform default (`rentals.rent_overdue_grace_days`, 0) |
| late_fee_type | enum('percent','fixed'), null | null = no late fee |
| late_fee_value | decimal(10,2), null | Percent of monthly rent (0.01–100) or a peso amount |

Edited in a "Rent rules" block on the property edit page. Preview line: "Rent due on the 1st can
be paid until the 3rd. From the 4th, a one-time late fee of 10% (₱350 on ₱3,500) is added."

**Frozen into `LeaseTerms::snapshot()`** as `grace_days` and `late_fee` (`{type, value}` or null). The
lease prints it under Rent. The ledger reads it from `LeaseTerms::for($reservation)`, so editing the
property later never changes an existing tenant's terms. Reservations with **no** snapshot (issued
before snapshots existed) get **no late fee**, and grace falls back to the platform default. A landlord
switching on a fee must never charge tenants who never agreed to it.

### Ledger rule (`RentLedger::periods()`)
A period carries a late fee when **both** are true:
1. The grace deadline (`due_on + grace_days`, end of day) has passed.
2. The money paid into that period **on or before** the deadline is less than the rent. Payments are
   filtered by `paid_at`, so a tenant who pays late still owes the fee after paying the rent.

`fee = percent ? round(rent × value / 100, 2) : value`. **One-time per period, no daily accrual.**
The period row gets a `late_fee` key, and `expected = rent + late_fee`. Everything downstream already
reads `expected` and `balance`: status, summary, `RentPaymentAllocator`, the PayMongo rent checkout
amount, reminders. The fee flows through with no extra plumbing. The grace used by `periodStatus()`
becomes the lease value, not the global config.

### Waiving a fee
These landlords said they treat tenants like family, so the fee must be easy to drop.
New table **`late_fee_waivers`**: `reservation_id` FK cascade, `billing_period` date, `waived_by` FK
users set null, `reason` varchar(255) null, timestamps, unique `(reservation_id, billing_period)`.
"Waive late fee" sits in the landlord's period detail modal. A waived period has `late_fee = 0` and
shows "Late fee waived". The waiver is audit-logged with `AuditLog::record()`, like voids.

### Surfaces
- Ledger rows and the period modal (landlord and tenant): "Rent ₱3,500 + late fee ₱350".
- Tenant rent checkout line item: "Rent (Oct 2026) + late fee".
- Rent reminder text mentions the fee when there is one.

### Tests
- `RentLedgerLateFeeTest`: paid in grace → no fee; paid after → fee stays after paying rent only;
  partial before deadline → fee; percent vs fixed; waiver zeroes it; no-snapshot reservation → no fee;
  property edit after issue doesn't change an existing lease; allocator spreads a lump over rent+fee.
- Extend `TenantCheckoutTest`: the rent checkout amount includes the fee.

---

## Phase 4 — Pay the landlord directly (item 1)

### The idea
The tenant pays the landlord outside AbangananHub (scan the landlord's GCash QR, or hand over cash),
then tells the system "I paid". The landlord confirms, and only then does it count as paid. This is
the same trust model as a landlord-recorded payment today. The difference is that the tenant starts it.

**Direct payments are not protected by escrow.** The move-in payment goes straight to the landlord, so
there is no Held money, no turnover clock, no confirmation clock and no dispute freeze. The tenant is
told this in plain words before choosing it.

### Landlord setting
On the landlord profile edit page (where the GCash payout details already live), a new
**"How tenants can pay you"** block:

| Value | Tenant sees |
|---|---|
| `platform` (default) | Online checkout only (today's behaviour) |
| `both` | Both options, online listed first as "Protected" |
| `direct` | Direct only |

Plus an uploaded **GCash QR image**. It is required when the value is `both` or `direct`, so a tenant is
never told to pay a landlord with no QR. The number and account name shown next to it are the existing
payout GCash fields.

Stored on `user_payout_accounts` (it is "how money reaches this landlord"):
`payment_preference` enum('platform','both','direct') default 'platform', `direct_qr_url` varchar
null, `direct_qr_public_id` varchar null. The QR is uploaded via `ImageUploader` to Cloudinary. A QR
is meant to be shown to tenants, so public media is fine.

Read live, not frozen per reservation. Changing it affects the next payment, never one already made.

### New table: `payment_claims`
A tenant's assertion. Kept apart from `payments` on purpose: `RentLedger::SETTLED_STATUSES` and the
analytics whitelists stay untouched, and an unconfirmed claim can never be counted as money by accident.

| Column | Type | Note |
|---|---|---|
| payment_claim_id | PK | |
| reservation_id | FK → reservations, cascade | Index `(reservation_id, status)` |
| claim_type | enum('Initial','Monthly') | |
| billing_period | date, null | Required for Monthly |
| amount | decimal(10,2) | Initial: fixed to `monthlyRent()`. Monthly: defaults to the period balance, editable |
| payment_method | enum('GCash','Cash','Bank Transfer','Maya','Other') | |
| reference_no | varchar(255), null | Required unless Cash |
| paid_on | date | ≤ today |
| note | varchar(500), null | |
| status | enum('Pending','Confirmed','Rejected','Withdrawn') | default Pending |
| decided_by | FK → users, set null | |
| decided_at | timestamp, null | |
| rejection_reason | varchar(255), null | Required on reject |
| timestamps | | |

New column **`payments.payment_claim_id`** (FK → payment_claims, set null). It is set on every payment
row a confirmed claim writes.

### Flows

**Move-in (status `Rental Agreement Signed`, no initial payment yet)**
1. The agreement page offers the routes the landlord allows. Direct shows the QR, GCash number, account
   name and amount (first month's rent), the "not protected" notice, and an "I've paid" form (method,
   reference, date).
2. Submit → `PaymentClaim` Pending, landlord notified ("Ana says she paid ₱3,500 by GCash, ref …").
3. Landlord confirms (chat panel and reservations list) → in one transaction with the reservation
   locked: a `payments` row `payment_type = 'Initial'`, `status = 'Paid'`, `recorded_by = landlord`,
   `payment_claim_id` set, no `payout_owed_at` (the landlord already has the money). Claim → Confirmed.
   `hasSettledInitialPayment()` is already true for `Paid`, so "Pay now" disappears with no change there.
4. Move-in: **either party** taps "Confirm move-in" → new `Reservation::confirmDirectMoveIn()`. It needs
   status `Rental Agreement Signed`, a settled `Initial` with `payment_claim_id` set, and no Held
   payment. It sets Occupied, `tenant_confirmed_move_in_at`, and the unit to Occupied, so
   `ReservationObserver` opens the tenancy thread as usual. Either party can do it because no money
   rides on it, the same reasoning as `confirmWalkInMoveIn()`. The existing `markOccupied()` has no
   callers; delete it rather than leave two near-identical methods.
5. Reject → claim Rejected with a reason, tenant notified, and the tenant can submit again.

**Monthly rent (status `Occupied`)**
1. Tenancy page: next to "Pay now" (if allowed), "I paid the landlord directly". Same form, defaults to
   the earliest unsettled period and its balance (late fee included).
2. Landlord confirms on the tenancy page or the payments page → `RentPaymentRecorder::record()` with
   `recorded_by = landlord` and the claim's method, reference and date, plus `payment_claim_id`. It
   spreads a lump over months exactly like a landlord-typed payment. `RentPaymentRecorder` gains an
   optional `payment_claim_id` in `$data`.

### Rules that keep money safe
- **One open route at a time.** In one locked transaction: a claim is refused while a fresh PayMongo
  checkout is Pending, and a checkout is refused while a Pending claim exists for the same thing
  (Initial, or the same billing period). This applies to web and API checkout.
- `payment_preference = direct` → web **and API** online checkout refuse with a plain reason. The API
  must be gated too, or the mobile app becomes a way around the landlord's choice.
- Only the reservation's tenant can file or withdraw a claim. Only that property's landlord can decide
  it. The decision re-checks status under `lockForUpdate()`, so a double-click can't confirm twice.
- Confirm and reject are audit-logged (`AuditLog::record()`). The confirmed payment can be voided
  later like any recorded payment (`canBeVoided()` already allows recorded `Paid` rows without
  `payout_owed_at`).
- `cancellationBlockedReason()`: no change for direct money (nothing is held). But the cancel confirm
  text says "₱3,500 was paid directly to the landlord. AbangananHub can't return it. Settle it with
  each other." A Pending claim is withdrawn automatically on cancel.
- Account deletion: also refused while the user has a Pending claim, next to the existing Held/Pending
  Payout guard.

### Ledger edge (must not miss)
`RentLedger::isPlatformInitial()` treats an `Initial` row as the first month's rent only when it is
**not** manually recorded. A confirmed direct move-in row *is* recorded (`recorded_by` set), so as the
code stands it would land in `otherCharges()`. The first month would then read Due and offer "Pay now"
again: the exact bug fixed on Oct 15. Change the rule to "`Initial` and (not recorded **or**
`payment_claim_id` set)", and rename it `isFirstMonthInitial()`. Legacy walk-in `Initial` rows have no
claim id, so they keep today's treatment.

### Copy that changes
- `agreements/show.blade.php:173` and `chat-panel.blade.php:218`: the "held by AbangananHub" consent
  lines only show on the escrow route. The landlord's Send-agreement consent becomes conditional on
  their preference.
- Move-in clock partials already render only when money is Held, so they stay silent on direct
  reservations. Verify, don't change.

### Notifications
Claim submitted → landlord. Confirmed / rejected → tenant. Each uses `Notification::notify()` with a
link to the page where the next action is.

### Out of this phase
- Mobile API endpoints for filing and deciding claims. The API only gets the checkout gate above, and
  its ledger output shows confirmed payments automatically. Claim endpoints are a follow-up for
  `plans/mobile-app.md`.
- Proof-of-payment screenshots. The reference number is enough to check in the landlord's own GCash
  app, and screenshots would mean another private upload store.

### Tests
- `DirectPaymentClaimTest`: file → confirm → `Initial Paid` with claim id → "Pay now" gone →
  `confirmDirectMoveIn` by tenant and by landlord → Occupied + tenancy thread.
- Reject and refile; withdraw; stranger landlord / other tenant get 403; double confirm writes one row.
- Mutual exclusion both ways (claim vs open checkout); `direct` preference blocks web and API checkout;
  `platform` preference refuses claims.
- `RentLedger`: a confirmed direct Initial settles month one; a monthly claim spreads over months;
  confirmed payments never get `payout_owed_at`.
- `move-in-deadlines` command ignores direct reservations (no Held row).

---

## Docs to update when each phase lands
- `context/SCHEMA.md`: `tenant_details`, `late_fee_waivers`, `payment_claims`, the new `properties`
  and `user_payout_accounts` columns, `payments.payment_claim_id`, and the `isFirstMonthInitial` rule.
- `context/ARCHITECTURE.md` decision log: claims kept apart from `payments`; fee frozen in the lease
  and derived not stored; ID encrypted and never in the snapshot; direct route has no escrow.
- `context/PRD.md` §3: add Direct payments and Late fees; amend the Move-In line to say escrow covers
  the online route.
- `context/CHANGELOG.md`: one entry per phase.

## Manual test checklist (run in a browser at 375px first, then desktop)
1. Unit with ₱0 deposit → property page says no deposit; lease summary says "None".
2. Property standard terms → prefilled in Send agreement (chat panel and reservations list) → edit →
   tenant sees them on the agreement.
3. New tenant signs → blocked until emergency contact and ID are filled → landlord sees them on the
   tenancy page; a CSV export has no ID.
4. Walk-in with emergency contact → landlord edits it later.
5. Property with 2 grace days + 10% fee → new tenancy → let a month go past the deadline (seed or
   `travel`) → fee shows for both sides → tenant online checkout charges rent + fee → waive on another
   month → reads "waived".
6. Landlord preference `both` + QR → tenant picks direct at move-in → landlord confirms → both see
   paid → tenant confirms move-in → Occupied, tenancy chat opens.
7. Same with `direct` only → online checkout is not offered.
8. Monthly direct claim → landlord rejects with a reason → tenant sees it → refiles → landlord confirms.
9. Dark mode on every new card.

## Open questions for the project manager
1. **Late fee number.** The transcript garbles it ("15"? "13"? percent?). It doesn't block the build,
   since the landlord types the value, but the demo seed should use their real number.
2. **"Cleansing period" for reviews.** She described one to the stakeholders (21:20). It does not
   exist in the code. Did she mean something planned, or should the demo script drop it?
3. **"Fraud triggers".** She described automated fraud detection (05:08). What exists is an audit
   trail. Agree on the wording before the defense.

## Source notes
The machine transcript (Whisper `small`, CPU) is rough in the Tagalog and Visayan parts. Points used
above, with timestamps in the recording `some_qna_during_meeting_with_stakeholders.mp3`:
payment trust 02:58–10:40; paper and phone tracking 11:00 and 27:00; first month only 21:48–24:40;
rules and contracts 24:47–26:50 and 31:00; grace and late fee 27:00–28:10; ID and emergency contact
28:40–29:30. Don't commit the 40 MB recording.
