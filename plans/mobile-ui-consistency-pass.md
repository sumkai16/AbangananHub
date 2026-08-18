# Mobile UI/UX consistency pass

## Context

The user's partner tried AbangananHub on a real phone and found the design and functionality "inconsistent" across the site — no specific page was named, it's a general impression. Since neither of us has the partner's phone, verification will be done with browser dev-tools mobile emulation at common widths (375px / 390px / 430px) during the work, with before/after screenshots shown here as we go, rather than relying on physical-device testing.

An initial codebase survey turned up one clearly confirmed, high-impact gap plus a couple of lower-priority items worth a pass:

1. **The public/tenant header (`resources/views/layouts/app.blade.php`) has no mobile navigation at all.** The primary nav — Browse, Areas, How it works — is `hidden lg:flex` (line 54), and the code comment at line 53 admits it outright: *"this header has no mobile menu."* On a phone, none of those are reachable. The "Become a Landlord" / "Landlord Dashboard" / "Admin Actions" CTA is also `hidden sm:block` (line 137), so it disappears on virtually every phone width too.
   This is the header shared by every public and tenant page, so its absence is likely the single biggest driver of "feels inconsistent" — a visitor gets a bare logo + bell + avatar on phones, then lands in the landlord dashboard (a different layout entirely) which *does* have a full hamburger/off-canvas sidebar (`layouts/landlord.blade.php`, `layouts/admin.blade.php` — these two already match each other well). That contrast between "no mobile menu" and "full mobile menu" one click apart is exactly the kind of thing that reads as inconsistent.

2. **Unverified but worth a sweep**: ~9 files use teleported custom modals (`properties/show.blade.php`, `landlord/units/all.blade.php`, `landlord/tenants/walk-in/create.blade.php`, `landlord/tenancies/partials/record-payment-modal.blade.php`, `landlord/reservations/index.blade.php`, `landlord/properties/create.blade.php`, `landlord/occupancy/index.blade.php`, `conversations/partials/_move-in-clock.blade.php`, `agreements/show.blade.php`). The shared `components/modal.blade.php` / `components/confirm-modal.blade.php` are responsive by default, so these are likely fine, but haven't been visually confirmed at 375px.

3. **Unverified but worth a sweep**: wide data tables on payouts/users/reservations pages are wrapped in `overflow-x-auto` (handled, not broken), but scrolling a wide table sideways on a phone is a rough UX pattern worth spot-checking for anything that reads as broken rather than just "acceptable but not great."

## Approach

Work in three passes, verifying each visually with dev-tools mobile emulation before moving to the next:

### Pass 1 — Add mobile nav to the public/tenant header (the confirmed fix)

File: `resources/views/layouts/app.blade.php`

- Add a hamburger button, `lg:hidden`, placed in the right-actions area near the existing icons (matching the icon-button sizing/style already used for the notification bell at line 194-204).
- Add an Alpine-driven mobile nav panel (simple `x-data="{ mobileNavOpen: false }"` toggle — no need for the full off-canvas sidebar machinery `landlord.blade.php` uses, since this header only needs 3-4 links, not a whole app's navigation tree). A slide-down panel under the header (similar transition style to the existing Areas/Avatar dropdowns already in this file) is the right weight for this — reuse the same `x-transition` timing/easing already established at lines 83-86 and 260-266 so it feels like the same design system, not a bolted-on component.
- Panel contents: Browse, Areas (as an expandable list, reusing `$navAreas` already available), How it works, and — for authed users — the Landlord Dashboard / Become a Landlord / Admin Actions link that's currently `hidden sm:block` and invisible on phones.
- Close on `@click.outside`, `@keydown.escape.window`, and on navigation (link click), consistent with how every other dropdown in this file behaves.
- Verify at 375/390/430px: menu opens, all links reachable and route correctly, closes properly, no layout shift/overflow introduced in the header itself.

### Pass 2 — Sweep the ~9 teleported-modal files at mobile widths

For each file listed above: open the relevant page/action in dev-tools mobile emulation, trigger the modal, confirm it doesn't clip, overflow horizontally, or leave content unreachable (the pattern of bugs already found and fixed today in `properties/show.blade.php` — `overflow-hidden` clipping a popover — is exactly the class of bug to check for here). Fix any instance found using the same technique used earlier: scope `overflow-hidden` to only the element that needs it (e.g. a rounded-corner background), never to an ancestor of something that needs to render outside its box.

### Pass 3 — Spot-check tables/dense layouts on mobile

Quick pass over the landlord/admin table-heavy pages (payouts, users, reservations, tenants) at 375px to confirm the existing `overflow-x-auto` wrappers behave acceptably (scrollable, not clipped, no broken column widths) — fix only if something is actually broken, not just "could be nicer," since a full table→card-view redesign is out of scope unless the user asks for it after seeing what's there.

## Verification

- Use the local dev server (`php artisan serve`, already running per earlier conversation) and Chrome DevTools responsive mode (or the `run` skill if it drives a browser) at 375px, 390px, and 430px widths for each page touched.
- For Pass 1 specifically: confirm as both a guest and a logged-in tenant/landlord/admin, since the right-actions content differs per auth state.
- Screenshot before/after for the header change and share with the user, since neither of us has the partner's device to confirm on.
