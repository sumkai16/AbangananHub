<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A second kind of conversation: the chat between a tenant who has moved in and
 * their landlord, with trackable "concern" cards. See plans/tenant-concerns-chat.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_concerns', function (Blueprint $table) {
            $table->id('concern_id');
            $table->unsignedBigInteger('conversation_id');
            $table->foreign('conversation_id')->references('conversation_id')->on('conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('reservation_id');
            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->string('category', 30);
            $table->text('description');
            $table->json('photos')->nullable();
            $table->string('status', 20)->default('Open');
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'status']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type', 20)->default('inquiry')->after('unit_id');
            $table->unsignedBigInteger('reservation_id')->nullable()->after('type');
            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->nullOnDelete();

            // The new key goes in before the old one comes out: the tenant_id foreign key
            // needs an index that starts with tenant_id at every moment.
            $table->unique(['tenant_id', 'landlord_id', 'property_id', 'type'], 'conversations_party_property_type_unique');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'landlord_id', 'property_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('concern_id')->nullable()->after('is_inquiry_summary');
            $table->foreign('concern_id')->references('concern_id')->on('tenant_concerns')->nullOnDelete();
        });

        $this->backfillTenancyThreads();
    }

    /** One tenancy thread per logged-in tenant who is already Occupied. Walk-ins cannot log in. */
    private function backfillTenancyThreads(): void
    {
        $occupied = DB::table('reservations')
            ->join('users', 'users.user_id', '=', 'reservations.tenant_id')
            ->join('properties', 'properties.property_id', '=', 'reservations.property_id')
            ->where('reservations.rental_status', 'Occupied')
            ->where('users.is_walk_in', false)
            ->orderBy('reservations.reservation_id')
            ->get(['reservations.reservation_id', 'reservations.tenant_id', 'reservations.property_id', 'reservations.unit_id', 'properties.landlord_id']);

        foreach ($occupied as $r) {
            $keys = ['tenant_id' => $r->tenant_id, 'landlord_id' => $r->landlord_id, 'property_id' => $r->property_id, 'type' => 'tenancy'];

            if (DB::table('conversations')->where($keys)->exists()) {
                DB::table('conversations')->where($keys)->update(['reservation_id' => $r->reservation_id, 'unit_id' => $r->unit_id]);

                continue;
            }

            DB::table('conversations')->insert($keys + [
                'unit_id' => $r->unit_id, 'reservation_id' => $r->reservation_id,
                'status' => 'Open', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('conversations')->where('type', 'tenancy')->delete();

        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['concern_id']);
            $table->dropColumn('concern_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unique(['tenant_id', 'landlord_id', 'property_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_party_property_type_unique');
            $table->dropForeign(['reservation_id']);
            $table->dropColumn(['type', 'reservation_id']);
        });

        Schema::dropIfExists('tenant_concerns');
    }
};
