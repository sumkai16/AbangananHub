# Nightly off-server backup (database + private files) to Cloudflare R2

Status: **built Nov 16 2026, pending Axcee's manual check** (checklist below). Real R2 upload not yet run. Delete this file once verified.

Built beyond the first draft: 14 daily + 8 weekly + 6 monthly retention, a self-check (decrypt and list the archive
before uploading, compare the stored size after), and a `backup:list --check` alarm (08:00) for "no good backup for 36 h".

## Why
`storage/app/private` holds the **only copy** of every government ID, selfie, lease and property document
(photos and videos are on Cloudinary, not here). A dead VPS or a bad deploy loses them for good, and the
runbook (§15) and `deployment-cost-and-payments.md` both list off-server backups as required but nothing
does it. The Deployment section of the thesis says "nightly backups in Cloudflare R2", so it must exist.

## What gets backed up

| Item | Included | Why |
|---|---|---|
| MySQL database | Yes, `mysqldump --single-transaction` | All tables |
| `storage/app/private` | Yes | IDs, selfies, leases, property documents |
| `storage/app/public` | Yes | Business logos (small) |
| `.env` | **No** | Holds every secret; keep a copy in the password manager, never in a bucket |
| Cloudinary photos/videos | No | Already off-server |

## Design

- **Command `backup:run`** (`app/Console/Commands/RunBackup.php`), logic in `app/Services/Backup/BackupService.php`.
  Steps: dump the database, tar+gzip it with the two storage folders, **encrypt**, upload to R2, prune old
  copies, record the result.
- **Encryption before upload (required).** The archive contains ID photos, so it is encrypted on the server
  (libsodium `secretstream`, key from `BACKUP_PASSPHRASE`). R2 only ever sees ciphertext. Lose the passphrase
  and the backups are unreadable, so it is also kept in the password manager.
- **Upload with the AWS SDK that is already installed** (`aws/aws-sdk-php`, S3 client pointed at the R2
  endpoint). No new package, and no change to `config/filesystems.php`.
- **File name** `abangananhub-YYYY-MM-DD-HHMM.tar.gz.enc`. Retention: delete anything older than
  `BACKUP_KEEP_DAYS` (default 14) after a successful upload; a failed upload never deletes anything.
- **Schedule** in `routes/console.php`: `dailyAt('02:30')` Manila time, `withoutOverlapping()`,
  `onOneServer()`. Runs before the 03:00 prune and 03:30 audit check.
- **Failure alert:** like `audit:verify`, a failed run notifies every admin (once a day) and logs at error
  level. The last result (time, size, ok/failed) is cached for later display.
- **mysqldump password** goes through the `MYSQL_PWD` environment variable, never the command line.
- **`--local` option:** writes the encrypted archive to `storage/app/backups` instead of R2, so it can be
  tried with no Cloudflare account. **`backup:restore {file}`** decrypts and unpacks to a folder for inspection
  (it never overwrites the live database or files by itself).

## Config

`config/backup.php`, new env keys (added to `.env.example`, blank):
`BACKUP_R2_ENDPOINT`, `BACKUP_R2_BUCKET`, `BACKUP_R2_KEY`, `BACKUP_R2_SECRET`, `BACKUP_PASSPHRASE`,
`BACKUP_KEEP_DAYS=14`.

## Docs to update when built
`context/ARCHITECTURE.md` (decision row), `context/CHANGELOG.md`, `plans/hostinger-vps-deployment.md`
(R2 bucket + API token setup, restore steps, remove "Deferred"), `plans/deployment-cost-and-payments.md`.

## Manual test checklist (Axcee runs these, in the project folder)

There is no backup screen, so this is command-line work; the one thing to look at in the browser is the admin
notification in step 9. Steps 1-10 need no Cloudflare account.

| # | Do | Expect |
|---|---|---|
| 0 | Put `BACKUP_PASSPHRASE=any-test-phrase-16plus` in `.env`, then `php artisan config:clear` | no output |
| 1 | `php artisan schedule:list \| grep backup` | two lines: `backup:run` and `backup:list --check` (shown in UTC: 18:30 and 00:00 = 02:30 and 08:00 Manila) |
| 2 | `php artisan backup:run --local` then `ls -la storage/app/backups` | six steps print, then "Backup abangananhub-... saved"; the file is `-rw-------` and a few MB |
| 3 | `strings storage/app/backups/*.enc \| grep -ci "INSERT INTO"` | `0` (the file is unreadable) |
| 4 | `php artisan backup:list --local` | one row, then "Last run: ok" |
| 5 | `php artisan backup:restore <name> --local`, then `diff -rq storage/app/backups/restored-*/private storage/app/private` and the same for `public` | "Unpacked to ..."; both diffs print nothing (identical); `grep -c "INSERT INTO \`users\`" storage/app/backups/restored-*/dump.sql` is 1 or more; `manifest.json` lists file counts |
| 6 | `BACKUP_PASSPHRASE=a-wrong-phrase-123 php artisan backup:restore <name> --local --to=/tmp/x` | "Wrong passphrase, or the backup file is damaged."; `/tmp/x` does not exist |
| 7 | `cp storage/app/backups/<name> /tmp/bad.enc; printf '\xff' \| dd of=/tmp/bad.enc bs=1 seek=2000000 conv=notrunc; php artisan backup:restore /tmp/bad.enc --to=/tmp/y` | same error, `/tmp/y` not created |
| 8 | Retention: copy the file to `abangananhub-2026-01-05-0230.tar.gz.enc`, `...-2026-10-27-0230...`, `...-2026-10-28-0230...`, `...-2026-11-10-0230...` (same folder), add a `notes.txt`, run `php artisan backup:run --local` | "Removed 2 old": the January one (older than 6 months) and 10-28 (second copy of that week). 10-27, 11-10, today's and `notes.txt` stay |
| 9 | Failure alert: `BACKUP_MYSQLDUMP=/nonexistent php artisan backup:run --local`, then **log in as admin in the browser** and open the notifications (bell or `/notifications`) | the command prints "Backup failed: ..."; the admin has one new notification "Backup failed" (a second failed run the same day adds none); `backup:list --local` still lists every file (nothing deleted) |
| 10 | Stale alarm: `php artisan backup:list --check` | "Last good backup ... ago." To see the alarm: `php artisan tinker --execute="Cache::forget('backup:last-ok'); Cache::forget('backup-alert:'.today()->toDateString());"` then `backup:list --check` again: "No good backup has been recorded yet." and a "Backups are not running" notification for admins |
| 11 | `php vendor/bin/phpunit tests/Unit/BackupTest.php` | OK (8 tests) |
| 12 | With R2 filled in `.env`: `php artisan backup:run` then `php artisan backup:list` | the same file shows in the R2 dashboard (Objects), and in `backup:list`. Then break `BACKUP_R2_SECRET` on purpose: the run fails, admins get one notification, nothing is deleted |
| 13 | Clean up: `rm -rf storage/app/backups`; delete test notifications if you do not want them | |

## Not in scope
Restoring onto a fresh server automatically, backing up Cloudinary, point-in-time recovery, a backup status
page in the admin panel (the cached result is there for it later).

## Open decisions
Decided: encrypt on the server; tiered retention (14 daily, 8 weekly, 6 monthly).
