# Merging `emman` into `joseph` (online move-in payments, both versions)

Status: merged on branch `merge/emman`, all 1,090 tests pass. **Pending manual verification in a browser.**

## Why this plan exists
`emman` and `axci`/`joseph` both built online move-in payments, in different ways. This records which side won each
rule and why, and how to check it by hand. CHANGELOG has the short version.

## Decisions (agreed with Axcee, Nov 1 2026)
| Rule | Winner | Why |
|---|---|---|
| Who picks how to pay | The tenant, per payment (joseph) | Many tenants pay cash; the admin switch now only turns the online card on or off |
| Online move-in amount | Emman's amount, joseph's rows | `Initial` row = advance months of rent (`moveInRent()`), `Deposit` row = deposit. One month was under-charging leases with advance months; separate rows keep refunds, payouts and the amount check working per row |
| When a unit is blocked | When the agreement is sent (joseph) | Nobody signs or pays for a unit they cannot get; fewer refunds |
| Unit shows Reserved when paid online | Emman's `holdUnit()` | Joseph's flow never marked it. Now done inside `PaymongoCheckout::settle()` so webhook and return-page poll both do it |
| "Is the move-in money paid" | Emman's `moveInSettled()` | Stage tabs, contract status and dashboard depend on it |
| Landlord pages | Emman's | Tenants roster, Contracts, Reservations stage tabs, contract modal |
| Admin money tools | Joseph's | Refunds, payouts, GCash approval, disputes |
| Agreement page | Joseph's layout, Emman's "rental agreement" wording | |
| Walk-in controller | Emman deleted it; joseph's one change (lease template in the snapshot) moved to `LeaseContractCreator` | |
| Web inquiry move-in date | Required (emman) | The contract start and rent due day come from it. App endpoint stays optional. **Confirm.** |

## Fixed on the way (not caused by the merge)
- `reservations:purge-tenant-documents` ran on UTC; now on the Manila timezone like the rest of the schedule.
- `RentLedgerInitialTest` expected "Open agreement"; joseph's `ac14170` changed that label to "Confirm move-in".

## Manual test guide (375px first, then desktop)
Setup: Admin → System Settings → Online payments **on**, PayMongo test keys. Landlord has GCash saved and approved.

1. **Send agreement with terms.** Landlord: Reservations → an inquiry → Send agreement. The form shows rent, dates,
   deposit, advance months (2). Tick-box appears only if terms not yet accepted. "Edit the agreement for this tenant
   in the chat" link opens the chat.
2. **One agreement per unit.** Send a second tenant an agreement for the same unit → refused with the "already in progress" message.
3. **Pay online, 2 advance months.** Tenant signs, picks *Pay online*. Card shows "Rent (2 months, paid in advance)",
   the deposit, any fee, total. PayMongo shows the same lines. Pay with the test card.
4. **After paying.** Unit reads *Reserved* and leaves search. Reservation leaves "Awaiting payment". Contracts shows
   "Awaiting move-in" with "paid online · held by AbangananHub", Open ledger, Mark keys turned over.
5. **Ledger.** Two months show paid; deposit shows held. Admin refund of the held group works.
6. **Pay directly (QR and cash).** Same agreement, other tenant/unit: cash amount is prefilled with the full move-in
   amount (rent x advance months + deposit), matching the landlord's "Record move-in" screen.
7. **Second tenant on a paid unit.** Pay attempt → "This unit is already taken by another tenant".
8. **Switch off.** Online card disappears; direct flow still works.
9. **Contracts / Tenants / Payments pages** open, filters work, "Policies" and "Contracts" both show in the sidebar.
10. **Inquiry form** (tenant): move-in date required; the app API still accepts none.

Delete this file once step 1-10 pass.
