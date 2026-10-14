<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * The one place that talks to Cloudinary for user photos. Bound in the container so
 * tests can swap in a fake instead of hitting the network.
 */
class ImageUploader
{
    /** @return array{url: string, public_id: string} */
    public function upload(UploadedFile $file, string $folder): array
    {
        $result = cloudinary()->uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder,
            'resource_type' => 'image',
        ]);

        return ['url' => $result['secure_url'], 'public_id' => $result['public_id']];
    }
}
