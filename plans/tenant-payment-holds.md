# Tenant payment holds and duplicate-account alerts

**Status:** design approved by Axcee (Nov 15, 2026), with one change: a held tenant can still chat with the landlord of the held unit. Built Nov 15, 2026 (steps 1-6); full suite green; **pending Axcee's manual verification** (checklist at the bottom). Do not delete this plan until he has run it.
**Chosen approach:** A (a separate `tenant_holds` record; duplicates matched live against it, no stored fingerprint table).

## Why

A tenant who does not pay can sign up again under a new account and walk away from the debt. Landlords need a way to
flag non-payment, and the platform needs to notice when the same person comes back. The tenant must still be able to
settle the debt, otherwise the hold could never end.

## Goals

1. A landlord flags a tenant who has **overdue rent** on one of their tenancies. A reason is required.
2. An **admin confirms** the flag before it bites. No landlord can lock a tenant out alone.
3. While a hold is active the tenant can still sign in, see what they owe and pay. Everything else is locked.
4. The hold **lifts by itself** once the overdue rent is paid. A landlord or admin can also release it.
5. When a new account looks like someone on hold, the **admin is alerted** with the signals that matched. A person
   decides. Nothing is blocked automatically.

## Non-goals

- Automatic blocking of any sign-up.
- Collecting IDs from tenants, a stored fingerprint table, or scoring everyone who signs up.
- A permanent ban. Holds are tied to a debt.
- Native mobile screens. The API enforces the hold (HTTP 403, `code: account_on_hold`); the app screen is a follow-up.

## Anti-goals (a failure even if it "works")

- Locking out innocent roommates or housemates who share a phone, Wi-Fi or computer.
- A hold with no way for the tenant to pay, or to talk to the landlord they owe.
- One landlord's disputed bill taking a tenant offline without a second pair of eyes.
- The new person learning who they were matched to, or a landlord learning a stranger's details.

## How it works

### 1. Flag, confirm, hold

States of a hold: `pending_admin` -> `active` -> `released`, or `pending_admin` -> `rejected`.

| Step | Who | Rule |
|---|---|---|
| Flag | Landlord, from the tenant's page | Only for **their own** tenant, only while `RentLedger::summary()['overdueCount'] > 0` on that tenancy. Reason required (min 10 characters). One open hold per tenant per tenancy. Throttled. |
| Notice | System | Tenant is notified at once: "Your landlord reported unpaid rent. An admin will review it. Pay now to clear it." |
| Pay before review | Tenant | If the overdue rent is cleared while `pending_admin`, the hold closes itself as `rejected` (reason `paid`). |
| Confirm / reject | Admin, on a new Tenant holds page | Note required on reject. Confirm and release need the step-up password (`recent.password`). |
| Auto-release | System | When the tenancy's `overdueCount` reaches 0. Checked by a daily command, and lazily when the tenant opens the hold page. Also hooked after a payment is recorded if the codebase has one clean place for it. |
| Manual release | Landlord or admin | Reason recorded. |
| Dispute | Tenant, on the hold page | A note box. Saved on the hold (`tenant_note`) and the admin is notified. |

### 2. What a hold restricts

A new middleware alias `tenant.hold` (`EnsureNotOnHold`), next to `active`. It applies to tenant-facing web and API
routes only, never to landlord or admin routes.

- **Allowed:** the hold page, paying outstanding rent, notifications, logout, the dispute note, and **chat with the
  landlord of the held tenancy** (see below).
- **Blocked:** new reservations and inquiries, starting chats with anyone else, browsing-to-book actions. Blocked
  requests redirect to `/account/on-hold` (JSON: 403 `account_on_hold`).

**Chat exception (Axcee, Nov 15).** The tenant can read and send messages in the conversation(s) with the landlord who
holds an active hold on them, for that property and unit, so the two can sort out the payment. Rule: a conversation is
open to a held tenant only when `conversation.landlord_id` equals the `landlord_id` of one of their active or pending
holds. Conversations with every other landlord stay locked, and a held tenant cannot start new ones. The same applies
to the conversation list, `messages.store` and the mobile API. The payment-claim and concern shortcuts inside that chat
stay available. A hold created by an admin because of a duplicate (no landlord) allows no chat.
- `account_status` is **not** touched. `suspended` stays a hard lock; a hold is a separate record.

