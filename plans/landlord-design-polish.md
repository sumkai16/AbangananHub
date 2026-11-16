# Landlord design polish pass

## Context
Axcee asked for design improvements. Scope agreed: **landlord pages** (mobile-first, 375px per CLAUDE.md / DESIGN.md §0b), audit first, then he picks the batches. An audit against `context/DESIGN.md` found the issues below. Seeded landlord `rosa@abangananhub.com` / `password` has data for checking.

On approval, copy this plan to `plans/landlord-design-polish.md` (CLAUDE.md planning rule).

## Decision
Axcee chose **A + B + C** only. D, E and F stay listed below as later options, not part of this implementation.

## Batches

### A. Phone tap targets (44px, DESIGN §7)
- `layouts/landlord.blade.php` :445/456/464/477 top-bar buttons `w-9 h-9` → `w-11 h-11`; add `aria-label` to hamburger (:444) and bell (:476).
- Nav rows :347 and section headers :331 → add `min-h-11`.
- `landlord/reservations/_row-actions.blade.php` :11–18 `h-8` → `h-11` below `lg` (`max-lg:h-11`).
- `landlord/profile/show.blade.php` :91 tabs → `min-h-11`.
- `landlord/properties/index.blade.php` :19 drop `max-sm:h-9 max-sm:px-4`.
- `landlord/properties/show.blade.php` :96–108 drop `sm:h-9`.

### B. Phone overflow bugs
- `components/page-header.blade.php` :37 actions wrapper → add `flex-wrap`; `landlord/payments/index.blade.php` :64–96 hide Export below `sm`.
- Layout :149 notification dropdown → anchor `right-0` / full-width below `lg` so it stays on screen.
- `landlord/contracts/index.blade.php` :60 search → `text-[16px] sm:text-[13.5px]` (stops iOS zoom).
- Properties index :93–110 hide table toggle below `lg` (`hidden lg:flex`, same as reservations :153).

### C. Colour and contrast fixes
- `landlord/properties/edit.blade.php` :97 coral text → `text-[#B35A3D]`.
- Reservations :190 inactive tabs `#94A3B8` → `#5B6A8E`.
- Properties index :360 `text-[#EF4444]` → `#DC2626`; spell out "rsvd"/"occ".
- Dashboard :58 dead `bg-[#EF4444]/12` → `/10`; :342 occupied bar navy → `#EF4444`.
- Layout tooltips :206/371/394/426 `#1e293b` → `#060D26`.
- Bell :478 dot → numbered pill matching messages badge.

### D. One CTA style
Standardise landlord primary coral buttons on `<x-primary-button>` (`rounded-xl`, 44px). Representative: dashboard :38/:85, contracts :33, properties show :440. Wide change (~80 buttons); do the page headers and main actions, not every inline link.

### E. Shared components instead of hand-rolled markup
- Empty states → `<x-empty-state>`: properties index :127–154, payments :273–288, reservations :261, policies `leases/index` :17 (add "Add a property" link).
- Dashboard :20–33 header → `<x-page-header>` (also fixes missing phone top-bar title).
- `leases/index` :11 `max-w-[1680px]` → `max-w-[1600px]`.
- Contracts :51 drop the filter card wrapper; properties index :68 search `h-10` → `h-11`.

### F. Reservations page declutter (largest UX change)
About 600px of controls sit above the first request on a phone, and the stat strip and stage tabs filter the same thing. Keep the stage tabs, drop the duplicating stat strip (or collapse it), and change phone card details (:411–424) from 3 cramped columns to 2.

## Not in scope (noted only)
Unifying the four stat-tile styles, breadcrumb vs `<x-back-link>` split, page padding differences, property card `shadow-lg`/zoom.

## Verification
1. Log in as Rosa; open each changed page in Chrome DevTools at 375px and at desktop width.
2. Check: no horizontal scroll (temporarily remove `overflow-x-clip` on `main` to expose hidden overflow), tap targets measure 44px, notification dropdown stays on screen, payments header wraps.
3. Run the `impeccable` skill on changed views.
4. `php artisan test` for anything touching shared components (`page-header`, layout).
