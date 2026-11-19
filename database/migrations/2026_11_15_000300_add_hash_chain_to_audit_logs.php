<?php

use App\Services\Security\AuditChain;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->char('prev_hash', 64)->nullable()->after('ip_address');
            $table->char('row_hash', 64)->nullable()->after('prev_hash');
            $table->index('row_hash');
        });

        // Chain the rows that already exist, oldest first, starting from the genesis value.
        $prev = AuditChain::genesis();
        DB::table('audit_logs')->orderBy('log_id')->chunkById(500, function ($rows) use (&$prev) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $data['prev_hash'] = $prev;
                $hash = AuditChain::hash($data);

                DB::table('audit_logs')->where('log_id', $row->log_id)->update(['prev_hash' => $prev, 'row_hash' => $hash]);
                $prev = $hash;
            }
        }, 'log_id');
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['row_hash']);
            $table->dropColumn(['prev_hash', 'row_hash']);
        });
    }
};
