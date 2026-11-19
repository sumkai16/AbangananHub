<?php

namespace App\Services\Backup;

use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The nightly backup: dump the database, pack it with the private and public storage folders, encrypt, prove the
 * archive opens, upload, check the upload is the size we sent, then prune old copies. Any failure stops the run
 * before anything is deleted. See plans/database-and-files-backup.md.
 */
class BackupService
{
    public const LAST = 'backup:last';

    public const LAST_OK = 'backup:last-ok';

    /** @return array{name:string,bytes:int,entries:int,pruned:list<string>,seconds:float,store:string} */
    public function run(BackupStore $store, ?callable $say = null): array
    {
        $say ??= fn () => null;
        $passphrase = (string) config('backup.passphrase');
        if (strlen($passphrase) < BackupCrypto::MIN_PASSPHRASE) {
            throw new RuntimeException('BACKUP_PASSPHRASE must be set to at least '.BackupCrypto::MIN_PASSPHRASE.' characters.');
        }

        $started = microtime(true);
        $tz = (string) config('rentals.timezone');
        $fs = new Filesystem;
        $fs->ensureDirectoryExists((string) config('backup.local_path'), 0700);
        $work = config('backup.local_path').'/.work-'.Str::random(8);
        $fs->makeDirectory($work, 0700);

        try {
            $say('Dumping the database');
            $this->dump($work.'/dump.sql');

            $say('Packing the files');
            $this->writeManifest($work);
            $tar = $work.'/archive.tar.gz';
            $this->runProcess(array_merge([config('backup.tar'), '-czf', $tar, '-C', $work, 'dump.sql', 'manifest.json'], $this->folderArgs()));

            $name = config('backup.prefix').'-'.CarbonImmutable::now($tz)->format('Y-m-d-Hi').'.tar.gz.enc';
            $enc = $work.'/'.$name;
            $say('Encrypting');
            BackupCrypto::encrypt($tar, $enc, $passphrase);
            $fs->delete([$tar, $work.'/dump.sql']);

            $say('Checking the archive opens');
            $entries = $this->verify($enc, $passphrase, $work);

            $say('Uploading to '.$store->label());
            $bytes = (int) filesize($enc);
            $store->put($enc, $name);
            $stored = $store->size($name);
            if ($stored !== $bytes) {
                throw new RuntimeException("The stored copy is {$stored} bytes but {$bytes} were sent. Nothing was deleted.");
            }

            $say('Removing copies past the retention window');
            $pruned = $this->prune($store, $name, $tz);
        } finally {
            $fs->deleteDirectory($work);
        }

        return ['name' => $name, 'bytes' => $bytes, 'entries' => $entries, 'pruned' => $pruned,
            'seconds' => round(microtime(true) - $started, 1), 'store' => $store->label()];
    }

    /** Unpack an archive into an empty folder for inspection. Never touches the live database or files. */
    public function extract(string $encrypted, string $passphrase, string $target): void
    {
        $fs = new Filesystem;
        if ($fs->isDirectory($target) && count($fs->allFiles($target, true)) > 0) {
            throw new RuntimeException("{$target} is not empty.");
        }
        $created = ! $fs->isDirectory($target);
        $fs->ensureDirectoryExists($target, 0700);
        $tmp = $target.'/.archive.tar.gz';
        $ok = false;
        try {
            BackupCrypto::decrypt($encrypted, $tmp, $passphrase);
            $this->runProcess([config('backup.tar'), '-xzf', $tmp, '-C', $target]);
            $ok = true;
        } finally {
            $fs->delete($tmp);
            if (! $ok && $created) {
                $fs->deleteDirectory($target);   // a failed restore leaves nothing behind
            }
        }
    }

    public function record(array $result): void
    {
        Cache::forever(self::LAST, $result + ['ok' => true, 'at' => now()]);
        Cache::forever(self::LAST_OK, $result + ['at' => now()]);
    }

    public function recordFailure(string $error): void
    {
        Cache::forever(self::LAST, ['ok' => false, 'error' => $error, 'at' => now()]);
    }

