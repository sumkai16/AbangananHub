<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PropertyUnit extends Model
{
    protected $primaryKey = 'unit_id';
    protected $fillable = [
        'property_id',
        'unit_label',
        'rental_fee',
        'occupancy_limit',
        'availability_status',
        'verification_status',
        'rejection_reason',
    ];
    protected function casts(): array
    {
        return [
            'rental_fee' => 'decimal:2',
        ];
    }
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }
    public function media()
    {
        return $this->hasMany(PropertyMedia::class, 'unit_id', 'unit_id');
    }
    // ─── Status Helpers ──────────────────────────────────────
    public function isApproved(): bool
    {
        return $this->verification_status === 'Approved';
    }
    public function isPending(): bool
    {
        return $this->verification_status === 'Pending';
    }
    public function isRejected(): bool
    {
        return $this->verification_status === 'Rejected';
    }
    // ─── Scopes ──────────────────────────────────────────────
    public function scopeApproved($query)
    {
        return $query->where('verification_status', 'Approved');
    }
    public function scopeAvailable($query)
    {
        return $query->where('availability_status', 'Available')
                     ->where('verification_status', 'Approved');
    }
}