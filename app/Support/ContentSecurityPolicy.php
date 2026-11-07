<?php

namespace App\Support;

/**
 * The Content-Security-Policy string.
 *
 * What it does today: stops other sites framing ours (frame-ancestors), blocks plugins and <base> tricks, limits
 * where forms may post and where scripts, styles, fonts, frames and connections may come from, and lets images
 * come from any https site (Cloudinary, Google and Facebook profile photos, map tiles).
 *
 * What it does NOT do: stop an injected inline script. The app is built on inline <script> blocks, inline
 * on-click handlers and Alpine (which evaluates expressions), so script-src needs 'unsafe-inline' and
 * 'unsafe-eval'. Closing that gap means moving scripts to files and using Alpine's CSP build with nonces, a
 * separate piece of work. Output escaping is the defence against injected script until then.
 */
final class ContentSecurityPolicy
{
    /** Places the map pages call from the browser (geocoding and routing). */
    private const CONNECT = [
        'https://nominatim.openstreetmap.org',
        'https://router.project-osrm.org',
    ];

    /** Where a form may send the user: this site, plus the payment page and the sign-in providers it redirects to. */
    private const FORM_ACTION = [
        'https://checkout.paymongo.com',
        'https://*.paymongo.com',
        'https://accounts.google.com',
        'https://www.facebook.com',
    ];

    public static function build(): string
    {
        $dev = self::devServer();
        $ws = self::websocket();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", ...$dev],
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev],
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'font-src' => ["'self'", 'data:', ...$dev],
            'media-src' => ["'self'", 'blob:', 'data:'],
            'connect-src' => ["'self'", ...self::CONNECT, ...$ws, ...array_map(fn ($o) => str_replace('http', 'ws', $o), $dev), ...$dev],
            'frame-src' => ["'self'", 'blob:'],
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'", ...self::FORM_ACTION],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'worker-src' => ["'self'", 'blob:'],
            'report-uri' => ['/csp-report'],
        ];

        return implode('; ', array_map(fn ($name, $values) => $name.' '.implode(' ', $values), array_keys($directives), $directives));
    }

    /** Camera for ID and listing photos, location for the map picker; nothing else is wanted. */
    public static function permissions(): string
    {
        return 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()';
    }

    /** The Vite dev server, only while `npm run dev` is running on a non-production machine. */
    private static function devServer(): array
    {
        if (app()->isProduction() || ! is_file(public_path('hot'))) {
            return [];
        }

        $url = trim((string) @file_get_contents(public_path('hot')));
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (! $host || ! $port) {
            return [];
        }

        $host = trim($host, '[]');
        $hosts = array_unique([$host, 'localhost', '127.0.0.1']);

        return array_map(fn ($h) => 'http://'.(str_contains($h, ':') ? "[$h]" : $h).':'.$port, $hosts);
    }

    private static function websocket(): array
    {
        $host = config('security.ws.host');

        if (! $host) {
            return [];
        }

        // Both schemes for this one host: the chat client's scheme is fixed when the assets are built, so it can
        // disagree with the .env read at runtime (seen locally: a bundle built for wss, .env says http).
        $port = config('security.ws.port');

        return array_map(
            fn ($scheme) => $scheme.'://'.$host.($port && ! in_array((int) $port, [80, 443], true) ? ':'.$port : ''),
            config('security.ws.scheme') === 'https' ? ['wss'] : ['ws', 'wss'],
        );
    }
}
