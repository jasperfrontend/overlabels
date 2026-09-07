<?php

namespace App\Services;

use App\Exceptions\TwitchTokenInvalidException;
use App\Jobs\SyncLivingTitle;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Bot\BotCommandResolver;
use App\Support\BotTags;
use App\Support\Conditionals;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The living Twitch title.
 *
 * A stream title written as a one-line template with [[[tags]]] in it:
 *
 *   Road to 2K | [[[followers_total]]] followers | playing [[[channel_game_name]]]
 *
 * Something a tag could read changes, the template is rendered again, and if
 * the string differs from the last one sent, it is PATCHed to Twitch. This is
 * the first thing in the platform that writes to Twitch other than the bot's
 * chat replies.
 *
 * RENDERING is BotCommandResolver, unchanged: a chat reply and a title are the
 * same shape (one line, tags, if/else, pipes, `??` defaults, no loops) and
 * resolve against the same data (Helix tags, controls, lists). Bot-context
 * tags have no meaning here and resolve empty.
 *
 * WHAT TRIGGERS A RENDER is "anything": every stored EventSub event
 * (TwitchEventSubController) and every control write (OverlayControl's saved
 * hook, which every service, counter, reset and manual edit goes through).
 * A curated list of triggering events would rot the first time a tag was
 * added; the debounce is what makes "anything" affordable.
 *
 * Three rules, decided before any of this was built:
 *
 *  1. `channel_title` is refused inside the template. Our PATCH fires
 *     channel.update, the app consumes it, and channel_title becomes our own
 *     rendered output. A template that read it would feed on itself.
 *     `rand:` and list `:random` are refused for the neighbouring reason: a
 *     value that differs every render defeats "only write on change".
 *  2. A channel.update carrying a title we did not write PAUSES the feature.
 *     The streamer edited it in the dashboard; overwriting them a minute
 *     later is the one behaviour that would get this switched off for good.
 *     Resume is an explicit click (or a save of the template).
 *  3. One PATCH per DEBOUNCE_SECONDS at most, and only when the rendered
 *     string differs from `last_written`. A 100-gift bomb is 100 events in
 *     seconds; a title that flickers is worse than one that lags.
 *
 * `last_written` is stored BEFORE the PATCH is sent, so the channel.update
 * Twitch fires in response is recognised as ours however fast it arrives.
 * A failed PATCH puts the previous value back.
 *
 * The category is NOT part of any of this. It is set once, from the settings
 * page, through the same PATCH helper, and never re-asserted by a render:
 * streamers change category from the dashboard mid-stream and the title must
 * not fight them over it.
 */
class LivingTitleService
{
    public const string SCOPE = 'channel:manage:broadcast';

    /** Twitch rejects a longer title with a 400. */
    public const int MAX_LENGTH = 140;

    public const int DEBOUNCE_SECONDS = 30;

    public function __construct(
        private readonly BotCommandResolver $resolver,
        private readonly TwitchApiService $twitch,
        private readonly TwitchTokenService $tokens,
        private readonly TwitchScopeService $scopes,
    ) {}

    /**
     * Why this template cannot be saved, as one sentence for the author, or
     * null when it can. Structural problems (unclosed if, foreach) speak in
     * the shared Conditionals::describeProblem() voice; the two refusals
     * specific to a title are worded here.
     */
    public function problem(string $template): ?string
    {
        $structural = Conditionals::structuralProblem($template);

        if ($structural !== null) {
            return Conditionals::describeProblem($structural);
        }

        $keys = array_merge(BotTags::keys($template), Conditionals::keys($template));

        foreach ($keys as $key) {
            if ($key === 'channel_title') {
                return '[[[channel_title]]] is the title itself. Once this is on, the title on Twitch is whatever this template renders, so reading it back here would feed on itself.';
            }

            if (str_starts_with($key, 'rand:') || (str_starts_with($key, 'c:list:') && str_ends_with($key, ':random'))) {
                return "'[[[{$key}]]]' gives a different value every time it renders, so the title would rewrite itself on every event. A title only changes when the text changes - leave randomness to overlays and chat.";
            }
        }

        return null;
    }

    /**
     * Render the user's template against their live values, fitted to what
     * Twitch accepts. Empty template renders empty.
     */
    public function render(User $user): string
    {
        return $this->renderTemplate($user, $user->livingTitle()['template']);
    }

    public function renderTemplate(User $user, string $template): string
    {
        return $this->preview($user, $template)['resolved'];
    }

    /**
     * The settings page's live preview: the fitted string plus how long the
     * render was before the cut, so the page can say "cut at 140".
     *
     * @return array{resolved:string,length:int,truncated:bool}
     */
    public function preview(User $user, string $template): array
    {
        if (trim($template) === '') {
            return ['resolved' => '', 'length' => 0, 'truncated' => false];
        }

        $oneLine = self::oneLine($this->resolver->resolve($user, $template));

        return [
            'resolved' => self::fit($oneLine),
            'length' => mb_strlen($oneLine),
            'truncated' => mb_strlen($oneLine) > self::MAX_LENGTH,
        ];
    }

    /**
     * One line, single spaces, at most MAX_LENGTH characters. Applied to what
     * we send AND to what channel.update echoes back, so whitespace Twitch
     * normalises on its side cannot make our own title look foreign.
     */
    public static function fit(string $rendered): string
    {
        return mb_substr(self::oneLine($rendered), 0, self::MAX_LENGTH);
    }

