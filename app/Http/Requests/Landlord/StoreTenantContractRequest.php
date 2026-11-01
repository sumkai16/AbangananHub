<?php

namespace App\Http\Requests\Landlord;

use Illuminate\Support\Arr;

/**
 * The contract half of "Add tenant": exactly the Add contract rules, minus the tenant pick, because the tenant is
 * created in the same submit. TenantController::store runs it on the same request when a unit was chosen.
 */
class StoreTenantContractRequest extends StoreContractRequest
{
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['landlord_tenant_id']);
    }
}
