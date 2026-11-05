<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One line of a property's history: what happened on the listing, who did it, when.
 *
 * Written by the observers (Property, PropertyUnit, media, documents, reservations, payments, reviews), not by
 * controllers, so a web form, the property wizard and the mobile API are all caught by the same code. The rows
 * are written inside the acting transaction, so a rollback leaves no line.
 *
 * Separate from `audit_logs` on purpose: that table is the admin's record of consequential actions, and
 * tenant events would flood it. Separate from `occupancy_activities`: Analytics reads that one.
 *
 * Privacy: summaries name a tenant by name only, never a phone number or e-mail, and the page that shows them
 * is the owning landlord's.
 */
class PropertyActivity extends Model
{
    /** Written once; the only edit is a roll-up (see recordMerged). */
    public const UPDATED_AT = null;

    public const ROLES = ['landlord', 'tenant', 'admin', 'system'];

    /** The chips on the Activity Log tab: query value => [label, roles it shows]. null = no filter. */
    public const FILTERS = [
        'all'     => ['All', null],
        'you'     => ['You', ['landlord']],
        'tenants' => ['Tenants', ['tenant']],
        'admin'   => ['Admin', ['admin']],
    ];

    protected $primaryKey = 'activity_id';

    protected $fillable = [
        'property_id',
        'unit_id',
        'actor_id',
        'actor_role',
        'actor_name',
        'action',
        'summary',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** While above zero nothing is recorded (see withoutRecording). */
    private static int $muted = 0;

    // ─── Relationships ───────────────────────────────────────

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    // ─── Writing ─────────────────────────────────────────────

    /**
     * Write one line.
     *
     * @param  array{unit_id?: ?int, metadata?: array, actor?: ?User, role?: string, at?: \DateTimeInterface}  $options
     *                                                                                                                   `actor` defaults to the signed-in user (none in a queue job or webhook). `role` forces a role when
     *                                                                                                                   the signed-in user is not who acted (an online payment settled by a webhook is the tenant's).
     */
    public static function record(int $propertyId, string $action, string $summary, array $options = []): ?self
    {
        return self::attempt(fn () => self::write($propertyId, $action, $summary, $options), $propertyId, $action);
    }

    /**
     * Write a line, or fold this event into the line the same actor wrote moments ago. One request that adds
     * five photos, or one payment that is spread over three months, is one line, not five.
     *
     * The line is found by `$batchKey` (stored in its metadata) among that property's last few lines of the same
     * action inside `$withinSeconds`. `$build` gets the previous metadata (empty for a new line) and returns
     * [summary, metadata].
     *
     * @param  Closure(array): array{0: string, 1: array}  $build
     */
    public static function recordMerged(
        int $propertyId,
        string $action,
        string $batchKey,
        Closure $build,
        array $options = [],
        int $withinSeconds = 120,
    ): ?self {
        return self::attempt(function () use ($propertyId, $action, $batchKey, $build, $options, $withinSeconds) {
            if (self::$muted > 0) {
                return null;
            }

            $recent = self::query()
                ->where('property_id', $propertyId)
                ->where('action', $action)
                ->where('created_at', '>=', now()->subSeconds($withinSeconds))
                ->orderByDesc('activity_id')
                ->limit(5)
                ->get()
                ->first(fn (self $row) => ($row->metadata['batch'] ?? null) === $batchKey);

            [$summary, $metadata] = $build($recent?->metadata ?? []);
            $metadata['batch'] = $batchKey;

            if ($recent) {
                $recent->update(['summary' => Str::limit($summary, 250, '…'), 'metadata' => $metadata]);

                return $recent;
            }

            return self::write($propertyId, $action, $summary, ['metadata' => $metadata] + $options);
        }, $propertyId, $action);
    }

    /**
     * Run $callback without recording anything it does. For code that takes several records away as part of
     * one bigger action which already has its own line (removing a unit removes its photos).
     */
    public static function withoutRecording(Closure $callback): mixed
    {
        self::$muted++;

        try {
            return $callback();
        } finally {
            self::$muted--;
        }
    }

    /**
     * Run an observer's whole body so that a lookup that fails before a line is even built (a missing row, a
     * lazy-loading violation) also cannot reach the action being described.
     */
    public static function safely(Closure $work): void
    {
        if (self::$muted > 0) {
            return;
        }

        try {
            $work();
        } catch (\Throwable $e) {
            Log::warning('PropertyActivity: observer failed', ['error' => $e->getMessage()]);
        }
    }

    /** True while the property is still an unsubmitted wizard draft: edits made on the way are not history. */
    public static function isDraft(int $propertyId): bool
    {
        return Property::query()->whereKey($propertyId)->value('publication_status') === 'Draft';
    }

    /** A failed history line must never undo or block the action it describes. */
    private static function attempt(Closure $write, int $propertyId, string $action): ?self
    {
        try {
            return $write();
        } catch (\Throwable $e) {
            Log::warning('PropertyActivity: could not record', [
                'property_id' => $propertyId,
                'action'      => $action,
                'error'       => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function write(int $propertyId, string $action, string $summary, array $options): ?self
    {
        if (self::$muted > 0) {
            return null;
        }

        [$actor, $role, $name] = self::resolveActor($propertyId, $options);

        return self::create([
            'property_id' => $propertyId,
            'unit_id'     => $options['unit_id'] ?? null,
            'actor_id'    => $actor?->user_id,
            'actor_role'  => $role,
            'actor_name'  => $name,
            'action'      => $action,
            'summary'     => Str::limit($summary, 250, '…'), // the column is varchar(255)
            'metadata'    => ($options['metadata'] ?? []) ?: null,
            'created_at'  => $options['at'] ?? now(),
        ]);
    }

    /**
     * Who acted, as a role: the signed-in user is the property's landlord (landlord), an admin (admin), or
     * someone else, which on a listing means a tenant (tenant). Nobody signed in is the system.
     *
     * @return array{0: ?User, 1: string, 2: string} actor, role, the name to keep
     */
    private static function resolveActor(int $propertyId, array $options): array
    {
        $actor = array_key_exists('actor', $options) ? $options['actor'] : Auth::user();
        $role = $options['role'] ?? null;

        if ($role === null) {
            if (! $actor) {
                $role = 'system';
            } else {
                $landlordId = (int) Property::query()->whereKey($propertyId)->value('landlord_id');
                $role = (int) $actor->user_id === $landlordId
                    ? 'landlord'
                    : ($actor->hasRole('Admin') ? 'admin' : 'tenant');
            }
        }

        $name = $role === 'system' || ! $actor
            ? 'System'
            : (trim($actor->first_name.' '.$actor->last_name) ?: 'Someone');

        return [$role === 'system' ? null : $actor, $role, $name];
    }

    /** A person's name for a summary: first and last name only, never contact details. */
    public static function nameOf(?int $userId, string $fallback = 'A tenant'): string
    {
        if (! $userId) {
            return $fallback;
        }

        $user = User::query()->whereKey($userId)->first(['user_id', 'first_name', 'last_name']);

        return trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')) ?: $fallback;
    }

    // ─── Reading ─────────────────────────────────────────────

    /** The role as the owning landlord reads it: their own lines say "You". */
    public function roleLabel(): string
    {
        return match ($this->actor_role) {
            'landlord' => 'You',
            'tenant'   => 'Tenant',
            'admin'    => 'Admin',
            default    => 'System',
        };
    }

    /** Where a line leads, when it leads anywhere. Units open the page's own modal instead (see the view). */
    public function targetUrl(): ?string
    {
        $meta = $this->metadata ?? [];
        $reservationId = $meta['reservation_id'] ?? null;

        if (str_starts_with($this->action, 'payment.') && $reservationId) {
            return route('landlord.payments.ledger', $reservationId);
        }

        if (str_starts_with($this->action, 'reservation.') && $reservationId) {
            if (in_array($meta['to_status'] ?? null, ['Occupied', 'Completed'], true)) {
                return route('landlord.contracts.index', ['open' => $reservationId]);
            }

            return ! empty($meta['conversation_id'])
                ? route('conversations.index', ['active' => $meta['conversation_id']])
                : route('landlord.reservations.index');
        }

        if ($this->action === 'review.posted') {
            return route('landlord.reviews.index');
        }

        return null;
    }
}
