<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The landlord's record that the security deposit (what was left after any deposit charges) went back to the
     * tenant. Null = not returned yet. Nothing here moves money; it is only the landlord's note, written when a
     * lease is ended or later from the lease modal.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('deposit_returned_amount', 10, 2)->nullable();
            $table->timestamp('deposit_returned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['deposit_returned_amount', 'deposit_returned_at']);
        });
    }
};
