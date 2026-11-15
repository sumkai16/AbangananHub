# Add rental modal: add a new tenant inline

## Context
The "Add a rental" modal (`resources/views/landlord/contracts/partials/_add-modal.blade.php`) only lets the landlord pick an existing tenant. If the tenant isn't on the list, the modal dead-ends with "Add the tenant on the Tenants page first". We want a **New tenant** option inside the modal (mockup variant A on the canvas: Existing / New toggle, then a locked tenant card).

Most of the backend already exists. `TenantController::store` (`app/Http/Controllers/Landlord/TenantController.php:147-188`) already creates a tenant and a contract in one transaction. We reuse that, not rebuild it.

## Findings that shape the plan
- Tenant = a placeholder `User` (walk-in, random password, `account_status=inactive`, `created_by_landlord_id`) + `LandlordTenant::ensureFor()` + `TenantDetail::saveFor()`.
- "Pending" is not stored. It means "on the list, no contract yet". Creating the rental makes them Active, so the mockup label "saved as Pending" is wrong. Use "New tenant" only.
- The invite is by **email only** (`app/Support/TenantInvites.php:25`, called after the transaction). No email = no invite. So the mockup's success screen with "Send invite" is dropped.
- After create, `ContractController::store` already redirects to the contracts list with the new rental open and a flash message. That replaces the success screen.
- Validation today: name is **first_name + last_name** (not one field), email unique in `users`, `contact_number` required if no email (`Phone::RULE`). No phone duplicate check and no phone normaliser exist.
- `StoreTenantContractRequest` already exists (contract fields without `landlord_tenant_id`), written for the combined case.

## Design (decisions made)
1. **One modal, two modes** via a `tenantMode` (`existing` | `new`) in the Alpine scope. Posts to the same `landlord.contracts.store`.
2. **New-tenant fields:** First name, Last name, Mobile number, Email (optional, same rule as the Tenants page: mobile required when no email). ID, address and emergency contact stay on the Tenants page.
3. **No success screen.** Keep the existing redirect and flash.
4. **Duplicate check, kept small:** before create, compare the typed mobile (digits only) against this landlord's own tenants. On a match, refuse with an inline error naming the tenant ("This number is already on your list as TN-0004 · Maria Santos. Choose Existing tenant instead.") and a button that switches to Existing with that tenant picked. Email duplicates keep the existing `unique:users,email` error. No schema change.
5. **Empty state:** with zero tenants the modal opens on New tenant. Remove the dead-end message. Keep the "no free unit" message.
6. **Invite:** send `TenantInvites::send` after the transaction when an email was given, same as `TenantController::store`.

## Implementation steps
1. **Extract `TenantCreator`** (`app/Services/TenantCreator.php`) from `TenantController::store` lines 164-179: create the User, assign the Tenant role, save details, `LandlordTenant::ensureFor`. Returns the `LandlordTenant` + `User`. `TenantController::store` calls it (no behaviour change).
2. **`StoreContractRequest`** (`app/Http/Requests/Landlord/StoreContractRequest.php`): add `tenant_mode`. When `new`, require the name/phone/email rules (reuse `TenantRequest` rules/messages) and drop the `landlord_tenant_id` requirement. When `existing`, behave as today.
3. **`ContractController::store`** (`:97-109`): for `new`, run `TenantCreator` and `LeaseContractCreator::create` together so a refused contract leaves no tenant (the same guarantee as `test_a_refused_contract_leaves_no_tenant_behind`). Mind that `LeaseContractCreator::create` stores the lease file before its own transaction and deletes it on failure, so keep that cleanup working. Send the invite after commit.
4. **Duplicate-phone helper:** a small digits-only normalise + lookup on the landlord's own `LandlordTenant` rows (put it next to `app/Support/Phone.php`). Used by the request's validation.
5. **Modal UI** (`_add-modal.blade.php`), mobile-first 375px, per variant A: segmented toggle, new-tenant fields with `id`/`for` labels and `@error` blocks, duplicate error with "Use her" button, Navy/Coral tokens from `context/DESIGN.md`. Restore `old()` values and the mode on a failed submit (`old('_form') === 'contract'`). Same 44px targets, focus rings and `motion-reduce` rules as the rest of the modal.
6. **Both pages share the modal** (`contracts/index.blade.php:192`, `tenants/index.blade.php:285`), so no new data is needed. `ContractFormOptions` is unchanged.
7. **Docs:** add a dated entry to `context/CHANGELOG.md`. After approval, copy this plan to `plans/add-rental-inline-new-tenant.md` (CLAUDE.md rule).

## Verification
Tests (style: `tests/Feature/ContractsPageTest.php`, `TenantsPageTest.php`, `CreatesMarketplaceFixtures`, `DatabaseTransactions`):
- New tenant + valid contract: tenant, `LandlordTenant` row and reservation all created; invite mailed only when an email is given (`Mail::fake`).
- Refused contract (taken unit): no tenant left behind.
- Duplicate mobile within the landlord's list: refused with the inline error; another landlord's matching number does not block.
- No email and no mobile: refused. Duplicate email: existing message.
- Existing-tenant path unchanged (current tests stay green).
- `TenantsPageTest` still green after the `TenantCreator` extraction.
- Run `php artisan test`.

Manual (you, in the browser, at 375px then desktop): toggle modes, create with a new tenant, hit the duplicate-number error and use "Use her", empty-list landlord opens on New tenant, failed submit keeps typed values, rental opens on the list after create. Keep the plan file until you have run this.

## Open items for you (small)
- OK to drop the success screen and rely on the existing redirect + flash?
- OK that duplicate checking is per landlord, by phone number only (not by name)?
