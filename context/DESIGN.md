# DESIGN.md — Design System

## 0. The three defaults to actively avoid
None of the three AI-default looks apply. AbangananHub uses a teal-forward identity with flat, structured cards — distinct from cream/serif, dark-mode acid, and broadsheet layouts. The risk to watch since the July 2026 flattening is a *fourth* default: generic admin-template Tailwind. What keeps it off that: the locked teal palette, color used only for status, and dense purpose-built data views rather than boxes of evenly-spaced widgets.

## 1. Reference / Inspiration
- Airbnb (June 2026) — card layout, image-is-the-card pattern, browse grid, clean listing detail pages
- Analyst prototype (July 2026, `prototype/*.png`) — the current reference: flat white cards, tinted stat tiles with circular icon badges, underline tabs, dense avatar/thumbnail tables, dark sidebar shells
- ~~Glassmorphism trend (frosted glass panels over gradient backgrounds)~~ — was the signature layer until July 2026; retired, see §6

## 2. Brief → Token System
- Subject: A rental accommodation marketplace for tenants and landlords in Cebu. The page's job varies by role: tenants browse/search/reserve; landlords manage listings/units/reservations; admin verifies/approves/monitors.
- Tone: **Trustworthy.** What it should NOT feel like: playful, startup-trendy, or corporate enterprise.
- Signature element: Flat white cards with a hairline `#E2E8F0` border and a soft 1px shadow, sitting on a quiet `#F7FCFC` page background. Structure and hierarchy do the work; color is reserved for status. (Superseded the glassmorphism signature in July 2026 — see §6.)

## 3. Color Palette

| Role | Hex | Usage |
|---|---|---|
| Primary (Deep Ocean Blue) | `#156F8C` | Navigation bar, headings, key interface elements |
| Secondary (Ocean Teal) | `#2AA7A1` | Primary buttons, icons, active states — fills/borders/backgrounds only, **never foreground text on white** (fails WCAG AA, ~2.9:1) |
| Accent (Aqua) | `#69D2C6` | Badges, highlights — fills only, **never foreground text on white** (fails WCAG AA, ~2.2:1) |
| CTA (Soft Coral) | `#FF8A65` | CTA buttons: Search, Book Now, List Property |
| Background (Ice White) | `#F7FCFC` | Main page background |
| Section Background (Mist Blue) | `#EEF8F8` | Distinguishes content sections |
| Surface/Card (White) | `#FFFFFF` | Property cards, forms, panels |
| Text primary (Charcoal) | `#1F2937` | Headings and essential content |
| Text muted (Slate Gray) | `#64748B` | Descriptions, labels, supporting info |
| Borders (Soft Gray) | `#E2E8F0` | Subtle separation between components |
| Success (Emerald Green) | `#22C55E` | Successful actions, verified statuses |
| Warning (Amber) | `#FBBF24` | Notifications, cautionary messages |
| Error (Red) | `#EF4444` | Validation errors, failed actions, critical alerts |
| Footer background | `#0F172A` | Accepted palette exception for footer only |

Rules:
- One accent color for CTAs (`#FF8A65`) — everything else neutral or teal-family.
- `#2AA7A1` and `#69D2C6` are restricted to fills, borders, and backgrounds. Never as foreground text on white.
- Hover states: `hover:brightness-95` only. No hardcoded darker hex values.
- No custom CSS class systems (`abg-*`), no inline `<style>` blocks — pure Tailwind only.

## 4. Typography
- **Page-title font: Source Serif 4** (`font-display`) — added July 2026. **Large page titles only**: the property detail `<h1>` today, home and about when they're redesigned. Not card headings, not section labels, not `<h3>`s — those stay on Poppins. The scope is the whole point: a serif used everywhere stops being a signal and becomes a third font tax.
  - **Why not Playfair Display**, the obvious pick: §0 names the high-contrast-serif look as an AI default to avoid, and Playfair is the face that look is made of. Source Serif 4 is screen-first, shares Inter's vertical proportions so the two sit together without one reading oversized, and lands *institutional* rather than *fashion-editorial* — which is what §2's "trustworthy, not startup-trendy" asks for.
  - Loaded in **all four** layouts even though only `layouts/app` uses it today. Google Fonts serves all three families in one request and browsers defer the file until a glyph needs it, so the cost is nil — and it avoids re-creating the bug documented below, where a utility existed app-wide but the font was only linked in two shells.
- Display/heading font: **Poppins** (`font-heading`) — card titles, section headings, UI labels
- Body font: **Inter** (`font-sans`, Google Fonts, loaded in base layout)
- Type scale: 12 / 14 / 16 / 20 / 24 / 32 / 48 (maps to Tailwind `text-xs` through `text-5xl`)
- Weight rules: Poppins 600–700 for headings, Inter 400 for body, Inter 500 for labels/buttons
- Line-height: 1.5–1.75 for body text
- Line length: 65–75 characters max (`max-w-prose` or equivalent)

**Font pipeline bug — found and fixed July 21, 2026.** "Body font is Inter" was true in `resources/css/app.css` (`body { font-family: 'Inter' }`) but false in practice on two of the four shells. Two independent defects stacked:
1. `tailwind.config.js` had `fontFamily.sans` mapped to `Figtree` — a leftover from the Laravel Breeze scaffold, never updated when the app moved to Poppins/Inter. Every layout puts `class="font-sans"` directly on `<body>`, and a Tailwind utility class (specificity 0-1-0) beats the plain `body {}` element rule (0-0-1) — so `.font-sans` silently won, and the app rendered in Figtree, not Inter.
2. Figtree itself was never loaded — only `layouts/app.blade.php` (tenant/public) and `layouts/guest.blade.php` (auth) had the Google Fonts `<link>` for Poppins/Inter. **`layouts/admin.blade.php` and `layouts/landlord.blade.php` had no font `<link>` at all.** So on those two shells the browser fell through past both Figtree and Inter to the OS default sans font (Segoe UI / -apple-system / etc.) — while public/tenant pages correctly rendered Inter/Poppins. That mismatch between shells is what read as "fonts aren't consistent."

