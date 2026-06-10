<?php

namespace App\Services\Bot;

use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Expressions\ExpressionFormatter;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves a Bot Expression template string into a chat-ready string.
 *
 * SINGLE-PASS BY DESIGN: the regex matches once over the input. Substituted
 * values are never re-scanned for tags. Mirrors the day-one rule from the
 * frontend overlay parser - prevents template injection if a control value
 * happens to contain something tag-shaped.
 *
 * Tag families inside [[[...]]]:
 *   c:<key>            -> own OverlayControl by key
 *   c:<service>:<key>  -> service-managed OverlayControl by broadcastKey
 *   bot:<key>          -> per-invocation context (from_user, args.0, ...)
 *   <bare>             -> Twitch Helix tag from TemplateDataMapperService
 *
 * Pipe formatters (e.g. |number, |round:2, |distance:mi) run after lookup.
 * Unknown tags resolve to empty string per the null-over-placeholder rule.
 */
class BotExpressionResolver
{
    private const string TAG_REGEX = '/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?]]]/';

    private const int MAX_RESOLVED_LENGTH = 500;

    public function __construct(
        private readonly TwitchApiService $twitch,
        private readonly TemplateDataMapperService $mapper,
        private readonly TwitchTokenService $tokens,
    ) {}

    /**
     * Resolve $expression for $user, given $botContext for the invocation.
     *
     * @param  array<string,mixed>  $botContext  Keys: from_user, from_user_login,
     *                                           from_user_id, command, args (string),
     *                                           args.0/1/... (tokens), channel.
     * @param  bool  $dryRun  When true, skips the (possibly expensive) Twitch
     *                        fetch and resolves bare tags to empty. Used by the
     *                        builder UI's live preview and the validator.
     */
    public function resolve(User $user, string $expression, array $botContext = [], bool $dryRun = false): string
    {
        $controls = $this->loadControls($user);
        $twitchTags = $dryRun ? [] : $this->loadTwitchTags($user);
        $locale = (string) ($user->preference('locale', 'en-US'));

        $resolved = preg_replace_callback(
            self::TAG_REGEX,
            function (array $matches) use ($controls, $twitchTags, $botContext, $locale): string {
                $key = $matches[1];
                $pipe = $matches[2] ?? null;
                $value = $this->lookup($key, $controls, $twitchTags, $botContext);
                if ($pipe !== null) {
                    $value = ExpressionFormatter::apply($value, $pipe, $locale);
                }

                return $value;
            },
            $expression
        );

        if (mb_strlen($resolved) > self::MAX_RESOLVED_LENGTH) {
            $resolved = mb_substr($resolved, 0, self::MAX_RESOLVED_LENGTH);
        }

        return $resolved;
    }

    /**
     * @param  array<string,string>  $controls
     * @param  array<string,mixed>  $twitchTags
     * @param  array<string,mixed>  $botContext
     */
    private function lookup(string $key, array $controls, array $twitchTags, array $botContext): string
    {
        if (str_starts_with($key, 'c:')) {
            $controlKey = substr($key, 2);

            return (string) ($controls[$controlKey] ?? '');
        }

        if (str_starts_with($key, 'bot:')) {
            $rest = substr($key, 4);
            // Bot context is flat: keys are literal strings like "from_user",
            // "args", "args.0". Literal lookup avoids data_get's dot traversal,
            // which would try to index into the "args" string.
            $value = $botContext[$rest] ?? null;

            return $value === null ? '' : (string) $value;
        }

        $value = $twitchTags[$key] ?? null;

        return $value === null ? '' : (string) $value;
    }

    /**
     * @return array<string,string> Map of control identifier -> resolved value.
     *                              Service-managed controls use broadcastKey
     *                              (e.g. "kofi:donations_received"); own
     *                              controls use the plain key.
     */
    private function loadControls(User $user): array
    {
        $rows = OverlayControl::where('user_id', $user->id)->get();
        $map = [];
        foreach ($rows as $control) {
            $identifier = $control->source_managed
                ? $control->broadcastKey()
                : $control->key;
            $map[$identifier] = $control->resolveDisplayValue();
        }

        return $map;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadTwitchTags(User $user): array
    {
        if (! $user->access_token || ! $user->twitch_id) {
            return [];
        }

        // Refresh a stale token before fetching, exactly like every other Twitch
        // consumer (overlay render, ExpressionTagController, BotFollowageController).
        // The bot fire path authenticates on the bot.internal secret, not a user
        // session, so it never passes through EnsureValidTwitchToken middleware -
        // the refresh has to happen here. Without it an expired token 401s and
        // EVERY Twitch tag (followers_latest_user_name, channel_title, ...)
        // resolves to empty, while bot: and c: tags keep working because they
        // need no token. ensureValidToken() mutates $user in place, so the
        // fetch below uses the freshly refreshed access_token.
        if (! $this->tokens->ensureValidToken($user)) {
            Log::warning('bot_expression.token_refresh_failed', ['user_id' => $user->id]);

            return [];
        }

        try {
            $data = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);

            return $this->mapper->mapTwitchDataForTemplates($data, '');
        } catch (Throwable $e) {
            Log::warning('bot_expression.twitch_fetch_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
