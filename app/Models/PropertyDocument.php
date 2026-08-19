<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyDocument extends Model
{
    protected $primaryKey = 'document_id';

    public const TYPES = [
        'Proof of Ownership',
        'Tax Declaration',
        "Authorization / Special Power of Attorney",
        "Business or Mayor's Permit",
        'Occupancy Permit',
        'Fire Safety Certificate',
        'Other',
    ];

    protected $fillable = [
        'property_id',
        'document_type',
        'file_path',
        'file_name',
        'document_number',
        'status',
        'rejection_reason',
        'expiry_date',
        'verified_by',
        'verified_at',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }

    // ─── Status Helpers ──────────────────────────────────────

    /**
     * A stored 'Verified' document ages into 'Expired' once its expiry_date
     * passes — there is no scheduled job maintaining that transition, so
     * every read must go through this (or getDisplayStatusAttribute()/
     * scopeCurrentlyValid()) rather than the raw `status` column.
     */
    public function isExpired(): bool
    {
        return $this->status === 'Verified'
            && $this->expiry_date !== null
            && $this->expiry_date->isPast();
    }

    public function isRequested(): bool
    {
        return $this->file_path === null;
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->isExpired() ? 'Expired' : $this->status;
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeCurrentlyValid($query)
    {
        return $query->where('status', 'Verified')
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString());
            });
    }
}
