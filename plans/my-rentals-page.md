# My Rentals (Nov 6 2026)

Status: **built Nov 6 2026, pending manual verification.**

## What was asked
Rename "My Unit", and add a general view of every place the tenant rents, each opening its own page (`/tenant/tenancy/{id}`).

## Decisions

| Decision | Choice | Why |
|---|---|---|
| Name | **My Rentals** | Reads as the list it is; "My home" stays as the Messages tab (a chat inbox, different thing) |
| Nav link target | Always the list, even with one rental | One meaning for the link; one extra tap |
| Who sees the link | Anyone with an Occupied or Completed rental | Past tenants keep their receipts and lease |
| Past rentals | Same page, under "Past rentals" | One place for everything rented |
| Not started | Stays on My Reservations | It is not a rental until the move-in is confirmed |

## Manual test guide (browser, 375px first)
1. Log in as a tenant who has a current rental. The header (and the hamburger, and the avatar menu) says **My Rentals**, not My Unit.
2. Click it: `/tenant/tenancy` lists the rental as a card with its photo, landlord, rent and one status pill.
3. Click the card: the single rental page opens; its breadcrumb reads **My Rentals › unit** and the first part goes back to the list.
4. A tenant with two current rentals sees two cards; both open their own page.
5. A tenant whose only rental is finished still sees My Rentals, with the rental under **Past rentals** and a "Moved out" date.
6. A tenant who never rented sees no My Rentals link. Opening `/tenant/tenancy` directly shows "No rentals yet" and a Browse button.
7. A tenant with a rental that is only at the agreement stage does not see it under My Rentals; it is still on My Reservations.
8. Status pills: overdue rent shows red `₱X overdue`; a lease that starts later shows `Nothing due yet`.
