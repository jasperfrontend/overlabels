<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\LivingTitleService;
use App\Services\TwitchApiService;
use App\Services\TwitchScopeService;
use App\Services\TwitchTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * /settings/title - the living Twitch title and the category picker.
 *
 * The title half is a template saved to preferences and rendered by
 * LivingTitleService whenever something changes. The category half is a
 * one-shot write: pick one, it is set on Twitch right then, and nothing here
 * remembers it or re-asserts it.
 */
class LivingTitleController extends Controller
{
    public function __construct(
        private readonly LivingTitleService $titles,
        private readonly TwitchApiService $twitch,
        private readonly TwitchTokenService $tokens,
        private readonly TwitchScopeService $scopes,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Title', [
            'livingTitle' => $user->livingTitle(),
            'hasScope' => $this->scopes->hasScope($user, LivingTitleService::SCOPE),
            'channel' => $this->channel($request),
            'maxLength' => LivingTitleService::MAX_LENGTH,
            'debounceSeconds' => LivingTitleService::DEBOUNCE_SECONDS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'template' => 'nullable|string|max:500',
        ]);

        $template = trim((string) ($data['template'] ?? ''));

        $problem = $this->titles->problem($template);

        if ($problem !== null) {
            throw ValidationException::withMessages(['template' => $problem]);
        }

        $enabled = (bool) $data['enabled'] && $template !== '';

        $user = $request->user();
        // A save is the streamer speaking, so a pause ends here, and what was
        // last written is forgotten so the next render always writes. Without
        // that, saving after a pause compared the render against a title
        // Twitch no longer showed, found it equal, and wrote nothing.
        $this->titles->apply($user, [
            'enabled' => $enabled,
            'template' => $template,
            'paused' => false,
            'paused_title' => null,
            'last_error' => null,
            'last_written' => null,
        ]);

        if ($enabled) {
            $this->titles->schedule($user, 0);
        }

        return back()->with('success', $enabled ? 'Living title saved. Twitch updates within a few seconds.' : 'Living title saved and switched off.');
    }

    /**
     * Render the template as typed against the account's real values.
     * Refused templates come back as a validation error with the same
     * sentence the save would show.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template' => 'nullable|string|max:500',
        ]);

        $template = trim((string) ($data['template'] ?? ''));

        $problem = $this->titles->problem($template);

        if ($problem !== null) {
            throw ValidationException::withMessages(['template' => $problem]);
        }

        return response()->json($this->titles->preview($request->user(), $template));
    }

    public function resume(Request $request): RedirectResponse
    {
        $this->titles->resume($request->user());

        return back()->with('success', 'Living title resumed.');
    }

    /**
     * Category search, proxied to Helix with the streamer's own token.
     */
    public function categories(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $user = $request->user();

        if (! $user->access_token || ! $this->tokens->ensureValidToken($user)) {
            return response()->json(['categories' => []]);
        }

        try {
            $categories = $this->twitch->searchCategories($user->access_token, $data['q']);
        } catch (Throwable) {
            $categories = [];
        }

        return response()->json(['categories' => $categories]);
    }

    public function setCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => 'required|string|regex:/^\d{1,20}$/',
            'name' => 'required|string|max:200',
        ]);

        $written = $this->titles->setCategory($request->user(), $data['id']);

        if (! $written) {
            return back()->with('error', 'Twitch did not accept that category. If you have not reauthorized Twitch since this feature arrived, do that first.');
        }

        return back()->with('success', "Category set to {$data['name']}.");
    }

    /**
     * What Twitch shows right now, for the "currently on Twitch" line. Null
     * when it cannot be fetched; the page copes.
     *
     * @return array{title:string,game_id:string,game_name:string}|null
     */
    private function channel(Request $request): ?array
    {
        $user = $request->user();

        if (! $user->access_token || ! $user->twitch_id) {
            return null;
        }

        try {
            $info = $this->twitch->getChannelInfo($user->access_token, $user->twitch_id);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($info)) {
            return null;
        }

        return [
            'title' => (string) ($info['title'] ?? ''),
            'game_id' => (string) ($info['game_id'] ?? ''),
            'game_name' => (string) ($info['game_name'] ?? ''),
        ];
    }
}
