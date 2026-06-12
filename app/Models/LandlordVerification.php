<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordVerification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'government_id',
        'verification_status',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at'  => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ─── Status Helpers ──────────────────────────────────────

    public function isPending(): bool
    {
        return $this->verification_status === 'Pending';
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'Approved';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'Rejected';
    }
}