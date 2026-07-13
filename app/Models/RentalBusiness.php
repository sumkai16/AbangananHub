<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalBusiness extends Model
{
    protected $primaryKey = 'business_id';

    protected $fillable = [
        'landlord_id',
        'business_name',
        'description',
        'logo_url',
        'logo_public_id',
        'contact_number',
        'business_address',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }
}