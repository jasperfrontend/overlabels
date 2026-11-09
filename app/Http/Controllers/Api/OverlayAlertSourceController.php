<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The source of one alert template, for an overlay that does not have it.
 *
 * An overlay loads the source of every alert that can fire on it at mount
 * (`alert_sources` in the render payload), and AlertTriggered carries the
 * slug plus a content-hash version rather than the template itself - Reverb
 * refuses a broadcast over 10 KB. When the overlay's copy is missing (alert
 * created after OBS loaded the overlay) or stale (edited since), and the
 * broadcast was too big to inline it, the overlay asks here.
 *
 * A read, the same kind of call as api.overlay.render: the token travels in
 * the body, nothing is written, no access is recorded. Only the token owner's
 * own alert templates resolve, and anything else is the same 404, so a token
 * cannot be used to probe which slugs exist.
 */
class OverlayAlertSourceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:255',
            'token' => 'required|string|size:64',
        ]);

        $token = OverlayAccessToken::findByToken($validated['token'], $request->ip());
        if (! $token) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        $template = OverlayTemplate::query()
            ->select(['id', 'slug', 'head', 'html', 'css', 'compiled_css'])
            ->where('slug', $validated['slug'])
            ->where('owner_id', $token->user_id)
            ->where('type', 'alert')
            ->first();

        if (! $template) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        return response()->json([
            'slug' => $template->slug,
            ...$template->alertSource(),
            'compiled_css' => $template->compiled_css ?? '',
        ]);
    }
}