    private static function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Queue a render for this user, coalescing everything that arrives in the
     * next DEBOUNCE_SECONDS into one job. The cache key IS the debounce: the
     * first caller sets it and dispatches a delayed job, later callers see
     * it and do nothing, and the job clears it as its first act so anything
     * arriving after that starts a new window. Trailing-edge, so the render
     * always sees the last event of a burst.
     *
     * A delay of 0 (save, resume) dispatches at once and ignores the window.
     */
    public function schedule(User $user, int $delaySeconds = self::DEBOUNCE_SECONDS): void
    {
        if (! $user->livingTitle()['enabled']) {
            return;
        }

        if ($delaySeconds <= 0) {
            SyncLivingTitle::dispatch($user->id);

            return;
        }

        if (! Cache::add(self::pendingKey($user->id), 1, $delaySeconds)) {
            return;
        }

        SyncLivingTitle::dispatch($user->id)->delay(now()->addSeconds($delaySeconds));
    }

    /**
     * OverlayControl's saved hook lands here for every control write on the
     * platform. Cheap for the accounts this is off for: one user lookup and
     * one preference read.
     */
    public function controlChanged(OverlayControl $control): void
    {
        $user = $control->user;

        if ($user instanceof User) {
            $this->schedule($user);
        }
    }

    public function hasScope(User $user): bool
    {
        return $this->scopes->hasScope($user, self::SCOPE);
    }

    public static function pendingKey(int $userId): string
    {
        return "living-title:pending:{$userId}";
    }

    /**
     * The job body. Render, compare, write if different.
     */
    public function sync(User $user): void
    {
        $settings = $user->livingTitle();

        if (! $settings['enabled'] || $settings['paused']) {
            return;
        }

        if (! $this->scopes->hasScope($user, self::SCOPE)) {
            $this->remember($user, ['last_error' => 'Twitch has not yet allowed Overlabels to change your title. Reauthorize Twitch once to grant it.']);

            return;
        }

        if (! $this->tokens->ensureValidToken($user)) {
            $this->remember($user, ['last_error' => 'Your Twitch login has expired. Log in again to keep the title updating.']);

            return;
        }

        $title = $this->render($user);

        // An empty render is never written: a template made only of tags that
        // all resolved empty must not blank the title on Twitch.
        if ($title === '' || $title === $settings['last_written']) {
            return;
        }

        $previous = $settings['last_written'];
        $this->remember($user, ['last_written' => $title]);

        try {
            $written = $this->twitch->updateChannel($user->access_token, $user->twitch_id, ['title' => $title]);
        } catch (TwitchTokenInvalidException) {
            $written = false;
        } catch (Throwable $e) {
            Log::warning('living_title.write_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $written = false;
        }

        if (! $written) {
            $this->remember($user, [
                'last_written' => $previous,
                'last_error' => 'Twitch did not accept the last title update. It will be tried again on the next change.',
            ]);

            return;
        }

        $this->twitch->clearChannelInfoCaches($user->twitch_id);
        $this->remember($user, ['written_at' => now()->timestamp, 'last_error' => null]);

        Log::info('living_title.written', ['user_id' => $user->id, 'length' => mb_strlen($title)]);
    }

    /**
     * Every channel.update lands here, our own echo included. A title equal
     * to what we last wrote is ours; anything else is the streamer, and the
     * feature pauses rather than overwrite them. Nothing written yet means
     * nothing to protect.
     *
     * @param  array<string,mixed>  $event  The channel.update payload.
     */
    public function handleChannelUpdate(User $user, array $event): void
    {
        $settings = $user->livingTitle();

        if (! $settings['enabled'] || $settings['paused'] || $settings['last_written'] === null) {
            return;
        }

        $incoming = self::fit((string) ($event['title'] ?? ''));

        if ($incoming === '' || $incoming === self::fit($settings['last_written'])) {
            return;
        }

        $this->remember($user, ['paused' => true, 'paused_title' => $incoming]);

        Log::info('living_title.paused', ['user_id' => $user->id]);
    }

    /**
     * The streamer's explicit "carry on": clear the pause and render at once.
     */
    public function resume(User $user): void
    {
        $this->remember($user, ['paused' => false, 'paused_title' => null]);
        $this->schedule($user, 0);
    }

    /**
     * Set the category on Twitch, once, now. Not remembered anywhere: the
     * next render never re-asserts it.
     */
    public function setCategory(User $user, string $gameId): bool
    {
        if (! $this->scopes->hasScope($user, self::SCOPE) || ! $this->tokens->ensureValidToken($user)) {
            return false;
        }

        try {
            $written = $this->twitch->updateChannel($user->access_token, $user->twitch_id, ['game_id' => $gameId]);
        } catch (Throwable $e) {
            Log::warning('living_title.category_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }

        if ($written) {
            $this->twitch->clearChannelInfoCaches($user->twitch_id);
        }

        return $written;
    }

    /**
     * @param  array<string,mixed>  $fields
     */
    private function remember(User $user, array $fields): void
    {
        foreach ($fields as $key => $value) {
            $user->setPreference("living_title.{$key}", $value);
        }

        $user->save();
    }
}
