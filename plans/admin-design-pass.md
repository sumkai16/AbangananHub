> **Status: pending manual verification.** Implemented on `joseph`; not yet checked in a browser.

# Admin design pass: remove the template look (Nov 2026)

## Context
Request: improve the admin side's design and eliminate anything that looks like AI slop. Audited from markup (33 views under `resources/views/admin`, 7,443 lines); nothing rendered yet.

**What is actually wrong.** The palette is disciplined and there are no gradients, glows, emoji or animations. The problem is structure, the "generic admin-template" default DESIGN.md §0 warns about:
- Rows of identical stat tiles with tinted icon squares (`x-stat-card`, 31 uses), and then tabs repeating the same counts. `reports/index` shows one number three times.
- 62 of 65 headings are `font-normal`, so nothing has hierarchy.
- 136 `rounded-full` pills; 3 table-header recipes; `tabular-nums` used twice; money is left-aligned.
- `#94A3B8` used about 299 times as text (about 2.6:1 on white, by calculation).
- Status colours disagree: "Rejected" is navy and red on the same dashboard.
- Filler and fakes: "Welcome back!", a static date `div` styled like a picker, a dead "Soon" sidebar item.
- Titles that don't match the sidebar: "Analytics" opens "Reports", "Payments" opens "Payment Management".

**User decisions:** dashboard is rebuilt **work queue first**; scope is **all admin pages**.

Admin stays desktop-oriented and dense (§0b); no mobile-first pass. Tenant and landlord pages must not change.

## Pre-step
Run `php artisan migrate` on the dev and test DBs (6 migrations from the Nov 5 pull are unapplied). This machine runs PHP 8.2 and `vendor/` may lag `composer.json`; if the app does not boot, stop and report rather than editing composer files.

## 1. Shared pieces (new, admin only, in `resources/views/components/admin/`)
Use only colour classes already remapped for dark mode in `resources/css/app.css`.

| Component | Replaces | Spec |
|---|---|---|
| `x-admin.nav-item` | 23 copy-pasted sidebar rows (about 350 lines) | props: `href`, `label`, `icon`, `active`. Active row: soft fill, coral icon |
| `x-admin.tabs` | 10 identical tab bars | `tabs: [{label, count, href, active}]`, underline style, `tabular-nums`, `aria-current` |
| `x-admin.status` | ad hoc `match()` chips and pills | one status-to-tone map (neutral, amber, green, red); `rounded-md`, not a pill. Unit statuses keep the §-mapped Available/Reserved/Occupied colours |
| `x-admin.th` | 3 table-header recipes | `align` prop; 11px semibold, `#5B6A8E`, `px-4 py-2.5` |
| `x-admin.empty` | 12 `p-16` empty cards with tinted icon boxes | one compact line inside the card |

