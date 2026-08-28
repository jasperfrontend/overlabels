<?php

namespace App\Http\Middleware;

use App\Models\Update;
use App\Models\UpdateInteraction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a What's New entry as visited when the reader lands on the page it
 * points at, however they got there.
 *
 * The point is that arriving at /wiring greys the "Wiring status" row out even
 * if you found it from the sidebar, from a bookmark, or by typing the URL. A
 * card that keeps shouting about a page you already went to is the same
 * mistake as a badge nobody notices, in the other direction.
 *
 * Cost control matters here, because this runs on every authenticated request.
 * Update::ctaTargets() is a cached array of the handful of routes any live
 * entry advertises; a request whose route is not in it does zero database
 * work, which is nearly all of them.
 */
class MarkWhatsNewVisited
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $request->user()) {
            return $response;
        }

        // Only a page the reader actually landed on counts. A redirect or an
        // error means they never saw it.
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $name = $request->route()?->getName();
        $path = trim($request->path(), '/') ?: '/';
        $targets = Update::ctaTargets();

        $matchesRoute = $name !== null && in_array($name, $targets['routes'], true);
        $matchesPath = in_array($path, $targets['paths'], true);

        if (! $matchesRoute && ! $matchesPath) {
            return $response;
        }

        $user = $request->user();

        // Only rows still on the card and not already grey. Without the second
        // condition this would write on every single page view of the target.
        $candidates = Update::query()
            ->unseenBy($user)
            ->whereDoesntHave('interactions', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereNotNull('visited_at'))
            ->get(['id', 'cta_route', 'cta_url']);

        foreach ($candidates as $update) {
            if (! self::pointsAt($update, $name, $path)) {
                continue;
            }

            UpdateInteraction::query()->updateOrCreate(
                ['user_id' => $user->id, 'update_id' => $update->id],
                ['visited_at' => now()],
            );
        }

        return $response;
    }

    /**
     * Whether this entry advertises the page that was just served.
     *
     * Route names match on the name alone, ignoring parameters: a CTA pointing
     * at a filtered view of a page is still satisfied by arriving at that page,
     * and demanding an exact query string would leave rows stuck teal for
     * readers who did exactly what was asked.
     */
    private static function pointsAt(Update $update, ?string $name, string $path): bool
    {
        if ($update->cta_route !== null && $update->cta_route === $name) {
            return true;
        }

        if ($update->cta_url === null || ! str_starts_with($update->cta_url, '/')) {
            return false;
        }

        $target = trim((string) parse_url($update->cta_url, PHP_URL_PATH), '/') ?: '/';

        return $target === $path;
    }
}
