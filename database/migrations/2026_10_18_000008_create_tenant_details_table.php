<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a landlord keeps on file about a tenant: who to call in an emergency, and which ID they showed.
     * One row per user, kept off `users` for the same reason as user_payout_accounts: it must never ride
     * along in a nested user payload. id_number holds the ciphertext of an `encrypted` cast, hence text.
     */
    public function up(): void
    {
        Schema::create('tenant_details', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();
            $table->string('emergency_contact_number', 20)->nullable();
            $table->string('id_type', 50)->nullable();
            $table->text('id_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_details');
    }
};