    /** Once a day, so a daily failing run is not an inbox flood. */
    public function alertAdmins(string $title, string $message): void
    {
        if (! Cache::add('backup-alert:'.today()->toDateString(), 1, now()->addDay())) {
            return;
        }
        User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))
            ->pluck('user_id')
            ->each(fn ($adminId) => Notification::notify((int) $adminId, 'account', $title, $message, route('admin.dashboard')));
    }

    private function dump(string $file): void
    {
        $db = config('database.connections.'.config('database.default'));
        if (! in_array($db['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('The backup only knows MySQL or MariaDB.');
        }
        $cmd = [config('backup.mysqldump'), '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
            '--default-character-set=utf8mb4', '--user='.$db['username'], '--result-file='.$file];
        array_push($cmd, ...(! empty($db['unix_socket']) ? ['--socket='.$db['unix_socket']] : ['--host='.$db['host'], '--port='.$db['port']]));
        $cmd[] = $db['database'];

        $this->runProcess($cmd, ['MYSQL_PWD' => (string) ($db['password'] ?? '')]);   // env, never the command line

        $size = is_file($file) ? (int) filesize($file) : 0;
        $tail = $size > 0 ? (string) file_get_contents($file, false, null, max(0, $size - 300)) : '';
        if ($size === 0 || ! str_contains($tail, 'Dump completed')) {
            throw new RuntimeException('The database dump is empty or was cut off.');
        }
    }

    private function writeManifest(string $work): void
    {
        $folders = [];
        foreach ($this->folders() as $folder) {
            $count = $bytes = 0;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(storage_path('app/'.$folder), \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile()) {
                    $count++;
                    $bytes += $f->getSize();
                }
            }
            $folders[$folder] = ['files' => $count, 'bytes' => $bytes];
        }
        file_put_contents($work.'/manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'dump_sha256' => hash_file('sha256', $work.'/dump.sql'),
            'folders' => $folders,
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ], JSON_PRETTY_PRINT));
    }

    /** Decrypt the finished file and list the tarball: proves the passphrase, the encryption and the archive all work. */
    private function verify(string $enc, string $passphrase, string $work): int
    {
        $check = $work.'/verify.tar.gz';
        try {
            BackupCrypto::decrypt($enc, $check, $passphrase);
            $list = $this->runProcess([config('backup.tar'), '-tzf', $check]);
        } finally {
            @unlink($check);
        }
        $entries = array_filter(explode("\n", $list));
        foreach (['dump.sql', 'manifest.json'] as $must) {
            if (! in_array($must, $entries, true)) {
                throw new RuntimeException("The archive is missing {$must}.");
            }
        }

        return count($entries);
    }

    /** @return list<string> names deleted */
    private function prune(BackupStore $store, string $justUploaded, string $tz): array
    {
        $keep = config('backup.keep');
        $drop = RetentionPolicy::expired(
            array_keys($store->list()), CarbonImmutable::now($tz), $keep['days'], $keep['weeks'], $keep['months'], config('backup.prefix'), $tz,
        );
        $drop = array_values(array_diff($drop, [$justUploaded]));
        if ($drop) {
            $store->delete($drop);
        }

        return $drop;
    }

    /** @return list<string> */
    private function folders(): array
    {
        return array_values(array_filter((array) config('backup.folders'), fn ($f) => is_dir(storage_path('app/'.$f))));
    }

    /** @return list<string> tar arguments that add each folder from storage/app */
    private function folderArgs(): array
    {
        return $this->folders() ? ['-C', storage_path('app'), ...$this->folders()] : [];
    }

    /** @param  list<string>  $cmd */
    private function runProcess(array $cmd, array $env = []): string
    {
        $p = new Process($cmd, null, $env ?: null, null, 1800);
        $p->run();
        if (! $p->isSuccessful()) {
            // The command line is left out on purpose: it can carry paths and user names.
            $err = collect(explode("\n", $p->getErrorOutput() ?: $p->getOutput()))
                ->reject(fn ($l) => $l === '' || str_contains($l, 'Deprecated program name'))   // MariaDB's rename notice
                ->implode(' ');
            throw new RuntimeException(basename($cmd[0]).' failed: '.$err);
        }

        return $p->getOutput();
    }
}
