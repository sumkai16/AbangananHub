<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class UnitMedia extends Model
{
    protected $table = 'unit_media';
    protected $primaryKey = 'media_id';

    protected $fillable = [
        'unit_id',
        'media_type',
        'media_url',
        'cloudinary_public_id',
        'source',
        'caption',
    ];

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id', 'unit_id');
    }

    /**
     * Delete the asset from Cloudinary. Uses the stored public id; rows that predate the column
     * fall back to the old guess (folder + file name from the URL). Videos are a different
     * Cloudinary resource type and must say so or the destroy is a silent no-op. Never throws:
     * a failure is logged and the caller still removes the row.
     */
    public function destroyFromCloudinary(): void
    {
        if (! $this->media_url && ! $this->cloudinary_public_id) {
            return;
        }

        try {
            $publicId = $this->cloudinary_public_id
                ?: 'abanganan/units/' . pathinfo(parse_url($this->media_url, PHP_URL_PATH), PATHINFO_FILENAME);

            cloudinary()->uploadApi()->destroy($publicId, [
                'resource_type' => $this->media_type === 'Video' ? 'video' : 'image',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cloudinary media delete failed', [
                'media_id'  => $this->media_id,
                'media_url' => $this->media_url,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
