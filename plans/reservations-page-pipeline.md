# Reservations page: the request pipeline only (new layout)

**Status: implemented Oct 31 2026 on branch `emman`, pending manual verification by Axcee (checklist below).**

## Context
The reservation flow now runs Inquiry → Discussing → Agreement sent → Signed → paid → Contracts (steps 2–7 of the
8-step flow). Once the landlord confirms the move-in money, the lease lives on the **Contracts** page (Awaiting
move-in → Active → Ended) and its money on **Payments**. But `landlord/reservations` still lists every status: in the
screenshot (Oct 31) 6 of 8 rows are **Occupied**, the stat strip counts Occupied, and the action column is mostly
"Rate Tenant". The page should answer one question: *which requests need me, and what is the next step on each?*

## New layout (sketch, desktop; phone = one card per request)

```
Reservations                                                         [Export]
Requests from tenants, from the first message to a signed, paid agreement.
[ Rental requests | Viewings (2) ]

┌ Needs your reply ┬ Discussing ┬ Waiting on tenant ┬ Payment to confirm ┐   ← each is a filter link
│ 3 new inquiries  │ 2           │ 1                 │ 1  ₱7,000          │
└──────────────────┴────────────┴───────────────────┴────────────────────┘
[ Search tenant, unit or property ]  [All properties ▾]  [Requested date]

Open (7) | New (3) | Discussing (2) | Agreement sent (1) | Awaiting payment (1) | Closed (4)

TENANT            UNIT                ASKED FOR                    STAGE                          NEXT STEP
Ana Santos        Tunghaan · Rm 3     Nov 1 → open · 2 people      New inquiry · you reply        [Accept] [Chat] [⋯]
Ben Cruz          Labangon · Bed C    Nov 5 → May 4 · 1 person     Discussing · step 3 of 6       [Send agreement] [Chat] [⋯]
Cara Lim          Talamban · Room 1   Dec 1 → open · 1 person      Agreement sent · tenant signs  [Chat] [⋯]
Dan Reyes         Labangon · Bed A    Nov 1 → Oct 31 · 1 person    Signed · ₱7,000 reported       [Confirm payment] [Chat] [⋯]

Signed and paid requests move to Contracts →
```

## Changes
**1. Scope** — `Landlord\ReservationController::index/export`: the page shows reservations still in the request
stage: Inquiry, Under Negotiation, Pending Rental Agreement, and Rental Agreement Signed **without**
`move_in_paid_at`. Closed = Rejected + Cancelled. Reserved / Occupied / Completed and signed-and-paid rows are
left to Contracts. The export follows the same scope.

**2. Stage tabs** (replace the 9 status tabs): Open (default: all four request stages), New, Discussing,
Agreement sent, Awaiting payment, Closed. Each maps to `rental_status` (+ `move_in_paid_at` null for Awaiting payment).

**3. Summary strip** (replace Total / In progress / Occupied / Rejected), each cell a filter:
- **Needs your reply** — Inquiry count
- **Discussing** — Under Negotiation
- **Waiting on tenant** — agreement sent, or signed with no payment reported yet
- **Payment to confirm** — signed with a Pending move-in claim (count + total ₱)

**4. Table / cards** — columns Tenant · Unit · **Asked for** (move-in → move-out or "open", occupants) · **Stage**
(plain label + whose turn: "you reply", "tenant signs", "₱X reported") · Requested · **Next step**: one primary button
per stage (Accept · Send agreement · Confirm payment; none while waiting on the tenant), a Chat icon, and a `⋯` menu with
Reject / Cancel. Details opens the existing modal. The `Requested on` column and view toggle stay. Phone cards show the
same fields stacked (existing card view).
- "Confirm payment" reuses the existing claim review (`payments.partials._claim-review` / `_direct-actions`).
- "Send agreement" opens the existing final-terms modal (`_agreement-fields`).

**5. Moved out of this page**
- **Rate Tenant** → the contract modal (`contracts/partials/_details.blade.php`) for Occupied and Ended contracts,
  linking to the existing `landlord.reservations.rateTenant` page (shows "Rated" when a rating exists).
- **Confirm walk-in move-in** for Reserved rows → already on the contract modal.
- A footer link "Signed and paid requests move to Contracts →".

**6. Cleanup in the view** — drop the Occupied/Reserved/Completed branches, the walk-in confirm block and the
`tenancy_url` modal field from `landlord/reservations/index.blade.php`; status pill colours limited to the request
stages.

## Files
`app/Http/Controllers/Landlord/ReservationController.php` (scope, stage map, counts), `resources/views/landlord/reservations/index.blade.php`,
`resources/views/landlord/contracts/partials/_details.blade.php` (+ Rate tenant), `ContractController::show`
(load `tenantRating`). No routes or migrations.

## Tests
New `ReservationsPipelineTest`: Occupied/Reserved/Completed and signed-and-paid rows are not listed; each stage tab
shows only its rows; strip counts (incl. Payment to confirm); Accept / Send agreement / Confirm payment buttons appear
on the right stage; another landlord's rows never appear; export matches the page. Contract modal shows Rate tenant
for Occupied/Ended and "Rated" once rated. Update any test asserting Occupied rows on the reservations page.
Full suite, `npm run build`, `impeccable`.

## Manual (Axcee, 375px then desktop)
Open tab lists only open requests; the strip numbers match; Accept a new inquiry → it moves to Discussing; Send
agreement → Agreement sent; tenant signs and reports payment → Payment to confirm → Confirm → the row leaves the page
and appears on Contracts as Awaiting move-in. Rate tenant from a contract.
