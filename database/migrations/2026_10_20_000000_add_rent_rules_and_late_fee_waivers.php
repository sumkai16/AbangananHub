<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-property rent rules (grace days and a one-time late fee) and the waivers a landlord grants.
     * The rules are only a default: they are frozen into each lease's snapshot when it is issued, and the
     * ledger reads them from there, so editing a property never changes an existing tenant's terms.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedTinyInteger('rent_grace_days')->nullable()->after('default_lease_terms');
            $table->enum('late_fee_type', ['percent', 'fixed'])->nullable()->after('rent_grace_days');
            $table->decimal('late_fee_value', 10, 2)->nullable()->after('late_fee_type');
        });

        Schema::create('late_fee_waivers', function (Blueprint $table) {
            $table->id('waiver_id');
            $table->foreignId('reservation_id')->constrained('reservations', 'reservation_id')->cascadeOnDelete();
            $table->date('billing_period');
            $table->foreignId('waived_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['reservation_id', 'billing_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_fee_waivers');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['rent_grace_days', 'late_fee_type', 'late_fee_value']);
        });
    }
};
