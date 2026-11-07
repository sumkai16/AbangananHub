# Tenant rental page redesign (Nov 6 2026)

Status: **built Nov 6 2026, pending manual verification.**

## What was asked
Make `/tenant/tenancy/{id}` match `/landlord/payments/ledger/{id}`, in the tenant's view.

## Decisions

| Landlord ledger | Tenant page |
|---|---|
| Tenant avatar and name | Unit photo and name, property, landlord, move-in date |
| Balance tile: Record payment, Remind tenant | Balance tile: **Pay rent** (jumps to the unchanged pay choice below) |
| Four fact tiles | Monthly rent, Oldest unpaid or Next due, Paid to date, Deposit held |
| Lease strip | Same strip, one shared partial |
| Payments table | Same, with receipts; a waiting report is marked |
| Security deposit with Deduct button | Same bar, read-only |
| Header buttons | Quiet row: agreement, message landlord, review, report a concern |

No route, payment logic or database change. The pay flow (online, QR, cash, pay in full, claims, checkout return) is the same component.

## Manual test guide (browser, 375px first)
1. Open My Rentals, then a rental. You see the identity row, the four buttons, the navy Balance due tile and four small tiles.
2. With rent owed, the tile shows the amount, "₱X overdue across N months" and a **Pay rent** button. Tap it: the page scrolls to "How do you want to pay…".
3. Pay online, pay by QR/cash report and pay all months owed still work as before.
4. With a payment report waiting, the button reads **See your payment report** and Payments shows a yellow "Waiting for … to confirm" row.
5. Tap a month in the Lease strip: the period popup opens with the receipt link for paid months.
6. Payments lists every payment, newest first, with a Receipt link where one exists.
7. Security deposit shows the held bar and any charges, with no way to edit them.
8. With late fees, the Balance tile says "incl. ₱X late fees".
9. A finished rental has no Pay button and no Report a concern, and says it ended.
10. Open the landlord's ledger for the same tenancy: the Lease strip looks and works as before.
