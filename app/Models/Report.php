<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $primaryKey = 'report_id';

protected $fillable = [
    'reporter_id',
    'property_id',
    'reported_user_id',
    'report_reason',
    'report_status',
    'admin_notes',
    'action_taken',
    'resolved_by',
    'resolved_at',
];

protected function casts(): array
{
    return [
        'resolved_at' => 'datetime',
    ];
}

public function resolver()
{
    return $this->belongsTo(User::class, 'resolved_by', 'user_id');
}

    // ─── Relationships ───────────────────────────────────────

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id', 'user_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id', 'user_id');
    }

    // ─── Status Helpers ──────────────────────────────────────

    public function isPending(): bool
    {
        return $this->report_status === 'Pending';
    }

    public function isResolved(): bool
    {
        return $this->report_status === 'Resolved';
    }

    // ─── State Transitions ───────────────────────────────────

public function resolve(string $notes, ?string $action, int $resolvedBy): bool
{
    if (!$this->isPending()) {
        return false;
    }

    $this->report_status = 'Resolved';
    $this->admin_notes = $notes;
    $this->action_taken = $action;
    $this->resolved_by = $resolvedBy;
    $this->resolved_at = now();
    return $this->save();
}

    // ─── Scopes ──────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('report_status', 'Pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('report_status', 'Resolved');
    }
}