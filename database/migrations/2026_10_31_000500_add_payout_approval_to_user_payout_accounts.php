<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_payout_accounts', function (Blueprint $table) {
            // An admin has looked at this GCash account and agreed money may be sent to it. Cleared whenever the
            // number or name changes. Until it is set, what the landlord is owed waits.
            $table->timestamp('payout_approved_at')->nullable()->after('paused_by');
            $table->foreignId('payout_approved_by')->nullable()->after('payout_approved_at')
                ->constrained('users', 'user_id')->nullOnDelete();
        });

        // A landlord an admin has already paid by hand has had their account looked at, so they are not asked again.
        DB::table('user_payout_accounts')
            ->whereIn('user_id', DB::table('payouts')->select('landlord_id'))
            ->update(['payout_approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('user_payout_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_approved_by');
            $table->dropColumn('payout_approved_at');
        });
    }
};
