<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE property_units MODIFY COLUMN availability_status ENUM('Available', 'Reserved', 'Occupied', 'Maintenance') DEFAULT 'Available'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE property_units MODIFY COLUMN availability_status ENUM('Available', 'Reserved', 'Occupied') DEFAULT 'Available'");
    }
};