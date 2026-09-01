<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only record of consequential admin actions.
 *
 * Rows are written by `record()` from inside the acting controller's
 * DB::transaction() block, so a rolled-back action leaves no phantom entry and
 * a 409 on an idempotency guard doesn't log twice. Nothing updates or deletes
 * an audit row — there is no route, controller method, or UI affordance for it.
 */
class AuditLog extends Model
{
    protected $primaryKey = 'log_id';

    /** Append-only: rows are never edited, so there is no updated_at column. */
    public const UPDATED_AT = null;

    /**
     * Actions that change money, remove data, or override a user's own decision.
     * Drives the red badge on the index — kept here so the view and any future
     * alerting read the same list.
     */
    public const DESTRUCTIVE_ACTIONS = [
        'payment.release',
        'payment.void',
        'user.delete',
        'user.status_change',
        'reservation.force_cancel',
        'reservation.force_reject',
    ];

    /** Every action this app writes, with its human label (drives the filter dropdown). */
    public const ACTION_LABELS = [
        'reservation.force_cancel'  => 'Reservation force-cancelled',
        'reservation.force_reject'  => 'Reservation force-rejected',
        'verification.approve'      => 'Verification approved',
        'verification.reject'       => 'Verification rejected',
        'listing.approve'           => 'Listing approved',
        'listing.reject'            => 'Listing rejected',
        'unit.approve'              => 'Unit approved',
        'unit.reject'               => 'Unit rejected',
        'payment.release'           => 'Payment released',
        // The first landlord-side action to write into this table — every
        // other row here is an admin action. See admin/audit-logs/index for
        // the "Admin" → "Actor" column rename this implied.
        'payment.void'              => 'Payment voided',
        'user.create'               => 'User created',
        'user.update'               => 'User updated',
        'user.status_change'        => 'User status changed',
        'user.delete'               => 'User deleted',
        'review.visibility_change'  => 'Review visibility changed',
        'report.resolve'            => 'Report resolved',
        'settings.update'           => 'System settings updated',
    ];

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_email',
        'action',
        'auditable_type',
        'auditable_id',
        'summary',
        'reason',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Write an audit entry for the currently authenticated actor.
     *
     * Call this inside the same DB::transaction() as the mutation it describes.
     * `$auditable` is the record acted upon; pass null for actions with no
     * surviving target (a hard delete).
     */
    public static function record(
        string $action,
        string $summary,
        ?Model $auditable = null,
        ?string $reason = null,
        array $metadata = [],
    ): self {
        $actor = Auth::user();

        return self::create([
            'actor_id'       => $actor?->user_id,
            // Snapshotted so the row stays readable after the actor is deleted
            // and actor_id is nulled out by the FK's ON DELETE SET NULL.
            'actor_name'     => $actor ? trim($actor->first_name . ' ' . $actor->last_name) : 'System',
            'actor_email'    => $actor?->email ?? '—',
            'action'         => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id'   => $auditable?->getKey(),
            'summary'        => $summary,
            'reason'         => $reason,
            'metadata'       => $metadata ?: null,
            'ip_address'     => request()->ip(),
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDestructive(): bool
    {
        return in_array($this->action, self::DESTRUCTIVE_ACTIONS, true);
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
