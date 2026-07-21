<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_media', function (Blueprint $table) {
            $table->string('caption', 150)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('unit_media', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
