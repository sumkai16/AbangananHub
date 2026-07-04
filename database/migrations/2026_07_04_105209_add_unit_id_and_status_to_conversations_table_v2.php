<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('property_id');
            $table->string('status')->default('Open')->after('unit_id');
            $table->foreign('unit_id')
                  ->references('unit_id')
                  ->on('property_units')
                  ->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'status']);
        });
    }
};