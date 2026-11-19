# Landlord page feedback (Nov 5 2026)

Status: **built Nov 5 2026 (1626 tests pass), pending manual verification.** Includes the follow-ups after the first pass (calendar zoom, open-ended years, two-row All years, notification toast, coral bell, two-column record modal, full-width messages shell) and the dashboard redesign (Part 8). Not checked by hand: Alpine behaviour (lease See more, calendar zoom, Pay all toggle, live badges, toast), the sidebar numbers, and anything at a real phone. Delete this file only after Axcee has run the test guide at the bottom in a browser.

Plan with Opus, code with Sonnet (CLAUDE.md). Mobile-first: check 375px first, then desktop. Before the redesign work in parts 3, 5 and 7, load `frontend-design`, `ui-ux-pro-max` and `impeccable`.

## What was asked

The PM's feedback on the landlord side, by page:

| Page | Asked for |
|---|---|
| Notifications | "Needs Action" shows up in notifications, so the landlord can track that person without opening Payments |
| Dashboard | Recent activity goes to the right page, not Reservations. It is a notification, not an audit. If it is an audit, it is the user's own activity only |
| Properties | Remove View Units and Edit from the list. A modal shows unit details on properties with 2 or more units. The activity log is direct on the property, with no shortcomings |
| Units | The grid when viewing a property is not rigid or crowded. What is Export for? |
| Payments | No redundancy. Remove the ledger's table. "See more" on the calendar, no tabs, stay on the page. Remove Needs Action. Number on the nav for unpaid payments. Calendar in year-month format. "Pay in full" for unpaid months. Notices like cash payments go on top |
| Messages | A number on the nav, same as Payments |

## Decisions

| # | Question | Decision |
|---|---|---|
| 1 | Who gets "Pay in full"? | Landlord, and tenant on QR and cash. Online stays one month at a time: PayMongo is off by default, and multi-month online would change checkout, settlement, payout and refund code |
| 2 | Which calendar? | Both: the Payments > Calendar grid and the ledger's Lease month bars |
| 3 | Whose actions in the property log? | Tenant, landlord and admin. Each row says who. Filter chips: All, You, Tenants, Admin. The "You" chip gives exactly what the PM described |
| 4 | Where do those log rows live? | A new table, `property_activities`. Using `audit_logs` would flood the admin audit page with tenant events |
| 5 | What does "no tabs" cover? | The ledger's Months / Payments / Deposit / Voided tabs only. Rent / Payouts (added Nov 2 on purpose) and List / Calendar stay |
| 6 | Does the unit modal need 2 or more units? | No. It works on every property, because a rule that hides it on single-unit properties has no benefit |
| 7 | Export on Units? | Keep it. It downloads the units on screen, with the current filters, as a CSV spreadsheet. Rename it "Export CSV" and add a tooltip |

**Not changing:** the mobile API's `recent_activity`, the Analytics page's Recent Activity, online rent checkout, admin pages.

**The log shows tenant names only**, no contact details, and only to the owning landlord.

## Part 1. Sidebar badges

**Payments badge (new).** The number of tenants owing, the same people as "Still owed" on the Payments page (`summary['outstanding'] > 0`, Occupied and Completed), plus anyone with a Pending payment claim. Each person counts once.

- New `App\Services\LandlordRentDueCount::for(int $landlordId): int`. It uses the same ledger as the Payments page, so the two numbers cannot disagree.
- The ledger runs per tenancy and the layout renders on every page, so cache it 10 minutes per landlord (`landlord:{id}:rent-due`). The store is `database`.
- Eager load `payments`, `depositCharges`, `lateFeeWaivers` and `leaseRenewals`. The last two are the known per-row queries in `plans/security-and-performance-next-steps.md`. Check the real relation names first.
- Clear the key from `PaymentObserver`, a new `PaymentClaim` saved hook, and `ReservationObserver` when status changes. Add `created` to `PaymentObserver`, which only has `updated` today.
- `layouts/landlord.blade.php`: pass it as `'badge'` on the Payments item, next to `$navUnreadMessages`.

**Messages badge (already exists, three gaps).**

