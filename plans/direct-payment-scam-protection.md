# Direct payments: scam protection (QR + cash)

**Status:** building Oct 31 2026, pending manual verification. Extends `plans/direct-payments-record-and-confirm.md`.

Direct payments (landlord's QR, cash) can't be made scam-proof, since AbangananHub never holds the money.
This leaves a record on both sides, blocks the cheap tricks, and gives the tenant a way to reach an admin.

## Four fixes
| # | Gap | Fix |
|---|---|---|
| 1 | Tenant hands over cash, landlord never records it | Tenant can **report cash handed over** (same claim flow; method `Cash`, no reference number). Landlord confirms or rejects it like any report |
| 2 | One real receipt reused for several reports | A non-cash reference number can be Pending/Confirmed only once, platform-wide (case-insensitive). A rejected report frees it |
| 3 | Landlord rejects a payment that really happened | **Send to AbangananHub for review** on a rejected report. Creates a `Report` (reported user = landlord, property attached) with the claim's amount, method, reference and the landlord's reason. Once per claim. Landlord is notified |
| 4 | Users pay before seeing the unit, or outside the app | `<x-payment-safety-tips>` under the method cards when QR or cash is picked |

## Schema (migration `2026_10_31_000600`)
- `payment_claims.payment_method` enum gains `Cash`.
- `payment_claims.reference_no` becomes nullable (cash has none).
- `payment_claims.disputed_at` (timestamp, null) and `dispute_report_id` (FK `reports.report_id`, null on delete).

## Code
- `PaymentClaim::METHODS` + `Cash`; `isCash()`.
- `PaymentClaims::file()`: reference duplicate check; cash notification wording. `PaymentClaims::dispute()`.
- `Tenant\PaymentClaimController`: reference required unless cash; `dispute` action; route `paymentClaims.dispute`.
- Views: new `payments/partials/_claim-status` (pending + rejected + dispute button) used by `_direct-pay` and the cash panel in `payment-route-choice`; cash "I handed over the cash" form; `_claim-review` cash wording.
- Web only. The mobile API has no claim endpoints.

## Manual test guide (sandbox, escrow fixtures: `php artisan escrow:scenarios`)
1. Tenant, "Signed, nothing paid yet": pick **Pay cash**, then **I've handed over the cash**, send. Panel switches to "Waiting for {landlord}".
2. Landlord: the report reads "handed you ₱X in cash", with no reference line. **Not received** with a reason.
3. Tenant: the rejection shows. Press **Send to AbangananHub for review**. Button is gone, "Sent for review" shows.
4. Admin: **Reports** has a new report naming the landlord with the amount and reason. Landlord got a notification.
5. Tenant: QR panel, report a payment with reference `TEST123`. Withdraw and report again (allowed). Have the landlord confirm it, then try `TEST123` on another reservation: refused, "already been reported". `test123` is refused too.
6. Pay screen: the safety tips show under QR and cash, and not under Pay online.
7. Cash report confirmed by the landlord counts as paid (Payment confirmed on the tenant's page).

## Admin side (added the same day)
- Report page for a payment dispute: evidence card, links to the reservation and the read-only chat, landlord's record.
- New admin action **Warn reported user**.
- Fixed a 500 on the landlord chat when a reservation has two or more claims (`chaperone`).

Manual test: 8. Admin opens the dispute report: card shows amount, reference, landlord's reason, the two links work, record line reads "1 sent to admin". 9. Resolve with **Warn reported user**: landlord gets "Warning from AbangananHub" and can still log in; the tenant is told the report was reviewed.
