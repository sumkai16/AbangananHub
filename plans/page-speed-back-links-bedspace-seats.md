# Page speed, one Back control, bedspace seat box (Nov 4 2026)

Source: PM feedback before the defense. Status: **in progress, pending manual verification.**

## Findings
1. Every navigation replays a page cross-fade: `@view-transition { navigation: auto }` in `resources/css/app.css` and `layouts/landlord.blade.php` (140ms out, 260ms in with a 6px slide). The landlord sidebar is its own layer, so it visibly morphs. Costs about 400ms per click, on the sidebar and Payments > Rent Ledger.
2. Heavy animation elsewhere: `transition-all duration-300` on the landlord sidebar; many `transition-all` / `animate-*` classes on `properties/show`, `layouts/app`, `chat-panel`.
3. Back controls: about 15 wordings, no shared component, 24 views draw their own arrow SVG.
4. Bedspace picking is a radio list behind "View all units"; no seat map.

## Decisions (agreed Nov 4)
- Seat box only for **Bedspace** and **Boarding House**; apartments and houses keep the list.
- One `<x-back-link>`: left arrow + "Back". Wizard "Previous" and pagination "Previous" stay (different action).

## Steps
1. Remove the view transitions (app.css, layouts/landlord). Narrow the sidebar's `transition-all` to width only.
2. Measure: `php artisan test --filter=PerformanceBaseline` (query counts) plus DevTools Network on a throttled connection.
3. `resources/views/components/back-link.blade.php`; swap it into every page-level back link.
4. Seat box on `properties/show` (375px first): green available, grey taken, coral ring selected; tapping an available bed sets `selectedUnit`. Legend above.
5. Manual test guide below; CHANGELOG entry.

## Manual test guide (to fill in when built)
1. **No fade (desktop, then 375px):** click through the landlord sidebar (Dashboard, Tenants, Payments, Rent Ledger). Pages switch at once, no fade or slide, and the sidebar does not morph.
2. **Public pages:** open Browse, then a listing, then the logo. No fade between them.
3. **Back links:** open Rent Ledger > a receipt, an admin report, a property wizard step, Account Settings (tenant), the agreement page, a listing. Each shows a chevron and "Back" (no destination text). Tab to one with a screen reader or inspect: it carries a hidden "to ..." part.
4. **Auth pages:** forgot password, reset password, accept invite. "Back" with chevron sits top right on the photo.
5. **Seat box (375px first):** open a Bedspace or Boarding House listing with several units. A "Pick your spot" box shows above the unit cards: green available, grey taken (cannot be tapped), legend on top. Tap a green bed: it turns coral, "Your pick" shows below, and the contact card price follows. Tap another: the pick moves. Taken beds do nothing.
6. **Not affected:** an Apartment or House listing shows no seat box. A property with one unit shows none.
7. **Still intact:** wizard Previous buttons and list pagination keep their own wording.