- It does not update live. `.MessageSent` already reaches `user.{id}`, and `partials/message-notifications` already listens. Add a small Alpine store so the sidebar and top bar listen too. The page you are reading a chat on must not count up.
- Two rules can disagree. The sidebar counts open chats with an unread latest message (`ConversationController::unreadIn`). The top bar and floating bubble count loose messages, including closed chats. Use the inbox rule for all three: one method, `Conversation::unreadCountFor(int $userId)`. Tenant pages share the bubble, so they get the fix too.
- The collapsed sidebar shows a dot only. Show the number, for Payments, Messages and Reservations alike.

## Part 2. Notifications and dashboard

**"Needs action" in notifications.** `ProcessRentReminders` already notifies the landlord once per tenancy, on the oldest unpaid month, at milestones (3 days before, due day, then every 7 days for 8 weeks). What is missing:

1. **Wording.** Today it names one month. New: "Ana Reyes owes ₱12,000 for 2 months (Bed A, Sunrise House)". Keep the single-month sentence when only one month is owed. Change `RentReminderNotifier::sendToLandlord` only; the tenant's wording stays.
2. **Filter.** New type `rent_due`, used for the landlord copy only. Add it to `Notification::RESERVATION_TYPES` and the icon map in `notifications/partials/dropdown`. A new **Needs action** tab on `notifications.index` shows `rent_due` plus "Payment to confirm" (give that one the new type `payment_to_confirm` in `PaymentClaims::file`, for the landlord).
3. **Stale rows.** An "overdue" notice stays unread after the tenant pays. `Notification::notify` gains an optional `?Model $notifiable`, stored in the existing `notifiable_type` / `notifiable_id` columns, and these notices point at the `Reservation`. When a payment leaves the tenancy with nothing outstanding, mark its unread `rent_due` rows read. When a claim is decided, mark its `payment_to_confirm` row read.

The `index` query uses `morphWith` for reviews only. Check that a `Reservation` target does not trip `preventLazyLoading`.

**Dashboard.** "Recent activity" becomes **Notifications**.

- Delete `DashboardController::recentActivity()`. Pass the latest 6 non-message notifications.
- Extract one row partial, `notifications/partials/_row`, used by the dropdown and the dashboard. Unread rows get a dot.
- New `GET /notifications/{notification}/open`: owner only (403 otherwise), marks it read, redirects to its `link`, or to `notifications.index` when there is none. Only follow internal links (same host or a relative path); anything else goes to `notifications.index`.
- "View all" goes to `notifications.index`. Empty text: "Nothing new. Payments, inquiries and reviews will show up here."

## Part 3. Ledger and Payments

**Ledger page (`landlord/payments/ledger.blade.php`), new order:**

1. Header
2. **Payment to confirm** tiles, full width, first thing under the header
3. Balance tile and the small tiles
4. Lease month bars
5. Payments list
6. Deposit section (only when there is a deposit)
7. Voided payments, collapsed (only when there are any)

**Removed:**

- The Months table and its phone list.
- The Needs action card.
- The tabs. Each panel becomes a plain section. Drop the `x-show` / `tab` state, and keep each list's own "Show all".

**Redundancy cut:**

| Remove | Because |
|---|---|
| The Balance tile's "{month} collected" bar | The month bars show it |
| The Balance tile's due-date sentence when the Next due tile says the same | Same fact twice. Keep the sentence only for "paid in advance through…" and "all paid up" |
| The Deposit tile's "of ₱X" detail and Deduct link | The Deposit section holds them |
| The summary strip on Payments while in Calendar view | The calendar's header already says collected of due for its month |

**What moves, so nothing is lost:**

- Per-month **Record** moves into the month popup (`open-period-detail`), for unpaid months while the tenancy is Occupied. It dispatches the same `open-record-payment` with period and balance.
- **Waive late fee** already lives in that popup.
- Past-due months must never be hidden behind "See more".

**Lease bars.**

- Group by year: one "2026" heading, then its months.
- Show the current year, plus any year holding an unpaid month. "See more" reveals the rest in place, with Alpine and no page load.
- Each month's tap target must be at least 44px on a phone (the bar itself is 10px tall today).

**Payments > Calendar (`_calendar.blade.php`).**

