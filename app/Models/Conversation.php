<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'tenant_id',
        'landlord_id',
        'property_id',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('sent_at');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    // ─── Helpers ─────────────────────────────────────────────

    public static function findOrStartConversation(int $tenantId, int $landlordId, int $propertyId): self
    {
        return static::firstOrCreate([
            'tenant_id'   => $tenantId,
            'landlord_id' => $landlordId,
            'property_id' => $propertyId,
        ]);
    }
}