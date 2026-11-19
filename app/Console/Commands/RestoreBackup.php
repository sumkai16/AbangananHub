<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use App\Services\Backup\BackupStores;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Throwable;

/** Decrypts one backup into a folder so it can be inspected or restored by hand. Never overwrites live data. */
class RestoreBackup extends Command
{
    protected $signature = 'backup:restore {file : A backup name (from backup:list) or the path of a downloaded .enc file}
                            {--to= : Folder to unpack into (default storage/app/backups/restored-<name>)}
                            {--local : The backup name is in storage/app/backups, not R2}';

    protected $description = 'Decrypt and unpack a backup into a folder (does not touch the live site)';

    public function handle(BackupService $backup): int
    {
        $passphrase = (string) config('backup.passphrase') ?: (string) $this->secret('Backup passphrase');
        $file = (string) $this->argument('file');
        $fs = new Filesystem;
        $downloaded = null;

        try {
            if (is_file($file)) {
                $source = $file;
            } else {
                $source = $downloaded = sys_get_temp_dir().'/'.basename($file);
                BackupStores::make((bool) $this->option('local'))->get($file, $source);
            }
            $target = $this->option('to') ?: config('backup.local_path').'/restored-'.preg_replace('/\.tar\.gz\.enc$/', '', basename($file));
            $backup->extract($source, $passphrase, $target);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $downloaded && $fs->delete($downloaded);
        }

        $this->info("Unpacked to {$target}");
        $this->line('  dump.sql       the database. Load it with: mysql -u USER -p DATABASE < dump.sql');
        $this->line('  private/       IDs, selfies, leases, documents. Copy over storage/app/private');
        $this->line('  public/        logos. Copy over storage/app/public');
        $this->line('  manifest.json  what the backup holds (dump checksum, file counts)');
        $this->warn('The unpacked folder holds private data. Delete it when done.');

        return self::SUCCESS;
    }
}
