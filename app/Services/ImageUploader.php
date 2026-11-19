<?php

namespace App\Services;

use App\Support\ListingPhotos;
use Illuminate\Http\UploadedFile;

/**
 * The one place that talks to Cloudinary for user photos. Bound in the container so
 * tests can swap in a fake instead of hitting the network.
 */
class ImageUploader
{
    /**
     * Cloudinary options for a property or unit photo. The incoming transformation shrinks the stored copy to
     * 2000px on its longest side at automatic quality, so a 5 MB phone photo is kept at a fraction of that. The
     * format is left as uploaded; delivery turns it into WebP or AVIF through `f_auto` (see Images::resize()).
     *
     * @return array<string, mixed>
     */
    public static function listingPhoto(string $folder): array
    {
        return [
            'folder' => $folder,
            'resource_type' => 'image',
            'transformation' => [
                'width' => ListingPhotos::MAX_SIDE,
                'height' => ListingPhotos::MAX_SIDE,
                'crop' => 'limit',
                'quality' => 'auto:good',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options  extra Cloudinary options, e.g. a crop transformation
     * @return array{url: string, public_id: string}
     */
    public function upload(UploadedFile $file, string $folder, array $options = []): array
    {
        $result = cloudinary()->uploadApi()->upload($file->getRealPath(), $options + [
            'folder' => $folder,
            'resource_type' => 'image',
        ]);

        return ['url' => $result['secure_url'], 'public_id' => $result['public_id']];
    }
}
