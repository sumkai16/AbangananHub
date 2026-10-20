<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per AI search, so prompts can be tuned on real sentences and the
 * daily free-tier quota can be watched. Pruned after 30 days (AiSearchLog).
 * No FK on user_id: guests search too, and a deleted user must not block pruning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_search_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('query', 300);
            $table->string('language', 8)->nullable();
            $table->json('intent')->nullable();
            $table->unsignedSmallInteger('result_count')->default(0);
            $table->unsignedSmallInteger('exact_count')->default(0);
            $table->json('relaxed')->nullable();
            $table->string('driver', 20);
            $table->boolean('used_ai')->default(false);
            $table->string('fallback_reason', 30)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_search_logs');
    }
};
