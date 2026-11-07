# Tenant design pass on the newly merged pages (Nov 1 2026)

## Context
`origin/axci` was merged into `joseph` (merge uncommitted, full test run in progress). It redesigned the agreement page,
added the online/direct pay choice, cash and QR claim forms, receipts and tenant documents. Two read-only surveys at 375px
(tenant is mobile-first, DESIGN.md §0b) found the problems below. User chose two groups: **Pay & sign clarity** and
**Phone usability**. Documents upload and palette polish are NOT in this pass (listed at the end).

## Pre-step
Let the running test suite finish, fix any merge fallout, commit the merge on `joseph` first so this pass is its own commit.

## A. Pay & sign clarity
| # | Fix | File |
|---|---|---|
| A1 | Disabled pay/sign/report buttons show why: a hint line "Tick the box above to continue" while `!agreed` (same pattern as the sign button's missing-documents line) | `components/payment-route-choice.blade.php` (~140 online, ~208 cash), `payments/partials/_direct-pay.blade.php` (~121), `agreements/show.blade.php` (~257) |
| A2 | Tick-box `h-4 w-4` to `h-5 w-5` | `components/payment-agree.blade.php:11` |
| A3 | Selected pay card gets `ring-2 ring-[#FF8A66]` plus a check icon; unselected cards read as tappable | `payment-route-choice.blade.php` (~64-96) |
| A4 | Copy: "Choose Pay cash above" becomes "Switch to Cash above"; consent text adds "or hand cash to the landlord myself" | `_direct-pay.blade.php:122`, `components/payment-consent.blade.php:4,8` |
| A5 | Routine instruction boxes ("Please read the entire agreement") from red to the info tint `#ECEEF6` with navy text; red stays for real errors | `agreements/show.blade.php` (~204-213, ~378-385) |
| A6 | Key terms date row: `grid-cols-2` on phones, hide the connector below `sm` | `agreements/show.blade.php:107-111` |

## B. Phone usability
| # | Fix | File |
|---|---|---|
| B1 | Inputs `text-[16px] sm:text-[14px]` (iOS stops zooming): cash form, QR report form incl. "Paid from" select, viewing note, report-issue textarea, inbox search | `payment-route-choice.blade.php` (~194-205), `_direct-pay.blade.php` (~56-116), `conversations/partials/_viewing-card.blade.php`, `agreements/show.blade.php:421`, `conversations/index.blade.php:136` |
| B2 | Paired fields `grid-cols-2` to `grid-cols-1 sm:grid-cols-2` | the two pay forms |
| B3 | Tap targets 40px+: Withdraw, "Send to AbangananHub for review", Dispute, Print, "I haven't received the keys" (add `py-2`) | `payments/partials/_claim-status.blade.php`, `agreements/show.blade.php` (~51, ~396) |
| B4 | Refund notice at 13px; "Claim your refund" becomes an `h-10` button when claimable (3-day deadline) | `payments/partials/_refund-notice.blade.php:14` |
| B5 | Hide the sticky rail's duplicate rent card below `lg` so the CTA sits closer on phones | `agreements/show.blade.php` (~191-195) / `sticky-rail` usage |

Reuse the existing sign button's missing-documents hint markup for A1. No backend, route or schema change.

## Verification
- `php artisan view:cache` then `view:clear`, `npm run build`, Pint, the Payments and agreement feature tests (existing assertions on button text such as "I&rsquo;ve paid" must still pass).
- Cannot render at 375px here; hand the user a browser checklist: tick-box hint, selected card ring, forms do not zoom on iPhone, buttons tappable, dates not wrapping.
- Update `context/CHANGELOG.md`; copy this plan to `plans/tenant-merged-pages-design-pass.md` (pending manual verification).

## Not in this pass (found, not fixed)
Documents upload hint/busy state/HEIC and dark-mode status pills; one amber/one red and blue "Waiting" pill; `font-normal` titles; 10-11px text; the earlier deferred list (tenancy header overflow, chat composer 13px, reports table, favorites cards).
