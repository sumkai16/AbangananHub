<?php

namespace App\Observers;

use App\Models\PropertyActivity;
use App\Models\PropertyUnit;
use App\Models\UnitMedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Photos and videos added to or removed from a unit, rolled up per unit like the listing's own. A unit that
 * is gone (its media goes first when a landlord removes it) leaves no line: the unit's own line says it.
 */
class UnitMediaObserver
{
    public function created(UnitMedia $media): void
    {
        $this->note($media, 'added');
    }

    public function deleted(UnitMedia $media): void
    {
        $this->note($media, 'removed');
    }

    private function note(UnitMedia $media, string $verb): void
    {
        PropertyActivity::safely(function () use ($media, $verb) {
            $unit = PropertyUnit::query()->whereKey($media->unit_id)->first(['unit_id', 'property_id', 'unit_label']);

            if (! $unit || PropertyActivity::isDraft($unit->property_id)) {
                return;
            }

            $kind = $media->media_type === 'Video' ? 'video' : 'photo';
            $where = ' '.($verb === 'added' ? 'to' : 'from').' unit "'.Str::limit((string) $unit->unit_label, 60).'"';

            PropertyActivity::recordMerged(
                $unit->property_id,
                "unit.{$kind}_{$verb}",
                "unit.{$kind}_{$verb}:{$unit->unit_id}:".(Auth::id() ?? 0),
                function (array $previous) use ($verb, $kind, $where) {
                    $count = ($previous['count'] ?? 0) + 1;
                    $noun = $count === 1 ? $kind : $kind.'s';

                    return [ucfirst($verb).' '.($count === 1 ? 'a' : $count)." {$noun}{$where}", ['count' => $count]];
                },
                ['unit_id' => $unit->unit_id],
            );
        });
    }
}
