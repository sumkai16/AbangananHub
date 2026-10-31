<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Landlord Tenants + Contracts (plans/landlord-tenants-contracts-direct-payments.md).
     *
     * - landlord_tenants: a landlord's own tenant list, so a tenant can exist before any contract and carries a
     *   per-landlord Tenant ID (TN-0001). Status is not stored: it is read from the tenant's contracts.
     * - lease_renewals: one row per renewal. Only the new values are kept; the previous ones are the contract's
     *   own dates and rent, or the renewal before.
     *
     * The backfill lists every walk-in a landlord created plus every tenant who reached a signed lease on one of
     * that landlord's properties, numbered in the order they first became that landlord's tenant.
     */
    public function up(): void
    {
        Schema::create('landlord_tenants', function (Blueprint $table) {
            $table->id('landlord_tenant_id');
            $table->foreignId('landlord_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->unsignedInteger('tenant_number');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['landlord_id', 'tenant_id']);
            $table->unique(['landlord_id', 'tenant_number']);
        });

        Schema::create('lease_renewals', function (Blueprint $table) {
            $table->id('lease_renewal_id');
            $table->foreignId('reservation_id')->constrained('reservations', 'reservation_id')->cascadeOnDelete();
            $table->date('new_move_out_date');
            $table->decimal('new_monthly_rent', 10, 2);
            $table->date('rent_effective_from');
            $table->foreignId('renewed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();

            $table->index(['reservation_id', 'rent_effective_from']);
        });

        $this->backfillRoster();
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_renewals');
        Schema::dropIfExists('landlord_tenants');
    }

    private function backfillRoster(): void
    {
        $walkIns = DB::table('users')
            ->whereNotNull('created_by_landlord_id')
            ->get(['created_by_landlord_id as landlord_id', 'user_id as tenant_id', 'created_at as first_at']);

        $contracts = DB::table('reservations')
            ->join('properties', 'properties.property_id', '=', 'reservations.property_id')
            ->whereIn('reservations.rental_status', ['Rental Agreement Signed', 'Reserved', 'Occupied', 'Completed'])
            ->groupBy('properties.landlord_id', 'reservations.tenant_id')
            ->get([
                'properties.landlord_id',
                'reservations.tenant_id',
                DB::raw('MIN(reservations.created_at) as first_at'),
            ]);

        $now = now();

        $walkIns->concat($contracts)
            ->filter(fn ($row) => $row->landlord_id && $row->tenant_id && $row->landlord_id !== $row->tenant_id)
            ->groupBy('landlord_id')
            ->each(function ($rows, $landlordId) use ($now) {
                $number = 0;

                $rows->groupBy('tenant_id')
                    ->map(fn ($same) => $same->sortBy('first_at')->first())
                    ->sortBy([['first_at', 'asc'], ['tenant_id', 'asc']])
                    ->each(function ($row) use ($landlordId, &$number, $now) {
                        DB::table('landlord_tenants')->insert([
                            'landlord_id' => $landlordId,
                            'tenant_id' => $row->tenant_id,
                            'tenant_number' => ++$number,
                            'created_at' => $row->first_at ?? $now,
                            'updated_at' => $now,
                        ]);
                    });
            });
    }
};
