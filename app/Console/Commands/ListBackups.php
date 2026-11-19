<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use App\Services\Backup\BackupStores;
use App\Services\Backup\RetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** What is stored, and an alarm when the last good backup is too old (the job itself may be what stopped). */
class ListBackups extends Command
{
    protected $signature = 'backup:list {--local : Look in storage/app/backups} {--check : Alert admins when the last good backup is stale}';

    protected $description = 'List stored backups; --check alerts when there is no recent good one';

    public function handle(BackupService $backup): int
    {
        if ($this->option('check')) {
            return $this->check($backup);
        }

        try {
            $items = BackupStores::make((bool) $this->option('local'))->list();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $tz = (string) config('rentals.timezone');
        krsort($items);
        $this->table(['Backup', 'Size', 'Age'], collect($items)->map(function ($bytes, $name) use ($tz) {
            $when = RetentionPolicy::parse($name, (string) config('backup.prefix'), $tz);

            return [$name, number_format($bytes / 1048576, 2).' MB', $when ? $when->diffForHumans() : '?'];
        })->values()->all());

        $last = Cache::get(BackupService::LAST);
        $this->line($last ? ($last['ok'] ? 'Last run: ok, '.$last['at']->diffForHumans() : 'Last run FAILED '.$last['at']->diffForHumans().': '.$last['error']) : 'No run recorded yet.');

        return self::SUCCESS;
    }

    private function check(BackupService $backup): int
    {
        $limit = (int) config('backup.stale_after_hours');
        $ok = Cache::get(BackupService::LAST_OK);
        if ($ok && CarbonImmutable::parse($ok['at'])->gt(now()->subHours($limit))) {
            $this->info('Last good backup '.$ok['at']->diffForHumans().'.');

            return self::SUCCESS;
        }
        $msg = $ok ? "No good backup for more than {$limit} hours (last: ".$ok['at']->diffForHumans().').' : 'No good backup has been recorded yet.';
        $backup->alertAdmins('Backups are not running', $msg);
        $this->error($msg);

        return self::FAILURE;
    }
}
