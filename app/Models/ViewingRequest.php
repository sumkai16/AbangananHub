<?php

namespace App\Models;

use App\Events\ViewingScheduleUpdated;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * A viewing of a unit. See plans/unit-viewing-scheduling.md.
 *
 * Online viewings come from a reservation's chat. Rescheduling them is
 * symmetric, same as the key handover: whoever put the current time forward
 * is `proposed_by`, and only the other party can confirm or decline it.
 *
 * Offline viewings are logged by the landlord for someone who arranged a
 * visit by phone or in person. There is no tenant account on the other end,
 * so they are Confirmed on creation and only the landlord ever touches them.
 */
class ViewingRequest extends Model
{
    protected $primaryKey = 'viewing_id';

    public const ACTIVE_STATUSES = ['Pending', 'Confirmed'];

    protected $fillable = [
        'source',
        'reservation_id',
        'property_id',
        'unit_id',
        'tenant_id',
        'visitor_name',
        'visitor_phone',
        'landlord_id',
        'scheduled_at',
        'status',
        'proposed_by',
        'note',
        'decline_reason',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id', 'unit_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Active and still ahead: what blocks the tenant from asking again.
     */
    public function scopeOpen($query)
    {
        return $query->active()->where('scheduled_at', '>', now());
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isOpen(): bool
    {
        return $this->isActive() && $this->scheduled_at->isFuture();
    }

    /**
     * Stored status plus two derived states, so no job has to flip them:
     * a confirmed viewing whose time has passed is Completed, and a request
     * nobody answered before its time is Expired.
     */
    public function displayStatus(): string
    {
        if ($this->scheduled_at->isPast()) {
            return match ($this->status) {
                'Confirmed' => 'Completed',
                'Pending'   => 'Expired',
                default     => $this->status,
            };
        }

        return $this->status;
    }

    public function isOffline(): bool
    {
        return $this->source === 'Offline';
    }

    /**
     * Who is coming to view: the tenant's name for an online viewing, the
     * name the landlord typed for an offline one.
     */
    public function visitorName(): string
    {
        if ($this->isOffline()) {
            return $this->visitor_name ?? 'Visitor';
        }

        return trim(($this->tenant?->first_name ?? '') . ' ' . ($this->tenant?->last_name ?? '')) ?: 'Tenant';
    }

    public function visitorPhone(): ?string
    {
        return $this->isOffline() ? $this->visitor_phone : $this->tenant?->contact_number;
    }

    public function isParticipant(int $userId): bool
    {
        return $userId === $this->landlord_id
            || ($this->tenant_id !== null && $userId === $this->tenant_id);
    }

    /**
     * Whether $userId is the one who has to answer the current proposal.
     */
    public function awaitsResponseFrom(int $userId): bool
    {
        return $this->status === 'Pending'
            && ! $this->isOffline()
            && $this->isParticipant($userId)
            && $this->proposed_by !== $userId;
    }

    public function counterpartyOf(int $userId): ?int
    {
        return $userId === $this->tenant_id ? $this->landlord_id : $this->tenant_id;
    }

    /**
     * Tell the open chat panel to refetch. Guarded like
     * ReservationObserver::broadcastQuietly: a stopped Reverb must not fail
     * an action whose row is already written. Offline viewings have no chat.
     */
    public function broadcastUpdate(): void
    {
        $conversationId = $this->reservation?->conversation_id;
        if (! $conversationId) {
            return;
        }

        try {
            ViewingScheduleUpdated::dispatch($conversationId, $this->viewing_id);
        } catch (BroadcastException $e) {
            Log::warning('Viewing broadcast failed, continuing without it', ['error' => $e->getMessage()]);
        }
    }

    /**
     * The default hourly slots for <x-datetime-picker :slots> — what offline
     * viewings use. Online ones use LandlordViewingHour::slotsFor().
     */
    public static function slotOptions(): array
    {
        return collect(range(config('rentals.viewing_first_hour'), config('rentals.viewing_last_hour')))
            ->map(fn (int $h) => [
                'value' => sprintf('%02d:00', $h),
                'label' => Carbon::createFromTime($h)->format('g A'),
            ])
            ->all();
    }

    /**
     * This landlord's blocked days from today on, as Y-m-d strings for the picker.
     */
    public static function blockedIsosFor(int $landlordId): array
    {
        return LandlordBlockedDate::where('landlord_id', $landlordId)
            ->where('date', '>=', today()->toDateString())
            ->orderBy('date')
            ->get()
            ->map(fn (LandlordBlockedDate $b) => $b->date->toDateString())
            ->all();
    }

    /**
     * Why $slot can't be booked for this landlord, or null when it can.
     * The one rule set every write path checks, so the tenant's request and
     * either party's reschedule can't disagree about what's allowed.
     *
     * $weeklyHours: online viewings must fall inside the landlord's own
     * weekly hours (LandlordViewingHour). Offline viewings pass false — the
     * landlord arranged those directly and only the default range applies.
     */
    public static function slotProblem(Carbon $slot, int $landlordId, bool $weeklyHours = true): ?string
    {
        if ($slot->lessThanOrEqualTo(now())) {
            return 'The viewing time has to be in the future.';
        }

        if ($slot->greaterThan(now()->addDays(config('rentals.viewing_max_days_ahead')))) {
            return 'Pick a viewing date within the next ' . config('rentals.viewing_max_days_ahead') . ' days.';
        }

        if ($slot->minute !== 0) {
            return 'Pick one of the hourly viewing slots.';
        }

        if ($weeklyHours) {
            $span = LandlordViewingHour::weekFor($landlordId)[$slot->dayOfWeek] ?? null;

            if (! $span) {
                return 'The landlord doesn\'t take viewings on ' . $slot->format('l') . 's. Please pick another day.';
            }

            if (! in_array($slot->hour, LandlordViewingHour::startHours($span), true)) {
                return 'The landlord takes viewings from ' . Carbon::createFromTime($span[0])->format('g A')
                    . ' to ' . Carbon::createFromTime($span[1] % 24)->format('g A') . ' on ' . $slot->format('l') . 's.';
            }
        } elseif ($slot->hour < config('rentals.viewing_first_hour') || $slot->hour > config('rentals.viewing_last_hour')) {
            return 'Pick one of the hourly viewing slots.';
        }

        if (LandlordBlockedDate::where('landlord_id', $landlordId)->whereDate('date', $slot->toDateString())->exists()) {
            return 'The landlord isn\'t available on that day. Please pick another date.';
        }

        return null;
    }
}
