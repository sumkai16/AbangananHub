<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use App\Services\Backup\BackupStores;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Nightly: database + private/public storage -> encrypted archive -> Cloudflare R2. */
class RunBackup extends Command
{
    protected $signature = 'backup:run {--local : Write to storage/app/backups instead of R2 (to try it without Cloudflare)}';

    protected $description = 'Back up the database and uploaded files to Cloudflare R2, encrypted';

    public function handle(BackupService $backup): int
    {
        $lock = Cache::lock('backup:run', 3600);
        if (! $lock->get()) {
            $this->warn('A backup is already running.');

            return self::SUCCESS;
        }

        try {
            $result = $backup->run(BackupStores::make((bool) $this->option('local')), fn ($step) => $this->line("  {$step}..."));
            $backup->record($result);
            $mb = number_format($result['bytes'] / 1048576, 2);
            $this->info("Backup {$result['name']} ({$mb} MB, {$result['entries']} entries) saved to {$result['store']} in {$result['seconds']} s.");
            if ($result['pruned']) {
                $this->line('Removed '.count($result['pruned']).' old: '.implode(', ', $result['pruned']));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            Log::error('Backup failed: '.$e->getMessage());
            $backup->recordFailure($e->getMessage());
            $backup->alertAdmins('Backup failed', 'Last night\'s backup did not finish: '.$e->getMessage());
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
