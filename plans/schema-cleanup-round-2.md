# Schema cleanup, round 2: five work packages, one `migrate` at the end

## Context
Step 1 of the schema proposal is done and uncommitted (3 indexes, 7 dead `property_units` columns,
`SCHEMA.md` rewrite, `SchemaDocTest`). Axcee wants the remaining work implemented first and
`php artisan migrate` run once at the end. Three read-only code surveys (Oct 18 2026) changed the
cost picture, so this plan covers the **worthwhile set** Axcee chose, not the full proposal.

Decisions already made by Axcee: scope = worthwhile set; dev database only (no real data
elsewhere), so each migration moves data and drops the old columns in one go, with a working
`down()`.

**Deferred on purpose** (pure tidiness, large blast radius): the `reservation_leases` /
`reservation_move_ins` split (about 40 call sites, 4 query scopes in `ProcessMoveInDeadlines`,
`ReservationObserver::wasChanged`, `ApiResource::attr()` silently dropping proxied fields, raw
`DB::table('reservations')` fixtures) and the `payment_escrow` / `payment_voids` splits (about 60
call sites, the webhook, the mobile `PaymentResource`). Revisit separately.

## Work packages (one migration each, dated `2026_10_18_000002` … `000006`)

### WP1 — `reservations.rental_status` → ENUM  (`…000002`)
- Values: Inquiry, Under Negotiation, Pending Rental Agreement, Rental Agreement Signed,
  **Reserved** (walk-in future-dated; used in code, missing from the docs), Occupied, Completed,
  Rejected, Cancelled. Default 'Inquiry', NOT NULL.
- Raw `ALTER TABLE … MODIFY`, guarded `DB::getDriverName() === 'mysql'` (existing pattern in
  `2026_10_13_000000_replace_condominium_with_dormitory_property_type`). `up()` first selects
  DISTINCT values and throws with the offender named if any value is outside the list. `down()`
  returns it to `varchar(255)`.
- Add `Reservation::STATUSES` (next to `TERMINAL_STATUSES`, `app/Models/Reservation.php:26`).
- Fix `tests/Feature/Payments/RecordPaymentTest.php:446`: it loops over a fake status `'Approved'`
  that a strict ENUM rejects.
- Not in scope (note in CHANGELOG): `Admin\ReservationController::STATUSES` and the two mobile
  API status lists omit `Reserved` / `Completed`.

### WP2 — `messages.kind`  (`…000003`)
- `kind` ENUM('user','system','inquiry_summary','concern'), NOT NULL, default 'user'.
  Backfill order: `is_system` → system; `is_inquiry_summary` → inquiry_summary; `concern_id`
  not null → concern; else user. Then drop `is_system`, `is_inquiry_summary`. `down()` rebuilds both
  flags from `kind`. `concern_id` stays (a system line about a concern is `kind=system` with
  `concern_id` set). No combination ever had both flags true.
- `Message` (`app/Models/Message.php`): `$fillable` swaps the two flags for `kind`; add read
  accessors `is_system` / `is_inquiry_summary` (bool, derived from `kind`) so the Blade views,
  `_bubble.blade.php`, `Events/MessageSent.php` and the chat JS keep reading the same names.
- Writers switched to `kind`: `Conversation::postSystemMessage` (`Conversation.php:52`),
  `Reservation::postSystemMessage` (`Reservation.php:~981`), `Tenant\ReservationController:112`,
  `Api\Tenant\ReservationController:134`, `TenantConcernController:36`, `TenantConcern.php:103`,
  `DemoSeeder.php:355,468,473,487`.
- `Http/Resources/MessageResource.php`: it uses `ApiResource::attr()`, which only sees real
  attributes, so read the accessors explicitly. Keep `is_system`, `is_inquiry_summary`; **add**
  `kind` and `concern_id` (mobile currently cannot tell a concern card from a plain message).
  `Events/MessageSent.php:68-71`: add `is_inquiry_summary` and `kind` to the broadcast.
- Tests asserting `is_system` columns: `MoveInDeadlinesTest.php:557`, `TenantReconcileTest.php:265`,
  `TenantConcernsTest.php:183` → assert `kind`.

### WP3 — media  (`…000004`)  [fork A]
- `unit_media.cloudinary_public_id` varchar(255) null. Backfill by parsing Cloudinary URLs
  (`…/upload/v123/abanganan/units/x.jpg` → `abanganan/units/x`); non-Cloudinary URLs stay null.
- Drop `property_media.unit_id` (drop FK first). Enable timestamps on `PropertyMedia`
  (`$timestamps = false` today though the columns exist). Remove `unit_id` from `$fillable`, the
  `unit()` relation (no callers) and `PropertyMediaResource.php:12`.
