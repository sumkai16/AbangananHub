<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scam protection on direct payments:
     * - a tenant can report cash handed over (method Cash, no reference number), so a landlord who never
     *   records it leaves a trace;
     * - a rejected report can be sent to an admin as a Report; the claim remembers when and which one.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_claims MODIFY payment_method ENUM('GCash','Maya','GoTyme','Bank Transfer','Cash','Other') NOT NULL");
        }

        Schema::table('payment_claims', function (Blueprint $table) {
            $table->string('reference_no', 100)->nullable()->change();
            $table->timestamp('disputed_at')->nullable()->after('rejection_reason');
            $table->foreignId('dispute_report_id')->nullable()->after('disputed_at')
                ->constrained('reports', 'report_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispute_report_id');
            $table->dropColumn('disputed_at');
        });

        DB::table('payment_claims')->whereNull('reference_no')->update(['reference_no' => '']);
        DB::table('payment_claims')->where('payment_method', 'Cash')->update(['payment_method' => 'Other']);

        Schema::table('payment_claims', function (Blueprint $table) {
            $table->string('reference_no', 100)->nullable(false)->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_claims MODIFY payment_method ENUM('GCash','Maya','GoTyme','Bank Transfer','Other') NOT NULL");
        }
    }
};
