# Admin payouts page redesign

**Status: built Nov 7 2026, suite 1754 passed (11 new, `PayoutsPageTest`), pending Axcee's manual pass.** Delete this plan after that pass.
The mockups (six boards, A to F plus G) are in the Claude artifact "Admin payouts: 5 directions"; F is what was built.

## What changed

`/admin/payouts` was three stacked sections with equal weight: GCash accounts to approve, one card per landlord each repeating its payments table, and a payout history. Failed payouts and approvals looked like everything else. Now:

1. **Slim money strip** (`partials/summary`): "₱X is owed to N landlords. ₱A goes out at 3:00 PM and ₱B is held back", with a bar of three segments that always add up: goes out (or "pay by hand"), needs you, waiting on someone else.
2. **Tabs**: Owed, Processing, Failed, Sent. The last three are the latest 30 payouts split by status.
3. **Needs you button** beside the search box, with a count. It opens a panel (`partials/needs-panel`) with two groups: *Failed payouts* (Retry) and *GCash accounts to approve* (Approve account, with the name-against-ID check). Close with X, Esc or a click outside. With nothing to do the button is replaced by "Nothing needs you right now."
4. **Owed table** (`partials/owed`): one row per landlord, biggest first. State chip + one sentence (Ready, GCash not approved, Payout failed, On hold, Paused, No GCash number, or Pay by hand in manual mode) and the one action that fits (Retry, Approve account, Resume payouts, Pause, Mark Paid Out). The chevron opens the GCash account (number, account name, name on ID) and the payments behind the amount, plus Pause/Resume and **Record a transfer sent by hand**.
5. **Header pill** (automatic mode only): "Automatic payouts on · next run 10:00 PM · up to ₱20 in fees". The run is hourly (`payouts:send`, Asia/Manila); the fee is an upper bound at ₱10 per ready transfer.
6. Search filters the Owed rows in the browser (no reload).

**Not changed:** routes, every money rule, the confirm dialogs and their wording, the Mark-paid-out modal, the manual-mode and missing-config banners, the sidebar.

## How it is built

- `LandlordPayouts::blockedKind()` (new) returns why a landlord is held back as a key (`no_details`, `paused`, `hold`, `not_approved`, `failed`, or null). `blockedReason()` now builds its sentence from it, so the words and the grouping cannot drift apart. The automatic-payouts switch is deliberately not part of the key.
- `PayoutController::index()` adds `kind`, `matches`, `idNames` to each owed row, `$failed` (unresolved failed payouts), `$failedByLandlord`, `$owedBy`, `$summary`, `$byHand` and `$nextRun`. `summary()` is the only place the three totals are computed.
- View split into `resources/views/admin/payouts/index.blade.php` and `partials/` (`summary`, `needs-panel`, `owed`, `history`, `payout-action`, `approve-form`, `modal`). `payout-action` is the one place Retry, Check status and Retry (checked) are defined.
- New Tailwind classes: run `npm run build`.

## Decisions to know about

- **Money still shows cents** (₱9,050.00). The mockups used whole pesos.
- **Ready and Sent use the shared `x-admin.status`**, which fills good states coral with white text. That is 2.3:1 contrast, the open question already in the Nov 6 changelog. The mockups used a green tint; this page follows the rest of the admin until that is decided.
- **The approve button is plain, not coral, when the account name does not match the ID** (a mismatch deserves a second look). It still has the stronger confirm text.
- **The modal stays a modal** for recording a transfer: it records money and asks for a reference.

## Manual test guide

Run `npm run build` first. Sign in as an admin. Have landlords in each state if you can (a seeded one with owed money, a GCash account waiting for approval, a paused one).

1. **Layout:** the strip, then tabs (Owed, Processing, Failed, Sent with counts), then the Needs you button and search on the right, then the table. Nothing else above the table.
2. **The bar:** the three segments and the three legend amounts add up to the owed total. With nothing owed, one plain sentence and no bar.
3. **Needs you:** click it. A panel opens under the button. X, Esc and a click outside each close it. With many items it scrolls inside; it never runs off the bottom of the screen.
4. **Approve from the panel:** the confirm dialog opens; confirming approves and the count goes down. For a name that does not match the ID, the dialog warns and the button is plain.
5. **Failed payout (if you have one):** Retry from the panel and from its row both ask "Send this payout again?".
6. **Row states:** Ready shows Pause; Paused shows Resume payouts; a hold shows its resume time; no GCash number says the landlord must add one.
7. **Expand a row:** the GCash block shows number, account name and name on ID; the table lists each payment with tenant, type, amount and date.
8. **Record a transfer sent by hand:** from the expanded row. The modal shows the right amount and landlord; the reference is required; confirming records it and the row leaves Owed. Try a landlord with an apostrophe in their name.
9. **Search:** typing filters the Owed rows as you type; clearing brings them back. Search is hidden on the other tabs.
10. **Tabs:** Processing shows Check status; a processing payout with no PayMongo id shows Retry (checked) with the "Did you check PayMongo?" warning; Failed shows reasons; Sent shows references and "by Admin (by hand)".
11. **Manual mode:** turn automatic payouts off in System Settings. The amber banner shows, no "next run" pill, rows say Pay by hand, and each ready row has Mark Paid Out.
12. **Missing PayMongo config:** the "Automatic payouts are off" banner still names the missing settings.
13. **Dark mode and a narrow window (about 1024px):** the table scrolls sideways inside its card; nothing overlaps. Not checked by hand.
14. **Mouse input:** the Needs you button and chevrons respond to a real click. (Only scripted clicks were tried while building, because the browser tool stopped delivering mouse events.)
