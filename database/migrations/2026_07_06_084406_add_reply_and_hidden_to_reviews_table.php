<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('landlord_reply')->nullable()->after('review_comment');
            $table->timestamp('landlord_replied_at')->nullable()->after('landlord_reply');
            $table->boolean('is_hidden')->default(false)->after('landlord_replied_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['landlord_reply', 'landlord_replied_at', 'is_hidden']);
        });
    }
};