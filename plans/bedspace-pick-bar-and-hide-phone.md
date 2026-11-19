# Bedspace picker, option 5 (seats + pick card), and no phone number on the listing (Nov 4 2026)

Status: **built, pending manual verification.** Mockups: the "Bedspace Picker Mockups" canvas (option 5, phone and web).
Follows `plans/page-speed-back-links-bedspace-seats.md`, which added the first seat box.

## Decisions (agreed Nov 4)
- Option 5: the seat box is the only picker for Bedspace and Boarding House (more than one unit). The unit cards below it are gone in that mode.
- Details move to the places that already show the pick: the sticky contact card on desktop (photo, spec line, three amenities, "Photos & details") and the sticky bottom bar on phones (new "Details" button). Both open the existing unit slideout.
- Remove "Show phone number" from the listing page. Reasons: it lets a deal leave the platform (no chat record, no held money, no complaint trail), and the number sat in the page source for every signed-in user. The number belongs in the chat or on the agreement once the landlord is engaged.

## Also done (Nov 5)
- The landlord business page, the listing API (`business_contact_number`) and the landlord profile API no longer give the business number to anyone but its owner.

## Manual test guide
1. Open `/properties/1` (Bedspace, several beds) at 375px. Beds show as big tiles, two per row, with a legend. No unit cards below.
2. Tap a green bed: it turns coral. The bottom bar shows `₱ / month · Bed C`, a **Details** button and **Contact**.
3. Tap **Details**: the bed's photos and details open. Close it, tap **Contact**: the inquiry sheet opens for that bed.
4. Tap a grey bed: nothing happens.
5. Desktop: the sticky card shows the pick (photo, floor, specs, three amenities, "Photos & details") and price. Pick another bed: the card follows.
6. The sticky card has no "Show phone number" button, for a guest and for a signed-in tenant.
7. Open an Apartment or House with several units: no seat box, the normal unit cards still show with "View all units".
8. Open a one-unit Boarding House (`/properties/11`): no seat box, the details tile reads "Number of rooms".
