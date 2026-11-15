<?php

namespace App\Services;

use App\Models\LandlordTenant;
use App\Models\TenantDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Puts a new tenant on a landlord's list: a placeholder account the tenant can claim through an email invite, plus
 * the landlord's own list row. Shared by the Tenants page ("Add tenant") and the Contracts page ("Add a rental" with a
 * new tenant), so both create the same thing. Runs inside the caller's transaction when there is one.
 */
final class TenantCreator
{
    /** @param array<string, mixed> $data first_name, last_name, optional email and contact_number, optional ID/emergency fields */
    public static function create(User $landlord, array $data): LandlordTenant
    {
        return DB::transaction(function () use ($landlord, $data) {
            $tenant = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                // Unknowable until the tenant accepts an invite and chooses their own.
                'password' => Hash::make(Str::random(40)),
                'contact_number' => $data['contact_number'] ?? null,
                'account_status' => 'inactive',
                'is_walk_in' => true,
                'created_by_landlord_id' => $landlord->user_id,
            ]);
            $tenant->assignRole('Tenant');

            TenantDetail::saveFor($tenant, $data);

            return LandlordTenant::ensureFor($landlord->user_id, $tenant->user_id);
        });
    }
}
