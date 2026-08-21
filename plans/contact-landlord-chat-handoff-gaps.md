# Fix Contact Landlord → chat handoff gaps

> **Superseded:** the "always create the first message" guard fix below (§1) was replaced by the
> unconditional inquiry-summary-card creation in
> [`embed-inquiry-summary-card.md`](embed-inquiry-summary-card.md). The Details auto-expand fix (§2)
> is unaffected and still stands.

## Context

The tenant-facing "Contact Landlord" modal (`resources/views/properties/show.blade.php`) lets a
tenant type an optional message before submitting an inquiry. That text is expected to show up as
the first bubble in the landlord's conversation thread (`resources/views/conversations/partials/chat-panel.blade.php`),
which is where the landlord reviews the inquiry and hits Accept & negotiate / Reject.

Investigating both ends of this flow turned up two confirmed problems, and the user chose to fix
both:

1. **API path silently drops the message.** `Api\Tenant\ReservationController::store()` computes a
   `$firstMessage` fallback (`message` if present, else `remarks`) but the `if` guard that decides
   whether to create the chat `Message` only checks `$request->filled('message')`. A client that
   submits `remarks` but not `message` gets the text saved onto `Reservation.remarks`, yet **no
   chat bubble is ever created** — the text silently never reaches the conversation the landlord
   reads. (The web modal path, `Tenant\ReservationController::store()`, does not have this bug —
   it already reliably creates the bubble when `message` is filled.)

2. **Landlord has no context on a fresh inquiry without an extra click.** In `chat-panel.blade.php`,
   the chat header only shows the tenant's name/email/phone for landlords (property/unit info is
   only shown in the tenant's header variant, lines 43-51). Property thumbnail, move-in/move-out
   dates, and any original message live in the "Details" panel, which starts collapsed
   (`x-data="{ detailsOpen: false }"`, line 30) for every role and every status. So a landlord
   opening a brand-new `Inquiry`-stage conversation — exactly like the screenshot — sees only a
   name, "hello", and Accept & negotiate/Reject, with no visible unit or move-in info unless they
   remember to click "Details" first.

Out of scope (explicitly declined): making the web modal path (`Tenant\ReservationController::store()`)
also populate `Reservation.remarks`. That path already gets the message into the chat correctly;
`remarks` staying null there is a separate, non-blocking inconsistency the user does not want
touched right now.

## Changes

### 1. Fix the API dead-code guard
**File:** `app/Http/Controllers/Api/Tenant/ReservationController.php` (around lines 129-136)

Changed the guard to key off the already-computed `$firstMessage` fallback instead of re-checking
only `message`:

```php
// Optional first message (accept either key for client convenience)
$firstMessage = $request->input('message', $request->input('remarks'));
if (filled($firstMessage)) {
    $conversation->messages()->create([
        'sender_id' => $tenantId,
        'message'   => $firstMessage,
    ]);
}
```

Now a client submitting either `message` or `remarks` (or both) reliably gets a first chat bubble;
submitting neither still creates no bubble, same as before.

### 2. Auto-expand Details for a fresh landlord inquiry
**File:** `resources/views/conversations/partials/chat-panel.blade.php` (line 30)

Defaulted `detailsOpen` to `true` when the viewer is the landlord and the reservation is still at
the `Inquiry` stage — the one moment the landlord needs the property/unit/dates context up front to
decide Accept & negotiate vs. Reject, before they've had a chance to click anything:

```php
<div class="flex flex-col h-full" id="chat-panel-root" data-conversation-id="{{ $conversation->conversation_id }}"
    x-data="{ detailsOpen: {{ ($isLandlord && $rentalStatus === 'Inquiry') ? 'true' : 'false' }} }">
```

`$isLandlord` and `$rentalStatus` are already computed at the top of the file (lines 4, 3), so no
new query is needed. Once the landlord advances the stage or the page reloads, this only affects
the initial state — the toggle still works normally afterward, and tenants/other stages keep the
current collapsed-by-default behavior.

## Verification

- **API guard fix:** submit a reservation via the API endpoint (`POST /api/reservations` or
  equivalent tenant endpoint) with `remarks` set and `message` omitted; confirm a `Message` row is
  created and appears in the conversation thread. Repeat with `message` set instead, and with both
  omitted (should still create no message) to confirm no regression.
- **Details auto-expand:** as a tenant, submit a fresh inquiry through the "Contact Landlord" modal
  with a move-in date set; log in as the landlord and open the resulting conversation — the Details
  panel (property thumbnail + move-in/move-out dates) should already be expanded without clicking
  "Details". Advance the reservation past `Inquiry` (Accept & negotiate) and reload — Details should
  no longer force-open.
