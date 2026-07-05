<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Conversation extends Model
{
    protected $primaryKey = 'conversation_id';
    protected $fillable = [
        'tenant_id',
        'landlord_id',
        'property_id',
        'unit_id',
        'status',
    ];
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id', 'unit_id');
    }
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id', 'conversation_id');
    }
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id', 'conversation_id')
            ->latestOfMany('message_id');
    }
    public function isResolved(): bool
    {
        return $this->status === 'Resolved';
    }
    public function isCancelled(): bool
    {
        return $this->status === 'Cancelled';
    }
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'conversation_id', 'conversation_id');
    }
    public function activeReservation(): HasOne
    {
        return $this->hasOne(Reservation::class, 'conversation_id', 'conversation_id')
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
            ->latestOfMany('reservation_id');
    }
}