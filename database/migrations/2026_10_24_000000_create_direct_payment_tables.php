<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Direct payments (plans/direct-payments-record-and-confirm.md). The tenant pays the landlord's own
     * QR outside AbangananHub and reports it; the landlord confirms. Nothing here holds money.
     *
     * - payment_qr_shares: a landlord's QR Ph code, shared in one conversation. Only the decoded text is
     *   kept (encrypted), never the screenshot, and the QR is redrawn from it.
     * - payment_claims: a tenant's "I paid". Kept apart from payments so an unconfirmed claim can never
     *   be read as money; confirming one writes ordinary Paid rows that point back at it.
     * - reservations.move_in_paid_at: the one signal that direct move-in money is settled.
     */
    public function up(): void
    {
        Schema::create('payment_qr_shares', function (Blueprint $table) {
            $table->id('payment_qr_share_id');
            $table->foreignId('conversation_id')->constrained('conversations', 'conversation_id')->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->text('payload');
            $table->string('account_name', 100);
            $table->string('note', 300)->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('payment_claims', function (Blueprint $table) {
            $table->id('payment_claim_id');
            $table->foreignId('reservation_id')->constrained('reservations', 'reservation_id')->cascadeOnDelete();
            $table->enum('claim_type', ['MoveIn', 'Monthly']);
            $table->date('billing_period')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['GCash', 'Maya', 'GoTyme', 'Bank Transfer', 'Other']);
            $table->string('reference_no', 100);
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->enum('status', ['Pending', 'Confirmed', 'Rejected', 'Withdrawn'])->default('Pending');
            $table->foreignId('filed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_claim_id')->nullable()->after('replaces_payment_id')
                ->constrained('payment_claims', 'payment_claim_id')->nullOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('move_in_paid_at')->nullable()->after('tenant_confirmed_move_in_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('payment_qr_share_id')->nullable()->after('concern_id')
                ->constrained('payment_qr_shares', 'payment_qr_share_id')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('GCash','QRPh','Cash','Bank Transfer','Maya','GoTyme','Check','Other') NOT NULL");
            DB::statement("ALTER TABLE messages MODIFY kind ENUM('user','system','inquiry_summary','concern','payment_qr') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('messages')->where('kind', 'payment_qr')->delete();
            DB::statement("ALTER TABLE messages MODIFY kind ENUM('user','system','inquiry_summary','concern') NOT NULL DEFAULT 'user'");
            DB::table('payments')->where('payment_method', 'GoTyme')->update(['payment_method' => 'Other']);
            DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('GCash','QRPh','Cash','Bank Transfer','Maya','Check','Other') NOT NULL");
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_qr_share_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('move_in_paid_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_claim_id');
        });

        Schema::dropIfExists('payment_claims');
        Schema::dropIfExists('payment_qr_shares');
    }
};
