---
name: impeccable
description: >
  Pre-ship design-polish check for AbangananHub's Blade/Tailwind views. Run this before calling
  any UI change, new page, or component "done" — it catches generic "AI slop" patterns (purple/
  indigo gradients, Inter-everywhere with no hierarchy, rounded-corner-everything, stacked
  shadows, generic icon-library icons, templated card grids with no intent) AND drift from this
  project's own locked design system in context/DESIGN.md (Navy #060D26 / Coral #FF8A66 palette,
  where coral is and isn't allowed as text, the one CTA treatment, hover-state rules, the
  Inter-default/serif-opt-in typography rule, mobile-first-for-Tenant/Landlord vs
  desktop-oriented-Admin). Use it whenever the user asks to "polish," "review the design of,"
  "make this look less AI-generated / less generic," or after writing/editing any file under
  resources/views/** that touches visual markup — don't wait to be asked explicitly, a finished
  UI task isn't finished until this pass has run.
---

# Impeccable

A checklist pass over Blade/Tailwind markup, run *before* reporting a UI change as complete —
not a hook, not automatic enforcement. You (Claude) invoke this deliberately and walk the diff or
new markup against both lists below. If something fails, fix it before calling the task done; if
you deliberately keep something that looks like a violation, say why in one line (this file's own
"documented exception" pattern from DESIGN.md, e.g. §6g-bis, is the right model for that).

Read `context/DESIGN.md` for full detail before a non-trivial pass — this file is the checklist,
DESIGN.md is the source of truth when a rule needs more context than fits here. For the "why do
AI UIs look the same" background behind Part 1, see
`.claude/skills/AI Design Slop Why It Happens & How to Kill It.md` in this repo.

## Part 1 — Generic AI-slop patterns to kill

These are defaults that language models reach for when a prompt under-specifies the design —
not bad taste, just the statistical average of every SaaS template scraped into training data.
AbangananHub already has an opinionated system, so none of these should ever appear here:

| Red flag | What to do instead |
|---|---|
| Purple/indigo gradients, `bg-indigo-*`, `from-purple-* to-indigo-*` | This app has no purple in its palette at all — navy/coral only. Any purple is a straight bug. |
| Full-bleed hero gradient as a default choice | Only `about.blade.php` and the browse hero (§6i) get the brand gradient. A new page doesn't get one just because "hero pages have gradients." |
| Rounded corners on every element ("rounded to oblivion") | This app's actual radius vocabulary is narrow: `rounded-2xl` on cards (§6), `rounded-xl` on icon boxes, sharp/angular on the verification camera surface (§6f, deliberately *not* rounded). Don't default every div to `rounded-lg` out of habit. |
| Shadow stacking / heavy drop shadows on everything | The one card shadow is `shadow-[0_1px_3px_rgba(15,23,42,0.06)]` — barely-there. A new shadow value is a smell; check §6 before inventing one. |
| Glassmorphism (`backdrop-blur` + translucent panel on a flat background) | Explicitly retired app-wide (§6, July 2026). The only two surviving `backdrop-blur` uses are modal backdrops and panels over photography — never a content card. |
| Generic icon-library look (a random Lucide/Heroicons/Feather icon dropped in because it's the default in a snippet) | This project hand-rolls inline SVGs and has a name-keyed `<x-amenity-icon>` component (§6e). Match the existing icon weight/style already in `resources/views/components/*`, don't import a new icon set. |
| Templated evenly-spaced widget/stat-tile grid with no purpose-built structure | §0 calls this out by name as the *fourth* AI default to watch for (generic admin-template Tailwind), on top of the three classic ones. Dense, purpose-built data views beat boxes of identical cards — see the actual admin dashboard patterns already in the codebase before adding a new grid. |
| Inter (or Roboto/Arial/system-ui) used for literally everything with no weight/size hierarchy | Inter is correct here (it's the locked body font) — the slop version is using it flat, same weight everywhere. Use the type scale (§4: 12/14/16/20/24/32/48) and the weight rules (headings Inter 600, body 400, labels/buttons 500) to build real hierarchy instead of relying on a font swap to do it. |
| "Mathematically perfect but emotionally cold" spacing — correct 4px grid, no intentionality | Follow the spacing scale (§5) for the grid math, but check whether the specific page has an established, more deliberate pattern first (§6c/6d/6e/6f each solve a different layout problem on purpose — don't flatten a page into the generic card-grid default when it has a specific job). |
| Zero micro-interactions / no hover or focus state | Every interactive element needs the app's actual hover system (§3's Sept 2026 interaction rules below), not silence. |

## Part 2 — AbangananHub design-system compliance

Pulled from `context/DESIGN.md`; check new/changed markup against these before shipping. Numbers
in brackets are the DESIGN.md section to read for the full reasoning if something looks off.

**Color** [§3]
- Coral `#FF8A66` is fills/borders/rings/badges/large-text-on-dark-ground *only*. It must never be
  foreground text on a light background at any size (fails WCAG AA, ~2.31:1) — that job belongs to
  `#B35A3D` (deep coral text). If you see `text-[#FF8A66]` on a white/light card, that's a bug, not
  a style choice — the one exception is the "Hub" logotype letter (WCAG exempts logotype text).
- One CTA treatment app-wide: coral fill (`bg-[#FF8A66]`) + navy text (`text-[#060D26]`) via
  `<x-primary-button>`. Don't invent a second "primary button" look.
- Ledger/table price columns (rent ledger, payments, receipts) stay navy, not coral — coral marks
  exactly one prominent display price per page, not every repeated figure in a dense table.
- Hover: coral fills get the named `hover:bg-[#E96F4F]`, not `brightness-95`. Everything else
  (outline buttons, badges, icon-only utility buttons) uses `hover:brightness-95`. Navy
  interactive text/icons/borders (nav links, dropdown icons, outline-button borders) transition to
  coral on hover — plain body text near a link does not. Transitions are `duration-200`/`duration-300`.
- No new hex values without checking §3/§10 first — this app has a locked palette and (per §3) a
  banned-hex list of retired identities (old teal/gold values). A hex not in the table is either a
  regression or needs to go through Axcee, not get invented ad hoc.

**Typography** [§4]
- Headings render in Inter by default (`font-semibold` via the weight bump), *not* a serif — the
  "every heading gets DM Serif Display" pattern was tried and explicitly reverted app-wide. DM
  Serif Display is an opt-in utility (`.font-heading`/`.font-display`) for the specific pages that
  already use it deliberately (marketing/hero headings) — don't apply it to a new page "to make it
  feel more premium" without that being a deliberate call.
- If a page does opt into `.font-heading`/`.font-display`, don't stack a `font-bold`/`font-black`
  weight class on it — DM Serif Display ships one 400 weight only; a heavier class browser-fakes a
  bold. Use size/color for hierarchy on serif headings instead.
- Peso figures in tables/ledgers stay on Inter with `tabular-nums`, even on a page that otherwise
  uses the serif — a serif's proportional figures misalign a column of amounts.

**Layout & structure** [§5, §6]
- Every `max-w-*` page container needs `mx-auto` beside it — a capped-but-uncentered container
  strands all its slack on one side (this shipped as a real bug 15 times).
- Pick the right container by *shell*, not role: `max-w-[1400px]` for no-sidebar (public/tenant/
  auth), `max-w-[1600px]` for sidebar work areas (admin/landlord), narrower `max-w-4xl`/`max-w-5xl`
  for single-column detail/form pages inside a work area.
- Cards are `bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)]`
  via `<x-card>` — don't hand-roll a different card look.
- Page-title headers sit bare on the background, never wrapped in a card, unless the page is a
  documented exception (§6b lists them) — check before adding a new one.

**Device priority** [§0b — this is the one most likely to get skipped]
- Tenant and Landlord surfaces: design at 375px first, then scale up with `lg:`. Before adding the
  desktop variant, check the layout actually works at 375px — don't design at desktop width and
  squeeze down.
- Any data table on a Tenant/Landlord page needs the `hidden lg:block` table + `lg:hidden` stacked
  `@foreach` card-list pair (established pattern, e.g. `tenant/reservations/index.blade.php`) — a
  bare horizontal-scroll table is a fallback, not the mobile design.
- Admin (`resources/views/admin/**`) is the deliberate exception — desktop-oriented, dense tables,
  wide multi-column layouts. Don't force an admin screen through a mobile-first redesign; that's
  solving a problem this surface doesn't have.
- Any element whose visibility/position depends on Alpine state (`:class`, `x-show`, or otherwise)
  needs `x-cloak`, or it flashes in its pre-Alpine state on load — this bit the admin/landlord
  sidebar for exactly this reason.

**Guardrails already established elsewhere in RULES.md**
- No custom CSS class systems (`abg-*`), no inline `<style>` blocks — pure Tailwind only.
- `tailwind.config.js`'s `colors.brand.*` tokens are **not** used by any current view (every view
  uses raw bracket-hex like `bg-[#060D26]`) — don't assume `bg-brand-navy` works, and don't
  silently start using the named tokens in one new file while everything else stays bracket-hex;
  that's a two-system split, not a fix. If token migration is ever wanted, it's a deliberate
  repo-wide pass, not something to start incidentally.

## How to actually run this

1. After writing or editing markup in `resources/views/**` (or before telling the user a UI task
   is done), scan the diff against both tables above.
2. For anything that trips a check, either fix it or, if it's a real documented exception (like
   the verification camera's non-rounded surface, or the shutter button's off-palette color),
   say so in one line rather than silently leaving it ambiguous.
3. If the change touches Tenant or Landlord surfaces, confirm the 375px layout was actually
   considered, not just the desktop one scaled down — see §0b.
4. Don't run this as a substitute for opening the page in a browser when the task calls for visual
   verification (per this project's `test-before-commit` habit) — this is a static-markup check, not
   a rendered-output check.
