<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyMedia extends Model
{
    protected $table = 'property_media';

    public $timestamps = false;

    protected $fillable = [
        'property_id',
        'media_type',
        'media_url',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isImage(): bool
    {
        return $this->media_type === 'Image';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'Video';
    }
}