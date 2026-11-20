<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Places a tenant named that are not in config/landmarks.php ("near Skyrise"), looked up once on OpenStreetMap
 * (Nominatim) and kept so the same name never calls it again. A miss is kept too (lat null), for a shorter time.
 * Only the NAME the tenant typed is ever looked up; no position, no account data (RULES.md, AI search).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_places', function (Blueprint $table) {
            $table->id();
            $table->string('query_key', 120)->unique();
            $table->string('name', 120)->nullable();
            $table->string('city', 80)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('osm_ref', 40)->nullable();
            $table->timestamp('fetched_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_places');
    }
};
