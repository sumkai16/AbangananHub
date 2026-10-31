# Rename "lease" in the UI: Contract = the arrangement, Rental agreement = the paper

## Context
The UI says "lease" for two different things:
- the **arrangement** (tenant + unit + rent + dates): "Every lease on your properties", the "Lease" timeline heading, "the lease moves to Contracts"
- the **paper** (printable page + signed copy): "Lease missing", "View & print lease", "Lease Agreement"

The Oct 31 work also added "Contract" for the arrangement, and the reservation statuses already say
"Pending Rental Agreement" / "Rental Agreement Signed". So one thing has three names.

Decided with Axcee:
- **Contract** always means the arrangement.
- **Rental agreement** always means the paper. Use the short form "agreement" only where the context makes it clear.
- **UI text only.** Class names (`LeaseController`, `LeaseTerms`, `LeaseContractCreator`), routes (`landlord.leases.*`, `tenancy.lease`), columns (`lease_snapshot`, `lease_file_*`), the `leases/` storage folder, Blade file names and mobile API JSON stay as they are.

## The rule for each string
| String is about… | New wording |
|---|---|
| The paper / the file / signing | rental agreement (or "agreement") |
| Dates, rent, renewal, ending, progress | contract |

## Changes

### Paper → "rental agreement"
| File | Current → new |
|---|---|
| `resources/views/leases/show.blade.php:36`, `agreements/show.blade.php:51`, `tenant/tenancy/show.blade.php:523`, `landlord/tenancies/show.blade.php:272` (picker title) | "Lease Agreement" → "Rental Agreement" |
| `resources/views/leases/_document.blade.php` (45, 86, 88, 122, 162, 163, 178) | "This Lease Agreement…" / "this lease" → "This Rental Agreement…" / "this agreement" (same meaning; older signed copies will render with the new wording too) |
| `landlord/contracts/index.blade.php:46-47` | tile "Lease missing" / "No signed lease on file" → "Agreement missing" / "No signed agreement on file" |
| `landlord/contracts/index.blade.php:131` | "No lease on file" → "No agreement on file" |
| `landlord/contracts/partials/_details.blade.php` (45, 73, 83, 90) | heading "Lease" → "Rental agreement"; pill "Lease missing" → "Agreement missing"; "Print the lease…" → "Print the agreement…"; "View & print lease" / "View lease" → "View & print agreement" / "View agreement" |
| `landlord/tenancies/show.blade.php` (224, 234, 242) | same three strings as the modal |
| `landlord/contracts/partials/_add-modal.blade.php:155` | section "Signed lease" → "Signed agreement" |
| `tenant/tenancy/show.blade.php:113` | "View lease" → "View rental agreement" |
| `landlord/properties/edit.blade.php:202` | "Standard lease terms" → "Standard agreement terms" |
| `app/Http/Controllers/Landlord/LeaseController.php:49-51, 92` | "signed lease file", "the signed lease", "The lease file", "Signed lease replaced/uploaded." → same with "agreement" |
| `app/Http/Requests/Landlord/StoreWalkInTenantRequest.php:158-160` | same three validation messages |
| `properties/index.blade.php:768` | "sign the lease on the platform" → "sign the rental agreement on the platform" |

### Arrangement → "contract"
| File | Current → new |
|---|---|
| `landlord/contracts/index.blade.php:25` | subtitle "Every lease on your properties…" → "Every contract on your properties…" |
| `landlord/contracts/partials/_add-modal.blade.php:70` | "A lease you agreed with a tenant…" → "A contract you agreed with a tenant…" |
| `landlord/tenancies/show.blade.php:183, 201` | timeline heading "Lease" → "Contract"; aria-label "Lease progress" → "Contract progress" |
| `landlord/reservations/_row-actions.blade.php:35` | "the lease moves to Contracts" → "the contract moves to Contracts" |
| `landlord/properties/edit.blade.php:214` | "Each lease keeps the rules…" → "Each contract keeps the rules…" |
| `app/Http/Controllers/Landlord/ContractController.php:163-164` | notification "Your lease was renewed" / "Your lease now runs until…" → "Your contract was renewed" / "Your contract now runs until…" |
| `app/Http/Requests/Landlord/SendAgreementRequest.php:54` | "The lease runs at least a month" → "The contract runs at least a month" |
| `emails/tenant-invite.blade.php:29`, `auth/accept-invite.blade.php:17` | "see your lease and rent" → "see your contract and rent" |

Leave alone: `legal/terms.blade.php:35` ("party to any lease" is legal wording), code comments, and any "releases" match.

During implementation, grep `resources/views` and `app/` once more for visible "lease" text missed here (notification classes, mail subjects, flash messages) and apply the same rule.

## Tests to update (they check the old wording)
- `tests/Feature/ContractsPageTest.php:194` "Lease missing" → "Agreement missing"; `:232` the new mimes message
- `tests/Feature/LeaseTest.php:107` "Lease Agreement" → "Rental Agreement"; `:135` "Lease missing" → "Agreement missing"
- `tests/Feature/ContractRenewalTest.php:67` "Your lease was renewed" → "Your contract was renewed"

## Docs
- `context/CHANGELOG.md`: new Oct 31 entry stating the naming rule and that code names were kept.
- `context/DESIGN.md` (or the copy section, if there is one): add the one-line glossary "Contract = arrangement, Rental agreement = the paper" so new screens follow it.
- Save a copy of this plan as `plans/contract-vs-rental-agreement-wording.md`.

## Verification
1. `php artisan test --filter="ContractsPageTest|LeaseTest|ContractRenewalTest|TenantsPageTest"` passes, then the full suite (the 3 known `intl` failures in `TenantConcernsTest` are expected).
2. `grep -rniE "\blease\b" resources/views app --include=*.php` shows only comments, code names and `legal/terms`.
3. In the browser at 375px and desktop (app runs at http://127.0.0.1:8000):
   - Landlord → Contracts: subtitle, "Agreement missing" tile, card hint.
   - Open a contract modal: the "Rental agreement" section, pill, buttons; upload a wrong file type and check the error text.
   - Contract page (`landlord/tenancies/{id}`): "Contract" timeline heading and the agreement block.
   - Print page: title "Rental Agreement", body says "this agreement".
   - Tenant → tenancy page: "View rental agreement" button and modal title.