- Add a year picker and a month picker beside prev / Today / next. They use the same `?month=YYYY-MM` links and the existing in-place fetch (`data-cal-nav`). Years run from the earliest tenancy start to next year.
- "+N more" on a day becomes a **See more** button that opens the existing day modal. On phones, where there are only dots, show "+N" and the day opens the same way. It is already one button per day, so this stays on the page.
- Keep the fragment swap working: the markup comes in through `innerHTML` plus `Alpine.initTree`.

**Notices on top: Payments page.** `Landlord\PaymentController::index` loads no claims today, so a landlord sees none there. Add a "Payments to confirm" block at the very top, one `_claim-review` per Pending claim across this landlord's reservations, eager loading `reservation.tenant`. Move-in claims included. Confirm and Not received work in place and redirect back to where they were pressed: check `Landlord\PaymentClaimController` for the redirect.

## Part 4. Pay in full

The money logic does not change. `RentPaymentRecorder` and `RentPaymentAllocator` already spread a lump over months, oldest first, for the landlord's form and for a confirmed claim. This part is buttons, wording and tests.

**Landlord.** In the Balance tile, when 2 or more months are unpaid, add **Record full balance ₱X**. It opens the Record modal with the type Monthly, the oldest unpaid month and the amount = outstanding. The existing split preview then shows how it spreads. Add `open-record-payment` handling for this (the modal already accepts `period` and `balance`). With a single unpaid month the normal Record is the same thing, so no second button.

**Tenant, QR and cash.** On `tenant/tenancy/show`, when 2 or more months are unpaid, a choice above the existing form: **This month ₱X** or **Pay all ₱Y (N months)**. It only changes the prefilled amount in `_direct-pay` and in the cash form inside `payment-route-choice`. `billing_period` stays the oldest unpaid month, because the allocator starts there and goes forward. The online card is unchanged, and when the tenant picks "Pay all" it says online pays one month at a time.

**Landlord's confirm card.** `_claim-review` says "Rent for Nov 2026 – Jan 2027" when the amount spreads over several months, and shows the split. Add `RentPaymentAllocator`-based `PaymentClaim::monthsCovered()` so the preview cannot drift from what gets written. Use the same text in the "Payment to confirm" notification.

**Rules that already hold, to pin in tests:**

- `hasPending` refuses a second report while one waits. The form only offers the oldest unpaid month, so the check holds for a multi-month report too.
- `referenceInUse` refuses a reused reference.
- The server re-allocates from its own figures, so a tampered amount only changes how far it spreads.
- `RentPaymentRecorder` sends one notification per recording, however many months.

**Check while building.** If a late fee changes between filing and confirming, the allocator spills the difference into the next month. That is existing behaviour, but write a test so it is deliberate.

## Part 5. Properties

**List (`landlord/properties/index.blade.php`).**

- Remove **View Units** and **Edit** from the cards and the table.
- Non-draft cards lose the action row. The card already opens the details page (`relative z-10` on the actions means a stretched link); confirm that on a phone.
- The table keeps only its eye button. Make the row open the details too, as the Units table does.
- Drafts keep Continue Setup and Delete.

**The filtered Units page confusion has two more sources:**

- The Overview's "View All Activity" link (replaced in part 6).
- Saving a unit redirects to `landlord.properties.units.index`, which forwards to the filtered Units page. Add a hidden `return_to` (`property` or `units`, a whitelist) on the unit forms. `property` goes to `landlord.properties.show` on the Units tab, with a `#units` hash that the tab's Alpine state reads on load. Default to `property` when opened from the property page, and `units` when opened from the Units page. Wizard redirects stay as they are. The route itself stays, so old links keep working.

**Unit modal.**

- Move the modal out of `units/all.blade.php` into `landlord/units/partials/_detail-modal`.
- Move the payload (`$unitPayload`, built inline today) to `PropertyUnit::detailPayload()`. It needs `property`, `media`, `amenities` and the active tenant loaded, so `Landlord\PropertyController::show` must load the same relations (`Model::preventLazyLoading` is on).
- On `properties/show`, the Units tab's grid card and list row get a **View** button that opens it, and the whole card opens it on a phone. Edit and Remove stay on the card.

## Part 6. Activity log

**New table `property_activities`** (append-only, no `updated_at`):

