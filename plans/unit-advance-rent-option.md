# Advance rent option on the unit form

Status: **built Nov 16 2026, pending Axcee's manual verification.** Asked for by the PM ("Require Advance Rent: enable/disable, number of months").

## What
- Unit form (create and edit), under the deposit: checkbox **Require advance rent** that reveals a **1 month / 2 months** choice. Posts `advance_months` (0 = none, 1, 2).
- New column `property_units.advance_months` (tinyint, default 1). Default 1 because every unit asked for one month before; nothing changes until a landlord edits it.
- `Reservation::agreementDefaults()['advance']` starts from the unit's value. The agreement form still allows 0 to 3 so a landlord can negotiate per tenant.
- Listing page: "+ ₱X security deposit · 1 month advance rent" under the price.
- Rent Control note: the advance box shows it when rent is ₱10,000 or less and the advance is 2 months.
- Mobile API (`UnitWriteController`): optional `advance_months` (create defaults to 1, update keeps the old value when absent); `PropertyUnitResource` returns it.
- Editing the advance does not send an approved unit back to Pending (only rent, occupancy and photos do).

## Files
`database/migrations/2026_11_16_000200_add_advance_months_to_property_units.php`, `resources/views/landlord/units/partials/_advance-rent.blade.php`, `landlord/units/{create,edit}.blade.php`, `properties/show.blade.php`, `PropertyUnit`, `PropertyUnitController`, `Api/Landlord/UnitWriteController`, `PropertyUnitResource`, `Reservation::agreementDefaults`.

## Manual checklist (run `php artisan migrate` first)
1. Edit a unit: the checkbox is ticked with 1 month selected (existing units). Untick it: the pills hide. Tick again, pick 2 months, Save, reopen: 2 months is kept.
2. New unit: starts ticked with 1 month; saving works.
3. Rent 8,000 and 2 months: the amber Rent Control note shows. 1 month: it hides. Rent 16,000: never shows.
4. Open Send agreement for a reservation on that unit: Advance rent starts at the unit's value (2 months, or None when unticked).
5. Public listing page: "· 2 months advance rent" appears next to the deposit; nothing extra when unticked.
6. Saving the advance alone leaves an Approved unit Approved.
