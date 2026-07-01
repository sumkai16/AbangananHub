<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('rental_status', [
                'Inquiry',
                'Under Negotiation',
                'Pending Rental Agreement',
                'Rental Agreement Signed',
                'Occupied',
            ])->default('Inquiry')->after('reservation_status');

            $table->text('agreement_terms_notes')->nullable()->after('rental_status');
            $table->timestamp('agreed_at')->nullable()->after('agreement_terms_notes');
            $table->string('agreed_ip')->nullable()->after('agreed_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['rental_status', 'agreement_terms_notes', 'agreed_at', 'agreed_ip']);
        });
    }
};