<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_number', 20)->nullable()->after('password');
            $table->string('profile_picture')->nullable()->after('contact_number');
            $table->enum('account_status', ['Active', 'Suspended'])->default('Active')->after('profile_picture');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'profile_picture', 'account_status']);
        });
    }
};
