<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one Payout to the landlord's GCash through PayMongo Send Money (POST /v2/batch_transfers) and keeps
 * its state: Processing until PayMongo confirms, then Sent or Failed.
 *
 * PayMongo documents no idempotency key, so a double send is prevented here instead: the Payout row exists and
 * its payments are linked before the HTTP call (LandlordPayouts::batch), and an answer we cannot read (timeout,
 * 5xx, no transfer id) leaves the payout Processing for an admin. It is never sent again automatically. Only a
 * clear refusal (4xx) or a `failed` transfer frees the payments for another attempt.
 *
 * Unverified against live PayMongo (the published docs do not say): the GCash receiving-institution code
 * (PAYMONGO_GCASH_BIC), whether `reference_number` is accepted on create, how an insufficient wallet balance is
 * reported, and whether test keys can send. All of it is configuration or best effort here.
 */
class PaymongoTransfers
{
    private const BASE = 'https://api.paymongo.com/v2';

    /** The bic PayMongo gives every PayMongo wallet as a source account. */
    private const SOURCE_BIC = 'PAEYPHM2XXX';

    /** InstaPay's hard cap per transfer, in pesos. Above it the transfer goes by PESONet. */
    public const INSTAPAY_MAX = 50000;

    /** The .env names still empty that automatic payouts need. */
    public static function missingConfig(): array
    {
        return collect([
            'PAYMONGO_SECRET_KEY' => 'services.paymongo.secret_key',
            'PAYMONGO_WALLET_ACCOUNT_NUMBER' => 'services.paymongo.wallet_account_number',
            'PAYMONGO_WALLET_ACCOUNT_NAME' => 'services.paymongo.wallet_account_name',
            'PAYMONGO_GCASH_BIC' => 'services.paymongo.gcash_bic',
        ])->filter(fn ($key) => blank(config($key)))->keys()->all();
    }

    public static function provider(float|string $pesos): string
    {
        return (float) $pesos > self::INSTAPAY_MAX ? 'pesonet' : 'instapay';
    }

    /**
     * Create the transfer for a Processing payout. Returns the payout's status afterwards, or 'Processing'
     * when PayMongo accepted it and has not finished yet.
     */
    public static function send(Payout $payout): string
    {
        $body = ['transfers' => [[
            'source_account' => [
                'number' => config('services.paymongo.wallet_account_number'),
                'name' => config('services.paymongo.wallet_account_name'),
                'bic' => self::SOURCE_BIC,
            ],
            'destination_account' => [
                'number' => preg_replace('/\D/', '', (string) $payout->gcash_number),
                'name' => $payout->gcash_account_name,
                'bic' => config('services.paymongo.gcash_bic'),
            ],
            'amount' => Payment::toCentavos($payout->amount),
            'currency' => 'PHP',
            'provider' => self::provider($payout->amount),
            'description' => 'AbangananHub payout #'.$payout->payout_id,
            'reference_number' => 'payout-'.$payout->payout_id,
            'callback_url' => route('webhooks.paymongo'),
        ]]];

        try {
            $response = self::client()->post(self::BASE.'/batch_transfers', $body);
        } catch (ConnectionException $e) {
            return self::markUnknown($payout, 'No answer from PayMongo: '.$e->getMessage());
        }

        // What PayMongo answered, without the account details it echoes back: a payout that goes wrong can then be
        // explained afterwards (the first live one was recorded as Sent and nobody could say what PayMongo had replied).
        Log::info('PayMongo transfer request answered', [
            'payout_id' => $payout->payout_id,
            'http' => $response->status(),
            'transfer_id' => $response->json('data.transfers.0.id'),
            'transfer_status' => $response->json('data.transfers.0.status'),
            'errors' => $response->json('errors'),
        ]);

        if ($response->successful()) {
            $transfer = $response->json('data.transfers.0') ?? $response->json('transfers.0');
            $transferId = $transfer['id'] ?? null;

            if (! $transferId) {
                return self::markUnknown($payout, 'PayMongo accepted the request but returned no transfer id.');
            }

            $payout->update(['paymongo_transfer_id' => $transferId]);

            // An answer that says the money already moved is not taken on trust: it is checked against PayMongo's
            // own record of the transfer first. (A first live payout was once recorded as Sent, with an id PayMongo
            // had no transfer for, and nothing reached the landlord.) Anything else is still going: the webhook or
            // the hourly sweep finishes it.
            if (in_array((string) ($transfer['status'] ?? ''), ['succeeded', 'failed'], true)) {
                return self::confirmOutcome($payout);
            }

            return 'Processing';
        }

        $status = $response->status();
        $detail = (string) ($response->json('errors.0.detail') ?? 'PayMongo refused the transfer.');
        $code = (string) ($response->json('errors.0.code') ?? '');

        if ($status >= 400 && $status < 500 && $status !== 408) {
            // Throttled or short of funds: PayMongo did nothing, so try again later without a failed record.
            if ($status === 429 || preg_match('/balance|insufficient/i', $detail.' '.$code)) {
                self::cancelUnsent($payout);
                LandlordPayouts::alertAdmins(
                    'payouts-funds',
                    'A payout could not be sent yet',
                    'PayMongo would not send a payout ('.($code ?: 'HTTP '.$status).': '.$detail.'). It stays owed and is tried again within the hour. If the wallet is low, top it up.',
                );

                return 'Cancelled';
            }

            return self::markFailed($payout, trim($code.' '.$detail) ?: 'HTTP '.$status);
        }

        return self::markUnknown($payout, 'PayMongo answered HTTP '.$status.': '.$detail);
    }

