<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Reservation extends Model
{
    protected $primaryKey = 'reservation_id';
   protected $fillable = [
        'property_id',
        'unit_id',
        'tenant_id',
        'reservation_date',
        'target_move_in_date',
        'target_move_out_date',
        'duration_of_stay',
        'occupants_count',
        'reservation_status',
        'rental_status',
        'agreement_terms_notes',
        'agreed_at',
        'agreed_ip',
        'remarks',
        'rejection_reason',
    ];
    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'target_move_in_date' => 'date',
            'target_move_out_date' => 'date',
            'agreed_at' => 'datetime',
        ];
    }
    // ─── Relationships ───────────────────────────────────────
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
    public function payments()
    {
        return $this->hasMany(Payment::class, 'reservation_id', 'reservation_id');
    }
    // ─── Status Helpers (reservation_status) ─────────────────
    public function isPending(): bool
    {
        return $this->reservation_status === 'Pending';
    }
    public function isApproved(): bool
    {
        return $this->reservation_status === 'Approved';
    }
    public function isRejected(): bool
    {
        return $this->reservation_status === 'Rejected';
    }
    public function isCancelled(): bool
    {
        return $this->reservation_status === 'Cancelled';
    }
    // ─── State Transitions (reservation_status) ──────────────
    public function approve(): bool
    {
        if (!$this->isPending()) {
            return false;
        }
        $this->reservation_status = 'Approved';
        return $this->save();
    }
    public function reject(): bool
    {
        if (!$this->isPending()) {
            return false;
        }
        $this->reservation_status = 'Rejected';
        return $this->save();
    }
    public function cancel(): bool
    {
        if (!$this->isPending() && !$this->isApproved()) {
            return false;
        }
        $this->reservation_status = 'Cancelled';
        return $this->save();
    }

    // ─── Aux Status Helpers (rental_status) ──────────────────
    public function isRentalStatus(string $status): bool
    {
        return $this->rental_status === $status;
    }

    public function isAgreementSigned(): bool
    {
        return $this->rental_status === 'Rental Agreement Signed';
    }

    public function isOccupied(): bool
    {
        return $this->rental_status === 'Occupied';
    }

    // ─── Aux State Transitions (rental_status) ───────────────
    // All transitions past Inquiry require the reservation to be Approved first.

    public function advanceToNegotiation(): bool
    {
        if (!$this->isApproved() || $this->rental_status !== 'Inquiry') {
            return false;
        }
        $this->rental_status = 'Under Negotiation';
        return $this->save();
    }

    public function advanceToPendingAgreement(?string $terms = null): bool
    {
        if (!$this->isApproved() || $this->rental_status !== 'Under Negotiation') {
            return false;
        }
        if ($terms !== null) {
            $this->agreement_terms_notes = $terms;
        }
        $this->rental_status = 'Pending Rental Agreement';
        return $this->save();
    }

    public function signAgreement(string $ip): bool
    {
        if (!$this->isApproved() || $this->rental_status !== 'Pending Rental Agreement') {
            return false;
        }
        $this->rental_status = 'Rental Agreement Signed';
        $this->agreed_at = now();
        $this->agreed_ip = $ip;
        return $this->save();
    }

    public function markOccupied(): bool
    {
        if (!$this->isApproved() || $this->rental_status !== 'Rental Agreement Signed') {
            return false;
        }
        $this->rental_status = 'Occupied';
        return $this->save();
    }
}