<?php

namespace App\Models;

use App\Events\MessageSent;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class Conversation extends Model
{
    /** The deal chat: inquiry → agreement → move-in. */
    public const TYPE_INQUIRY = 'inquiry';

    /** The chat between a tenant who has moved in and their landlord. Holds concern cards. */
    public const TYPE_TENANCY = 'tenancy';

    protected $primaryKey = 'conversation_id';

    protected $fillable = [
        'tenant_id',
        'landlord_id',
        'property_id',
        'unit_id',
        'type',
        'reservation_id',
        'status',
    ];

    public function scopeInquiries(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_INQUIRY);
    }

    public function scopeTenancies(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_TENANCY);
    }

    public function isTenancy(): bool
    {
        return $this->type === self::TYPE_TENANCY;
    }

    /** The tenancy a tenancy thread is about. Null on inquiry threads. */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function concerns(): HasMany
    {
        return $this->hasMany(TenantConcern::class, 'conversation_id', 'conversation_id');
    }

    /** Narration line in this thread, e.g. a concern changing status. */
    public function postSystemMessage(string $text, ?int $concernId = null): void
    {
        $message = $this->messages()->create([
            'sender_id' => null,
            'message' => $text,
            'kind' => 'system',
            'is_read' => true,
            'concern_id' => $concernId,
        ]);

        $this->broadcastQuietly($message);
    }

    /**
     * Push a message to the live channel without letting a stopped Reverb roll back the
     * caller (same guard as Reservation::postSystemMessage()). The row is the message of record.
     */
    public function broadcastQuietly(Message $message): void
    {
        try {
            MessageSent::dispatch($message);
        } catch (BroadcastException $e) {
            Log::warning('Message broadcast failed, continuing without it', [
                'message_id' => $message->message_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id', 'unit_id');
    }

    public function messages(): HasMany
    {
        // Oldest first. Without an ORDER BY MySQL returns rows in index order (grouped by
        // sender), which scrambles the chat. message_id breaks ties inside the same second.
        return $this->hasMany(Message::class, 'conversation_id', 'conversation_id')
            ->orderBy('sent_at')
            ->orderBy('message_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id', 'conversation_id')
            ->latestOfMany('message_id');
    }

    public function isResolved(): bool
    {
        return $this->status === 'Resolved';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'Cancelled';
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'conversation_id', 'conversation_id');
    }

    /**
     * The deal this thread is about, for display in the inbox and chat panel.
     *
     * Deliberately NOT Reservation::TERMINAL_STATUSES, unlike every other
     * "non-terminal reservation" query in the app. Those ask whether a unit is
     * still spoken for; this one asks what a conversation is about, and a
     * finished tenancy is still what its thread was about. Excluding
     * 'Completed' here would blank the stage stepper and the status pill the
     * moment a landlord ends a tenancy, as though the deal had never happened.
     */
    public function activeReservation(): HasOne
    {
        return $this->hasOne(Reservation::class, 'conversation_id', 'conversation_id')
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
            ->latestOfMany('reservation_id');
    }
}
