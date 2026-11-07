<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreWalkInTenantRequest;
use App\Http\Resources\ReservationResource;
use App\Models\User;
use App\Services\LeaseContractCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Mobile walk-in endpoint: a tenant and their contract in one call. The web form it mirrored was replaced by
 * Add Tenant + Add contract (Oct 2026); both now go through LeaseContractCreator. Validation is
 * StoreWalkInTenantRequest, which Add contract's request extends.
 */
class WalkInTenantController extends Controller
{
    public function store(StoreWalkInTenantRequest $request): JsonResponse
    {
        $data = $request->validated();
        $landlordId = auth()->id();

        // Same path as the web form (LeaseContractCreator); the tenant is created in the same transaction so a
        // refused unit leaves no orphaned account. Mobile gets the frozen lease snapshot too.
        $reservation = DB::transaction(function () use ($data, $landlordId) {
            return LeaseContractCreator::create($landlordId, $this->resolveTenant($data, $landlordId), $data);
        });

        return response()->json([
            'data' => new ReservationResource($reservation->load(['unit', 'property', 'tenant'])),
        ], 201);
    }

    private function resolveTenant(array $data, int $landlordId): User
    {
        if (! empty($data['existing_tenant_id'])) {
            return User::where('user_id', $data['existing_tenant_id'])
                ->where('is_walk_in', true)
                ->where('created_by_landlord_id', $landlordId)
                ->firstOrFail();
        }

        $tenant = User::create([
            'first_name'             => $data['first_name'],
            'last_name'              => $data['last_name'],
            'email'                  => $data['email'] ?? null,
            'password'               => Hash::make(Str::random(40)),
            'contact_number'         => $data['contact_number'] ?? null,
            'account_status'         => 'inactive',
            'is_walk_in'             => true,
            'created_by_landlord_id' => $landlordId,
        ]);

        $tenant->assignRole('Tenant');

        return $tenant;
    }
}
