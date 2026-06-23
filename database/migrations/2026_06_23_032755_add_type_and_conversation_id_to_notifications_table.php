<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type')->default('system')->after('user_id');
            $table->unsignedBigInteger('conversation_id')->nullable()->after('type');

            $table->foreign('conversation_id')
                ->references('conversation_id')
                ->on('conversations')
                ->onDelete('cascade');

            $table->index(['user_id', 'type', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropIndex(['user_id', 'type', 'is_read']);
            $table->dropColumn(['type', 'conversation_id']);
        });
    }
};