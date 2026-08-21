# Sticky terms strip + slim progress bar for the inquiry chat panel

> **Correction:** §5 below (shrinking the inline inquiry card to a minimal "Inquiry sent" chip) was
> reverted after the user pointed at the full card in the mockup screenshot — the elaborate card
> (thumbnail, dates, rent/deposit breakdown, quoted note) renders in the thread as a persistent record
> *alongside* the always-visible strip, not replaced by it. Everything else below still stands.

## Context

After the inquiry-summary-card work landed, the user made a design mockup ("2a": combines an
active-inquiry layout with a sticky terms strip and a slim progress bar in place of the circle
stepper) showing a different, more consolidated layout for `chat-panel.blade.php`. Instead of a
circle-node stepper + a collapsible "Details" toggle that hides the property/rent info, the mockup
puts property + unit + the full rent/deposit/due-at-move-in breakdown + a stage pill into one
always-visible horizontal strip right under the header, followed by a slim linear progress bar with
stage labels. The primary CTA ("Accept & negotiate") and the message "Send" button both turn coral
(`#FF8A65`, the locked CTA coral). The inline inquiry card in the thread itself shrinks to a minimal
"Inquiry sent" label + a small chip with the tenant's note — since the property/rent info it used to
carry now lives permanently in the strip above, showing it twice would be redundant.

Confirmed with the user:
- The collapsible "Details" toggle stays, but now only holds move-in/move-out dates and the original
  message/remarks — property + rent/deposit move out into the new strip.
- This strip + slim bar layout replaces the old stepper for **every non-terminal stage**, not just
  Inquiry. The existing Cancelled/Rejected/Completed banner is untouched.
- Both "Accept & negotiate" and the message "Send" button recolor to coral `#FF8A65`. No other
  stage's action button (Send agreement, Confirm & send agreement, etc.) is touched — out of scope.
- The inline inquiry card becomes just "Inquiry sent" + a note chip, and renders nothing at all in
  the thread when the tenant left no note (the strip's stage pill already conveys "an inquiry exists").

This also makes the "auto-expand Details for a fresh landlord inquiry" fix from earlier this session
obsolete — the info it was surfacing (property/rent) now lives in the always-visible strip, so Details
can go back to always starting collapsed.

## Changes

**File:** `resources/views/conversations/partials/chat-panel.blade.php`

### 1. Top `@php` block (~lines 1-28)
Add a `$stageLabel` computed the same way `index.blade.php`'s sidebar `$rowLabel` already is (reuse
that exact mapping): `'Inquiry' => 'Inquiry'`, `'Under Negotiation' => 'Negotiation'`, `'Pending
Rental Agreement' => 'Agreement'`, `'Rental Agreement Signed' => $hasSettledPayment ? 'Paid' :
'Signed'`, `'Occupied' => 'Occupied'`. Used for the strip's stage pill.

### 2. New sticky terms strip
Replace the current always-visible stepper block (~lines 84-102) with, in order:
- **The strip** (new): thumbnail (same source as the old Details property card) + property title +
  unit label on the left; Monthly rent / Security deposit / Due at move-in as three label-over-value
  columns (same numbers/source as the inquiry-card work: `$conversation->unit->rental_fee` /
  `security_deposit`); the `$stageLabel` pill on the right. Rendered whenever `$reservation &&
  !$isTerminal` (terminal keeps today's red banner, unchanged).
- **The slim progress bar** (redesigned `_stage-stepper.blade.php`): replace the circle-node markup
  with a single rounded bar (`bg-[#E2E8F0]`) containing an inner filled bar (`bg-[#2AA7A1]`), width =
  `($currentStageIndex + 1) / count($stageLabels) * 100%`. Six uppercase labels below, same
  flex-1/text-right alignment convention the current labels already use; current stage bold+teal,
  others gray. Since `admin/conversations/show.blade.php` includes this same partial, its read-only
  view inherits the slim bar automatically — no separate change needed there, matching the partial's
  existing "renders the same thing from the same rules" intent.

### 3. Action bar recolor
`bg-[#1F2937]` → `bg-[#FF8A65]` (with a matching `hover:brightness-95`) on the "Accept & negotiate"
button only (~line 119). No other action-bar button changes.

### 4. Details panel trim
Remove the property-card block (~lines 323-331) from the collapsible Details section — it's now
redundant with the strip. Keep the dates block and the `remarks`/"Original message" block as-is.

Revert the `x-data="{ detailsOpen: ... }"` on the panel root (~line 30) back to always
`detailsOpen: false` — the auto-expand-for-landlord-on-Inquiry fix from earlier this session is no
longer needed now that the strip always shows the property/rent context.

### 5. Inline inquiry card → minimal chip
Replace the current card branch (the `is_inquiry_summary` block added in the last change) with:
```blade
@if($message->is_inquiry_summary)
    @if(trim($message->message ?? '') !== '')
        <div class="self-stretch flex justify-center my-2 px-2">
            <div class="text-center">
                <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-1">Inquiry sent</p>
                <div class="inline-block bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 text-[12px] text-[#1F2937]">
                    {{ $message->message }}
                </div>
            </div>
        </div>
    @endif
@elseif($message->is_system)
    ...
```
When blank, nothing renders for that row (the `is_inquiry_summary` message row still exists for
run-grouping purposes — the existing `$next->is_inquiry_summary`/`$prev->is_inquiry_summary` checks
added in the last change already account for it whether or not it's visually rendered).

### 6. Message input Send button recolor
Same coral treatment as Accept & negotiate, on the "Send" button (~line 535).

### 7. Header copy (landlord only)
**File:** `resources/views/conversations/index.blade.php` (~lines 17, 20). Landlord title
`'Inquiries / Messages'` → `'Inquiries'`, subtitle → `'Respond to tenant inquiries across your
properties.'` — matches the mockup header exactly. Tenant's title/subtitle (`'Messages'` / `'Manage
your active inquiries...'`) is untouched.

## Verification

- Open a landlord's Inquiry-stage conversation: strip shows correct property/unit/rent/deposit/due
  numbers and an "Inquiry" pill; slim bar shows a small filled segment at the far left; Accept &
  negotiate is coral; Details (collapsed by default) opens to show only dates + original message.
- Advance a reservation through Negotiation/Agreement/Signed/Paid/Occupied and confirm the strip's
  pill and slim-bar fill update correctly at each stage, and terminal states (Reject/Cancel) still
  show the existing red banner, not the strip.
- Submit an inquiry with a note and one without: confirm the thread shows "Inquiry sent" + chip only
  when a note was typed, and nothing extra when it wasn't.
- Confirm the message Send button is coral and still posts normally.
- Check the admin read-only conversation view picks up the slim bar automatically and still renders
  correctly.
