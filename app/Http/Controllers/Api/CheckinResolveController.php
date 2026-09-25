<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geo\PlaceResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public place lookup behind the homepage's !checkin demo.
 *
 * The demo is the real product: a visitor types `!checkin Avarua` into the
 * globe on the front page and this endpoint resolves it through the SAME
 * gazetteer and the SAME resolver the bot uses (PlaceResolverService), miss
 * reply included, verbatim. It only reads: no pin is stored, no event fires,
 * no channel is touched. Fuzzy matching is the product's behaviour and is
 * mirrored as-is - "xyzzy" landing on Khizi, AZ is what a real chat gets too.
 *
 * Contract (the homepage JS codes against these shapes):
 *   200 {"place": {name, country_code, country, label, lat, lng}}
 *   404 {"place": null, "reply": "<the bot's miss reply>"}
 *   422 {"place": null, "reply": "<short sentence>"}   missing, blank or > 120 chars
 *   429 from throttle:30,1 (per IP)
 *
 * Hits are cached per normalized lowercase query for 24 hours. Misses are
 * cached for minutes only: a miss still runs indexed lookups, so it is cheap,
 * and the cache shares one 200 MB allkeys-lru Redis with the rate limiter,
 * the unique-chatters sets and the stream-state keys - a day of every
 * distinct junk string an anonymous visitor can type would evict those.
 *
 * Browser callers must be the homepage itself. Any other site could
 * otherwise fetch() this as a free geocoder from its own visitors' browsers
 * (CORS is open on api/* for overlay tokens), spreading the per-IP throttle
 * across an audience that is not ours. Browsers set Origin and
 * Sec-Fetch-Site and page script cannot forge them, so a cross-site caller
 * gets a 404; curl never was the concern.
 *
 * As a GET, the query travels in the request URI, so it is in the Caddy
 * access log like every other GET's query string. Nothing else logs it.
 */
class CheckinResolveController extends Controller
{
    /** Mirrors PlaceResolverService::MAX_QUERY_LENGTH, which is private. */
    private const int MAX_QUERY_LENGTH = 120;

    private const int HIT_CACHE_HOURS = 24;

    private const int MISS_CACHE_MINUTES = 5;

    public function __construct(private readonly PlaceResolverService $resolver) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! self::isFirstParty($request)) {
            abort(404);
        }

        $raw = $request->query('q');

        if (! is_string($raw)) {
            return $this->reject('Type a place first - like Rotterdam, NL');
        }

        $query = self::normalizeQuery($raw);

        if ($query === '') {
            return $this->reject('Type a place first - like Rotterdam, NL');
        }

        if (mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            return $this->reject('That is too long. Try City, CC - like Rotterdam, NL');
        }

        // A stored null reads as "not cached", so the answer is wrapped in an
        // array; the TTL depends on which answer it is (see the class docblock).
        $key = self::cacheKey($query);
        $cached = Cache::get($key);

        if (! is_array($cached)) {
            $place = $this->resolver->resolve($query);

            $cached = ['place' => $place ? [
                'name' => $place->name,
                'country_code' => $place->countryCode,
                'country' => $place->countryName,
                'label' => $place->label(),
                'lat' => $place->lat,
                'lng' => $place->lng,
            ] : null];

            Cache::put(
                $key,
                $cached,
                $place ? now()->addHours(self::HIT_CACHE_HOURS) : now()->addMinutes(self::MISS_CACHE_MINUTES),
            );
        }

        if ($cached['place'] === null) {
            // Byte-identical to BotCheckinController's miss reply: the demo
            // must say exactly what the bot says in chat.
            return response()->json([
                'place' => null,
                'reply' => "Couldn't find that place. Try City, CC - like Rotterdam, NL",
            ], 404);
        }

        return response()->json(['place' => $cached['place']]);
    }

    /**
     * Trim and collapse internal whitespace. Case is left alone here so the
     * resolver sees what the visitor typed; the cache key lowercases.
     */
    public static function normalizeQuery(string $raw): string
    {
        return trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
    }

    /**
     * `checkin:resolve:<sha1 of the lowercased normalized query>`. Hashed so
     * a visitor's free text never becomes a cache key: keys are listed by
     * cache tooling, and a fixed-length key cannot be over-long either.
     */
    public static function cacheKey(string $normalizedQuery): string
    {
        return 'checkin:resolve:'.sha1(mb_strtolower($normalizedQuery));
    }

    /**
     * True unless a browser says the request came from another site. A
     * same-origin GET fetch carries no Origin header and Sec-Fetch-Site
     * "same-origin"; a direct navigation carries "none". A non-browser client
     * sends neither and passes - CORS is a browser concern.
     */
    public static function isFirstParty(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        if (is_string($origin) && $origin !== '' && strcasecmp(rtrim($origin, '/'), $request->getSchemeAndHttpHost()) !== 0) {
            return false;
        }

        $site = $request->headers->get('Sec-Fetch-Site');

        return ! is_string($site) || $site === '' || in_array(strtolower($site), ['same-origin', 'none'], true);
    }

    private function reject(string $reply): JsonResponse
    {
        return response()->json(['place' => null, 'reply' => $reply], 422);
    }
}
