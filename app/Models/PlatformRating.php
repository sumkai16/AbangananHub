<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A user's rating of AbangananHub itself (not of a property or a tenant). One per user. */
class PlatformRating extends Model
{
    protected $primaryKey = 'platform_rating_id';

    protected $fillable = ['user_id', 'rating', 'comment'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
