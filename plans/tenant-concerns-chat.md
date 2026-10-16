# Tenant chat + concern cards (after move-in)

**Status:** implemented Oct 14 2026 (363 tests green). Pending Axcee's manual verification in the browser (checklist at the bottom).

## Context
Once a tenant moves in, the only chat with the landlord is the old **inquiry thread**: the deal chat with the stage stepper, agreement buttons and "Mark as Resolved". It is the wrong place for "the faucet is leaking":
- The thread is about the deal, and landlords often mark it Resolved after move-in, so it drops out of the Active tab.
- Walk-in tenants have no thread at all, but they also can't log in (random password, inactive), so they are out of scope.
- The landlord has no way to track which reported problems are still unfixed.

**Goal:** every logged-in current tenant gets a separate **tenancy chat** with their landlord. Inside it, a **Report a concern** button posts a card (category, description, up to 3 photos) whose status the landlord moves Open → In progress → Fixed. The tenant can reopen a Fixed one.

Decisions already made with Axcee: chat + concern cards, photos (max 3), inbox tab plus shortcuts on the tenancy pages.

## Data model (one migration + backfill)

| Change | Detail |
|---|---|
| `conversations.type` | `VARCHAR(20)` default `'inquiry'`. Values: `inquiry`, `tenancy`. |
| `conversations.reservation_id` | Nullable FK → `reservations`, `nullOnDelete`. Set only on tenancy threads and points at the tenancy it is for. |
| Unique key | Drop `unique(tenant_id, landlord_id, property_id)` and add `unique(tenant_id, landlord_id, property_id, type)`. A tenant renting the same property again later reuses their tenancy thread, and `reservation_id` moves to the new tenancy. |
| New table `tenant_concerns` | `concern_id` PK, `conversation_id` FK, `reservation_id` FK, `tenant_id` FK users, `category` VARCHAR(30), `description` TEXT, `photos` JSON nullable (`[{url, public_id}]`, max 3), `status` VARCHAR(20) default `'Open'` (`Open`, `In Progress`, `Fixed`), `fixed_at` nullable, timestamps. Index `(reservation_id, status)`. |
| `messages.concern_id` | Nullable FK → `tenant_concerns`, `nullOnDelete`. Set on the concern card message and on the status-change system messages. |
| Backfill | In the same migration: create a tenancy thread for every `Occupied` reservation whose tenant has `is_walk_in = false`. |

Categories are a constant on the model: Plumbing, Electrical, Water supply, Appliance, Internet / Wi-Fi, Door & lock, Pests, Noise / neighbors, Other.

## Backend

1. **`Conversation`** (`app/Models/Conversation.php`)
   - Add `TYPE_INQUIRY` and `TYPE_TENANCY` constants, `isTenancy()`, `scopeInquiries()` and `scopeTenancies()`.
   - Add `reservation()` (belongsTo) and `concerns()` (hasMany).
   - Keep the `messages()` ordering from today's fix.
2. **Inquiry lookups must ignore tenancy threads.** Add `->inquiries()` to every place that finds a thread by tenant, landlord and property. Otherwise an inquiry could attach to the tenancy thread:
   - `ConversationController::store` (`firstOrCreate`)
   - `Tenant\ReservationController::store` (line ~72)
   - `Api\ConversationController` (~119)
   - `Api\Tenant\ReservationController` (~98)
3. **Thread creation:** `Reservation::openTenancyThread(): ?Conversation`.
   - Returns null for walk-in tenants.
   - Otherwise it runs `firstOrCreate` on (tenant, landlord, property, `type=tenancy`), sets `reservation_id`, `unit_id` and `status=Open`, and returns the thread.
   - It is called from `ReservationObserver::updated` when `rental_status` changes to `Occupied`. That one hook covers tenant confirmation, the deadline timer (`ProcessMoveInDeadlines`) and an admin release. It must run before the observer's existing `! conversation_id` early return.
   - When `rental_status` becomes `Completed`, the observer sets the tenancy thread's status to `Resolved`, which makes it read-only.
4. **`TenantConcern` model** with `CATEGORIES` and `STATUSES` constants, a `photos` array cast, and the relations.
   - `markStatus(string $status, User $actor)` updates the status and sets or clears `fixed_at`.
   - It then posts a system message with `concern_id` set, e.g. *"Maria marked "Leaking faucet" as fixed."*, and notifies the other party through `Notification::notify()`.
   - Add a small `Conversation::postSystemMessage()` mirroring `Reservation::postSystemMessage()`, including its broadcast try/catch.
5. **`TenantConcernController`** (new) and **`TenantConcernPolicy`**:
   - `POST /conversations/{conversation}/concerns`
     - Only the tenant of a tenancy thread whose reservation is `Occupied` may post.
     - Validated by a `StoreTenantConcernRequest`: category in the list, description ≤ 1000 characters, `photos.*` image, ≤ 5 MB each, max 3.
     - Uploads the photos, creates the concern plus its card message (`concern_id` set, text = description), broadcasts `MessageSent`, and notifies the landlord ("New concern: Plumbing").
   - `PATCH /concerns/{concern}/status`
     - The landlord can set `In Progress` or `Fixed`.
     - The tenant can only reopen a `Fixed` concern back to `Open`.
     - Not allowed once the tenancy has ended.
