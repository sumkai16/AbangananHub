<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One phone registered for Expo push. The token is unique, so a token that changes hands (a shared phone,
 * a second login) moves to the new user instead of being duplicated.
 */
class UserDevice extends Model
{
    protected $primaryKey = 'device_id';

    protected $fillable = [
        'user_id',
        'expo_push_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
