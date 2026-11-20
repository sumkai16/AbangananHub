<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // An admin may read a thread only between these two moments: a participant asked for a review, and the
            // admin finished it. Both null = nobody has asked, so the thread is closed to admins.
            $table->timestamp('admin_review_requested_at')->nullable();
            $table->unsignedBigInteger('admin_review_requested_by')->nullable();
            $table->timestamp('admin_review_ended_at')->nullable();

            $table->foreign('admin_review_requested_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['admin_review_requested_by']);
            $table->dropColumn(['admin_review_requested_at', 'admin_review_requested_by', 'admin_review_ended_at']);
        });
    }
};
