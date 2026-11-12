<?php

namespace App\Models;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A landlord's payment QR (QR Ph / InstaPay) shared in one conversation. Only the decoded text is kept,
 * encrypted, and the QR is redrawn from it as SVG: what a tenant scans is exactly the code that passed
 * QrPh::parse(), and no uploaded image is ever stored. See plans/direct-payments-record-and-confirm.md §2.
 */
class PaymentQrShare extends Model
{
    protected $primaryKey = 'payment_qr_share_id';

    protected $fillable = [
        'conversation_id',
        'shared_by',
        'payload',
        'account_name',
        'note',
        'replaced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted',
            'replaced_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by', 'user_id');
    }

    public function isCurrent(): bool
    {
        return $this->replaced_at === null;
    }

    /**
     * False when the payload cannot be decrypted: the row was written under a different APP_KEY (data copied
     * between machines, or the key was rotated). Such a QR is unrecoverable, so callers skip it rather than
     * let one bad row crash the whole chat page.
     */
    public function isReadable(): bool
    {
        try {
            $this->payload;

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /** The landlord's newest QR that can still be read (any chat), or null. */
    public static function lastReadableBy(int $landlordId): ?self
    {
        return static::where('shared_by', $landlordId)
            ->latest('payment_qr_share_id')
            ->limit(10)
            ->get()
            ->first(fn (self $share) => $share->isReadable());
    }

    /**
     * The newest QR this landlord has shared with this tenant for this property, in any thread between
     * them (the inquiry thread before move-in, the tenancy thread after). Null when none was shared.
     */
    public static function latestFor(Reservation $reservation): ?self
    {
        // A property has one landlord, so tenant + property pins down the conversation pair without
        // loading the property.
        return static::query()
            ->whereNull('replaced_at')
            ->whereHas('conversation', fn ($q) => $q
                ->where('tenant_id', $reservation->tenant_id)
                ->where('property_id', $reservation->property_id))
            ->with('sharedBy')
            ->latest('payment_qr_share_id')
            ->limit(10)
            ->get()
            ->first(fn (self $share) => $share->isReadable());
    }

    /** What the mobile app needs to draw the QR itself and say who it pays. */
    public function toApi(): array
    {
        return [
            'account_name' => $this->account_name,
            'note' => $this->note,
            'payload' => $this->payload,
            'shared_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** The QR drawn from the validated text. Safe to inline: it is generated here, not uploaded. */
    public function svg(int $size = 280): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        return $writer->writeString($this->payload);
    }
}
