<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a landlord page be shown inside the page panel (an iframe over the list the landlord came from) without its
 * sidebar and top bar: `EmbeddedPage::active()` is what the layout reads.
 *
 * A request counts as embedded when any of these is true:
 *  - it asks for it (`?embed=1`), which is how the panel loads the page;
 *  - the browser says it is an iframe load (`Sec-Fetch-Dest: iframe`), which covers a form posted from inside the panel;
 *  - it was sent from an embedded page of this site (the Referer carries `embed=1`), for plain-http addresses
 *    (a phone on the LAN) where browsers do not send Sec-Fetch-Dest.
 * An embedded request that ends in a redirect to this site gets `embed=1` added to the target, so recording, voiding
 * or reminding lands back on the embedded ledger and not on the full page inside the frame.
 *
 * It only changes what is drawn. Nothing here grants access: the route's own middleware and policy still run.
 */
class EmbeddedPage
{
    public const PARAM = 'embed';

    /** True when the current request is being shown inside the page panel. */
    public static function active(?Request $request = null): bool
    {
        return (bool) ($request ?? request())->attributes->get('embedded', false);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $embedded = $this->isEmbedded($request);
        $request->attributes->set('embedded', $embedded);

        $response = $next($request);

        if ($embedded && $response instanceof RedirectResponse) {
            $response->setTargetUrl($this->withEmbedParam($response->getTargetUrl(), $request));
        }

        return $response;
    }

    private function isEmbedded(Request $request): bool
    {
        return $request->boolean(self::PARAM)
            || $request->header('Sec-Fetch-Dest') === 'iframe'
            || $this->refererIsEmbedded($request);
    }

    private function refererIsEmbedded(Request $request): bool
    {
        $referer = (string) $request->headers->get('referer');
        $parts = parse_url($referer);

        if (! is_array($parts) || ($parts['host'] ?? null) !== $request->getHost()) {
            return false;
        }

        parse_str($parts['query'] ?? '', $query);

        return ($query[self::PARAM] ?? null) === '1';
    }

    /** Adds `embed=1` to a link back into this site; any other address is returned untouched. */
    private function withEmbedParam(string $url, Request $request): string
    {
        $parts = parse_url($url);

        if ($parts === false || (isset($parts['host']) && $parts['host'] !== $request->getHost())) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);

        if (isset($query[self::PARAM])) {
            return $url;
        }

        $query[self::PARAM] = '1';

        return (isset($parts['host']) ? ($parts['scheme'] ?? 'http') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') : '')
            . ($parts['path'] ?? '')
            . '?' . http_build_query($query)
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