The exact allow-list is the builder's job, from `route:list`, but it must err on the side of letting the tenant pay.

### 3. Spotting a duplicate

`HoldMatcher` compares a person against tenants with an **active** hold. It runs on sign-up, on a new-device sign-in
(next to `NewDeviceDetector`), and when a landlord creates a walk-in tenant. It reads data we already keep: `users`
(name, `contact_number`) and `auth_events` (`device_id`, IP).

| Signal | How it is compared | Alerts alone? |
|---|---|---|
| Phone | Digits only, last 10 (so `0917…`, `+63917…` and `63917…` match) | **Yes** |
| Device cookie (`ah_device`) | Same id seen on the held tenant's sign-ins | **Yes** (admin reviews, so a shared laptop is only a false alarm, never a lockout) |
| Name | Lower-cased, accents and spacing removed | No |
| IP | Same address | No |
| Name **and** IP | Both | **Yes** |

IP alone and name alone **never** alert, because of shared Wi-Fi and common names like "Maria Santos". The rule is one
constant in `config/security.php` so it can be tuned.

### 4. The alert and what happens next

Each match writes a `hold_match_alerts` row (hold, new user, the signals that matched, status `open`, unique per
hold + user so it does not repeat) and an in-app alert to the admins listing the signals.

The **flagging landlord** only gets: "A possible duplicate account of {tenant} was found. An admin is reviewing it."
No details of the new person.

The admin can:
- **Dismiss** (not the same person). Stored, pruned after 90 days.
- **Put on hold too.** Creates a second hold on the new account, `active` at once, linked to the first by
  `source_hold_id`, and flagged by the admin. It lifts when the source hold is released.

Neither action ever tells the new person which hold they were matched against.

### 5. Data

| Table | Columns |
|---|---|
| `tenant_holds` | `hold_id`, `tenant_id`, `landlord_id` (nullable for admin-created), `reservation_id`, `amount_owed` (snapshot), `reason`, `status`, `source_hold_id` (nullable), `tenant_note`, `flagged_at`, `confirmed_by`, `confirmed_at`, `rejected_note`, `released_by`, `released_at`, `release_reason` (`paid`, `landlord`, `admin`, `source_released`), timestamps |
| `hold_match_alerts` | `alert_id`, `hold_id`, `user_id` (the new account), `signals` (JSON), `status` (`open`, `dismissed`, `held`), `reviewed_by`, `reviewed_at`, timestamps. Unique on (`hold_id`, `user_id`). |

No new personal data is collected. The match reads what accounts and sign-ins already store. Add one line to the
privacy text that unpaid-rent holds exist and that sign-up details may be compared against them.

### 6. Audit

Every step writes `AuditLog::record()`, so it joins the tamper-evident chain: `hold.flagged`, `hold.confirmed`,
`hold.rejected`, `hold.released`, `hold.duplicate_flagged`, `hold.duplicate_dismissed`, `hold.duplicate_held`.
Add them to `AuditLog::ACTION_LABELS`.

### 7. Screens

| Surface | What |
|---|---|
| Landlord tenant page | "Report unpaid rent" button (only while overdue) -> small reason form, then a status chip (Pending review / On hold). Mobile-first. |
| Tenant hold page `/account/on-hold` | Who reported, amount owed, Pay button, dispute box. Mobile-first, 375px. |
| Admin Tenant holds page | Tabs: Pending, Active, Duplicates, History. Confirm / Reject / Release / Dismiss / Put on hold. Desktop. |

All colours are design tokens (hex), per DESIGN.md.

## Approaches considered

