<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordVerification extends Model
{
    protected $primaryKey = 'verification_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'government_id',
        'business_name',
        'description',
        'logo_url',
        'contact_number',
        'business_address',
        'verification_status',
        'admin_notes',
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
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
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