Fixed: `tailwind.config.js` now maps `sans` → `Inter` (matching `app.css`) and adds a `heading` → `Poppins` utility; the missing Google Fonts `<link>` was added to `admin.blade.php` and `landlord.blade.php`, matching the other two layouts. **When a font-consistency complaint comes in, check both halves independently — that the font is loaded, and separately that nothing with higher CSS specificity is pointing at a different family — a visual re-check of "does app.css say Inter" is not sufficient on its own.**

## 5. Spacing & Layout
- Base unit: 4px (Tailwind default)
- Scale: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 (Tailwind `p-1` through `p-24`)
- **Two container widths, chosen by context — always with `mx-auto`:**

  | Context | Container | Why |
  |---|---|---|
  | Public / tenant / auth (no sidebar) | `max-w-[1400px] mx-auto` | Full-viewport pages. Tenant views live in `layouts/app` and follow this rule — the split is driven by *whether there's a sidebar*, not by role |
  | Admin & landlord work areas (behind a 256px sidebar) | `max-w-[1600px] mx-auto` | The sidebar already consumes 256px; at 1920px that leaves 1600px usable, so a 1280px cap stranded ~320px. Dense tables and multi-column dashboards need the width |
  | Detail/form pages inside a work area | `max-w-4xl` / `max-w-5xl` `mx-auto` | Single-column reading width — deliberately narrower than the shell |

  **`mx-auto` is not optional.** A container that caps width without centering pins content to the left edge and strands all the slack on one side. 15 admin views shipped that way — they capped at `max-w-7xl` with no `mx-auto`, which on a 1920px monitor read as a broken layout with a dead right margin. Every `max-w-*` page container needs `mx-auto` beside it.

  Gutters are `px-4 sm:px-6 lg:px-8` everywhere. The old off-scale `lg:px-[50px]` is gone from all 11 views that carried it.

  **Views that pick their layout at runtime must derive their width, not hard-code it.** Five views render in a sidebar shell for one role and the public shell for another (`conversations/index`, `conversations/show`, `tenant/reservations/index`, `profile/edit`, `landlord/profile/show`). Each hard-coded a single width, so it was wrong for one of its two audiences. They now call `auth()->user()->shellContainerClass()`, which returns the right cap for the shell; pass the view's own condition when it isn't the landlord default — `shellContainerClass($isOwner)`, `shellContainerClass(auth()->user()->hasRole('Admin'))`. `User::usesLandlordShell()` holds the landlord-not-admin condition that four `@extends` lines share, so the layout choice and the width can't drift apart.

  Note the history, because this entry has been wrong twice:
  - Admin widths were briefly normalised *down* to `max-w-7xl` in the glassmorphism sweep by applying the old "one container width" rule literally. That rule was written for full-width public pages and does not transfer to a shell with a fixed sidebar — the usable area is `viewport − sidebar − padding`, not `viewport`.
  - This table then claimed public pages used `max-w-7xl` when **no public page ever did** — they were all `1400px`. Documenting an intended value rather than the shipped one is the same failure as the rule above. **When updating this table, grep the views first and record what is actually there.**
- z-index scale — **as actually built** (the old 10/20/30/50 scale documented here was aspirational and never matched the code):

  | Layer | Value | Where |
  |---|---|---|
  | Decorative / in-card overlays | `z-10` | guest-layout right panel, sidebar collapse toggle |
  | Mobile sticky bottom bar, dropdown menus | `z-20` | `properties/show`, landlord property show |
  | Mobile slim top bar | `z-30` | admin + landlord layouts (sticky) |
  | Mobile sidebar backdrop | `z-40` | admin + landlord layouts |
  | Sidebar (aside) | `z-50` | admin + landlord layouts |
  | Public site header (sticky) + skip link on focus | `z-[100]` | `layouts/app.blade.php` |
  | Page-level modal (`x-modal`) | `z-[200]` | delete-user-form, property inquiry modal, handover picker |
  | Unit slideout panel | `z-[998]` | `properties/show` |
  | Photo lightbox | `z-[999]` | `properties/show`, landlord property show |
  | Message toasts (bottom-right) | `z-[9997]` | `partials/message-notifications` |
  | Notification/confirm modal (`x-confirm-modal`) | `z-[9998]` | global |
  | Auth modal | `z-[9999]` | `layouts/app.blade.php` |


  **Leaflet is contained, not out-ranked.** Leaflet gives its own children `z-index: 400` (`.leaflet-pane`) up to `1000` (`.leaflet-top`), and its container is `position: relative` with `z-index: auto` — so it forms no stacking context and those values compete with the whole page. A map painted straight over the `z-[200]` report modal on `properties/show`. Fixed once in `resources/css/maps.css` with `.leaflet-container { z-index: 0 }`, which scopes the 400–1000 range inside each map. **Don't raise a modal's z-index to beat a map** — that is almost certainly why the unit slideout and photo lightbox sit at `z-[998]`/`z-[999]`, and they can come back to this scale now.
  **A modal in `layouts/app` must be `z-[200]`, not `z-50`.** The public site header is `sticky z-[100]`, so a `z-50` overlay renders *behind* it and loses its top ~68px — which on a top-aligned dialog is the title and the close button. The `z-50` modals that work do so by accident: they sit in the admin/landlord shells where the only sticky chrome is `z-30`, or they're vertically centred and short enough that the header never reaches them. Four `z-50` overlays in `layouts/app` are still latent (`agreements/show`, `tenant/reservations/index`, `landlord/verification/create` + `show`) — centred, so they only clip when taller than the viewport.

  **The rule when adding a layer:** blocking dialogs outrank passive UI. `x-confirm-modal` sits at `z-[9998]` deliberately — it must cover the sticky header, the slideout, the lightbox and the toasts, because it demands acknowledgement, while the auth modal alone outranks it. It was `z-50` until July 2026, which put it *below* the `z-[100]` public header (the header stayed bright and clickable over the dimmed backdrop) and tied it with the admin/landlord sidebar, where it only won on DOM order.

