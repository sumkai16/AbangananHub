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
        'remarks',
        'rejection_reason',
    ];
    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'target_move_in_date' => 'date',
            'target_move_out_date' => 'date',
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
    // ─── Status Helpers ──────────────────────────────────────
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
    // ─── State Transitions ───────────────────────────────────
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
}