<?php

namespace App\Observers;

use App\Models\PropertyActivity;
use App\Models\PropertyMedia;
use Illuminate\Support\Facades\Auth;

/**
 * Photos and videos added to or removed from the listing. One line per person per minute or two, however many
 * files were in the request ("Added 4 photos"). Quiet while the property is a draft.
 */
class PropertyMediaObserver
{
    public function created(PropertyMedia $media): void
    {
        $this->note($media, 'added');
    }

    public function deleted(PropertyMedia $media): void
    {
        $this->note($media, 'removed');
    }

    private function note(PropertyMedia $media, string $verb): void
    {
        PropertyActivity::safely(function () use ($media, $verb) {
            if (PropertyActivity::isDraft($media->property_id)) {
                return;
            }

            $kind = $media->isVideo() ? 'video' : 'photo';

            PropertyActivity::recordMerged(
                $media->property_id,
                "{$kind}.{$verb}",
                "{$kind}.{$verb}:".(Auth::id() ?? 0),
                function (array $previous) use ($verb, $kind) {
                    $count = ($previous['count'] ?? 0) + 1;
                    $noun = $count === 1 ? $kind : $kind.'s';

                    return [ucfirst($verb).' '.($count === 1 ? 'a' : $count)." {$noun}", ['count' => $count]];
                },
            );
        });
    }
}
