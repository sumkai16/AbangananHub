<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_templates', function (Blueprint $table) {
            // Goes up by one each time the wording or a rule changes; sent leases record the version they used.
            $table->unsignedInteger('version')->default(1)->after('name');
            // Plain text shown at the top of the lease, after the parties.
            $table->text('welcome_message')->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('lease_templates', function (Blueprint $table) {
            $table->dropColumn(['version', 'welcome_message']);
        });
    }
};