    /**
     * Read the transfer back from PayMongo and settle the payout from what it says there. If PayMongo cannot show us
     * the transfer (an error, or an id that is not ours) the payout stays Processing with a note, and the admins are
     * told: it is never marked Sent without PayMongo's own record, and never re-sent by itself.
     */
    private static function confirmOutcome(Payout $payout): string
    {
        try {
            $response = self::client()->get(self::BASE.'/transfers/'.$payout->paymongo_transfer_id);
        } catch (ConnectionException $e) {
            return self::unconfirmed($payout, 'No answer from PayMongo when confirming: '.$e->getMessage());
        }

        if (! $response->ok()) {
            return self::unconfirmed($payout, 'PayMongo said the transfer went through but would not show it to us (HTTP '.$response->status().').');
        }

        $transfer = $response->json('data') ?? [];

        return self::applyStatus($payout, (string) ($transfer['status'] ?? ''), $transfer);
    }

    private static function unconfirmed(Payout $payout, string $reason): string
    {
        self::markUnknown($payout, $reason);

        LandlordPayouts::alertAdmins(
            'payout-unconfirmed-'.$payout->payout_id,
            'A payout could not be confirmed',
            'Payout #'.$payout->payout_id.' is not marked Sent because PayMongo could not confirm it. Check the PayMongo dashboard (Wallets) for the transfer before pressing Retry. '.$reason,
        );

        return 'Processing';
    }

    /** Ask PayMongo how a Processing payout ended. Anything unclear changes nothing. */
    public static function refresh(Payout $payout): void
    {
        if (! $payout->isProcessing() || ! $payout->paymongo_transfer_id) {
            return;
        }

        try {
            $response = self::client()->get(self::BASE.'/transfers/'.$payout->paymongo_transfer_id);
        } catch (ConnectionException $e) {
            Log::warning('PayMongo transfer poll failed', ['payout_id' => $payout->payout_id, 'error' => $e->getMessage()]);

            return;
        }

        if (! $response->ok()) {
            return;
        }

        $transfer = $response->json('data') ?? [];

        self::applyStatus($payout, (string) ($transfer['status'] ?? ''), $transfer);
    }

    /** Settle a Processing payout from a PayMongo status (`succeeded`, `failed`, anything else = still going). */
    public static function applyStatus(Payout $payout, string $status, array $transfer = []): string
    {
        if ($status === 'succeeded') {
            // The bank's own reference when PayMongo has it; otherwise markSent keeps the transfer id (tr_...).
            return self::markSent($payout, $transfer['provider_reference_number'] ?? null);
        }

        if ($status === 'failed') {
            return self::markFailed($payout, (string) ($transfer['provider_error'] ?? 'PayMongo reported that the transfer failed.'));
        }

        return 'Processing';
    }

