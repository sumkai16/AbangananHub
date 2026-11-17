<?php

/*
| Nightly off-server backup (plans/database-and-files-backup.md).
| The archive holds government ID photos, so it is encrypted on the server before it leaves it.
*/
return [

    // Cloudflare R2 (S3-compatible). Endpoint looks like https://<account-id>.r2.cloudflarestorage.com
    'r2' => [
        'endpoint' => env('BACKUP_R2_ENDPOINT'),
        'bucket' => env('BACKUP_R2_BUCKET'),
        'key' => env('BACKUP_R2_KEY'),
        'secret' => env('BACKUP_R2_SECRET'),
    ],

    // Encrypts every archive. At least 16 characters. Lose it and the backups cannot be opened: keep a copy in
    // the password manager, away from the server.
    'passphrase' => env('BACKUP_PASSPHRASE'),

    // Copies kept: every one from the last N days, plus the first of each of the last N weeks and N months.
    'keep' => [
        'days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'weeks' => (int) env('BACKUP_KEEP_WEEKS', 8),
        'months' => (int) env('BACKUP_KEEP_MONTHS', 6),
    ],

    // The "last good backup is older than this" alarm, in hours (the job runs once a day).
    'stale_after_hours' => (int) env('BACKUP_STALE_HOURS', 36),

    // Folders under storage/app that go into the archive (IDs, selfies, leases, documents; logos).
    'folders' => ['private', 'public'],

    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
    'tar' => env('BACKUP_TAR', 'tar'),

    'prefix' => 'abangananhub',
    'local_path' => storage_path('app/backups'),
];
