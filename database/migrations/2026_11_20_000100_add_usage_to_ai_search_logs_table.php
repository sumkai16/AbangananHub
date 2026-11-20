<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a search cost: AI calls, dollars (OpenRouter's own `usage.cost`), and which optional features ran.
 * `feedback` is the tenant's thumbs up (1) or down (-1) on the summary (Phase 8 of plans/ai-search-maps-and-haiku.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_search_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_search_logs', 'ai_calls')) {
                $table->unsignedTinyInteger('ai_calls')->default(0)->after('latency_ms');
                $table->decimal('cost_usd', 8, 6)->default(0)->after('ai_calls');
                $table->json('features')->nullable()->after('cost_usd');
                $table->tinyInteger('feedback')->nullable()->after('features');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_search_logs', function (Blueprint $table) {
            $table->dropColumn(['ai_calls', 'cost_usd', 'features', 'feedback']);
        });
    }
};
