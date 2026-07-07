<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('reservation_status');

            $table->string('rental_status')->default('Inquiry')->change();

            $table->unsignedBigInteger('conversation_id')->nullable()->after('tenant_id');
            $table->foreign('conversation_id')->references('conversation_id')->on('conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reservation_status')->default('Pending');
            $table->string('rental_status')->default(null)->nullable()->change();

            $table->dropForeign(['conversation_id']);
            $table->dropColumn('conversation_id');
        });
    }
};