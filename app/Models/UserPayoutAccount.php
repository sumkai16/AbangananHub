<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a landlord is paid: the GCash number and the name on the account. One row per user, kept off
 * `users` so it is never part of a nested user payload. Written through User::savePayoutAccount().
 */
class UserPayoutAccount extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'gcash_number',
        'gcash_account_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
