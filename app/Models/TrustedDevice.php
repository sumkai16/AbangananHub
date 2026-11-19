<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A browser an admin chose to remember after passing two-factor. Written only through TrustedDevices. */
class TrustedDevice extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'token_hash', 'user_agent', 'ip_address', 'last_used_at', 'expires_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'created_at'   => 'datetime',
    ];
}
