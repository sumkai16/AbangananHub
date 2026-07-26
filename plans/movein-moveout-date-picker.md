# Custom move-in / move-out date picker

## Context
All move-in/move-out fields (`properties/show.blade.php` reserve form ×2 (desktop+mobile), `landlord/tenants/walk-in/create.blade.php`, `landlord/tenancies/show.blade.php` end-tenancy) use bare `<input type="date">`. Browsers render that as an unstyleable native control, which is exactly the problem DESIGN.md §6h already documents and rejected for `datetime-local` on the handover scheduler. The user supplied a prototype ("2c — Dark, refined") with a dark navy calendar dropdown, circular teal "today"/selected marker, and Clear/Today footer actions, and wants it applied to every move-in/move-out field, including the tenancy end-date field.

The dark navy panel (`#0F172A`) is currently a footer-only exception in DESIGN.md §3. Per the user's decision, this picker becomes a **second, deliberate, documented exception** — the trigger input stays on the app's normal light palette; only the popover panel goes dark.

## Component design

**`resources/views/components/date-picker.blade.php`** — new Alpine+Tailwind component, modeled on the existing `<x-datetime-picker>` (`resources/views/components/datetime-picker.blade.php`) but date-only, circular-cell, dark-panel.

Props:
```
name, id, value (ISO string|null), min, max (static ISO strings|null),
minExpr, maxExpr, disabledExpr (raw Alpine expression strings, for reactive cross-field rules),
placeholder (default 'mm / dd / yyyy')
```

Root markup:
```html
<div x-data="datePicker({ date: @js($value), min: @js($min), max: @js($max) })"
     x-modelable="date"
     @if($minExpr) x-effect="min = ({{ $minExpr }}) || null" @endif
     @if($maxExpr) x-effect="max = ({{ $maxExpr }}) || null" @endif
     @if($disabledExpr) x-effect="fieldDisabled = !!({{ $disabledExpr }})" @endif
     @click.outside="open = false" x-on:keydown.escape.window="open = false"
     {{ $attributes }} class="relative">
    <input type="hidden" name="{{ $name }}" :value="date">
    <button type="button" @click="toggle()" :disabled="fieldDisabled" ...trigger styling (light, matches existing input recipe)...>
        <span x-text="date ? formatted : '{{ $placeholder }}'"></span>
    </button>
    <div x-show="open" x-cloak ...dark navy popover... >
        <!-- month nav, weekday row, day grid (circular cells), Clear / Today footer -->
    </div>
</div>
```

**Why `x-modelable` + `x-effect` instead of copying `<input type="date">`'s plain `x-model`/`:min`/`:disabled`:** the reserve form's move-out field needs a *live* `min`/`disabled` derived from the sibling move-in field (`:min="moveIn || minMoveIn" :disabled="!moveIn"` today), and the two mobile/desktop duplicates share `onMoveInChange()`/`canSubmit` from the surrounding Alpine root. `x-modelable="date"` lets the caller just write `x-model="moveIn"` on the component tag, bridging the component's internal `date` to the parent's existing variable — no new wiring in the parent's `x-data`. `minExpr`/`disabledExpr` are raw Alpine expression strings (e.g. `min-expr="moveIn || minMoveIn"`) evaluated via `x-effect` on the root element, which — because it sits in the same DOM scope as the ancestor `x-data` — can read the parent's reactive `moveIn` even though the component declares its own `x-data`. This keeps 100% of the existing `onMoveInChange()`/`canSubmit()`/`minMoveIn`/`maxMoveIn` logic in `properties/show.blade.php` untouched; only the `<input type="date">` tags are swapped for `<x-date-picker>` tags with equivalent props.

**Dropped: native `required`/`min`/`max` HTML validation.** Neither `canSubmit` (properties/show) nor the walk-in/tenancy forms rely on the browser's native constraint validation for their actual gating — `canSubmit` already disables the submit button and swaps its label off pure JS state, and server-side `Form Request` rules (`StoreReservationRequest`, `StoreWalkInTenantRequest`) are the real enforcement, unchanged. This matches `<x-datetime-picker>`, which never had native validation either.

**Behaviour script:** `public/js/date-picker.js`, a `datePicker(config)` Alpine factory — same shape as `public/js/datetime-picker.js` (`toDate()` parses ISO as local midnight, never `new Date(iso)`, per the documented UTC-off-by-one trap). Adds `max` handling (`datetime-picker.js` only had `min`), a `clear()` and `today()` action, and an `open`/`toggle()` popover state machine. Loaded via a literal `<script src="{{ asset('js/date-picker.js') }}">` tag on each consuming page — matching the existing convention (not `@push('scripts')`, so the pattern stays uniform even though only the conversations page actually has the AJAX-partial constraint that requires it).

