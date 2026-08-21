# Embed an inquiry summary card as the first chat message

> **Status:** the full card design below (thumbnail, dates, rent/deposit breakdown, quoted note) is
> the current implementation — it renders in the thread alongside, not instead of, the sticky terms
> strip added in [`sticky-terms-strip-redesign.md`](sticky-terms-strip-redesign.md) (an earlier pass
> briefly shrunk it to a minimal chip, but the mockup confirmed the full card is what's wanted; that
> shrink was reverted). The `is_inquiry_summary` column, migration, and controller changes below are
> unaffected either way.

## Context

The earlier fix in this session made sure a tenant's typed note reliably becomes the first plain
chat bubble in the landlord's conversation thread. Looking at the actual result (screenshot), that's
not what's wanted: the plain-text bubble only carries the free-text note ("test") — none of the
structured info the tenant saw and confirmed in the "Contact Landlord" modal (move-in date, stay
length/move-out date, monthly rent, security deposit, due-at-move-in) makes it into the thread. The
landlord has to open the collapsible "Details" panel to see any of that, and even then it only shows
the property card + dates, not the rent/deposit breakdown.

The ask: the very first message in a freshly-created inquiry conversation should be a rich,
"embedded" card — not a plain bubble — carrying the same info the tenant reviewed in the modal, plus
their optional note. This replaces the current plain-text-only first message.

## Data already available (no new snapshot fields needed)

- `Reservation.target_move_in_date` / `target_move_out_date` — already stored (`app/Models/Reservation.php`).
- `PropertyUnit.rental_fee` / `security_deposit` — real, required columns (`app/Models/PropertyUnit.php`;
  `security_deposit` added in `2026_07_27_000000_add_unit_type_floor_security_deposit_to_property_units.php`).
  "Due at move-in" is `rental_fee + security_deposit`, computed the same way the modal already does
  it client-side (`resources/views/properties/show.blade.php` ~lines 1122-1136).
- Property title/thumbnail — same source the existing Details-panel property card already uses
  (`chat-panel.blade.php` ~lines 323-331).

All of this is rendered live from `$reservation` / `$conversation->unit` / `$conversation->property`
at display time (same approach the existing Details panel already uses) — no snapshot columns, so no
risk of the card going stale if a landlord edits pricing later is any different from the existing
Details panel's behavior today.

## Changes

### 1. New column: `messages.is_inquiry_summary`
Migration `database/migrations/2026_08_21_000001_add_is_inquiry_summary_to_messages_table.php`
(boolean, default `false`, after `is_system`) — mirrors how `is_system` was added.
`app/Models/Message.php`: added `is_inquiry_summary` to `$fillable` and `$casts` (boolean).

### 2. Always create the summary message when a reservation is created
**Files:** `app/Http/Controllers/Tenant/ReservationController.php` and
`app/Http/Controllers/Api/Tenant/ReservationController.php`.

Replaced the "only create a message if `message` was filled" logic with an **unconditional** create
— the card is worth showing even if the tenant left the note blank:

```php
$conversation->messages()->create([
    'sender_id'          => Auth::id(), // or $tenantId in the API controller
    'message'             => $request->message ?? '', // API: keeps the message-or-remarks fallback
    'is_inquiry_summary'  => true,
]);
```

This supersedes the `filled($firstMessage)` guard fix made earlier in this session (documented in
`plans/contact-landlord-chat-handoff-gaps.md`) — that fix was correct for the plain-message behavior
but is now replaced by "always create the summary row."

### 3. Render the card in the chat thread
**File:** `resources/views/conversations/partials/chat-panel.blade.php`, in the `@foreach ($msgs as
$i => $message)` loop.

New branch, checked before `is_system`, rendering a centered card (thumbnail + property title + unit
label, move-in/move-out dates or "Open-ended", monthly rent / security deposit / due-at-move-in
breakdown, and the tenant's note only if non-blank).

Also updated the `$endsRun`/`$startsRun` run-grouping checks to treat `is_inquiry_summary` as a
run-break, the same way `is_system` already is — otherwise the bubble right after the card would
incorrectly skip rendering its avatar/sender name.

**Out of scope, intentionally:** no changes to the realtime broadcast (`MessageSent`) or the JS
live-append path in `conversations/index.blade.php` — this message is only ever created synchronously
alongside the `Reservation`, before anyone can be live-viewing the (not-yet-existing) conversation, so
the append-via-websocket path never needs to render this type.

### 4. Sidebar preview fallback text
**File:** `resources/views/conversations/index.blade.php`. Added a `$previewText` computed value: if
the latest message `is_inquiry_summary` and the tenant left no note, falls back to "Sent an inquiry"
instead of showing a blank preview line.

### 5. API parity
**File:** `app/Http/Resources/MessageResource.php` — added `is_inquiry_summary` alongside the
existing `is_system` field, so the mobile client gets the flag even though building a mobile card UI
is not part of this change.

**Not touched:** `resources/views/admin/conversations/show.blade.php` — its read-only thread loop
already renders `is_system` messages oddly (no special-casing there today); the summary message will
render there as a plain bubble with the tenant's note (or blank). Acceptable for this admin-only,
read-only secondary surface — consistent with existing behavior, not a regression.

## Verification

- Submit a fresh inquiry through the "Contact Landlord" modal (with and without a typed note, with
  and without a move-in date) and confirm, as the landlord, the first item in the thread is the card
  with the correct property/unit/dates/rent/deposit numbers, and the note only appears when typed.
- Confirm a normal follow-up chat message sent afterward (via the input box) still renders as a
  regular bubble with correct avatar grouping (no missing avatar on the message right after the card).
- Check the conversation list sidebar preview text for a conversation whose only message is a
  no-note inquiry summary.
- Re-run the same scenarios through whatever exercises the API path (`Api\Tenant\ReservationController::store()`),
  if there's a way to hit it directly, to confirm parity with the web path.
