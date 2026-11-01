<?php

namespace App\Console\Commands;

use App\Models\TenancyDocument;
use App\Services\TenancyDocuments;
use Illuminate\Console\Command;

/** Deletes tenant document files whose retention has run out; the row stays as the record. Scheduled nightly. */
class PurgeTenantDocuments extends Command
{
    protected $signature = 'reservations:purge-tenant-documents';

    protected $description = 'Delete tenant document files 30 days after the tenancy ended';

    public function handle(): int
    {
        $count = 0;

        TenancyDocument::query()
            ->whereNull('purged_at')
            ->whereNotNull('file_path')
            ->whereDate('purge_after', '<=', today())
            ->each(function (TenancyDocument $document) use (&$count) {
                TenancyDocuments::purge($document);
                $count++;
            });

        $this->info("Purged {$count} tenant document ".($count === 1 ? 'file' : 'files').'.');

        return self::SUCCESS;
    }
}
