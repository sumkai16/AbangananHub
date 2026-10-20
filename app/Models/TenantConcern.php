<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something a current tenant reported to their landlord, shown as a card in the
 * tenancy chat. The landlord moves it Open → In Progress → Fixed; the tenant can
 * reopen a Fixed one.
 */
class TenantConcern extends Model
{
    public const CATEGORIES = [
        'Plumbing', 'Electrical', 'Water supply', 'Appliance', 'Internet / Wi-Fi',
        'Door & lock', 'Pests', 'Noise / neighbors', 'Other',
    ];

    public const OPEN = 'Open';

    public const IN_PROGRESS = 'In Progress';

    public const FIXED = 'Fixed';

    public const STATUSES = [self::OPEN, self::IN_PROGRESS, self::FIXED];

    public const MAX_PHOTOS = 3;

    protected $primaryKey = 'concern_id';

    protected $fillable = [
        'conversation_id', 'reservation_id', 'tenant_id',
        'category', 'description', 'photos', 'status', 'fixed_at',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'fixed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'concern_id', 'concern_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isFixed(): bool
    {
        return $this->status === self::FIXED;
    }

    /** Still needs the landlord's attention. */
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', [self::OPEN, self::IN_PROGRESS]);
    }

    /**
     * Move to a new status, tell the thread, and notify the other party.
     * Who may make which move is the policy's job, not this method's.
     */
    public function markStatus(string $status, User $actor): void
    {
        $this->update([
            'status' => $status,
            'fixed_at' => $status === self::FIXED ? now() : null,
        ]);

        $conversation = $this->conversation;
        $actorIsTenant = $actor->user_id === $this->tenant_id;
        $name = $actor->first_name;
        $what = '"'.$this->category.'" concern';

        $line = match ($status) {
            self::IN_PROGRESS => "{$name} is working on the {$what}.",
            self::FIXED => "{$name} marked the {$what} as fixed.",
            default => "{$name} reopened the {$what}.",
        };
        $conversation->postSystemMessage($line, $this->concern_id);

        Notification::notify(
            $actorIsTenant ? $conversation->landlord_id : $conversation->tenant_id,
            'concern',
            match ($status) {
                self::IN_PROGRESS => 'Concern in progress',
                self::FIXED => 'Concern fixed',
                default => 'Concern reopened',
            },
            $line,
            route('conversations.index', ['box' => 'tenancy', 'active' => $conversation->conversation_id]),
            $conversation->conversation_id,
        );
    }
}