- Store the id at all six unit upload sites: `Api\Landlord\UnitWriteController:80-99,161-180`,
  `Landlord\PropertyUnitController:97-116,229-248`. Add `UnitMedia::destroyFromCloudinary()`
  (stored id, else the legacy URL-guess, `resource_type=video` when `media_type=Video`) and use it
  at the four deletion sites (`UnitWriteController:202-206,236-241`,
  `PropertyUnitController:278-282,324`). Fixes unit videos never being deleted.
- Not in scope: deleting a whole property does not remove unit media from Cloudinary (note it).

### WP4 — `users` splits  (`…000005`)  [fork B]
New tables (custom PKs per repo convention, FKs cascade):
- `social_accounts` (`social_account_id`, `user_id`, `provider`, `provider_id`, timestamps;
  UNIQUE `(provider, provider_id)`, UNIQUE `(user_id, provider)`).
- `user_payout_accounts` (`user_id` PK/FK, `gcash_number`, `gcash_account_name`, timestamps).
- `user_devices` (`device_id`, `user_id`, `expo_push_token` UNIQUE, timestamps).
Backfill from `users`, then drop `users.provider`, `provider_id` (and its unique index),
`expo_push_token`, `gcash_number`, `gcash_account_name`. `down()` copies back (first account /
device per user).
- `User` model: relations `socialAccounts()`, `payoutAccount()`, `devices()`; remove the five from
  `$fillable`; `hasPayoutDestination()` (`User.php:288`) reads the relation; add
  `savePayoutAccount(?string, ?string)` (both blank deletes the row) used by
  `Landlord\ProfileController:211` and `Api\Landlord\ProfileController:65`.
- Views: `admin/payouts/index.blade.php:38`, `landlord/profile/show.blade.php:197-198`,
  `landlord/profile/edit.blade.php:131-144` read `payoutAccount`; eager-load it in
  `Admin\PayoutController` (no N+1).