## 6. Layout Concept — flat cards (glassmorphism retired July 2026)
A quiet `#F7FCFC` page background fills the viewport. All content panels sit on it as **flat white cards**:

```
bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)]
```

Use the `<x-card>` component rather than retyping the classes; it takes a `flush` prop for cards that wrap a table or divided row-list supplying its own padding. Swapping class strings in place is acceptable when the panel is also a large Alpine root and re-tagging would risk mismatched `</div>`s.

**Glassmorphism is gone.** `bg-white/70 backdrop-blur-xl border-white/30 shadow-lg` and the teal mesh gradient were the app-wide signature until July 2026, when they were retired in favour of the analyst prototype's flat, structured look. Depth now comes from the hairline border and a barely-there shadow, not translucency. Do not reintroduce `backdrop-blur` on a content panel.

Two uses of `backdrop-blur` survive, both where the blur does legibility work over something visually busy rather than decorating a panel:
1. **Modal backdrops** — `bg-black/40 backdrop-blur-sm` behind a blocking dialog.
2. **Panels layered over photography** — the auth split-panel hero (`bg-[#0F172A]/25 backdrop-blur-lg border-white/15`) sits on the guest layout's photo; without the blur the heading loses contrast against the image.

Anywhere else — any panel whose backdrop is a flat page background — is a bug.

**Gradients** are likewise not extinct: brand heroes (`about`), gradient-clipped display text on the auth pages, and image scrims (`bg-gradient-to-t from-black/70`) are all intentional. What was removed is the *page-background* mesh gradient.

Because no card creates a `backdrop-filter` containing block any more, the old "modals must be teleported or they get clipped" hazard is weaker — but keep `x-teleport="body"` on modals nested in cards regardless, since overflow and stacking contexts still trap them (see RULES.md → Modals & Overlays).

Property browse grid: Airbnb-style image-is-the-card pattern — no white box wrapping. Text sits below the image on the page background. 3-column grid on desktop, 2 on tablet, 1 on mobile.

## 6b. Page Headers (standard)
Page-title headers are **never wrapped in a card** — they sit bare on the page background: optional breadcrumb/back-link, then title, then subtitle.
- Title: `text-2xl font-bold text-[#1F2937]` (charcoal — not ocean-blue)
- Subtitle: `text-sm text-[#64748B]`
- Index pages may prefix the title with an icon box (`w-11 h-11 rounded-xl bg-[#1F2937]`); the title styling stays the same.
- `#156F8C` is reserved for nav/section accents, not page titles.
- Exception: `profile/show`'s name lives in the avatar hero banner (an identity card, not a page header).

## 6c. Two-column form layout (create/edit)
Long forms use a 12-col grid: fields in `lg:col-span-7`, a right rail in `lg:col-span-5`. The rail holds a **live preview card** (mirrors the record as the user types) and secondary selectors (e.g. amenities). Keep width-hungry inputs (camera capture, multi-column galleries) in the left column. Drop `sticky` on the rail when its content (e.g. a long amenities list) is taller than the viewport.

## 6d. Data tables (index pages with row-level actions)
Dense management lists (e.g. landlord reservations) use a table inside one flat card (`<x-card flush>`): `overflow-x-auto` wrapper with `min-w-[980px]` table, uppercase 11px slate column headers, `divide-y divide-[#E2E8F0]` rows, `hover:bg-[#F7FCFC]/70` row hover. Status shown as pill badges (teal/amber/emerald/red/slate families per lifecycle). Footer inside the card: "Showing X to Y of Z" left, paginator right. Search + filter bar sits in its own flat card above the table (GET form; status tabs carry filters through as query params).

Where both a card grid and a table make sense (e.g. landlord Units), offer a grid/table segmented toggle at the right end of the filter bar (two icon buttons in a `bg-[#F7FCFC]` pill; active = white bg + `text-[#156F8C]` + shadow). The choice is client-side Alpine persisted to `localStorage` — both views render server-side and swap via `x-show` (add `x-cloak` to the non-default view). Per-unit derived data is precomputed once in a `@php` loop and shared by both views.

## 6e. Public property page (properties/show) — split-immersive (rebuilt July 2026)
The page went flat first (July 2026) and became the pattern the rest of the app was restyled to in the glassmorphism retirement (§6). **Rebuilt later that month from a three-column grid into a split-immersive layout** — media rail left, editorial column right.