| Column | Type | Note |
|---|---|---|
| activity_id | bigint PK | |
| property_id | FK → properties, cascade | Index with `created_at` |
| unit_id | bigint, null | No FK, so the row survives a removed unit. `unit_label` goes in the summary |
| actor_id | FK → users, set null | |
| actor_role | enum `landlord`, `tenant`, `admin`, `system` | Index. Drives the filter chips |
| actor_name | varchar(255) | Snapshot |
| action | varchar(60) | |
| summary | varchar(255) | Shown as is |
| metadata | json, null | Changed fields, amounts |
| created_at | timestamp | |

`occupancy_activities` stays: Analytics reads it.

**One writer:** `App\Models\PropertyActivity::record(...)`. It works out the role: the signed-in user is the property's landlord, the reservation's tenant, or has the Admin role; with no signed-in user, the caller says (an online payment is the tenant, a scheduled job is `system`). Observers run inside the caller's transaction, so a rollback leaves no row.

**Observers, not controller calls, so no path can be missed** (web, wizard and `Api\Landlord\PropertyWriteController` / `UnitWriteController`):

| Observer | Logs | Skips |
|---|---|---|
| New `PropertyObserver` | Created, deleted, edited with changed field names, published, unpublished, verification and publication status changes | Everything while a draft, except created |
| `PropertyUnitObserver` (extend) | Unit added, edited with field names, removed, approval status | `availability_status`: the reservation events already say why it changed |
| New `PropertyMediaObserver`, `UnitMediaObserver` | Photo added, removed. One row per request per property, not one per photo | Draft properties |
| New `PropertyDocumentObserver` | Uploaded, replaced, removed, approved, rejected | |
| `ReservationObserver` (extend) | Inquiry sent, agreement sent, signed, moved in, moved out, cancelled, rejected | Other field changes |
| `PaymentObserver` (extend) | Rent paid, recorded, voided | Rows still Pending. Per-month rows from one recording roll up to one line via `payment_claim_id` or the same `paid_at` + `recorded_by` |
| New `ReviewObserver` | Review posted | Admin hiding a review |

Admin decisions come through the same observers because they change the same status columns. Keep the existing `AuditLog::record` calls: that is the admin's own log. Add a test that a change made by each actor records the right role.

