<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'reporter_id',
        'property_id',
        'reported_user_id',
        'report_reason',
        'report_status',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
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

    public function resolve(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->report_status = 'Resolved';
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