- **Media rail** (`lg:col-span-5`): gallery + unit list, `lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto`. Sticky *with its own scroll* rather than a fixed full-height rail: a one-unit property gets the immersive panel, a twelve-unit one scrolls its list inside the rail instead of pushing the page out of alignment. Static below `lg`, where it simply stacks above the content.
- **Editorial column** (`lg:col-span-7`): breadcrumb → `font-display` title → location + rating → price → description → 2×2 fact tiles → single CTA + favourite → landlord row, then stacked `<section>`s (Details, Amenities, Location, Reviews) with `id`s so the hero can link into them.
- **The tabbed card is gone.** Sections scroll rather than hide behind Overview/Location/Reviews. Better for scanning and for SEO, and it removed `goTab()`'s `window.dispatchEvent(new Event('resize'))` hack — the map was only being nudged because it initialised inside a hidden panel.
- **Price follows the unit picked in the rail**, so there is one source of truth. A hero price range plus a picker elsewhere is two numbers that can disagree about what the form is submitting.
- **One CTA; the form lives in a modal** (`inquireOpen`), teleported to body because the editorial column sits in a sticky/overflow context that would clip a dialog rendered inside it. Desktop only — below `lg` the existing sticky bottom bar and two-step sheet already cover it, so there is one form per breakpoint, not two.
- Unit picker rows: radio + thumb + label/meta, price stacked over an Available badge on the right; >4 units collapse behind a "View all units (N)" button (`moreUnits` Alpine flag).
- Amenities render as a labelled two/three-column grid in their own section, guarded by `@if(count > 0)` — the 64px icon squares were a compact *preview* near the top of the old layout and read badly at section scale with truncated captions.
- Mobile (<lg): a teleported sticky bottom bar (selected unit + coral Inquire button) opens a two-step bottom-sheet modal (Select a Unit → Message Landlord) sharing `selectedUnit` state with the desktop rail.
- Prototype's blue accents are always rendered in the locked Ocean Teal/Deep Ocean Blue palette; CTA stays coral `#FF8A65`.

- **Inquiry and Reserve are a real distinction, not decoration.** `mode` posts as a hidden input and `StoreReservationRequest` validates `in:inquiry,reserve`. **Inquiry collects only a unit and an optional message**; **Reserve** adds move-in (required) and move-out (optional). Both still land at `rental_status = 'Inquiry'`, so the landlord's accept step is unchanged — naming a date is what signals firmer intent. Defaults to Inquiry, and the mobile sheet carries the same toggle.
  - `target_move_in_date` is `required_if:mode,reserve|nullable`, and `prepareForValidation()` nulls both dates on an inquiry — otherwise a date typed under the Reserve tab and then abandoned still posts, attaching a commitment the tenant backed out of.
  - This is what stopped a guessed date from driving escrow escalation; see ARCHITECTURE's Clock 1 notes.
- **No "Message landlord" card.** It posted to `conversations.store` and produced a thread with *no reservation attached* — no stage stepper, no actions, a dead end indistinguishable from Inquiry. Inquiry is the single way to open a conversation from a listing. The route survives for the landlord profile page.
- **Report this listing opens a modal**, not `/reports?property_id=N`. That page redirects to itself on success, which from here would discard the selected unit and scroll position for a fire-and-forget action. `ReportController::store()` returns JSON only when the request expects it, so the standalone page keeps its redirect and both entry points share one validated action.
- **Location is a compact utility card**: header (address + segmented travel-mode control), full-bleed map, summary bar (distance, per-mode estimate, directions CTA). The mode control lives in the header and is always visible, so the choice is made *before* deciding to share your location.

**Trap that bit during the rebuild:** `Landlord@if($x) · Verified Host @endif` renders the directive as *literal text* and orphans the `@endif` — Blade's `\B@` regex does not match an `@` preceded by a word character. Build the sentence in PHP instead. This is the second time it has appeared (see `chat-panel`'s `$occupiedNote`); if a page dies with "unexpected token endif", grep for `@if` with no whitespace before it.

## 6f. Multi-step wizard layout (landlord verification) — July 2026
The verification flow (`landlord/verification/create`) is the pattern for any multi-step task.

**The problem it solves.** It shipped as `max-w-2xl mx-auto` — a 672px column on an 1866px screen — with every step wrapped in a flat white card. A camera feed is already a hard-edged rectangle, so the card framed a frame: two borders, two radii, no added meaning, floating in ~1,200px of dead margin. **`max-w-2xl` is a reading measure; don't reach for it on a page with no prose.** The card was the visible symptom, the width was the cause.

The shape now:
- **`max-w-[1200px] mx-auto`**, split `lg:grid-cols-[248px_minmax(0,1fr)] lg:gap-11` — a step rail and a stage. 248 + 44 + 896 = 1188, so the widest step (`max-w-4xl`) fills the stage exactly and nothing is sized for space that never gets used. It was briefly 1400px, which left the stage 250px wider than any step's content — **the container has to be sized by the widest step, not by the viewport**, or you rebuild the same dead-margin problem one level in.
- **Every phase block owns its `max-w-*`, and its footer bar is the last child inside that block.** This is the rule that keeps things aligned: a footer placed at the step root stretches the full stage while the content above it is half that, so the primary button floats off to the right attached to nothing. Widths in use: `max-w-xl` (ready screens), `max-w-2xl` (forms, selfie camera), `max-w-4xl` (ID capture, review). The one deliberate exception is the `lg:hidden` phone hand-off, which spans the stage because on mobile the stage *is* the content width.
- **Step rail** (`landlord/verification/_stepper`): a sticky vertical list of all five steps with numbers, names, and per-step sublabels (chosen ID type, "Front and back", "Liveness verified"). Done = green check, current = teal fill + white card behind the row, upcoming = hairline outline. It reads `step` from the existing Alpine root — **no new state**. Below `lg` it collapses to the horizontal 5-dash bar plus a "Step N of 5" label and a back chevron, which is close to what the flow shipped with, so the phone experience is unchanged.
- **Numbering is earned here.** The flow genuinely is a fixed five-step sequence, so the order is information the applicant needs. Don't copy the numbered rail onto content that isn't a real sequence.
- **No cards on step content.** Each step opens with an eyebrow (`text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]`), a `text-2xl font-bold tracking-tight` heading, and a `max-w-md` supporting line. The instruction outranks the viewfinder — the reverse of what shipped, where "Front of ID" was the smallest text on a screen dominated by a black rectangle.
- **Capture steps** split again at `xl:grid-cols-[minmax(0,1fr)_300px]`: stage left, a guidance column right holding the requirement checklist and captured-so-far thumbnails. Guidance now sits on screen *at the moment it applies* rather than on the previous screen.
- **Footer bar** per phase: `mt-7 pt-5 border-t border-[#E2E8F0]`, ghost Back left, primary `ml-auto`. The primary is whatever actually advances *that* phase — "Open camera" and "Start face check" on the ready screens, "Continue" once there's something to continue past — so a disabled Continue never sits next to the real action competing with it. Disabled buttons carry `disabled:hover:brightness-100` so they stop responding to hover.
- Page-level notices (rejection banner, validation errors) stay capped at `max-w-3xl mx-auto` above the grid — they're prose, so the reading measure is right for them.

