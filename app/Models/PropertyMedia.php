<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyMedia extends Model
{
    protected $table = 'property_media';
    protected $primaryKey = 'media_id';

    protected $fillable = [
        'property_id',
        'media_type',
        'media_url',
        'cloudinary_public_id',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
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