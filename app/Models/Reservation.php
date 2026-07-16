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
        'conversation_id',
        'reservation_date',
        'target_move_in_date',
        'target_move_out_date',
        'duration_of_stay',
        'occupants_count',
        'rental_status',
        'agreement_terms_notes',
        'agreed_at',
        'agreed_ip',
        'remarks',
        'rejection_reason',
        'landlord_tc_accepted_at',
        'tenant_tc_accepted_at',
        'tenant_confirmed_move_in_at',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'target_move_in_date' => 'date',
            'target_move_out_date' => 'date',
            'agreed_at' => 'datetime',
            'landlord_tc_accepted_at' => 'datetime',
            'tenant_tc_accepted_at' => 'datetime',  
            'tenant_confirmed_move_in_at' => 'datetime',
        ];
    }
public function confirmMoveIn(): bool
{
    if ($this->rental_status !== 'Rental Agreement Signed') {
        return false;
    }

    // Must have a held payment
    if (!$this->payments()->where('status', 'Held')->exists()) {
        return false;
    }

    $this->rental_status = 'Occupied';
    $this->tenant_confirmed_move_in_at = now();
    $this->save();

    if ($this->unit) {
        $this->unit->availability_status = 'Occupied';
        $this->unit->save();
    }

    return true;
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

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function tenantRating()
    {
        return $this->hasOne(TenantRating::class, 'reservation_id', 'reservation_id');
    }
    // ─── Status Helpers ──────────────────────────────────────

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

    public function isLeaseExpired(): bool
    {
        return $this->rental_status === 'Occupied'
            && $this->target_move_out_date
            && $this->target_move_out_date->isPast();
    }

    // ─── State Transitions ───────────────────────────────────

    public function advanceToNegotiation(): bool
    {
        if ($this->rental_status !== 'Inquiry') {
            return false;
        }
        $this->rental_status = 'Under Negotiation';
        return $this->save();
    }

    public function advanceToPendingAgreement(?string $terms = null): bool
    {
        if ($this->rental_status !== 'Under Negotiation') {
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
        if ($this->rental_status !== 'Pending Rental Agreement') {
            return false;
        }
        $this->rental_status = 'Rental Agreement Signed';
        $this->agreed_at = now();
        $this->agreed_ip = $ip;
        return $this->save();
    }

    public function markOccupied(): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
            return false;
        }
        $this->rental_status = 'Occupied';
        $this->save();

        if ($this->unit) {
            $this->unit->availability_status = 'Occupied';
            $this->unit->save();
        }

        return true;
    }

    public function reject(?string $reason = null): bool
    {
        if (in_array($this->rental_status, ['Occupied', 'Cancelled', 'Rejected'])) {
            return false;
        }
        $this->rental_status = 'Rejected';
        if ($reason !== null) {
            $this->rejection_reason = $reason;
        }
        $saved = $this->save();

        if ($saved) {
            $this->cancelLinkedConversation();
            $this->releaseUnit();
        }

        return $saved;
    }

    public function cancel(): bool
    {
        if (in_array($this->rental_status, ['Occupied', 'Cancelled', 'Rejected'])) {
            return false;
        }
        $this->rental_status = 'Cancelled';
        $saved = $this->save();

        if ($saved) {
            $this->cancelLinkedConversation();
            $this->releaseUnit();
        }

        return $saved;
    }

    public function releaseUnit(): void
    {
        if ($this->unit && $this->unit->availability_status !== 'Available') {
            $this->unit->availability_status = 'Available';
            $this->unit->vacated_at = now();
            $this->unit->save();
        }
    }

    protected function cancelLinkedConversation(): void
    {
        if ($this->conversation && !$this->conversation->isCancelled()) {
            $this->conversation->update(['status' => 'Cancelled']);
        }
    }

    public function postSystemMessage(string $text): void
    {
        if (!$this->conversation_id) {
            return;
        }

        Message::create([
            'conversation_id' => $this->conversation_id,
            'sender_id' => null,
            'message' => $text,
            'is_system' => true,
            'is_read' => true,
        ]);
    }
}