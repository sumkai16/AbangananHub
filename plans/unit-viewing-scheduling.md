# Unit Viewing Scheduling

**Date:** 2026-09-22
**Status:** Implemented 2026-09-28 (incl. offline visitors + Viewings tab). Pending manual verification.
**Deadline:** Last feature before the pre-defense freeze. Land and verify by Sept 27.

## Goal

Once the landlord accepts an inquiry, the tenant can request a viewing of the unit from the chat.
The landlord approves it, declines it, or suggests another time, either from the chat or from a
calendar of all their viewings. On that calendar the landlord can also mark days they aren't
available, and tenants can't pick those days.

This is additive. It never touches `rental_status`, payments or escrow.

## Decisions

| Decision | Choice | Why |
|---|---|---|
| When a viewing can be requested | `Under Negotiation` and `Pending Rental Agreement` | Axcee asked for "after the landlord confirms the inquiry"; tenants often want to see a unit before signing |
| Time selection | Fixed hourly slots, 8 AM – 5 PM (last viewing starts at 5) | Easy to tap on a phone and lines up on the calendar. Enforced on the server |
| Blocked dates | Per landlord, across every property | The landlord is the one who isn't free, and one list is simpler |
| Rescheduling | Either party suggests a time, and the other confirms | Same pattern as the key-handover scheduling (`HandoverController`) |
| "Completed" | Shown once a confirmed viewing's time has passed; not stored | No nightly job needed, and it can't drift out of sync |
| Blocking a day that already has viewings | Refused: "Reschedule or cancel the N viewings on this day first" | A confirmed tenant is never silently stranded |
| Where the calendar lives | A **Viewings** tab on the Reservations page (`/landlord/reservations/viewings`), next to "Rental requests", with a pending-count badge | Axcee asked to see the schedule inside Reservations. It is a separate tab, not another status tab, because the status tabs filter rental requests |
| Offline visitors | Landlord adds a viewing for someone who called, messaged or walked in: name, phone (optional), property, unit (optional), time, note. No account; saved as Confirmed | Axcee: "the landlord can add sched if someone wants to schedule a visit, not just online" |
| Offline edits | Landlord edits or cancels directly; no confirm step, no chat message, no notification | There is nobody on the other end with an account |
| One viewing per reservation | One *open* viewing (Pending/Confirmed and still ahead) | A past viewing shouldn.t stop the tenant asking for a second look |
| Date picker | Extend the existing `<x-datetime-picker>` with `blocked`, `slots` and `customTime` options | No new library, and it matches the handover picker the app already uses |

## Database (2 new tables, nothing existing changes)

**`viewing_requests`**: `viewing_id`, `source` (Online / Offline), `reservation_id` (nullable),
`property_id`, `unit_id` (nullable), `tenant_id` (nullable), `visitor_name`, `visitor_phone`,
`landlord_id`, `scheduled_at`, `status` (Pending / Confirmed / Declined / Cancelled),
`proposed_by`, `note`, `decline_reason`, `responded_at`, timestamps.
Indexed on `(landlord_id, scheduled_at)` for the calendar and `(reservation_id, status)` for the
one-active-viewing check.

**`landlord_blocked_dates`**: `blocked_date_id`, `landlord_id`, `date`, `reason`, timestamps.
Unique on `(landlord_id, date)`.

## Server rules

- Only the reservation's tenant can request a viewing. Either participant can reschedule or cancel,
  and only the person who **didn't** propose the current time can confirm or decline it.
- One active viewing (Pending or Confirmed) per reservation.
- Viewings must be in the future, within 60 days, on the hour between 8 AM and 5 PM, and not on a
  day the landlord blocked.
- When a reservation is rejected, cancelled or completed, its active viewings are cancelled.
- Every action locks the row, posts a system message in the chat, notifies the other person, and
  refreshes their open chat panel live. The broadcast is guarded, so a stopped Reverb can't fail
  the action.

## Files

