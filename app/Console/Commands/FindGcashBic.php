<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Prints PayMongo's list of receiving institutions so the GCash code for PAYMONGO_GCASH_BIC can be copied from
 * it. A helper for setup, never used while sending. The endpoint is the one PayMongo's Send Money guide names;
 * PayMongo announced it would retire its v1 wallet endpoints in April 2026 and publishes no v2 replacement, so
 * an error here means "ask PayMongo for GCash's receiving-institution code".
 */
class FindGcashBic extends Command
{
    protected $signature = 'payouts:find-gcash-bic {--provider=instapay : instapay or pesonet}';

    protected $description = "List PayMongo's receiving institutions to find the code for GCash";

    public function handle(): int
    {
        $response = Http::withBasicAuth((string) config('services.paymongo.secret_key'), '')
            ->timeout(20)
            ->get('https://api.paymongo.com/v1/wallets/receiving_institutions', ['provider' => $this->option('provider')]);

        if (! $response->ok()) {
            $this->error('PayMongo answered HTTP '.$response->status().': '.$response->body());
            $this->line('Ask PayMongo support for the receiving-institution code of GCash, then set PAYMONGO_GCASH_BIC.');

            return self::FAILURE;
        }

        $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('Find the GCash entry above and put its code in PAYMONGO_GCASH_BIC.');

        return self::SUCCESS;
    }
}
