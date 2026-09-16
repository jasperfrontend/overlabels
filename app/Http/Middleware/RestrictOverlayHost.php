<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the second domain (overlabels.net) to the hosted overlay and nothing
 * else.
 *
 * Why a second domain exists at all: Chrome remembers zoom per ORIGIN, so a
 * hosted overlay open in a tab next to the dashboard zooms with it. Serving
 * the OBS browser-source URL from its own origin ends that. It is additive -
 * overlabels.com keeps serving every existing OBS URL exactly as before.
 *
 * Why it must be gated: the public share pages (`overlay.public`, its `.md`
 * twin, the screenshot) are indexed by search engines under overlabels.com
 * and must stay the only copy. So on the overlay host, anything that is not
 * the authenticated overlay page or the overlay API is a 404, as if the
 * route did not exist there.
 *
 * Host comes from `config('app.overlay_url')` (`OVERLAY_URL`). Unset, which
 * is every local install, and this does nothing. It runs in the `web` and
 * `api` groups rather than globally because group middleware runs AFTER the
 * route is matched, which is what makes the allowlist a list of route names
 * instead of a second copy of the path patterns. Static files never reach
 * PHP (Caddy serves `public/` itself), so `/build/*` and the favicon need
 * no entry.
 */
class RestrictOverlayHost
{
    /**
     * Route names that answer on the overlay host. Everything else 404s there.
     */
    public const ALLOWED_ROUTES = [
        'overlay.authenticated',
        'api.overlay.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $overlayHost = self::overlayHost();

        if ($overlayHost === null || strcasecmp($request->getHost(), $overlayHost) !== 0) {
            return $next($request);
        }

        $name = $request->route()?->getName();

        if ($name === null || ! self::isAllowed($name)) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * The overlay host from config, or null when the feature is off.
     */
    public static function overlayHost(): ?string
    {
        $url = config('app.overlay_url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    public static function isAllowed(string $routeName): bool
    {
        return Str::is(self::ALLOWED_ROUTES, $routeName);
    }
}
