# Rental flow business rules (BR-01 to BR-12)

Status: planned, not implemented. Decision made: **time-boxed hold** (keep one agreement per unit, make it a
disclosed payment window). Not "first verified payment wins" (see Why below).

## Why time-boxed, not first-payment-wins
Tenants pay the landlord directly, outside the system, before the landlord confirms in AbangananHub. If two
agreements were open on one unit, tenant B could pay for a unit tenant A just got confirmed, and there is no refund
path for that (`context/RULES.md:198`). One agreement per unit at a time (`Reservation::unitHeldByAnother()`) stays.

## Already in the system (do not rebuild)
| Rule | Where |
|---|---|
| BR-01/02 inquiry first, accepted before negotiation/viewing | `config('rentals.viewing_statuses')`, `Reservation::canRequestViewing()` |
| BR-03/04 viewing needs approval, does not hold the unit | `ViewingRequest`; `UNIT_HOLDING_STATUSES` excludes Inquiry/Under Negotiation |
| BR-05 landlord sends the agreement | `Reservation` status `Pending Rental Agreement` |
| BR-08 landlord verifies direct payments | `payment_claims`, `move_in_paid_at` |
| BR-09/Scenario 3 no double booking, under a lock | `unitHeldByAnother()` on a `PropertyUnit` row lock |
| BR-10 Occupied only after move-in confirmed | `markOccupied()`, `confirmMoveIn()` |
| BR-11/12 deadlines that expire | `reservations:process-stale`, `config/rentals.php` `stale_reservations`: agreement 3 days (remind at 1), signed-unpaid 3 days (remind at 1), auto-cancel. Shortened from 5 / 10 days (done) |

## Gaps to close
1. **Disclose the payment deadline** in the agreement (lease text + tenant sign screen + chat card): "pay within N days of signing or this expires". Today the 10-day cancel exists but the tenant is not told the date. (BR-11)
2. **Wording: signing alone never reserves** (BR-06). Tenant and landlord screens must say "Signed, waiting for payment", not "Reserved". Check labels in Blade, API resources and notifications.
3. **Reserved only after confirmed payment + future move-in date** (BR-07, Scenario 4). Verify `Reservation` -> `PropertyUnit.availability_status` mapping in `PropertyUnitObserver` matches: signed-unpaid = held but not shown Reserved.
4. **Tell Tenant A when the unit is gone** (Scenario 2): when another reservation reaches a holding status, cancel A's pending viewing and send A a notification. Verify `ViewingController` / `ViewingScheduleUpdated` does this.
5. **Landlord "release unit" button** on a signed-unpaid reservation (free the unit before the deadline). Goes through `cancellationBlockedReason()`.
6. **Expiry message**: auto-cancel notifies both sides with the reason "payment deadline passed" (BR-12).
7. **Make deadline days one config value per stage**, shown to the landlord when sending the agreement (optionally editable per agreement within a min/max).
8. **Landlord can extend one agreement's payment deadline** (up to 7 days) for a tenant who needs more time. Needs a per-reservation override column read by `ProcessStaleReservations`; the 3-day default stays for everyone else.

## Not doing
- Renaming statuses to Draft / Pending Signature / Pending Payment / Confirmed / Active / Expired. The existing
  `rental_status` enum covers the same states; renaming touches the enum migration, API and mobile app for no
  behaviour change. Map the names in the panel documentation instead.
- Concurrent agreements on one unit (see Why).

## Test checklist (manual)
- Landlord sends agreement to A: unit held; landlord cannot send to B.
- A signs, does not pay: tenant sees the deadline date; reminder on day 3; auto-cancel on day 10; unit Available again.
- A signs and pays by QR, landlord confirms, future move-in: unit Reserved. Move-in date arrives, landlord confirms: Occupied.
- A has an approved viewing; B's reservation reaches a holding status: A's viewing is cancelled and A is notified.
- Landlord presses release on A: unit free, A notified, B can now get an agreement.
- Two landlord tabs send agreements to A and B at once: exactly one succeeds.
