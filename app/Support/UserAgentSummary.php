<?php

namespace App\Support;

/** "Chrome on Android" from a raw User-Agent. A small table, no package: good enough for a sign-in list. */
class UserAgentSummary
{
    public static function from(?string $ua): string
    {
        if (! $ua) {
            return 'Unknown device';
        }

        $browser = match (true) {
            (bool) preg_match('~Edg(e|A|iOS)?/~', $ua)            => 'Edge',
            (bool) preg_match('~OPR/|Opera~', $ua)                => 'Opera',
            (bool) preg_match('~Firefox/|FxiOS/~', $ua)           => 'Firefox',
            (bool) preg_match('~Chrome/|CriOS/~', $ua)            => 'Chrome',
            (bool) preg_match('~Safari/~', $ua)                   => 'Safari',
            default                                               => 'Browser',
        };

        $os = match (true) {
            (bool) preg_match('~Android~', $ua)                   => 'Android',
            (bool) preg_match('~iPhone|iPad|iPod~', $ua)          => 'iOS',
            (bool) preg_match('~Windows~', $ua)                   => 'Windows',
            (bool) preg_match('~Mac OS X|Macintosh~', $ua)        => 'macOS',
            (bool) preg_match('~Linux|X11~', $ua)                 => 'Linux',
            default                                               => null,
        };

        return $os ? "{$browser} on {$os}" : $browser;
    }
}
