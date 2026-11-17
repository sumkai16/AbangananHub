<?php

namespace App\Support;

/**
 * Resizes remote listing photos by rewriting the size hint in their URL, so a
 * 640px card doesn't download the 1200px original.
 *
 * Seeded data is a set of local files under public/images/seed (site-relative paths like /images/seed/<id>.jpg, so the
 * demo needs no internet); Unsplash URLs (?w=&q=) are still understood for older rows; real landlord uploads are Cloudinary
 * (/upload/ transformation segment). Any other host is returned untouched —
 * we can't resize what we don't control, and a broken URL is worse than a
 * large one.
 */
class Images
{
    /** Widths offered in a srcset — enough steps for phone, tablet, desktop. */
    public const SRCSET_WIDTHS = [320, 640, 960];

    /**
     * The sizes a listing photo is shown at. Use these names instead of a bare number so every page agrees:
     * thumb = list rows and small tiles, card = cards and grids, gallery = a property or unit page, full = the
     * full-screen lightbox. plans/image-and-demo-data-cleanup.md
     */
    public const SIZES = ['thumb' => 160, 'card' => 640, 'gallery' => 1200, 'full' => 1600];

    /** The URL at one of the named sizes (thumb, card, gallery, full). */
    public static function src(?string $url, string $size): ?string
    {
        return self::resize($url, self::SIZES[$size]);
    }

    public static function resize(?string $url, int $width): ?string
    {
        if (! $url) {
            return $url;
        }

        // A site-relative path (the seeded local photos): there is nothing to resize, and it has no host to look up.
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host === 'images.unsplash.com') {
            $base = strtok($url, '?');
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $query['w'] = $width;
            $query['q'] = $query['q'] ?? 80;
            $query['auto'] = 'format';

            return $base.'?'.http_build_query($query);
        }

        if ($host === 'res.cloudinary.com' && str_contains($url, '/upload/')) {
            // f_auto/q_auto let Cloudinary serve WebP/AVIF at a sane quality.
            return preg_replace('#/upload/(?:[a-z]_[^/]+/)*#', '/upload/f_auto,q_auto,w_'.$width.'/', $url, 1);
        }

        return $url;
    }

    /**
     * The URL with a host, for places that leave the page (the mobile API, emails): a site-relative path such as
     * /images/seed/x.jpg becomes an absolute URL on this site; anything already absolute is returned as it is.
     */
    public static function absolute(?string $url): ?string
    {
        if (! $url || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }

        return url($url);
    }

    /** A srcset string for the URL, or null when it can't be resized. */
    public static function srcset(?string $url): ?string
    {
        if (! $url || self::resize($url, 640) === $url) {
            return null;
        }

        return collect(self::SRCSET_WIDTHS)
            ->map(fn (int $w) => self::resize($url, $w).' '.$w.'w')
            ->implode(', ');
    }
}
