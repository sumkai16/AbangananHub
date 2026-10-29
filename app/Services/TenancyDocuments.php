<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\TenancyDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Every rule for the documents a lease asks a tenant to upload before signing
 * (plans/required-tenant-documents.md).
 *
 * Each method runs in one transaction on the locked reservation and re-checks state under that lock. Callers
 * authorize who may act (TenancyDocumentPolicy); this class only guards the state. Nothing here touches payments,
 * escrow or move-in: approval is the landlord's record, never a gate on the tenant's money.
 */
class TenancyDocuments
{
    public const DISK = 'local';

    /** Days a file is kept after a signed tenancy ends. A tenancy that never reached signing is purged at once. */
    public const RETENTION_DAYS = 30;

    public const MAX_PER_TENANCY = 8;

    /** Rows for what the lease asked for. Called when the agreement is sent, in the same save as the snapshot. */
    public static function requestFor(Reservation $reservation): void
    {
        foreach ($reservation->lease_snapshot['required_documents'] ?? [] as $item) {
            TenancyDocument::firstOrCreate(
                ['reservation_id' => $reservation->reservation_id, 'doc_key' => $item['key']],
                ['label' => $item['label'], 'status' => 'Requested'],
            );
        }
    }

    /** Requirements the tenant still has to meet before signing: nothing uploaded, or sent back. */
    public static function missing(Reservation $reservation)
    {
        return $reservation->documents()->whereIn('status', ['Requested', 'Rejected'])->get();
    }

    public static function blocksSigning(Reservation $reservation): bool
    {
        return $reservation->documents()->whereIn('status', ['Requested', 'Rejected'])->exists();
    }

    /** The tenant uploads (or replaces) the file for one requirement. Old file is deleted. */
    public static function upload(TenancyDocument $document, User $tenant, UploadedFile $file): TenancyDocument
    {
        return DB::transaction(function () use ($document, $tenant, $file) {
            $locked = self::lock($document);
            $reservation = $locked->reservation;

            if ($reservation->tenant_id !== $tenant->user_id) {
                abort(403);
            }
            if (! $locked->tenantCanReplace()) {
                self::fail('This document is already '.strtolower($locked->status).' and can no longer be changed.');
            }
            if (in_array($reservation->rental_status, Reservation::TERMINAL_STATUSES, true)) {
                self::fail('This tenancy has ended, so documents can no longer be uploaded.');
            }

            $old = $locked->file_path;
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $path = $file->storeAs("tenancy-documents/{$reservation->reservation_id}", bin2hex(random_bytes(16)).'.'.$ext, self::DISK);

            $locked->update([
                'status' => 'Pending',
                'file_path' => $path,
                'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'rejection_reason' => null,
                'uploaded_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'purged_at' => null,
            ]);

            if ($old) {
                Storage::disk(self::DISK)->delete($old);
            }

            Notification::notify(
                $reservation->property?->landlord_id,
                'reservation',
                'Document to review',
                trim(($tenant->first_name ?? 'Your tenant').' uploaded '.$locked->label.'.'),
                self::chatLink($reservation),
                $reservation->conversation_id,
            );

            return $locked;
        });
    }

    public static function approve(TenancyDocument $document, User $landlord): TenancyDocument
    {
        return self::decide($document, $landlord, 'Approved', null, 'Your landlord approved '.$document->label.'.');
    }

    public static function reject(TenancyDocument $document, User $landlord, string $reason): TenancyDocument
    {
        return self::decide($document, $landlord, 'Rejected', $reason, 'Your landlord sent back '.$document->label.': '.$reason);
    }

    /** Excuse one tenant from one requirement. Allowed for anything not already approved. */
    public static function waive(TenancyDocument $document, User $landlord): TenancyDocument
    {
        return self::decide($document, $landlord, 'Waived', null, 'Your landlord excused you from uploading '.$document->label.'.');
    }

    /** Put an approved, rejected or waived row back to waiting so the tenant can upload again. */
    public static function reopen(TenancyDocument $document, User $landlord): TenancyDocument
    {
        return DB::transaction(function () use ($document, $landlord) {
            $locked = self::lock($document);
            self::guardOpen($locked);
            $hasFile = $locked->hasFile();
            $locked->update([
                'status' => $hasFile ? 'Pending' : 'Requested',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            return $locked;
        });
    }

    /** Record that the landlord opened a file. Written to the audit log, not shown to the tenant. */
    public static function recordView(TenancyDocument $document, User $viewer): void
    {
        AuditLog::record(
            'tenancy_document.view',
            trim(($viewer->first_name ?? '').' '.($viewer->last_name ?? '')).' opened '.$document->label.' for reservation #'.$document->reservation_id.'.',
            $document,
        );
    }

    /** Delete the file, keep the row as the record. */
    public static function purge(TenancyDocument $document): void
    {
        if ($document->file_path) {
            Storage::disk(self::DISK)->delete($document->file_path);
        }

        $document->update(['file_path' => null, 'file_name' => null, 'mime' => null, 'size' => null, 'purged_at' => now()]);
    }

    /**
     * The reservation ended. Files go straight away if the agreement was never signed (no tenancy happened),
     * otherwise after the retention period. Called by ReservationObserver.
     */
    public static function scheduleRemoval(Reservation $reservation): void
    {
        $when = $reservation->agreed_at === null ? today() : today()->addDays(self::RETENTION_DAYS);

        $reservation->documents()->whereNull('purge_after')->update(['purge_after' => $when->toDateString()]);

        if ($reservation->agreed_at === null) {
            $reservation->documents()->whereNull('purged_at')->get()->each(fn ($d) => self::purge($d));
        }
    }

    // ─── internals ───────────────────────────────────────────

    private static function decide(TenancyDocument $document, User $landlord, string $status, ?string $reason, string $tenantMessage): TenancyDocument
    {
        return DB::transaction(function () use ($document, $landlord, $status, $reason, $tenantMessage) {
            $locked = self::lock($document);
            self::guardOpen($locked);

            if ($status === 'Approved' && ! $locked->hasFile()) {
                self::fail('There is no file to approve yet.');
            }
            if ($status === 'Rejected' && ! $locked->hasFile()) {
                self::fail('There is no file to send back yet.');
            }
            if ($locked->status === 'Approved' && $status !== 'Approved') {
                self::fail('Reopen an approved document before changing it.');
            }

            $locked->update([
                'status' => $status,
                'rejection_reason' => $reason,
                'reviewed_by' => $landlord->user_id,
                'reviewed_at' => now(),
            ]);

            $reservation = $locked->reservation;
            Notification::notify($reservation->tenant_id, 'reservation', 'Document '.strtolower($status), $tenantMessage, self::chatLink($reservation), $reservation->conversation_id);

            return $locked;
        });
    }

    private static function guardOpen(TenancyDocument $locked): void
    {
        if (in_array($locked->reservation->rental_status, Reservation::TERMINAL_STATUSES, true)) {
            self::fail('This tenancy has ended.');
        }
    }

    private static function lock(TenancyDocument $document): TenancyDocument
    {
        $locked = TenancyDocument::whereKey($document->getKey())->lockForUpdate()->firstOrFail();
        $locked->setRelation('reservation', Reservation::whereKey($locked->reservation_id)->lockForUpdate()->firstOrFail());

        return $locked;
    }

    private static function chatLink(Reservation $reservation): ?string
    {
        return $reservation->conversation_id ? route('conversations.index', ['active' => $reservation->conversation_id]) : null;
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
