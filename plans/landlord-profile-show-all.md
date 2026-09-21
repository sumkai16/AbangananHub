# Landlord profile — "Show all properties" expands in place

**Status:** implemented; pending manual verification. The feature test was NOT written: there is no Property factory and `phpunit.xml` runs against the dev MySQL DB (RefreshDatabase would wipe it). Endpoint was smoke-checked read-only in tinker instead.

## Problem

On `/landlord/{user}/profile`, the "Show all N properties" button links a visitor to
`/properties?landlord={id}`. The browse page never reads a `landlord` filter, so the visitor lands
on **every** landlord's listings. It also takes them off the landlord's page.

The owner's version of the button goes to `landlord.properties.index` (their management table).

## Decision

The button reveals the rest of the list on the profile itself. No page change.

- The list shows the first **6** rows on load, as now.
- The button reads **"Show N more"** and fetches the next **12** rows.
- If more remain after that, the button stays and shows the new count. Otherwise it disappears.
- The same behavior applies to the owner and to visitors. The profile is "how visitors see me," so
  the owner should see the same thing. The owner can still reach their management table from the sidebar.

Scale: what matters is how many properties **one** landlord has, not how many landlords exist.
Only 6 rows load up front, then 12 per click, so a landlord with 200 listings stays fast.

## Changes

| File | Change |
|---|---|
| `app/Http/Controllers/Landlord/ProfileController.php` | **1.** Move the visibility check out of `show()` into a private `ensureVisible(User $user)`, so the profile and the new endpoint enforce the same rules. **2.** In `showProfile()`, count approved properties and read their IDs (for the stats, reviews and ratings) without loading every row. Load only the first 6 rows with `media` + `units`. **3.** Add `properties(User $user, Request $request)`: it runs `ensureVisible`, then returns the rendered rows for `?offset=` (12 at a time) as HTML, plus `remaining`, as JSON. |
| `routes/web.php` | Add `GET /landlord/{user}/profile/properties` → `landlord.profile.properties`, next to the existing public profile route (line 142), with the same middleware. |
| `resources/views/landlord/profile/_property-row.blade.php` (new) | Pull the existing `<li>` row markup out of `show.blade.php` unchanged, so the first render and the fetched rows are identical. |
| `resources/views/landlord/profile/show.blade.php` | **1.** Render the first 6 rows through the partial. **2.** Replace the `<a href>` with a `<button>` driven by Alpine: it fetches the next rows and appends them to the `<ul>`, has a loading state (disabled + "Loading…"), and moves focus to the first new row for keyboard users. **3.** `$properties->count()` becomes `$propertyCount` (lines 6, 134, 218, 269, 272). |
| `tests/Feature/` | Add a test covering 3 things: the endpoint returns the right rows and `remaining`; it returns 404 for a `private` profile and for `landlords_only` when the viewer is a guest; it only returns `Approved` properties. |

## Out of scope

- The browse page's ignored `?landlord=` parameter. Nothing else links to it once this lands, so
  there's nothing to fix there.

## Manual test checklist

Test at **375px** and at desktop width.

- [ ] A landlord with ≤ 6 properties shows no button.
- [ ] A landlord with 14 properties shows "Show 8 more". Clicking it adds 8 rows and the button disappears.
- [ ] With more than 18 properties, the button stays and the count drops each click.
- [ ] Loaded rows look identical to the first 6 (photo, price, "N available" / "Fully occupied").
- [ ] Profile stats (Properties, Units, Available now, Rating) are unchanged from before.
- [ ] While loading, the button is disabled and double-clicking doesn't load duplicate rows.
- [ ] Your own profile (`/landlord/profile`) behaves the same way.
- [ ] A `private` profile's endpoint URL returns 404 when opened directly.
