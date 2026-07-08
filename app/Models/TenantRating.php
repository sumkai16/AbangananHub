<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantRating extends Model
{
    protected $primaryKey = 'rating_id';

    protected $fillable = [
        'reservation_id',
        'landlord_id',
        'tenant_id',
        'rating',
        'comment',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }
}