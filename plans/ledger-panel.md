# Ledger panel: Open ledger opens over the list

Status: built, automated tests pass, **pending manual verification by Axcee**. Delete this file once the checklist below has been run.

## Goal
Open ledger should not take the landlord to a new page. The ledger opens over Payments (bottom sheet on a phone, full screen on desktop), the list keeps its place, and the address bar still shows the ledger's own address. Mockups: Claude artifact "Ledger panel mockups".

## Decisions
| Decision | Choice | Why |
|---|---|---|
| Pattern | Panel over the list, not whole-app SPA | Asked for Open ledger first; 43 views carry inline `<script>` blocks, which makes a whole-app page swap risky. |
| Desktop | Fills the work area, sidebar stays visible (not a right drawer, not covering the sidebar) | Axcee's calls after seeing the mockup. |
| Phone | Bottom sheet, 95% tall | Main device for landlords. |
| Build | Iframe with `?embed=1` | The ledger page has five modals, `date-picker.js` and an Echo reload listener. An iframe keeps all of it working with no second copy. Fetch-and-swap was the alternative. |
| Scope | Ledger only | Contract and Tenant detail stay candidates. Adding one is two attributes on a link. |

## What was built
- `app/Http/Middleware/EmbeddedPage.php` + alias `embeddable` (bootstrap/app.php) on the landlord route group (routes/web.php).
- `layouts/landlord.blade.php`: no sidebar, top bar, prefetch rules, chat bubble or notification toasts when embedded; mounts `<x-page-panel />` otherwise; links that leave the page open in the full window.
- `components/page-panel.blade.php`, `resources/js/page-panel.js`, registered in `resources/js/app.js`.
- `landlord/payments/ledger.blade.php`: breadcrumb hidden when embedded.
- Links marked `data-page-panel`: Payments list (card and table), calendar, dashboard action list (`'panel' => 'Dashboard'` in `DashboardController`), lease modal (`contracts/partials/_details`).
- `tests/Feature/LedgerPanelTest.php`.

## Manual test list (run in a browser as a landlord with an occupied tenancy)
Phone first (DevTools at 375px, or a real phone):
1. Payments, tap **Open Ledger** on a card. A sheet rises; the list is dimmed behind; the spinner shows briefly, then the ledger. The address bar shows `/landlord/payments/ledger/{id}`.
2. The ledger has no sidebar, no top bar, no breadcrumb, no chat bubble. Scroll inside the sheet; the list behind does not scroll.
3. Close with ✕, then reopen and close with a tap on the dimmed area, then reopen and close with the browser Back button. Each time the list is where you left it, and the address bar is back on `/landlord/payments`.
4. Reopen, tap **Record payment**, record a small payment. The ledger inside updates. Close the sheet: the list reloads and shows the new balance.
5. Reopen, only read, close: the list must **not** reload.
6. In the ledger, tap **Remind**. The confirm modal shows inside the sheet; confirm; the success message shows inside the sheet and you are still on the embedded ledger.
7. Reload the browser while the sheet is open: the full ledger page loads (with sidebar), as before.
8. Dark mode on: panel bar and ledger both follow it.

Desktop (≥1024px):
9. Table row, **Open ledger →**. The ledger fills the area to the right of the sidebar; the sidebar stays visible and clickable, with Payments still lit. The panel has **← Payments**, **Open full page ↗**, ✕. Open full page goes to the full ledger. Collapse the sidebar (with the panel open, then reopen): the panel's left edge follows it. Open the bell in the sidebar: its dropdown opens over the panel.
10. Escape closes it (click outside the frame first if focus is inside, then Escape is expected to do nothing; use the buttons).
11. Ctrl or Cmd + click, and middle-click, **Open ledger** opens the normal page in a new tab.

Other entry points:
12. Payments **calendar**, open a day, Open ledger: same panel, Back says Payments.
13. **Dashboard** action list, an overdue row: panel opens, Back says Dashboard.
14. **Tenants**, a tenant's lease modal, Open ledger: panel opens over the modal, Back says Tenants.
15. In the ledger, open a **receipt** link: it opens in the full window, not inside the frame.

Regression:
16. Open `/landlord/payments/ledger/{id}` directly: it looks exactly as before.
17. Sidebar pages (Dashboard, Payments, Tenants) look and behave as before, sidebar collapse still remembered.

## Known limits
- Escape only works while focus is outside the frame.
- A used panel reloads the list on close, a visible flash.
- The frame loads Alpine and Echo a second time.
- On plain http the embed mode depends on the Referer, since browsers send `Sec-Fetch-Dest` only on https and localhost.

## Next, if wanted
Contract and Tenant detail as panels; refresh just the affected list row instead of reloading; forward Escape from the frame.