| | Chosen? | Why |
|---|---|---|
| **A. Separate `tenant_holds` record, live matching** | **Yes** | Keeps reason, reviewer and landlord, doubles as the audit trail, adds no stored personal data. |
| B. New `account_status = 'on_hold'` | No | One status cannot carry the reason or reviewer, and every status check in the code would need the new value. |
| C. Stored identity fingerprint table | No | Stores more personal data (Data Privacy Act exposure) to catch people we already know about. |

## Calls I made (change any)

- Admin confirmation is required for every hold.
- The tenant is told when flagged and can clear it before review.
- A device-cookie match alerts on its own, because the admin reviews it.
- The flagging landlord gets no details about the new account.
- Dismissed alerts are pruned after 90 days. Holds are kept.
- A hold applies to the tenant role only. A user who is also a landlord keeps full landlord access.
- A hold releases itself when the tenancy is **Cancelled or Rejected** (reason `debt_dropped`). A **Completed** tenancy
  that still owes rent stays on hold, otherwise a tenant could clear a hold just by ending the tenancy while owing
  money (changed during the build). The landlord's "stop collecting" action does not exist in the code yet; when it
  does, `HoldService::releaseIfPaid` needs a branch for it.
- A tenant who owes two landlords can have two holds; any active hold restricts them, and each lifts on its own debt.
- Walk-in tenants (inactive accounts that cannot sign in) can be flagged like any other. The restriction has no effect
  until they activate an account, and the matcher still covers them when a landlord creates a walk-in record.

## Left to the builder

The route allow-list, the exact hook for "after a payment is recorded", the reminder to admins for holds pending too
long, wording of the notices, and the test layout.

## Testing

Feature tests for: flag rules (own tenant only, overdue only, reason, duplicates, throttle), admin confirm with
step-up, the restriction allow-list (web and API), pay-while-held, auto-release, and the matcher's rules including the
false-positive cases (same IP only, same common name only, phone in three formats). Then a manual checklist for
Axcee, as with the security work.

## Docs to update when built

`context/SCHEMA.md` (two tables), `ARCHITECTURE.md` (a "Payment holds" section), `RULES.md` (the never-auto-block and
no-detail-to-landlord rules), `CHANGELOG.md`, and the privacy text.

---

# Implementation plan

Design above is approved. Build order matters only for step 1 (the contract). Steps 2-4 touch different files and can
run side by side with sub-agents; step 5's three screens also split cleanly.

## Calls made while planning

- **Block-list, not allow-list.** The `tenant.hold` middleware goes on the routes that start something new
  (`reservations.store`, viewing requests, `conversations.store`, new inquiries; the same on the API). Everything on an
  existing tenancy (ledger, paying rent, payment claims, receipts, lease, notifications) stays open, so a held tenant
  can never be stopped from paying. Chat is handled by a rule on `conversations.show`, `messages.store` and the
  conversation list, not by the middleware.
- Auto-release is a daily command plus a check when the tenant opens `/account/on-hold`. A payment hook comes only if
  `RentLedger`/payment recording has one clean place; otherwise skip it.

## Steps

