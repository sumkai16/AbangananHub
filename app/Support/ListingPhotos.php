<?php

namespace App\Support;

/**
 * How many photos a listing may carry, and how big one may be. One place, so the web forms, the mobile API and the
 * hints under the upload boxes cannot drift apart (they used to say 10 in six places).
 * plans/image-and-demo-data-cleanup.md
 */
final class ListingPhotos
{
    /** Most photos one property may have in total. */
    public const MAX_PROPERTY = 6;

    /** Most photos one unit may have in total. */
    public const MAX_UNIT = 6;

    /** A unit is not bookable without this many. */
    public const MIN_UNIT = 3;

    /** Per-file limit in kilobytes. Cloudinary shrinks the stored copy, so a large phone photo is still accepted. */
    public const MAX_KB = 5120;

    /** Longest side of a stored image, in pixels (see ImageUploader::listingPhoto()). */
    public const MAX_SIDE = 2000;

    /** True when adding $adding photos to $existing would pass $max. */
    public static function tooMany(int $existing, int $adding, int $max): bool
    {
        return $existing + $adding > $max;
    }

    public static function propertyLimitMessage(): string
    {
        return 'A property can have at most '.self::MAX_PROPERTY.' photos total. Remove some before adding more.';
    }

    public static function unitLimitMessage(): string
    {
        return 'A unit can have at most '.self::MAX_UNIT.' photos total. Remove some before adding more.';
    }
}