6. **Photo upload seam:** a new `App\Services\ImageUploader` service. It has one method, `upload(UploadedFile $file, string $folder): array{url, public_id}`, which wraps the existing `cloudinary()->uploadApi()->upload()` pattern from `PropertyController.php:345`. It's bound in the container so tests can swap in a fake that never calls Cloudinary. The folder is `abanganan/concerns`.
7. **Opening the chat from other pages:** `GET /tenancy/{reservation}/chat` calls `openTenancyThread()` and then redirects to `conversations.index?box=tenancy&active={id}`. The policy allows both the reservation's tenant and its landlord. Adding `?concern=new` opens the concern sheet straight away.
8. **Sending is blocked on an ended tenancy thread.** `MessageController::store` already refuses Cancelled threads. It will also refuse a tenancy thread whose status is `Resolved`.
9. **`MessageSent::broadcastWith`** adds `concern_id` to its payload.

## Inbox and chat UI (mobile-first, 375px first)

Files: `conversations/index.blade.php`, `conversations/partials/chat-panel.blade.php`, and a new `conversations/partials/_concern-card.blade.php` and `_concern-sheet.blade.php`.

- **Box switch** above the status tabs, a 2-segment control:
  - Landlord: **Inquiries | Tenants**
  - Tenant: **Inquiries | My home**, shown only if they have a tenancy thread.
  - Driven by `?box=` and defaulting to `inquiries`. Each segment shows its own unread count, and the existing status tabs and counts are filtered by box (`ConversationController::index`).
  - On the Tenants box the tabs read Active / Unread / Ended (Ended = `Resolved`), and Cancelled is hidden.
- **List rows for tenancy threads** show the tenant or landlord name, the unit and the last message, plus an "N open" coral chip when concerns are Open or In progress.
- **The chat panel branches on `$conversation->isTenancy()`:**
  - **Header:** the other party, unit and "Tenant since {move-in date}".
  - **Hidden here:** the stage stepper, action bar, viewing card, move-in clock and "Mark as Resolved".
  - **Open-concerns strip:** "2 open concerns", which scrolls to the oldest open card.
  - **Ended tenancy:** the composer is replaced by "This tenancy ended on {date}. The chat is read-only."
- **Concern card** (rendered where `$message->concern_id` is set, styled like the inquiry summary card):
  - Category, status pill (Open = amber, In progress = navy tint, Fixed = green), description, photo thumbnails (tap → lightbox), and "Reported {time}".
  - The landlord sees **Mark in progress** and **Mark fixed** buttons. The tenant sees **Not fixed yet** on a Fixed card.
- **Report a concern** (tenant only) is a button beside the composer.
  - It opens a bottom sheet on phones and a centered modal at `sm+`, with a category chip grid, a description, and up to 3 photo pickers with previews.
  - The form posts multipart via `fetch`, and on success calls the existing `refreshPanel()`.
- **Live updates:** in `wireEcho()`'s `.MessageSent` listener, a payload with `concern_id` calls `refreshPanel()` because the card is role-dependent. Plain messages keep `appendMessage()`.

## Shortcuts
- **Tenant `tenant/tenancy/show.blade.php`:** the "Message" button goes to `/tenancy/{reservation}/chat` instead of the inquiry thread. A new **Report a concern** button goes to the same route with `?concern=new`.
- **Landlord `landlord/tenants/index.blade.php`** (both the mobile and desktop row variants):
  - The chat icon goes to `/tenancy/{reservation}/chat` for non-walk-in tenants.
  - An "N open" chip appears when concerns are open. `TenantController` eager-loads a `withCount` of open concerns.

## Seed / docs
- **`DemoSeeder`:** give one current tenant (e.g. Mylene) a tenancy thread with 3 messages, one Open Plumbing concern and one Fixed Electrical concern. No photos, so seeding never calls Cloudinary.
- **Docs:** `context/SCHEMA.md` (new columns and table), `context/CHANGELOG.md` entry, and an ARCHITECTURE.md Key Decisions row ("separate tenancy thread instead of reusing the inquiry thread; unique key widened by `type`").
- **Plan copy:** save this plan as `plans/tenant-concerns-chat.md` once plan mode ends.

## Out of scope (later)
Walk-in tenants (they can't log in), the `/api/v1` mobile endpoints, a landlord dashboard "open concerns" tile, and moving post-move-in system messages (rent and deposit events) out of the inquiry thread.

## Verification
**Feature tests:** `tests/Feature/TenantConcernsTest.php`, with a fake `ImageUploader` and `DatabaseTransactions` plus `CreatesMarketplaceFixtures`, like the existing tests.
1. Moving a reservation to Occupied creates exactly one tenancy thread. A walk-in tenant gets none.
2. An inquiry by the same tenant on the same property still uses or creates the **inquiry** thread, not the tenancy one.
3. The tenant reports a concern with 2 photos: a concern row, a card message with `concern_id`, and a landlord notification are created.
4. Permissions:
   - The landlord can't report a concern.
   - The tenant can't mark one In Progress.
   - The tenant can reopen a Fixed one.
   - Another landlord gets 403.
5. A status change posts a system message and notifies the other party.
6. Completing the tenancy makes the thread Resolved, and `MessageController::store` then refuses to send.
7. `/tenancy/{reservation}/chat` redirects both parties into the right thread, and a stranger gets 403.

**Run:** `php artisan test` (full suite) and `npm run build`.

**Manual pass (Axcee), at 375px first, then desktop, then dark mode:**
1. Log in as a current tenant → My rental → Report a concern with a photo.
2. As the landlord in another browser → Messages → Tenants: the card appears live, with the "1 open" chip on the row.
3. Mark it in progress, then fixed: the tenant sees the system message live.
4. As the tenant, tap "Not fixed yet".
5. End the tenancy: the composer turns read-only.
