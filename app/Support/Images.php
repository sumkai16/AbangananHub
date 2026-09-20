<?php

namespace App\Support;

/**
 * Resizes remote listing photos by rewriting the size hint in their URL, so a
 * 640px card doesn't download the 1200px original.
 *
 * Seeded data is Unsplash (?w=&q=); real landlord uploads are Cloudinary
 * (/upload/ transformation segment). Any other host is returned untouched —
 * we can't resize what we don't control, and a broken URL is worse than a
 * large one.
 */
class Images
{
    /** Widths offered in a srcset — enough steps for phone, tablet, desktop. */
    public const SRCSET_WIDTHS = [320, 640, 960];

    public static function resize(?string $url, int $width): ?string
    {
        if (! $url) {
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
