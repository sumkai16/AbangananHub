<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments MODIFY paymongo_payment_intent_id VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments MODIFY paymongo_payment_intent_id VARCHAR(255) NOT NULL');
    }
};