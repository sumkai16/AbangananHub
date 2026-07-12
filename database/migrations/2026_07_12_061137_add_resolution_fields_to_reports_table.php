<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('admin_notes', 1000)->nullable()->after('report_status');
            $table->string('action_taken')->nullable()->after('admin_notes');
            $table->unsignedBigInteger('resolved_by')->nullable()->after('action_taken');
            $table->foreign('resolved_by')->references('user_id')->on('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['admin_notes', 'action_taken', 'resolved_by', 'resolved_at']);
        });
    }
};