    public static function markSent(Payout $payout, ?string $reference = null): string
    {
        $sent = DB::transaction(function () use ($payout, $reference) {
            $locked = Payout::whereKey($payout->payout_id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isProcessing()) {
                return null;
            }

            $locked->update([
                'status' => 'Sent',
                'paid_out_at' => now(),
                'payout_reference' => $reference ?: ($locked->payout_reference ?: $locked->paymongo_transfer_id),
                'failure_reason' => null,
                'resolved_at' => now(),
            ]);

            return $locked;
        });

        if ($sent) {
            Notification::notify(
                $sent->landlord_id,
                'payment',
                'Payout sent',
                '₱'.number_format((float) $sent->amount, 2).' was sent to your GCash ('.$sent->gcash_number.').',
                route('landlord.payouts.index'),
            );
        }

        return $sent?->status ?? $payout->fresh()->status;
    }

    /** The transfer failed: free its payments so they are owed again, and put the payout in front of an admin. */
    public static function markFailed(Payout $payout, string $reason): string
    {
        $failed = DB::transaction(function () use ($payout, $reason) {
            $locked = Payout::whereKey($payout->payout_id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isProcessing()) {
                return null;
            }

            Payment::where('payout_id', $locked->payout_id)->update(['payout_id' => null]);

            $locked->update([
                'status' => 'Failed',
                'failure_reason' => mb_substr($reason, 0, 500),
                'resolved_at' => null,
            ]);

            return $locked;
        });

        if ($failed) {
            LandlordPayouts::alertAdmins(
                'payout-failed-'.$failed->payout_id,
                'A payout failed',
                'The ₱'.number_format((float) $failed->amount, 2).' payout to '.$failed->gcash_account_name.' failed: '.mb_substr($reason, 0, 200).' Check the GCash details, then press Retry on the Payouts page.',
            );

            Notification::notify(
                $failed->landlord_id,
                'payment',
                'We could not send your payout',
                'A ₱'.number_format((float) $failed->amount, 2).' payout to your GCash did not go through. An administrator is looking into it. Please check your GCash number and name in Account Settings.',
                route('landlord.payouts.index'),
            );
        }

        return $failed?->status ?? $payout->fresh()->status;
    }

    /**
     * PayMongo never saw the request: delete the payout and free its payments for the next attempt. No Failed
     * record, so an hour of "wallet is low" does not pile up rows.
     */
    public static function cancelUnsent(Payout $payout): void
    {
        DB::transaction(function () use ($payout) {
            $locked = Payout::whereKey($payout->payout_id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isProcessing() || $locked->paymongo_transfer_id) {
                return;
            }

            Payment::where('payout_id', $locked->payout_id)->update(['payout_id' => null]);
            $locked->delete();
        });
    }

    /**
     * An admin checked the PayMongo dashboard and confirmed a stuck Processing payout was never sent: free its
     * payments and mark it resolved so the next attempt can send them. Refuses a payout PayMongo gave an id.
     */
    public static function releaseForRetry(Payout $payout, string $reason): bool
    {
        return DB::transaction(function () use ($payout, $reason) {
            $locked = Payout::whereKey($payout->payout_id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isProcessing() || $locked->paymongo_transfer_id) {
                return false;
            }

            Payment::where('payout_id', $locked->payout_id)->update(['payout_id' => null]);
            $locked->update(['status' => 'Failed', 'failure_reason' => mb_substr($reason, 0, 500), 'resolved_at' => now()]);

            return true;
        });
    }

    /** We cannot tell whether PayMongo sent it. Leave it Processing and ask an admin to check the dashboard. */
    public static function markUnknown(Payout $payout, string $reason): string
    {
        Payout::whereKey($payout->payout_id)->where('status', 'Processing')
            ->update(['failure_reason' => mb_substr('Outcome unknown: '.$reason, 0, 500)]);

        LandlordPayouts::alertAdmins(
            'payout-unknown-'.$payout->payout_id,
            'A payout needs checking',
            'Payout #'.$payout->payout_id.' (₱'.number_format((float) $payout->amount, 2).') may or may not have been sent: '.mb_substr($reason, 0, 200).' Check the PayMongo dashboard before doing anything else; it will not be sent again automatically.',
        );

        return 'Processing';
    }

    private static function client(): PendingRequest
    {
        return Http::withBasicAuth((string) config('services.paymongo.secret_key'), '')->timeout(20);
    }
}
