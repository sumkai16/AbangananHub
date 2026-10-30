<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $primaryKey = 'message_id';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message',
        'is_read',
        'kind',
        'concern_id',
        'payment_qr_share_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    /** user | system (status narration, no sender) | inquiry_summary (the first card) | concern (a tenant's concern card) | payment_qr (a landlord's shared payment QR). */
    public const KINDS = ['user', 'system', 'inquiry_summary', 'concern', 'payment_qr'];

    /** Read-only views of `kind`, so Blade, JS payloads and API resources keep their flag names. */
    public function getIsSystemAttribute(): bool
    {
        return $this->kind === 'system';
    }

    public function getIsInquirySummaryAttribute(): bool
    {
        return $this->kind === 'inquiry_summary';
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(TenantConcern::class, 'concern_id', 'concern_id');
    }

    public function paymentQrShare(): BelongsTo
    {
        return $this->belongsTo(PaymentQrShare::class, 'payment_qr_share_id', 'payment_qr_share_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            $message->sent_at ??= now();
        });
    }
}