- `Auth\SocialiteController::resolveSocialUser` (`:111,126`): look up `SocialAccount` first, then
  link by email by **adding a row** (today `forceFill` overwrites an existing account's provider).
  Linking policy itself is unchanged.
- Push: `Api\ProfileController:95-98` becomes `UserDevice::updateOrCreate(['expo_push_token' => …],
  ['user_id' => …])` (a token that changes hands moves to the new user).
  `Services\ExpoPushNotifier` sends to every device of the user and deletes a device row on
  `DeviceNotRegistered`. `Api\AuthController` logout accepts an optional `expo_push_token` and
  deletes that device (today a logged-out phone keeps receiving pushes).
- **Leak fix**: `Http/Resources/UserResource.php:16-17,24-25` stops emitting `provider`,
  `provider_id` and `gcash_*` for every nested user (conversations, messages, reviews, reports,
  payments). The own-profile endpoints emit `gcash_number` / `gcash_account_name` flat (same keys
  as today, so the mobile app keeps working) only when `payoutAccount` is loaded.
- Seeders: `LandlordSeeder:79-80`, `DemoSeeder:454-455`; drop the pointless tenant GCash values in
  `TenantSeeder:126-127`.

### WP5 — `payouts` table  (`…000006`)
- `payouts` (`payout_id`, `landlord_id` FK cascade, `amount` decimal(10,2), `gcash_number`,
  `gcash_account_name` (snapshot at send time), `payout_reference`, `paid_out_by` FK set null,
  `paid_out_at`, timestamps).
- `payments`: add `payout_id` (FK `payouts`, `restrictOnDelete`) and `payout_owed_at` (timestamp),
  index `(payout_owed_at, payout_id)`. Drop `payout_status` (and its index), `paid_out_at`,
  `paid_out_by` (FK first), `payout_reference`.
- Backfill: `payout_owed_at = COALESCE(released_at, paid_at)` for rows that had a `payout_status`.
  `Paid Out` rows grouped by `(landlord via reservation→property, payout_reference, paid_out_by,
  paid_out_at to the second)` become one `payouts` row each (amount = sum; GCash snapshot = the
  landlord's current values, since history was never stored). `down()` restores the old columns
  from the payout row.
- Derived state (no stored status): pending = `payout_owed_at` not null and `payout_id` null; paid
  out = `payout_id` not null. Add `Payment::scopePendingPayout()` / `scopePaidOut()` and a derived
  `payout_status` accessor so Blade, `PaymentResource` (mobile contract keeps `payout_status`,
  `paid_out_at`, `payout_reference`) and tests that *read* it keep working.
- Writers of "Pending Payout" now set `payout_owed_at = now()`: `Reservation.php:136`,
  `ProcessMoveInDeadlines.php:290`, `Admin\PaymentController:78`, `PayMongoWebhookController:75`,
  `Concerns\ReconcilesPaymongoCheckout:61`.
- `Admin\PayoutController::markPaidOut` (`:62-95`), already inside `DB::transaction` +
  `lockForUpdate`: create the `Payout` (GCash snapshot from the landlord's `payoutAccount`), set
  `payout_id` on the locked payments, audit metadata gains `payout_id`. The "recently paid out"
  list becomes `Payout` rows.
- Consumers to rewrite from `where('payout_status', …)`: `Admin\PayoutController:23,39-41`,
  `Landlord\PayoutController:30-37`, `Api\Landlord\PayoutController:25-33`,
  `Landlord\DashboardController:66-69`, `ProfileController:157-159` (deletion guard),
  `Landlord\PaymentController:367` (void guard), `Payment::isPendingPayout/isPaidOut/canBeVoided`
  (`Payment.php:184-212`), Blade `admin/payouts/index` and `landlord/payouts/index`.
- Test fixtures: teach `tests/Support/CreatesPaymentFixtures::makePayment` to translate a
  `payout_status` override into `payout_owed_at` / a `Payout` row so the ~10 tests that set it
  need little change. Direct assertions on the dropped columns in `PaymentAccessAndStateTest`,
  `PayMongoWebhookTest`, `TenantReconcileTest`, `MoveInDeadlinesTest`, `TenantSafetyTest`,
  `RecordPaymentTest` (incl. the smuggled-field test at `:489-505`) are updated.

## How the work is split (per CLAUDE.md › Agents)
Contract: each fork owns its files and **does not edit `context/*.md`**; it reports the exact
SCHEMA.md / CHANGELOG changes instead. I apply all doc edits to avoid collisions.
- Fork A: WP3 (media). Fork B: WP4 (users). Both touch disjoint files.
- Me: WP1, WP2, WP5 (they share `Reservation.php`, `Message`, payment fixtures, so one head holds
  them) plus all docs.
- Neither fork runs `migrate`.

## Order
1. Write all migrations, code, tests and doc updates. **Nothing is migrated until the end.**
2. Run `php artisan migrate` on the dev DB, then `DB_DATABASE=abanganan_hub_test php artisan migrate`.
3. Run the suite, fix, then roll back all five migrations and migrate again (the up/down/up cycle
   that caught the error 1553 trap last time).
4. Docs: `context/SCHEMA.md` (new tables, changed columns, a Migration traps line per package),
   `ARCHITECTURE.md` §6 rows for payouts and push devices, `CHANGELOG.md` entry. Copy this plan to
   `plans/schema-cleanup-round-2.md`.
5. Do not commit until Axcee has read the result. Nothing from step 1 or the SCHEMA.md rewrite is
   committed yet. Suggested commits: (1) docs + `SchemaDocTest`, (2) step-1 migrations + code,
   then one commit per work package. Commits are authored as Axcee only, no co-author line.

## New tests
- `SchemaDocTest` (existing) must pass against the new schema: it is the doc gate.
- `MessageKindTest`: each writer produces the right `kind`; accessors; `MessageResource` keys.
- `UserSplitTest`: `savePayoutAccount` save/clear, `hasPayoutDestination`, device add / token
  reassign / delete on `DeviceNotRegistered` (Http::fake), social link adds a row and never
  overwrites, `UserResource` never emits `gcash_*` / `provider*` when nested.
- `PayoutBatchTest`: `markPaidOut` creates one payout, sums the amount, snapshots GCash, sets
  `payout_id` on every locked payment, 409 on an empty set, audit row; derived `payout_status`;
  deletion guard and void guard on the new columns.
- `UnitMediaTest`: upload stores the public id (Cloudinary faked), deletion uses it and passes
  `resource_type=video` for videos.
- A migration test per backfill is overkill; instead verify them by hand (below).

## Verification
- `php artisan migrate`, `migrate:rollback --step=7`, `migrate` again: no error 1553, no data loss
  on the dev rows (spot-check counts: 25 users with GCash → 25 `user_payout_accounts`; 17 Occupied
  reservations unchanged; 489 `unit_media` rows).
- Full `php artisan test` green (432 now) including the new tests.
- Manual (Axcee, per `test-before-commit`): landlord profile saves and clears GCash; admin payouts
  queue shows the account and "mark paid out" produces one batch; chat shows inquiry card, system
  lines and concern cards unchanged; add and delete a unit photo.
- `grep` for the dropped column names returns nothing outside migrations / `down()`.

## Risks and follow-ups (not done here)
- Mobile app: `AbangananHubMobile` still has the removed unit fields; `MessageResource` gains
  `kind` / `concern_id` (additive); `UserResource` loses `provider*` / nested `gcash_*`. Confirm the
  mobile app reads no `provider*` before shipping; push logout should send the token.
- `PayMongoWebhookController` and `ReconcilesPaymongoCheckout` are not atomic or locked today
  (double settlement is only guarded by `status !== 'Pending'`); WP5 adds a write there, so wrap the
  status flip plus `payout_owed_at` in one `DB::transaction` + `lockForUpdate` (small, in scope).
- Social login still auto-links by matching email without checking provider-verified email: a
  takeover risk worth its own look.
- Admin conversation view renders system lines as an "Unknown" Landlord bubble.
- Deferred splits: reservations (leases, move-ins) and payments (escrow, voids).
