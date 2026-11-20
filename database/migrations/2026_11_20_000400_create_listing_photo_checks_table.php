<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The advisory AI check of a listing's photos against the amenities claimed (Phase 7b of plans/ai-search-maps-and-haiku.md).
 * Shown to the admin on the approval page only; it never blocks anything. One row per property, replaced by each new check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_photo_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id')->unique();
            $table->unsignedTinyInteger('photos_checked')->default(0);
            $table->json('amenity_results'); // amenity name => seen | not_seen | unclear
            $table->json('flags'); // stock_or_watermarked, different_kind_of_place, repeated_photos
            $table->string('note', 255)->nullable();
            $table->decimal('cost_usd', 8, 6)->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_photo_checks');
    }
};