**Do not log:** `tenancy_document.view` (the tenant's private files), chat, or anything from a page view.

**Page (`properties/show`).**

- Replace the "coming soon" panel with a timeline, newest first, 20 per page. A `paginate` under its own page name keeps it apart from other lists on the page.
- Each row: who (a role chip: You, Tenant, Admin, System), the summary, and the time. A row links to its target where one exists (unit modal, ledger, reservation).
- Filter chips: All, You, Tenants, Admin, using the query string, which is read on load so the tab stays open.
- Overview: delete the fake `$recentUnits` card and its "View All Activity" link. Show the real latest 3, with "View all" switching to the tab.
- Data comes from `Landlord\PropertyController::show`, not `@php` in the view.

**Backfill migration**, one pass, idempotent:

- Property created and unit created from `created_at`, actor the landlord.
- Admin decisions from `audit_logs` (`listing.approve`, `listing.reject`, `unit.approve`, `unit.reject`).
- Past unit status changes from `occupancy_activities`, using its `actor_id` for the role.
- Reviews from `reviews`.

Earlier edits were never recorded, so an empty stretch before the deploy date is expected. A footer line says "History before Nov 2026 is partial".

## Part 7. Units grid and Export

**Property details, Units tab (`properties/show:455`).** The column sits beside a 240px rail, so three cards are cramped.

- `grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-5`.
- Photo `aspect-[4/3]` in place of the fixed `h-36`.
- Body `p-4 gap-3`, and larger text (label 15px, rent 15px, meta 13px).
- 44px buttons on a phone.
- Rent is in red today, which reads as money owed (DESIGN §6n): make it navy.

**Units page (`units/all.blade.php`).** `gap-4 sm:gap-5` and body `p-4` on the horizontal cards. `xl:grid-cols-2 2xl:grid-cols-3` stays. At 375px the photo is `w-24`: check it holds up with the padding.

**Export.** Rename to "Export CSV", with `title="Download these units as a spreadsheet"`, on Units. Apply the same label to the other landlord Export buttons (Payments, Reservations, Tenants, Analytics) so one word means one thing.

## Tests

| Part | New or changed | Covers |
|---|---|---|
| 1 | `LandlordNavBadgesTest` | Count matches the Payments page's owing list. A tenant owing and with a pending claim counts once. Another landlord's tenants are excluded. Cache clears on a payment. All three message counts agree. A closed chat does not count |
| 2 | `RentDueNotificationTest`, `NotificationOpenTest`, `LandlordDashboardNotificationsTest` | Name, months and total. The tab filters. Settling marks it read, a partial payment does not. `open` refuses another user's row and an external link. The dashboard shows notifications and has no Reservations "View all" |
| 3 | `PaymentsLedgerSplitTest` (update: it asserts `Needs action`), `LedgerLayoutTest`, `PaymentsPendingClaimsTest` | No Months table, no tabs, claim before Balance. Deposit and Voided still render. An older unpaid month is outside "See more". Calendar year and month links work, and days with more than 2 entries show See more |
| 4 | `PayInFullTest` | Three months spread oldest first; one notification. Second report refused. Reference reuse refused. Partial amount stops where it runs out. Tampered amount. Void one month of the spread. Late fee changed between filing and confirming. Button hidden with one unpaid month. Online unchanged |
| 5 | `PropertyListActionsTest`, `UnitDetailModalTest`, `UnitReturnToTest` | Buttons gone, drafts keep theirs. `detailPayload` has no lazy loads. `return_to` whitelist, default, and that a bad value is ignored |
| 6 | `PropertyActivityTest`, `PropertyActivityPageTest` | Each action writes one row with the right role via web and API. A rollback leaves no row. Draft edits skipped. A removed unit keeps its row. Multi-month payment is one line. Filter chips. Another landlord gets 403. The backfill runs twice with no duplicates |

Full suite (`php artisan test`) before each commit. Run `DB_DATABASE=abanganan_hub_test php artisan migrate` for the new table.

## Docs to update when landed

- `context/SCHEMA.md`: `property_activities`, the new notification types.
- `context/CHANGELOG.md`: one entry per part, with this test guide referenced.
- `context/ARCHITECTURE.md`: why a separate activity table, why observers.
- `context/DESIGN.md`: the ledger's new layout replaces the bento notes; `PRD.md`: tenants can pay several months on QR and cash.
- `plans/security-and-performance-next-steps.md`: tick the two N+1 items if part 1's eager loading fixes them.

## Order and risks

Parts can land in order, each as its own commit. Parts 1, 5 and 7 touch separate files and could run side by side; parts 2, 3 and 4 share notification and ledger files, so one agent holds them.

| Risk | Handling |
|---|---|
| The ledger's Blade file is 886 lines and part 3 reshapes it | Move sections to partials as they change; do not rewrite the rest |
| Observers fire in jobs, seeders and tests with no signed-in user | `record()` never throws. A failed activity row must not abort a money action: try/catch and log, as the broadcast code does |
| Activity rows add writes to payment flows | One small insert per event; no external calls |
| Cached badge can lag by up to 10 minutes after a date rolls over | Acceptable. Money changes clear it at once |
| Tenant pay-all with a typed amount | The tenant can still edit the amount, as today; the allocator handles any figure |

## Manual test guide (browser, 375px first, then desktop)

Run `php artisan reservations:process-rent-reminders` where a step needs a reminder. Use a landlord with an Occupied tenant at least 2 months behind.

**Badges**
1. Payments in the sidebar shows the number of tenants owing; it matches the "Still owed" list on the Payments page.
2. Collapse the sidebar on desktop: Payments, Messages and Reservations show numbers.
3. As the tenant in another browser, send a message. The landlord's sidebar and top-bar numbers go up without a reload and show the same figure. Open the chat: both drop.

**Notifications and dashboard**
4. Run the reminder command. The bell shows "Ana Reyes owes ₱… for 2 months". The Needs action tab lists it; tapping it opens her ledger.
5. Record her full balance. The notice is no longer unread.
6. Dashboard: "Notifications" lists real notifications. Tap a review, a payment and an inquiry notice: each goes to its own page. "View all" opens the notifications page.

**Ledger**
7. Open a ledger with a pending cash report: "Payment to confirm" is the first thing under the name.
8. No Months table, no Needs action, no tabs. Payments, Deposit and Voided are sections on the page.
9. Tap an unpaid month bar: the popup offers Record, and Waive when there is a late fee.
10. A tenancy older than a year: bars are grouped by year and See more reveals older years without a page load. An old unpaid month is visible without it.

**Payments page**
11. A pending report shows in a block at the very top. Confirm it there: you stay on Payments.
12. Calendar: pick another year and month from the pickers. The grid changes in place and the address updates. Back works.
13. A day with 3 or more payments shows See more and opens the day in place. The summary strip is hidden in Calendar view and back in List.

**Pay in full**
14. Landlord: on a tenant 3 months behind, Record full balance fills the amount and shows the 3-month split. Save: all 3 months read Paid, and the tenant gets one notification.
15. Tenant 3 months behind, QR and cash: choose Pay all. The amount fills in; submit. The landlord's card says "Rent for … – …" and shows the split. Confirm: all 3 months read Paid.
16. While that report waits, the tenant cannot file another. With online on, the online card still pays one month.
17. A tenant with one unpaid month sees no Pay all, and the landlord sees no Record full balance.

**Properties**
18. Properties list: no View Units or Edit in either view. A card or row opens the details. A draft still shows Continue Setup and Delete.
19. Property details, Units tab: View opens the modal with photos, rent, specs and tenant. Escape and the backdrop close it.
20. Edit a unit from there and save: you return to that Units tab. Edit from the Units page and save: you return to the Units page.

**Activity log**
21. Edit the property's title and add a photo: Activity Log shows "You" rows naming what changed. Add and remove a unit: both show, and the removed one stays.
22. As admin, approve a unit: an "Admin" row appears. As a tenant, send an inquiry: a "Tenant" row appears with no contact details.
23. Filter chips narrow the list and survive a reload. Overview shows the latest 3 and View all opens the tab.
24. As another landlord, the page returns 403.

**Units grid**
25. Property details, Units tab at 375px, 768px and 1440px: cards have room, nothing clips, and rent is navy. The Units page's spacing is wider. Export CSV downloads a file matching the filters.

## Part 8 — Dashboard on one screen (added after the first pass)

The dashboard took more than 1,100px. Five mockups were drawn (one screen, one inbox, tabs, by property, phone first); **tabs** was picked and built, then dropped the same day: on a tall screen it left a blank band because notifications and viewings were hidden behind it. Final layout:

- **One navy band** of numbers: rent this month (with payout line), overdue, occupancy, vacant units, open inquiries.
- **Two columns that end level.** Left: Needs your attention, then Notifications (takes the leftover height; the list scrolls inside on desktop). Right: Your properties (8 rows), then Viewings. Phones: one column, 3 notifications and 5 properties shown.
- **Setup alerts** are tinted rows with an icon and a button: coral for blockers (danger, warn), outline for plain status such as "waiting for admin approval".
- Occupancy bars: navy = occupied, amber = reserved (red used to mean occupied).
- No controller change. Files: `resources/views/landlord/dashboard/index.blade.php`, `_viewings.blade.php` (plain row when `embedded`), `tests/Feature/LandlordDashboardLayoutTest.php`. Needs `npm run build`.

Open question for the PM: they asked that the audit log cover only the user's own actions. Decision taken (Axcee): it shows tenant, landlord and admin activity, each labelled by role. Confirm this is what they meant.

**Dashboard (add to the guide above)**
26. At 1440×900 the numbers sit in one navy band and the page needs little or no scroll; on a tall screen (1640×1028) there is no blank band at the bottom.
27. Click a notification row: it opens the page it is about and is marked read. "View all" opens the notifications page.
28. A listing waiting for approval shows as a tinted row with a "View" button. With a payout or draft problem, the button is coral.
29. Viewings row opens the calendar modal; Escape and the backdrop close it.
30. At 375px: one column in the order band, attention, notifications (3), properties (5), viewings; no sideways scroll. A landlord with no units sees "No units yet" in the band.
