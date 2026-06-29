<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyMedia extends Model
{
    protected $table = 'property_media';
    protected $primaryKey = 'media_id';

    public $timestamps = false;

    protected $fillable = [
        'property_id',
        'unit_id',
        'media_type',
        'media_url',
        'cloudinary_public_id',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id', 'unit_id');
    }

    public function isImage(): bool
    {
        return $this->media_type === 'Image';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'Video';
    }
}