**Camera surface layering.** The shutter is `<x-shutter-button>` — the white ring-and-disc a phone camera uses — positioned *inside* the video at `absolute bottom-5 left-1/2`, not as a labelled button below it. **This is a deliberate exception to the "one accent colour for CTAs" rule in §3:** it's a device control layered on a live feed rather than a page-level action, and coral on video reads as a record button. It went through a coral pill first; that fought the surface and had to overlap the frame edge to feel attached, which is a sign the control was in the wrong place to begin with.

Because the shutter owns the bottom of the frame, **every in-video overlay is anchored to `top-4`, never the bottom** — the hint pill on ID capture and the face-detected / no-face pills on the selfie. They were originally at `bottom-4` / `bottom-16` and the shutter landed straight on them. Centred overlays (countdown, the green liveness flash) are unaffected, and the shutter clears both the corner brackets (no horizontal overlap) and the liveness ring (39px).

The control carries only an `aria-label`; the visible instruction lives in the hint pill at the top of the frame, so nothing is said twice. The selfie's shutter renders only in the fallback states (`!livenessActive && !livenessLoading && !livenessPassed`) because the happy path captures itself — one button covering both the CDN-failure and skipped cases, not two.

**Signature: capture brackets** (`<x-capture-brackets />`). Four teal L-shapes on the camera surface, replacing the thin white rectangle that used to float in the feed. Colour is a real state signal, so it only changes where the page actually knows: the selfie ring drives off `livenessGuideColor` (teal → green), while ID framing stays teal because nothing detects whether an ID is lined up — **don't fake a "ready" state the code can't detect.** The shutter overlaps the surface bottom edge (`-mt-6 relative z-10` + a lifted shadow) where a camera puts it, instead of orphaned below the card.

## 6g. Read-then-act pages (rental agreement) — July 2026
`agreements/show` is a document you must read and then act on. It shipped as `max-w-3xl mx-auto` with the sign controls as the last thing on the page, so on a 1920 screen it was a 768px strip with ~1,150px of dead margin **and** the tenant had to scroll past the whole contract to reach the checkboxes. The action was below the fold on the one page where the action matters most.

