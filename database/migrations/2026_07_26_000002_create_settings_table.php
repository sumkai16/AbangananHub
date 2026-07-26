<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id('setting_id');

            // Only keys an admin has actually overridden are stored. Anything
            // absent falls through to the defaults in config/rentals.php, which
            // stays the documentation for what each key means.
            $table->string('key', 100)->unique();
            $table->text('value');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
