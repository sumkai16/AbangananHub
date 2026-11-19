# Payments tabs switch in place (Rent | Payouts)

Status: built, automated tests pass, **pending manual verification by Axcee**. Delete this file once the checklist below has been run.

## Goal
Rent and Payouts are one Payments page to the landlord (the sidebar item is lit on both). Switching tabs should not reload the page: the sidebar stays, the work area swaps, Back works.

## Decisions
| Decision | Choice | Why |
|---|---|---|
| Not a panel | The Payouts page has no detail links to open | It is a tab, not a detail of a list. |
| Mechanism | `data-page-swap` links, fetch the target, swap `<main id="main">` | Both pages share the landlord shell and have no inline script (one `<script src>` and one JSON block), so a swap is safe. Same approach as `live-filter.js`. |
| Safety net | Fall back to a normal load on any doubt | Failed request, redirect, inline JavaScript in the new content. |
| Calendar script | Registers at once if Alpine is running | It used to register only on `alpine:init`, which would have left the calendar dead after Payouts to Rent. |

## Manual test list (landlord with some payments)
1. Payments (Rent tab). Click **Payouts**. The sidebar does not flash; the heading, tabs and content change; the address bar shows `/landlord/payouts`; the Payouts tab is underlined.
2. Click **Rent**. Same, and the Rent list shows again.
3. Browser Back, then Forward: each walks between the tabs without a reload.
4. Start on `/landlord/payouts` directly (full load), click **Rent**, then open the **Calendar** view: the calendar draws and zooms. This is the case the calendar script fix covers.
5. On Rent, filter or search, then switch to Payouts and back: nothing breaks; the list loads.
6. Payouts: set or edit the GCash account (the form is a small Alpine block). Works after arriving by tab swap.
7. Ctrl or Cmd + click a tab: opens in a new tab as a normal page.
8. Slow network (DevTools, Slow 4G): the content fades while loading, and the old tab stays usable until the new one arrives.
9. Keyboard: Tab to the tab strip, Enter on Payouts: focus stays on the Payouts tab afterwards.
10. Dark mode and a 375px phone: both tabs look as before.
11. Open Ledger from Rent after a swap, close it: still works (the panel and the swap share Back).

## Known limits
- The target page is fetched whole and only `#main` is used, so a swap costs the same server work as a normal load.
- Viewings (Reservations tab pair) is not wired: it carries inline scripts.

## Next, if wanted
Allow JSON data blocks in `live-filter.js` so Payments filters also swap in calendar view; Reservations | Viewings after Viewings drops its inline scripts.
