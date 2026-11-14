<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\Security\AuditChain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Re-checks the audit log's hash chain; caches the result for the page badge and alerts admins on a break. */
class VerifyAuditChain extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Check that no audit log entry was edited or deleted';

    public function handle(): int
    {
        $result = AuditChain::verify();

        Cache::forever('audit-chain:last', $result + ['at' => now()]);

        if ($result['ok']) {
            $this->info("Audit log intact: {$result['checked']} entries checked.");

            return self::SUCCESS;
        }

        $this->error("Audit log altered at entry #{$result['broken_at']} after {$result['checked']} entries checked.");

        // Once a day, so a daily failing run does not become an inbox flood.
        if (Cache::add('audit-chain-alert:'.today()->toDateString(), 1, now()->addDay())) {
            User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))
                ->pluck('user_id')
                ->each(fn ($adminId) => Notification::notify(
                    (int) $adminId,
                    'account',
                    'Audit log was altered',
                    "Entry #{$result['broken_at']} no longer matches its record. Open the audit log to review.",
                    route('admin.audit-logs.index'),
                ));
        }

        return self::FAILURE;
    }
}
