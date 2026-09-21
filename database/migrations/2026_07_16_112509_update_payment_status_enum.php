<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raw ALTER is MySQL-only syntax (Blueprint can't widen an enum without
        // doctrine/dbal, which isn't installed here). SQLite has no enum type
        // to begin with — it stores the column as TEXT and never enforces the
        // allowed-values constraint — so the statement is simply skipped there
        // rather than erroring out under the test suite's in-memory SQLite DB.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('Pending', 'Paid', 'Held', 'Released', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending'");
        }
    }
};