**Visual spec (the new dark exception):**
- Trigger: unchanged from today's light recipe (`rounded-xl bg-white border border-[#E2E8F0] focus-within:border-[#2AA7A1]/60 ...`) — reads as a normal AbangananHub input.
- Popover panel: `bg-[#0F172A]` (reuses the existing footer token, not a new hex), `rounded-2xl`, `ring-1 ring-white/10`, `shadow-2xl`, positioned `absolute` below the trigger, `z-30` (existing dropdown tier, one above the mobile sticky bar at `z-20`).
- Weekday row / adjacent-month days: `text-white/40` / `text-white/25`.
- Day cells: `rounded-full` (circular — the deliberate visual difference from `<x-datetime-picker>`'s square cells). Selected = `bg-[#2AA7A1] text-white`. Today (unselected) = `ring-1 ring-[#2AA7A1] text-white`. Disabled (outside min/max) = `text-white/20 cursor-not-allowed`. Hover (selectable, unselected) = `hover:bg-white/10`.
- Footer row: `Clear` (`text-white/50 hover:text-white`) left, `Today` (`text-[#2AA7A1] font-semibold hover:brightness-110`) right — exactly the prototype.
- No new color tokens introduced — everything is `#0F172A` (existing footer exception) + `#2AA7A1` (existing secondary teal) + white-alpha tints, all already-locked palette entries.

**DESIGN.md update:** extend §3's footer-exception row and add a short entry under a new "§6l Date picker" (or folded into §6h) documenting: the dark popover is a second, deliberate use of `#0F172A`, scoped to `<x-date-picker>`'s dropdown only; circular cells vs. `<x-datetime-picker>`'s square ones is intentional (date-only vs. date+time widgets read as distinct controls); reuses the `x-modelable`/`x-effect` cross-field pattern description above so a future picker copies it instead of re-deriving it.

## Call-site changes

Replace the native `<input type="date">` in all 7 spots with `<x-date-picker>`, keeping each surrounding `<label>`/error markup as-is (just swap the input tag and drop the now-redundant `:min`/:max`/`:required`/`disabled:*` input classes):

| File | Field | Component props |
|---|---|---|
| `properties/show.blade.php` (~1079, desktop) | `target_move_in_date` | `x-model="moveIn" x-on:change="onMoveInChange()" min-expr="minMoveIn" max-expr="maxMoveIn"` |
| same (~1096) | `target_move_out_date` | `x-model="moveOut" min-expr="moveIn || minMoveIn" disabled-expr="!moveIn"` |
| same (~1294, mobile `m_move_in`) | `target_move_in_date` | identical to desktop |
| same (~1306, mobile `m_move_out`) | `target_move_out_date` | identical to desktop |
| `landlord/tenants/walk-in/create.blade.php` (~383) | `move_in_date` | `x-model="moveIn"` |
| same (~393) | `move_out_date` | `min-expr="moveIn"` (no `x-model` needed — original had none either, purely `old()`-seeded) |
| `landlord/tenancies/show.blade.php` (~333, "End tenancy") | `move_out_date` | standalone (no ancestor `x-data`): `value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"` |

`properties/show.blade.php`'s two `<script>` includes and `landlord/tenants/walk-in/create.blade.php` / `landlord/tenancies/show.blade.php` each need one `<script src="{{ asset('js/date-picker.js') }}">` added (once per page, not per field).

## Verification
- Manual: user will test in-browser per his standing preference — build the picker and call out the checklist rather than writing automated tests.
- Checklist to hand off: open each of the 4 pages; move-in picker respects `min=today`/`max=+1yr`; picking move-in before an existing move-out clears move-out (reserve form only); move-out picker stays disabled until move-in is chosen and its `min` tracks the chosen move-in date; Clear/Today both work and close the popover; click-outside and Escape close it; submitting each form still round-trips correctly through the unchanged `StoreReservationRequest`/`StoreWalkInTenantRequest`/`TenancyController::end` validation (server error messages still render under the field via the existing `@error` blocks); mobile bottom-sheet duplicate behaves identically to desktop.
- Confirmed: installed Alpine is `3.15.12` (`node_modules/alpinejs/package.json`), well past the 3.11 minimum for `x-modelable` — no dependency bump needed.
