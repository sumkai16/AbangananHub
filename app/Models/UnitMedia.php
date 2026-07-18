<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitMedia extends Model
{
    protected $table = 'unit_media';
    protected $primaryKey = 'media_id';

    protected $fillable = [
        'unit_id',
        'media_type',
        'media_url',
        'source',
    ];

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id', 'unit_id');
    }
}