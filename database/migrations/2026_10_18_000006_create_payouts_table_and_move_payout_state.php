<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A payout is one GCash transfer an admin sent to a landlord, which can cover several payments. It
 * used to be smeared across four columns on every payment it covered (payout_status, paid_out_at,
 * paid_out_by, payout_reference), repeated per row with nothing tying a batch together, and the
 * landlord's GCash details were never recorded at send time.
 *
 *   payments.payout_owed_at  - money became owed to the landlord (was payout_status = 'Pending Payout')
 *   payments.payout_id       - the transfer that settled it (was payout_status = 'Paid Out')
 *   payouts                  - the transfer itself, with the GCash details as they were when sent
 *
 * Pending = owed and not yet in a payout. up() rebuilds one payout per old batch (same landlord,
 * reference, admin and second). Their GCash snapshot can only be the landlord's current details,
 * since history was never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $t) {
            $t->id('payout_id');
            $t->foreignId('landlord_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
            $t->string('gcash_number')->nullable();
            $t->string('gcash_account_name')->nullable();
            $t->string('payout_reference')->nullable();
            $t->foreignId('paid_out_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('paid_out_at');
            $t->timestamps();
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->unsignedBigInteger('payout_id')->nullable()->after('payout_status');
            $t->timestamp('payout_owed_at')->nullable()->after('payout_id');
            $t->foreign('payout_id')->references('payout_id')->on('payouts')->restrictOnDelete();
            $t->index(['payout_owed_at', 'payout_id'], 'payments_payout_owed_index');
        });

        DB::table('payments')->whereNotNull('payout_status')->update([
            'payout_owed_at' => DB::raw('COALESCE(released_at, paid_at, created_at)'),
        ]);

        $this->backfillPayouts();

        Schema::table('payments', function (Blueprint $t) {
            $t->dropIndex('payments_payout_status_index');
            $t->dropForeign(['paid_out_by']);
            $t->dropColumn(['payout_status', 'paid_out_at', 'paid_out_by', 'payout_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->string('payout_status')->nullable()->after('release_reason');
            $t->timestamp('paid_out_at')->nullable()->after('payout_status');
            $t->unsignedBigInteger('paid_out_by')->nullable()->after('paid_out_at');
            $t->string('payout_reference')->nullable()->after('paid_out_by');
            $t->index('payout_status', 'payments_payout_status_index');
            $t->foreign('paid_out_by')->references('user_id')->on('users')->nullOnDelete();
        });

        DB::table('payments')->whereNotNull('payout_owed_at')->whereNull('payout_id')
            ->update(['payout_status' => 'Pending Payout']);

        DB::table('payments')
            ->join('payouts', 'payouts.payout_id', '=', 'payments.payout_id')
            ->update([
                'payments.payout_status' => 'Paid Out',
                'payments.paid_out_at' => DB::raw('payouts.paid_out_at'),
                'payments.paid_out_by' => DB::raw('payouts.paid_out_by'),
                'payments.payout_reference' => DB::raw('payouts.payout_reference'),
            ]);

        Schema::table('payments', function (Blueprint $t) {
            $t->dropForeign(['payout_id']);
            $t->dropIndex('payments_payout_owed_index');
            $t->dropColumn(['payout_id', 'payout_owed_at']);
        });

        Schema::dropIfExists('payouts');
    }

    /** One payouts row per old batch; sets payments.payout_id on every payment in it. */
    private function backfillPayouts(): void
    {
        $paidOut = DB::table('payments as p')
            ->join('reservations as r', 'r.reservation_id', '=', 'p.reservation_id')
            ->join('properties as pr', 'pr.property_id', '=', 'r.property_id')
            ->where('p.payout_status', 'Paid Out')
            ->get(['p.payment_id', 'p.amount', 'p.paid_out_at', 'p.paid_out_by', 'p.payout_reference', 'pr.landlord_id']);

        $batches = $paidOut->groupBy(fn ($row) => implode('|', [
            $row->landlord_id,
            $row->payout_reference,
            $row->paid_out_by,
            substr((string) $row->paid_out_at, 0, 19),
        ]));

        foreach ($batches as $rows) {
            $first = $rows->first();
            [$number, $name] = $this->gcashFor($first->landlord_id);
            $now = now();

            $payoutId = DB::table('payouts')->insertGetId([
                'landlord_id' => $first->landlord_id,
                'amount' => $rows->sum('amount'),
                'gcash_number' => $number,
                'gcash_account_name' => $name,
                'payout_reference' => $first->payout_reference,
                'paid_out_by' => $first->paid_out_by,
                'paid_out_at' => $first->paid_out_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('payments')->whereIn('payment_id', $rows->pluck('payment_id'))->update(['payout_id' => $payoutId]);
        }
    }

    /** @return array{0: ?string, 1: ?string} */
    private function gcashFor(int $landlordId): array
    {
        if (Schema::hasTable('user_payout_accounts')) {
            $row = DB::table('user_payout_accounts')->where('user_id', $landlordId)->first();
        } elseif (Schema::hasColumn('users', 'gcash_number')) {
            $row = DB::table('users')->where('user_id', $landlordId)->first(['gcash_number', 'gcash_account_name']);
        } else {
            $row = null;
        }

        return [$row->gcash_number ?? null, $row->gcash_account_name ?? null];
    }
};
