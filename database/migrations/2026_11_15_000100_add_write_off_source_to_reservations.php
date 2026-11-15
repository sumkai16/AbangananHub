<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An ended tenant's unpaid rent is now written off by a daily job after a set time (config rentals.write_off_after_days),
     * or by the landlord pressing Write off now. `rent_write_off_source` says which ('auto' | 'manual'). When the landlord
     * presses Collect again, `rent_write_off_resumed_at` is set so the job never writes that rental off a second time.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('rent_write_off_source', 10)->nullable()->after('rent_written_off_amount');
            $table->timestamp('rent_write_off_resumed_at')->nullable()->after('rent_write_off_source');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['rent_write_off_source', 'rent_write_off_resumed_at']);
        });
    }
};