| # | Step | Owns (files) | Depends on |
|---|---|---|---|
| 1 | **Contract.** Migrations for `tenant_holds` and `hold_match_alerts`; models `TenantHold`, `HoldMatchAlert` (status constants, relations); `User::holds()` and `User::isOnHold()`; `config/security.php` `hold_match` block; `AuditLog::ACTION_LABELS` entries; `SCHEMA.md` | `database/migrations/*`, `app/Models/*`, `config/security.php`, `context/SCHEMA.md` | none |
| 2 | **HoldService.** `flag()`, `confirm()`, `reject()`, `release()`, `dispute()`, `releaseIfPaid()` (uses `RentLedger::summary()['overdueCount']`); notifications; `AuditLog::record()` on every step; command `holds:release-paid` (daily) and the stop-collecting / tenancy-ended release | `app/Services/Holds/HoldService.php`, `app/Console/Commands/ReleasePaidHolds.php`, `routes/console.php` | 1 |
| 3 | **Enforcement.** `EnsureNotOnHold` + alias `tenant.hold`; apply to the new-commitment routes (web + API, 403 `account_on_hold`); the chat rule (conversation open to a held tenant only with a landlord who holds a pending/active hold on them) in `ConversationController`, `MessageController` and the API twins | `app/Http/Middleware/EnsureNotOnHold.php`, `bootstrap/app.php`, `routes/web.php`, `routes/api.php`, the conversation/message controllers | 1 |
| 4 | **HoldMatcher.** Signals and rule from the design (phone last-10, device, name, IP; name+IP together); runs on web and API sign-up, new-device sign-in (beside `NewDeviceDetector`), and walk-in creation; writes `hold_match_alerts` (unique per hold + user), notifies admins and the flagging landlord (no details); prunes dismissed alerts after 90 days | `app/Services/Holds/HoldMatcher.php`, `RegisteredUserController`, `Api\AuthController`, `RecordAuthEvents`, landlord `TenantController::store`, `routes/console.php` | 1 |
| 5a | **Landlord screen.** "Report unpaid rent" on the tenant page (overdue only), reason form, status chip, release button. Mobile-first | `Landlord/TenantHoldController.php`, `landlord/tenants/show` partial, routes | 2 |
| 5b | **Tenant screen** `/account/on-hold`: who reported, amount, Pay button, dispute box, link to the chat. 375px first | `Account/OnHoldController.php`, `resources/views/account/on-hold.blade.php`, routes | 2, 3 |
| 5c | **Admin screen** "Tenant holds": tabs Pending / Active / Duplicates / History; confirm, reject, release (step-up password), dismiss, put-on-hold-too; sidebar entry | `Admin/TenantHoldController.php`, `resources/views/admin/tenant-holds/*`, routes, sidebar | 2, 4 |
| 6 | **Tests, docs, checklist.** Feature tests listed under Testing above; `ARCHITECTURE.md`, `RULES.md`, `CHANGELOG.md`, privacy text; manual checklist for Axcee | `tests/Feature/Holds/*`, `context/*`, `resources/views/legal/privacy.blade.php` | all |

Sub-agent split: step 1 alone (me), then steps 2 / 3 / 4 as three agents, then 5a / 5b / 5c as three agents, then step 6
alone. Each agent gets only its own files, per CLAUDE.md. `routes/web.php` is touched by 3, 5a, 5b and 5c, so route
lines are added by me after the agents finish, to avoid edit collisions.

## Manual checklist (written in step 6, run by Axcee)

1. Landlord flags an overdue tenant. Tenant sees the notice. Admin sees it pending.
2. Admin confirms (step-up password). Tenant can still pay and chat with that landlord, but cannot start a new
   reservation or chat with anyone else.
3. Tenant pays. The hold lifts by itself.
4. Sign up a second account with the held tenant's phone in another format. Admin gets an alert. Roommate case: same IP
   only gives no alert.
5. Admin dismisses one alert and puts another account on hold.

## Manual checklist, as built (dev data: tenant Rhea #10 owes P9,500 on reservation 5 with landlord Maria #2)
1. Landlord Maria: open Rhea's contract panel, **Report unpaid rent**, reason under 10 characters is refused, a real reason is accepted, chip shows Pending review.
2. Rhea (tenant): sees a notice. While pending she can start a new chat (nothing restricted yet).
3. Admin: Tenant holds > Pending > **Confirm** (password prompt). Chip becomes On hold.
4. Rhea: new reservation / new chat / viewing request redirect to `/account/on-hold`. She can open the chat with Maria and send a message; a chat with any other landlord is refused. Pay now opens her tenancy. Write a note to the admin.
5. Pay the overdue month (or have Maria record it). Hold lifts itself (open `/account/on-hold` or run `php artisan holds:release-paid`).
6. Duplicates: with a hold active, sign up a new account with Rhea's phone in another format. Admin gets a bell alert and a Possible duplicates row. Same IP only gives no alert. Dismiss one; Put on hold too on another.