| File | Change |
|---|---|
| `database/migrations/2026_09_24_000000_create_viewing_requests_table.php` | New |
| `database/migrations/2026_09_24_000001_create_landlord_blocked_dates_table.php` | New |
| `app/Models/ViewingRequest.php`, `app/Models/LandlordBlockedDate.php` | New |
| `app/Models/Reservation.php` | `viewings()`, `activeViewing()` |
| `app/Policies/ViewingRequestPolicy.php` | New: `participate`, `respond` |
| `app/Http/Controllers/ViewingController.php` | New: request, reschedule, confirm, decline, cancel |
| `app/Http/Controllers/Landlord/ViewingController.php` | New: Viewings tab, add/edit/cancel offline viewings, block and unblock a day |
| `app/Policies/ReservationPolicy.php` | `requestViewing` |
| `resources/views/viewings/_slot-form.blade.php`, `resources/views/components/picker-modal.blade.php` | New: shared slot form and modal shell (chat + Viewings tab) |
| `resources/views/landlord/reservations/_section-tabs.blade.php` | New: "Rental requests / Viewings" switch |
| `resources/views/landlord/viewings/_row.blade.php`, `_offline-form.blade.php` | New |
| `tests/Feature/ViewingSchedulingTest.php` | New (DatabaseTransactions on the dev DB) |
| `app/Events/ViewingScheduleUpdated.php` | New: tells the other person's chat panel to refresh |
| `app/Observers/ReservationObserver.php` | Cancel active viewings when a reservation ends |
| `routes/web.php` | New routes |
| `resources/views/components/datetime-picker.blade.php`, `public/js/datetime-picker.js` | `blocked`, `slots`, `customTime`, `heading` options |
| `resources/views/conversations/partials/_viewing-card.blade.php` | New: the viewing card and its actions in the chat |
| `resources/views/conversations/partials/chat-panel.blade.php` | Include the card for both roles |
| `resources/views/conversations/index.blade.php` | Listen for `ViewingScheduleUpdated` |
| `resources/views/landlord/viewings/index.blade.php` | New: the calendar page |
| `resources/views/landlord/reservations/index.blade.php` | Section tabs |
| `context/SCHEMA.md`, `context/ARCHITECTURE.md` | Document the new tables and flow |

## Testing

**Feature tests:**
- Tenant requests a viewing
- A second active viewing is refused
- Wrong tenant or wrong landlord gets 403
- Past, blocked, out-of-hours and off-the-hour times are refused
- Reschedule flips who has to confirm
- The proposer can't confirm their own time
- Decline and cancel
- Blocking a day with viewings on it is refused
- A reservation ending cancels its viewings

**Manual checklist (375px and desktop, both roles):**
- [ ] Tenant, Negotiation stage: Schedule viewing → blocked days are greyed out → pick a slot → the card shows "Waiting for landlord"
- [ ] Landlord chat: the card updates live → Approve → the tenant sees "Confirmed" without refreshing
- [ ] Landlord suggests another time → tenant confirms it
- [ ] Decline with a reason, then the tenant requests again
- [ ] Reservations page shows "Rental requests / Viewings" tabs; Viewings has a badge when a request needs an answer
- [ ] Viewings tab: the month grid shows dots per day; tap a day → the list filters to it; approve, decline or reschedule from the list; block or unblock the day
- [ ] Add viewing (offline): name, phone, property, unit, slot → shows as "Added by you · Confirmed"; phone is a tap-to-call link
- [ ] Edit / reschedule and cancel an offline viewing
- [ ] Viewing hours: set e.g. Mon/Wed 9 AM–4 PM → calendar shows other weekdays as "No viewings"; tenant picker greys out those days and only offers 9 AM–3 PM starts
- [ ] Viewing hours: unticking every day or end ≤ start is refused
- [ ] Blocking a day that has a viewing shows the warning
- [ ] Rejecting the reservation cancels the viewing
- [ ] A viewing whose time has passed shows "Completed"
