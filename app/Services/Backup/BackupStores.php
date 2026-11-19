<?php

namespace App\Services\Backup;

use RuntimeException;

class BackupStores
{
    /** The R2 bucket, or the local folder when $local is true. */
    public static function make(bool $local): BackupStore
    {
        if ($local) {
            return new LocalStore((string) config('backup.local_path'));
        }
        $r2 = (array) config('backup.r2');
        if (! R2Store::configured($r2)) {
            throw new RuntimeException('R2 is not set up: fill BACKUP_R2_ENDPOINT, BACKUP_R2_BUCKET, BACKUP_R2_KEY and BACKUP_R2_SECRET, or add --local.');
        }

        return new R2Store($r2, (string) config('backup.prefix'));
    }
}
