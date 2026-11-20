<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A real search an admin has checked and corrected (Phase 8 of plans/ai-search-maps-and-haiku.md). `intent` is what the
 * search SHOULD have been; `ai-search:export-fixtures` turns reviewed rows into test sentences for `ai-search:eval`, which
 * is how real searches improve the prompt without retraining anything. `exported_at` stops a row going out twice.
 * No FK on the log: the log table is pruned at 30 days and a review must outlive its source row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_search_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_search_log_id')->nullable();
            $table->string('query', 300);
            $table->json('intent');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_search_reviews');
    }
};