Now `max-w-[1200px] mx-auto` split `lg:grid-cols-[minmax(0,1fr)_400px]`: the document keeps its 768px reading measure (deliberately — a contract wants a reading column, unlike the wizard's capture surfaces) and a **sticky action rail** takes the space that was empty. The rail is the same device §6c uses for create/edit forms and §6e for the property sidebar, so this is the established pattern, not a new one.

- The rail leads with an **at-a-glance panel** — rent, property, target move-in, deposit, reference — then the state-dependent controls. Restating the figures beside the controls means the money isn't off-screen at the moment of signing. It doesn't replace reading the document; it stops the commitment being invisible.
- All five mutually exclusive states live in the rail (sign, pay, escrow held + move-in clocks, processing, occupied). Each is a self-contained white panel; the disputed-move-in modal still teleports to body.
- **Print needs explicit handling.** The grid carries `print:block` and the rail `print:hidden` — otherwise the document prints squeezed into a grid column whose sibling no longer exists.
- The agreement body lost its `border + rounded-xl + bg-[#F7FCFC]` wrapper. It's the document text, so it sits directly on the sheet; boxing it inside the card that already frames it was a third nested border carrying no meaning. The parties block and signature block keep their insets — those are data, not prose.

## 6g-bis. The chat panel is a fixed-height flex column — mind what you put in it
`conversations/partials/chat-panel` is `flex flex-col h-full`: header, stepper, action bar, message list (`flex-1 overflow-y-auto`), composer. **The action bar is `flex-shrink-0`**, so anything tall placed in it takes the space from the messages and then overflows the panel — and because the clipping container has no scroll of its own, a tall action's buttons become unreachable rather than merely cramped. Two rules came out of that:
- **The bar carries `max-h-[55%] overflow-y-auto`.** A ceiling means no future action, however tall, can crush the thread — reading it is why anyone opened the screen.
- **Anything approaching full-panel height belongs in a teleported modal, not the bar.** The handover picker (~600px) is `x-teleport="body"` with a scrolling backdrop; it escapes the flex column entirely, and mobile gets a full-width sheet for free. Actions that are a row of buttons stay inline.

Long-lived cards in the bar are collapsible with a chevron in their header (`expanded`), defaulting open only when something is genuinely waiting on this viewer.

## 6h. Date/time picking — `<x-datetime-picker>` (July 2026)
`<input type="datetime-local">` is not usable here. Browsers render it as an unstyleable segmented control (`07 / 23 / 2026, --:-- --`) that ignores every token in this file, and — the reason that actually matters — it cannot show where the escalation deadline falls, which is the only fact that makes one handover slot better than another.

`resources/views/components/datetime-picker.blade.php`: a month grid beside a time-slot grid, Alpine + Tailwind, **no picker library**. §3 rules out third-party CSS systems, and a runtime CDN dependency has already bitten this app once (face-api on the verification wizard). The grid marks the review deadline, tints days past it amber, and disables anything before `min`.

Composition, as built for the handover scheduler: the status strip **is** the panel's header. Collapsed, it's a one-line tinted strip; open, the same element becomes the tinted head of a bordered white panel — calendar left, times right, actions in a `#F7FCFC` footer bar — so the deadline stays in view beside the calendar being chosen against. The days-left counter becomes a pill in that header but keeps tracking urgency (teal → red at ≤1 day) rather than becoming decorative.

Detail worth copying: **day markers sit under the cell, not inside it.** A dot or underline drawn inside a selected day has to fight the teal fill for contrast; a 2px rule beneath the button never does. Lead-in days from the previous month are rendered greyed rather than blank — an empty corner reads as a rendering fault, a dimmed `30` reads as a date — while the trailing row is left blank, because the lead-in exists to explain where the first week starts and the tail has nothing to explain.

Two traps worth knowing before reusing it:
- **The behaviour script lives in `public/js/datetime-picker.js`, not in an `@push('scripts')` block inside the component.** `ConversationController` returns the bare `chat-panel` partial on AJAX with no layout, so `@stack('scripts')` never renders — open Messages with no thread selected, click one, and a pushed script would never have run, leaving the component calling an undefined function. **Any component used inside an AJAX-swapped partial has this problem.** Load its JS from the owning page instead.
- **Dates are parsed as local midnight via `toDate()`, never `new Date(iso)`.** `new Date('2026-08-01')` is read as UTC and renders as July 31 for anyone west of Greenwich — which in a picker means the day you click isn't the day you get.

The default slot renders inside the component's Alpine scope, so caller buttons can bind straight to `value` / `date` / `time` / `label` (e.g. `:disabled="!value"`).

## 7. Components
- Border radius default: `rounded-2xl` (standard), `rounded-3xl` (hero sections only)
- Shadow style: `shadow-[0_1px_3px_rgba(15,23,42,0.06)]` on cards — a hairline lift, not a drop shadow. `shadow-lg` is reserved for floating UI (dropdowns, modals, tooltips) that must read as detached from the page. Property cards use the image-is-the-card pattern (no shadow wrapper).
- Button rules: `cursor-pointer` on all clickable elements; hover via `hover:brightness-95`, never layout-shifting scale. CTA buttons use `#FF8A65`. Standard buttons use `#2AA7A1` fill.
- Input/form rules: every input has a real `<label for>`, not placeholder-as-label
- **Search input recipe (standardized July 22, 2026):** every text search field — landlord Properties/Tenants/Units/Reservations, Conversations, Favorites, plus the pre-existing admin index pages — now shares one class string: `h-10 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] focus:bg-white transition-all duration-200`, with a `w-4 h-4`/`15×15` search icon in `text-[#94A3B8]` at `left-3.5`. Before this pass there were 4 different combinations in the wild (`border-[#64748B]/25` vs `border-[#E2E8F0]`, `focus:ring-1` vs `ring-2`, `h-10` vs `h-11` vs `py-2.5`, icon color `#64748B` vs `#94A3B8`). The one exception is the narrow conversation-sidebar search (`conversations/index.blade.php`), which keeps a smaller `text-[12px]`/`py-2` footprint for its tight column width but uses the same border/ring/icon colors. When adding a new search field, copy this recipe rather than approximating it.
- Icon set: Heroicons (outline/stroke), inline SVG only. **No emojis anywhere, ever.** Unicode checkmarks (✓) acceptable as plain text only.
- Touch targets: minimum 44x44px on interactive elements
- Card spec: `<x-card>` → `bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)]` (see §6)
- Stat cards: a tinted card per status (`bg-[#22C55E]/[0.07]` + matching `/25` border) with a solid circular icon badge in the status color, label and count beside it, a sub-line beneath. Neutral/total cards use the `#EEF8F8` + `#156F8C` teal family. Never purple or blue.
- Status color mapping — fixed across the app: Available `#22C55E`, Reserved `#FBBF24`, Occupied `#EF4444`, Maintenance `#94A3B8`. Text-on-white variants darken to `#15803D` / `#B45309` / `#DC2626` for contrast. Never use raw Tailwind `emerald-*`/`amber-*`/`red-*`/`slate-*` utilities — always the token hexes.
- **Chart.js color parity (bug found and fixed July 21, 2026):** the migration audit greps in §11b only catch raw color utilities in Blade — they can't see hex literals inside a `<script>` block's Chart.js config, so two dashboard charts drifted from the token palette independently of the CSS migration. `admin/dashboard.blade.php`'s line-chart legend swatches (`bg-[#22C55E]`, `bg-[#FBBF24]`) didn't match the actual `borderColor` values Chart.js was rendering (`#10b981`, `#f59e0b` — a different green/amber entirely), and its fill `backgroundColor` was a leftover `rgba(40,108,210,...)`, a blue with no token behind it at all. The user-distribution donut (dashboard) and the role-breakdown donut (`admin/users/index.blade.php`) both used raw `#a855f7` purple for "Admins" — the exact anti-pattern §10 bans in CSS, just written as a JS color literal instead — and that purple didn't even match its own HTML legend dot, which was reusing the Tenant teal. Fixed: every Chart.js `borderColor`/`backgroundColor`/dataset color is now the literal token hex, matched 1:1 against the Blade-side legend swatch it corresponds to; Admins now render as `#69D2C6` (aqua accent) instead of purple. **When auditing charts for palette compliance, read the `<script>` block too — hex literals in JS are invisible to a Blade-only grep.**
- **Deadline banners** (move-in escrow, July 22 2026) — a countdown is only ever shown to the party who can miss it. On Clock 1 the tenant sees a calm "Payment secured" note with **no** number, because a countdown there implies a deadline they can act on and the deadline is the landlord's; on Clock 2 they get a live day count that escalates from amber to red at ≤1 day, and after expiry states plainly that the deposit will be released rather than pretending the window is still open. Escalation is by tint only — never by size, motion, or an added icon. `agreements/show.blade.php` was converted from raw `amber-*`/`red-*`/`sky-*` Tailwind utilities to the token hexes (`#FBBF24`/`#EF4444`/`#EEF8F8`+`#156F8C`) on July 22, 2026, matching the rest of the app. **Still pending:** `admin/reservations/index.blade.php` has not had the same pass yet — check it before assuming the whole escrow surface is token-clean.
- **Landlord move-in turnover confirmation (July 22, 2026)** — "Mark keys turned over" (`landlord/reservations/index.blade.php`, both the table row and card view) starts Clock 2 and is effectively irreversible from the landlord's side, but shipped as a bare `<button type="submit">` with zero warning. Now gated behind the standard `data-confirm` → `x-confirm-modal` flow (see `public/js/modal-confirm.js`), with the message reading the live `config('rentals.move_in_confirmation_days')` value rather than a hardcoded day count, so the copy can't drift from the actual deadline logic in `Reservation::markKeysTurnedOver()`.
- **"Needs review" tab** (admin index pages) — a queue filter that layers on top of the status tabs rather than being a status itself, so it must explicitly suppress the active state on every status tab or two tabs highlight at once (see `admin/reservations/index`'s `$disputedActive` guard). Carries a red count pill only when the queue is non-empty; an empty queue shows no badge, not a zero.
- **Notification modal** (`x-confirm-modal`) — one global component covering four types, distinguished only by the icon ring and the confirm button fill: confirm (teal `#2AA7A1`, question mark), success (emerald `#22C55E`, check), warning (amber `#FBBF24`, triangle), error (red `#EF4444`, x-circle). Soft icon ring on a tinted circle, centered title over muted message, `max-w-[370px]` white `rounded-2xl` panel. Renders a single OK button unless an `onConfirm` callback is supplied, which turns it into a two-button confirmation. Every post-redirect flash message in the app surfaces here — there are no inline flash banners.

## 8. Motion
- Standard transition: `transition-all duration-200 ease-in-out` (200ms)
- **Modals** (both the auth modal and `x-confirm-modal` share this): backdrop fades in over 300ms with `backdrop-blur-sm`; the panel rises and scales from `opacity-0 scale-95 translate-y-4` on `cubic-bezier(0.34, 1.56, 0.64, 1)` — a slight overshoot so it lands with a bounce rather than a flat fade. Closing is quicker at 200ms `ease-in` and reverses more subtly (`scale-95 translate-y-2`). On the notification modal the icon ring scales in 100ms behind the panel, so it lands just after the panel settles — a small stagger that reads as deliberate instead of everything appearing at once.
- Use `transform`/`opacity` for animation, not `width`/`height`
- Respect `prefers-reduced-motion` — gate movement behind Tailwind's `motion-reduce:` variants so the opacity fade survives but scale/translate are neutralized
- Favorites: unfavorite triggers fade-out + DOM removal (not left sitting unfavorited)
- No orchestrated page-load animations — keep it fast and functional

## 9. Accessibility
Target: **WCAG 2.2 Level AA.** Swept across all ~110 Blade views in July 2026.
- Minimum contrast ratio: 4.5:1 for normal text (why `#2AA7A1` and `#69D2C6` are banned as foreground text on white)
- Focus state: visible focus ring on every interactive element
- Keyboard nav: tab order matches visual order
- Skip-to-main-content link as the first focusable element in every layout, targeting `id="main"` on `<main>`. Styled `sr-only focus:not-sr-only` so it only appears on keyboard focus, then as a teal pill pinned top-left.
- Alt text on meaningful images, `aria-label` on icon-only buttons
- Every form input needs a programmatic name — a `<label for>`/`id` pair, an enclosing `<label>`, or `aria-label` for placeholder-only search fields. A visible label that isn't wired to its input is a silent failure: it looks right and is invisible to screen readers.
- `[x-cloak] { display: none; }` in global CSS to prevent Alpine flash-of-unstyled-content

## 10. Anti-patterns (things to actively cut)
- No emojis as icons — SVG only
- No inline `<style>` blocks — Tailwind utilities only
- No custom CSS class systems (`abg-*` prefixes)
- No hardcoded hover hex values — `hover:brightness-95` only
- No `Storage::url()` on full external URLs (Cloudinary/Unsplash) — output `$media->media_url` directly
- No mixing container widths across pages
- No `<template x-if>` inside `<svg>` elements — use `x-show` on `<path>` instead
- No explicit `x-init="init()"` — Alpine.js v3 auto-invokes `init()`
- **No raw Tailwind color utilities** (`bg-gray-50`, `text-red-600`, `border-emerald-200`, `ring-indigo-500`, …). Always the token hexes. All ~1,100 uses were migrated in July 2026; a new one is drift.
- Joseph audit items: watch for `#1A1A2E` (wrong palette — 0 remain as of July 2026), unresolved git merge conflict markers, `<x-app-layout>` instead of `@extends`
- Off-palette tints that keep reappearing and are always wrong: `#EEF2F5` (use `#EEF8F8`), `#1A1A2E` (use `#1F2937`)

## 11. Self-Critique Checklist
- [ ] Is hierarchy carried by structure and spacing rather than by color or effects?
- [ ] Is color doing only one job — signalling status — with everything else neutral or teal?
- [ ] Responsive at 375px, 768px, 1024px, 1440px — no horizontal scroll
- [ ] Cards use `<x-card>` (or its exact class string), not an ad-hoc shadow/border combination
- [ ] No `backdrop-blur` except a modal backdrop or a panel over photography (§6); no raw `emerald-*`/`amber-*`/`red-*`/`slate-*` utilities
- [ ] Content doesn't hide behind fixed nav
- [ ] Page background is `#F7FCFC` — no gradient, no `bg-fixed`

## 11b. Migration audit (the greps that prove a page is converted)
Run from `resources/views`. Every one of these must return zero — they are the exact defect classes the July 2026 conversion produced or exposed:

```bash
grep -rn 'backdrop-blur-xl\|bg-white/70' . --include='*.blade.php'          # content-panel glass
grep -rhoE '\b(bg|text|border|ring)-(emerald|amber|red|slate|green|gray|blue|indigo|purple)-[0-9]+' . --include='*.blade.php'
grep -rnoE '\[#[0-9A-Fa-f]{6}\](/\[0\.[0-9]+\])?[0-9]' . --include='*.blade.php'   # malformed classes
grep -rn '1A1A2E\|EEF2F5' . --include='*.blade.php'                          # off-palette
grep -rhoE '\[#[0-9A-Fa-f]{6}\]/[0-9]+' . --include='*.blade.php' | grep -oE '/[0-9]+$' | sort -u
```

**The fourth grep catches out-of-scale opacity modifiers, and it found a live one (July 2026).** `bg-[#EF4444]/8` appeared in four places — three warning banners on `agreements/show` and one bubble in `admin/conversations/show`. `8` is **not** in Tailwind's default opacity scale (0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100), so no class was generated and every one of those panels rendered with a fully transparent background — red text and a border floating on white. Confirmed by grepping the built CSS: `EF4444\]\/10`, `\/25`, `\/30`, `\/5` are all emitted, `\/8` is not.

This is the same failure mode as the sed incident above and is *not* caught by the malformed-class grep, which only detects broken *syntax*. An opacity modifier can be perfectly well-formed and still produce nothing. Run the grep above and eyeball the value list against the scale; anything outside it is dead. Use `/[0.08]` bracket syntax if you genuinely need an off-scale value.

**The third grep is the one that matters most, and the lesson behind it.** The bulk migration was done with `sed`, and the substitution list put `bg-emerald-50` *before* `bg-emerald-500`. sed matched the shorter prefix, leaving a stray trailing digit: `bg-emerald-500` → `bg-[#22C55E]/[0.07]0`. That is not a valid Tailwind class, so every solid status fill silently rendered colorless — occupancy bars, legend dots, status pills — across 32 sites in 5 files. It shipped past a "no raw Tailwind colors remain" check, because that check could only detect *un*-migrated input, never *mis*-migrated output. **A validity check on what you produced is not optional; an absence check on what you replaced is not sufficient.** `php artisan view:cache` will not catch it either — a nonsense class name is still valid Blade. Only rendering the page does.

Order any future bulk substitution slash-opacity-variants first, then `\b`-anchored bases, so `-50` can never match inside `-500`.

## 12. Admin panel index-page pattern (established July 2026)
Applies to the admin review/moderation queues: Landlord Verifications, Property Verifications, Unit Approvals, Payments, Reports, Reviews, Conversations.
- Container: `max-w-[1600px] mx-auto` — the work-area width from §5. Previously mixed (`max-w-5xl`, `max-w-[1400px]`, `max-w-[1600px]`), then briefly over-corrected to `max-w-7xl` without `mx-auto`, which stranded ~320px of dead space on the right at 1920px.
- Header row: title + subtitle on the left; an optional "N awaiting review" coral/amber pill on the right when a pending count > 0.
- Stat summary: a `grid grid-cols-2 sm:grid-cols-4` (or fewer columns if fewer statuses) row of glass cards above the tabs, one per status plus a Total. Each card: small dot + uppercase label, then a large bold count in a status-tinted color (amber/emerald/red/teal family), clickable to that filter. Active filter gets `ring-2 ring-[#2AA7A1]`.
- Tabs: existing pill-tab pattern, now with a live count suffix per tab (`text-[11px]` in a muted tone).
- List body: dense multi-column tables were replaced with a single-card, `divide-y divide-[#E2E8F0]` row-list where each row is one full-row `<a>` (avatar/thumb, key fields with `text-[11px] uppercase text-[#94A3B8]` micro-labels, status badge, trailing chevron that shifts right on hover). Kept as a genuine `<table>` only where a page needs a row-level form action inline (Payments' Release button, Property Verifications' Approve/Reject — no detail page to link to yet).
- Empty states: icon in an `bg-[#EEF8F8]` circle with `text-[#2AA7A1]` icon (not gray), bold title + muted subtitle.
- Fixed straggling off-palette classes across these views: banned `#1A1A2E`, raw Tailwind `gray-*`/`blue-*`/`amber-*`/`emerald-*`/`slate-*` utilities were swapped for the DESIGN.md token set (`#1F2937`, `#64748B`, `#94A3B8`, `#E2E8F0`, `#F7FCFC`, `#EEF8F8`, plus the status colors `#FBBF24`/`#22C55E`/`#EF4444`/`#156F8C`/`#2AA7A1`).
- New controller convention for these pages: alongside the paginated/filtered query, also compute a `$counts` array (one query per status, cheap at current scale) and pass it to the view for the stat cards + tab badges. See `Admin\VerificationController`, `Admin\PropertyUnitController`, `Admin\ListingController`, `Admin\PaymentController`, `Admin\ReportController` for the pattern.

### 12a. Missing-container defect (found and fixed July 21, 2026)
`admin/verifications/show.blade.php` and `admin/reports/show.blade.php` had **no width cap on their root `@section('content')` div at all** — not "wrong width," genuinely absent. On a wide monitor behind the admin sidebar shell this rendered edge-to-edge instead of stopping at a reading-appropriate width. Both are detail pages inside a work area, so per §5's table they now get `max-w-5xl mx-auto`. When auditing a page for width issues, check that a `max-w-*` class exists at all before checking whether it's the *right* `max-w-*` — the migration audit greps in §11b only catch known-wrong values, not absence.