**Reuse, unchanged:** `x-stat-strip` (already the landlord's answer to tile grids), `x-card`, `x-page-header`, `x-back-link`, `x-styled-select`.
**Do not edit** `x-stat-card` or `x-page-header`: landlord and tenant pages share them. Admin just stops using `x-stat-card`.

## 2. Sidebar (`layouts/admin.blade.php`)
- Move rows to `x-admin.nav-item`.
- Delete the dead "Rental Businesses / Soon" row.
- Replace the one-link "Reservations" group with **Rentals**: Reservations, Payments, Payouts, Deposit Charges.
- Group labels `text-white/30` to `/50`. Avatar is navy on navy: use `bg-white/10`. Tooltips `#1e293b` to `#060D26`.
- The small inline `<style>` stays: it prevents the collapsed-sidebar flash on load (documented exception).

## 3. Dashboard (`admin/dashboard.blade.php`, `Admin/DashboardController`)
Order, top to bottom:
1. **Header:** "Dashboard", today's date as plain text, no subtitle.
2. **Needs you** (one card, the page's focal point): one row per queue with count, age of the oldest item, and a link to that queue. Rows: Landlord verifications, Property verifications, Property documents, Unit approvals, Reports, Move-in disputes. Zero rows stay in place, muted, so positions never shift. Footer line: money held and owed to landlords (`Payment` `Held` sum, `pendingPayout()` sum).
3. **Totals:** `x-stat-strip` with Users, Verified landlords, Properties, Units, Active reservations.
4. **Activity chart + Recent reservations**, side by side. Chart: no area fill, `tension: 0`, small points, neutrals from the palette.

**Removed:** six tiles, Quick Actions card, pending tables with tabs, user donut, tinted reservation tiles, fake date control.

Removing the donut also fixes a real bug: unverified landlords are a subset of landlords but were added on top, so its percentages double-count.

**Controller:** add a private `queue()` returning `{label, count, oldest, href}`; drop the now-unused `pending*List`, `reservationStats` and role totals. Oldest uses `min()`, one small query per queue.

## 4. Index pages
**Rule:**
- Page has tabs: delete the tile row and the "N awaiting review" pill. Tab counts carry the numbers.
- Page has no tabs: tiles become one `x-stat-strip`.
- Exception: Payments keeps a strip of **peso sums only** (Held, Released, Recorded), since the tabs don't show those.

Tabs win over strips because some pages have more statuses than a strip's 5 columns (Payments has 7, Reservations more).

**Tables:** `x-admin.th`; rows `px-4 py-3`; money and counts right-aligned with `tabular-nums`; initials circles removed where they add nothing (payments, dashboard); no decorative hover.

Pages: `users`, `verifications`, `listings/approval`, `documents`, `units`, `catalogue/properties`, `catalogue/units`, `reservations`, `payments`, `payouts`, `deposit-charges`, `reviews`, `reports`, `conversations`, `audit-logs`, `ratings`.
- `catalogue/*`: remove hover lift and image zoom.
- `listings/approval`: remove `hover:shadow-xl`.
- `ratings`: remove extra gutters; `max-w-[1600px]` like the rest.

## 5. Analytics (`admin/report-analytics/index.blade.php`, the second-worst page)
- Tile rows become `x-stat-strip`.
- Stacks of tiny cards with pill titles (around lines 73-103, 207-232, 331-349) become compact tables.
- Property types stop using status colours; status colours come from `x-admin.status`.
- Charts get the same plain treatment as the dashboard. Exports stay.

## 6. Detail pages and forms (sweep)
`verifications/show`, `reservations/show`, `users/show`, `catalogue/properties/show`, `units/show`, `documents/show`, `reports/show`, `conversations/show`, `settings/edit`, `profile/edit`, `users/_form*`, `payments/partials/refund-modal`:

| Find | Replace |
|---|---|
| `font-normal` on `h1`-`h3` | `font-semibold`; page titles through `x-page-header` where the page lacks it |
| `#94A3B8` as text | `#5B6A8E` (placeholders and disabled states may keep it) |
| `rounded-3xl` | `rounded-2xl`; nested tiles `rounded-lg` |
| `#059669`, `#92600A`, `#2563EB`, `#9CA3AF`, `#CBD5E1`, `#22C55E` as text | `#15803D`, `#B45309`, navy, `#5B6A8E`, `#E2E4EC`, `#15803D` |
| Hand-rolled status chips | `x-admin.status` |

**From the second audit (re-check each in the code before changing it):**
- **`verifications/show` (highest stakes):** the page exists to compare an ID with a selfie, but IDs are cropped with `object-cover`, the selfie is a separate card and the face-match verdict is small text under OCR results. Put ID and selfie in one strip with `object-contain`, and show the verdict as a chip above Approve. Header out of its card (§6b).
- **Button contrast:** white on `#22C55E` (about 2.3:1) becomes a `#15803D` fill; white on amber (about 1.6:1) becomes navy text.
- **Missing friction on risky actions:** add `data-confirm` with specific wording to suspend (`users/show`) and approve (`listings/approval`), and clearer wording on suspend/delist in `reports/show`.
- **Queue rows that aren't tables:** `verifications`, `reports`, `documents` and `listings/approval` repeat micro-labels per row, and approval rows are about 184px tall. Convert to real tables, as the §6d and §12 rewrite will say.
- **Filters:** search `h-10` beside `h-11` selects becomes one height; routine Filter buttons stop using the coral CTA (coral is for the one primary action); `reservations` "Needs review" tab moves first; `catalogue/units` defaults to the table view.
- **Modals:** `rounded-3xl` hand-rolled shells move to `x-modal`, or `data-confirm` for simple toggles.
- **Vanity and decoration:** duplicate "Days as member" (`users/show`), icon tiles on section headers, coloured button glow (`listings/approval`).
- **Likely bugs found on the way; fix if confirmed, otherwise report:** `conversations/show` reads `unit_number`, and says "Read-only" while offering a refund.

**Not doing:** search boxes on the 8 queues without one (a feature, needs controller work), sticky table headers, a shared filter-bar component (a height and button fix gets most of the benefit).

## 7. Words
- Titles match the sidebar: Payments, Ratings, Analytics, and Users / Landlords / Tenants by role filter.
- Delete subtitles that restate the title ("View and manage all registered accounts").
- Fix "Manage escrow payments and releases", which is no longer accurate now that direct payments exist.
- Keep subtitles that say something real (`settings/edit`, `payouts`, `audit-logs`).

## Not changing
No routes, permissions or business logic beyond the dashboard's read queries. Keep `max-w-[1600px]`, collapsible sidebar, `x-styled-select`, Review links, and the status mapping in DESIGN §7.

## Verification
- `php artisan view:cache` then `view:clear`, `npm run build`, Pint.
- Full test suite in the background; update only assertions about deliberately changed wording. Known machine-only failures may remain (see Pre-step) and are reported as such.
- Greps across `resources/views/admin`, expected 0 (except noted exceptions): `x-stat-card`, `rounded-3xl`, `font-normal` on headings, `#1e293b`, `w-4.5`, `Welcome back`.
- Run the `impeccable` checklist on the diff.
- **Cannot render here.** Give the user a browser checklist, light and dark: dashboard queue with zero and non-zero rows, sidebar collapsed and expanded, one queue page, Payments, Analytics, one detail page. Stated plainly as not yet checked in a browser.

## Docs
- `context/CHANGELOG.md` entry.
- `context/DESIGN.md`: rewrite §12 (it still prescribes the tile-and-tab stack and old hexes); fix §0 and §2 (they still say teal).
- Copy this plan to `plans/admin-design-pass.md`, marked pending manual verification.
- Implement on `joseph`. Per CLAUDE.md: switch to Sonnet for implementation, and no AI trailer on commits. Do not commit until asked.
