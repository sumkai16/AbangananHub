# Payments sidebar = money only; each tenancy's ledger lives under Payments

**Status: approved Oct 31 2026, in progress on branch `emman`.**

## Context

The landlord sidebar now has **Contracts** (leases) and **Rent & Payments** (collections). But both lead to the
same 1,095-line page, `landlord/tenancies/show`, which mixes two jobs: the contract (tenant profile, lease
document, tenant details, unit terms, End contract) and the money (summary, rent calendar, payment reports,
ledger, transactions, deposit charges, record/void). Axcee wants Payments to be only about payments and
recording them, and the contract modal's **Open ledger** to land inside Payments on that tenant's ledger.

Decided with Axcee: the contract parts stay on a **contract page under Contracts** (today's tenancy page,
slimmed); the money parts move to a **new ledger page under Payments**. Each links to the other.

## The flow

| From | Click | Lands on | Sidebar lit |
|---|---|---|---|
| Contracts → contract modal | **Open ledger** | `/landlord/payments/ledger/{reservation}` | Payments |
| Contracts → contract modal | **View contract** | `/landlord/tenancies/{reservation}` (contract page) | Contracts |
| Payments list or calendar | a tenant row | the ledger | Payments |
| Ledger page | **View contract** | the contract page | Contracts |
| Contract page | **Open ledger** (on a small balance strip) | the ledger | Payments |
| Rent reminder / payment-report / overdue notifications, dashboard overdue items | the link | the ledger | Payments |

## Changes

**1. Sidebar** (`layouts/landlord.blade.php`): "Rent & Payments" → **"Payments"**, lit for `landlord.payments.*`
(includes the ledger). Contracts stays lit for `landlord.contracts.*` and `landlord.tenancies.*`. Page title on
`landlord/payments/index` → "Payments".

**2. New ledger page** — route `GET /landlord/payments/ledger/{reservation}` → `landlord.payments.ledger`,
new `Landlord\LedgerController@show` (keeps `PaymentController` from growing past 500 lines). Same gate
(`viewTenancy`) and the same eager loads and `RentLedger` data `TenancyController::show` builds today (lines 35–58),
moved there. New view `landlord/payments/ledger.blade.php`, sections **moved unchanged** from
`tenancies/show.blade.php` (they already have phone cards + desktop tables):
- header: tenant name + Tenant ID, property · unit, current rent (`currentRent()`), due day, payment status;
  actions **Record payment**, **Remind tenant** (existing `tenancies.remind`), **View contract**; breadcrumb Payments › name
- money summary tiles, rent calendar, pending payment reports (`payments.partials._claim-review`), rent ledger
  (with waive late fee), all transactions with receipts/void, voided entries, deposit charges + voided charges
- modals: `tenancies/partials/{record-payment,void-payment,add-deposit-charge,void-deposit-charge}-modal`
  (included from the new page; they move to `payments/partials/` since they now belong to Payments)

**3. Contract page** (`tenancies/show.blade.php`, route unchanged so old links keep working): keeps tenant profile,
lease document (view/print/upload), tenant details, unit & terms, End contract, rating/review cards; loses the
money sections; gains a one-line strip "Balance ₱X · Overdue/Paid · **Open ledger**". `TenancyController::show`
loads only what that needs (tenant, unit, lease, `RentLedger::summary()` for the strip). Breadcrumb Contracts › name.

**4. Contract modal** (`contracts/partials/_details.blade.php`): "Open ledger" → `landlord.payments.ledger`;
add "View contract" → `landlord.tenancies.show`.

**5. Re-point money links to the ledger:** `payments/index.blade.php` (list rows, both layouts),
`PaymentController` calendar `url` (line 256), `payments/receipt.blade.php` back link,
`RentReminderNotifier` (line 41), `PaymentClaims::decisionLink` for walk-ins (line 348),
`Admin\DepositChargeController` link (line 127), dashboard overdue items (`DashboardController` 157/374, money ones only).
Contract-type links (end tenancy, tenant details, lease) keep pointing at the contract page.

Money actions (record, void, deposit charges, waive, confirm/reject report) already `return back()`, so they
work from the ledger page without change.

## Not changing
Routes for the money actions (`/tenancies/{reservation}/payments`, `/late-fee/waive`, `/remind`, deposit
charges) keep their URLs: only the page that shows them moves. Tenant-side pages, the mobile API, and the
Payments list/calendar logic are untouched.

## Files
New: `app/Http/Controllers/Landlord/LedgerController.php`, `resources/views/landlord/payments/ledger.blade.php`.
Moved: four modal partials `landlord/tenancies/partials/*` → `landlord/payments/partials/*`.
Changed: `routes/web.php`, `layouts/landlord.blade.php`, `landlord/tenancies/show.blade.php`,
`Landlord/TenancyController.php`, `landlord/payments/index.blade.php`, `landlord/payments/receipt.blade.php`,
`landlord/contracts/partials/_details.blade.php`, `PaymentController.php`, `RentReminderNotifier.php`,
`PaymentClaims.php`, `Admin/DepositChargeController.php`, `Landlord/DashboardController.php`.
Docs: CHANGELOG, DESIGN.md §6q (page renamed, ledger moved), ARCHITECTURE decision row.

## Verification
Tests: move assertions that read money off `landlord.tenancies.show` (ledger rows, record/void buttons, deposit
charges, payment reports, late-fee waive) to `landlord.payments.ledger`; new tests: ledger page 200 for owner,
403 for another landlord; contract page shows the balance strip and no ledger table; contract modal links to
both; reminder and report notifications link to the ledger; sidebar lights Payments on the ledger and Contracts on
the contract page. Full suite, Pint, `npm run build`, `impeccable` on the two pages.

Manual (Axcee, 375px and desktop): Contracts → open a contract → Open ledger → Payments is lit, record a
payment, void it, waive a late fee, add a deposit charge → View contract → Contracts is lit, print the lease,
end the contract. Payments list → click a row → same ledger. Open a rent-reminder notification → ledger.
