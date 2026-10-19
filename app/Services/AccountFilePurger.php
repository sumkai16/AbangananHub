<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the files a deleted account leaves behind: verification ID/selfie images on the private disk, the
 * profile picture and the business logo on Cloudinary. Two steps because the rows cascade away with the user:
 * snapshot() before the delete, purge() after it succeeds. Best effort, a failure is logged and never blocks.
 */
class AccountFilePurger
{
    /** @return array{disk: string[], public: string[], cloudinary: string[]} */
    public static function snapshot(User $user): array
    {
        $files = ['disk' => [], 'public' => [], 'cloudinary' => []];

        try {
            $application = $user->verificationApplication()->first();

            if ($application) {
                foreach ([$application->government_id, $application->id_back, $application->selfie] as $path) {
                    if (filled($path)) {
                        $files['disk'][] = $path;
                    }
                }

                if (filled($application->logo_url) && ! str_starts_with($application->logo_url, 'http')) {
                    $files['public'][] = $application->logo_url;
                }
            }

            $logoId = $user->rentalBusiness()->value('logo_public_id');
            if (filled($logoId)) {
                $files['cloudinary'][] = $logoId;
            }

            if ($publicId = self::publicIdFromUrl($user->profile_picture)) {
                $files['cloudinary'][] = $publicId;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not list account files for cleanup.', ['user_id' => $user->getKey(), 'error' => $e->getMessage()]);
        }

        return $files;
    }

    public static function purge(array $files): void
    {
        foreach ($files['disk'] ?? [] as $path) {
            try {
                Storage::disk('local')->delete($path);
            } catch (\Throwable $e) {
                Log::warning('Could not delete a private account file.', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        foreach ($files['public'] ?? [] as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                Log::warning('Could not delete a public account file.', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        foreach ($files['cloudinary'] ?? [] as $publicId) {
            try {
                cloudinary()->uploadApi()->destroy($publicId);
            } catch (\Throwable $e) {
                Log::warning('Could not delete a Cloudinary asset.', ['public_id' => $publicId, 'error' => $e->getMessage()]);
            }
        }
    }

    /** users.profile_picture stores only the delivery URL, so the public id is recovered from it. */
    public static function publicIdFromUrl(?string $url): ?string
    {
        if (! $url || ! str_contains($url, 'res.cloudinary.com') || ! str_contains($url, '/upload/')) {
            return null;
        }

        $tail = explode('/upload/', $url, 2)[1];
        $tail = preg_replace('#^(?:[^/]*,[^/]*/)*#', '', $tail);
        $tail = preg_replace('#^v\d+/#', '', $tail);
        $tail = preg_replace('#\.[A-Za-z0-9]+$#', '', $tail);

        return $tail !== '' ? $tail : null;
    }
}
