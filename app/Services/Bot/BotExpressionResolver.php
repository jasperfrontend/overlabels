<?php

namespace App\Services\Bot;

use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Expressions\ExpressionFormatter;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use App\Support\ListItems;
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
 *   c:list:<slug>:<field> -> own List (OptionSet) read tag, snapshot at fire time
 *   c:<key>            -> own OverlayControl by key
 *   c:<service>:<key>  -> service-managed OverlayControl by broadcastKey
 *   bot:<key>          -> per-invocation context (from_user, args.0, ...)
 *   <bare>             -> Twitch Helix tag from TemplateDataMapperService
 *
 * List read tags mirror the overlay render projection (OverlayTemplateController),
 * but chat is a one-shot text sink so every value is a STATIC SNAPSHOT at resolve
 * time - nothing ticks. Supported fields: count, first, last, empty, sum, random,
 * expires_at, countdown. Deliberately omitted: the bare c:list:<slug> (raw JSON
 * array string) and :json (full item objects) - dumping JSON into chat is noise -
 * plus the .N indexed scalars and foreach machinery, which aren't chat constructs.
 *
 * Pipe formatters (e.g. |number, |round:2, |distance:mi) run after lookup.
 * Unknown tags resolve to empty string per the null-over-placeholder rule.
 *
 * Default values: `[[[key ?? literal]]]` (or `[[[key|pipe ?? literal]]]`) emits
 * the literal text VERBATIM when the looked-up value is empty. The default is a
 * presentation fallback for ABSENCE only - it is never re-scanned for tags
 * (single-pass), the pipe is not applied to it, and it never feeds back into a
 * control's stored value. Chat output is a plain-text sink, so the default
 * needs no HTML encoding here.
 */
class BotExpressionResolver
{
    // Group 1: tag key. Group 2 (optional): pipe formatter. Group 3 (optional,
    // after `??`): literal default emitted when the value resolves empty. The
    // default captures lazily up to the closing `]]]` so it may contain spaces
    // and punctuation; the only thing it can't contain is the literal `]]]`.
    private const string TAG_REGEX = '/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?(?:\s*\?\?\s*(.*?))?]]]/';

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
        $lists = $this->loadLists($user);
        $twitchTags = $dryRun ? [] : $this->loadTwitchTags($user);
        $locale = (string) ($user->preference('locale', 'en-US'));

        $resolved = preg_replace_callback(
            self::TAG_REGEX,
            function (array $matches) use ($controls, $lists, $twitchTags, $botContext, $locale): string {
                $key = $matches[1];
                $pipe = ($matches[2] ?? '') !== '' ? $matches[2] : null;
                $default = isset($matches[3]) ? trim($matches[3]) : null;
                $value = $this->lookup($key, $controls, $lists, $twitchTags, $botContext);

                // Absence backstop: an empty value renders the literal default
                // (verbatim, no pipe). A present value never triggers it.
                if ($value === '' && $default !== null && $default !== '') {
                    return $default;
                }

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
     * @param  array<string,string>  $lists
     * @param  array<string,mixed>  $twitchTags
     * @param  array<string,mixed>  $botContext
     */
    private function lookup(string $key, array $controls, array $lists, array $twitchTags, array $botContext): string
    {
        // List read tags resolve from OptionSets, not OverlayControls. Checked
        // before the generic c: branch, which would otherwise swallow a
        // c:list:... key into a missing-control empty string. Unsupported list
        // tags (the bare c:list:<slug> array dump and :json) are never put in
        // $lists, so they fall through to '' here per null-over-placeholder.
        if (str_starts_with($key, 'c:list:')) {
            return $lists[$key] ?? '';
        }

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
     * Project the user's Lists (OptionSets) into a flat map of
     * c:list:<slug>:<field> => value string, mirroring the overlay render
     * projection in OverlayTemplateController. Chat is a one-shot text sink,
     * so every field is a static snapshot at resolve time - no live ticking.
     * countdown is computed once as max(0, expires_at - now) instead of the
     * overlay's RAF-driven timer, since a chat line cannot tick. The bare
     * array tag, :json, and the foreach-only .N / .count scalars are
     * intentionally not projected here (see class docblock).
     *
     * @return array<string,string> Map of c:list:<slug>:<field> -> value string.
     */
    private function loadLists(User $user): array
    {
        $now = now()->timestamp;
        $map = [];

        foreach (OptionSet::where('user_id', $user->id)->get() as $list) {
            $values = ListItems::values($list->items ?? []);
            $count = count($values);
            $base = 'c:list:'.$list->slug;

            $map[$base.':count'] = (string) $count;
            $map[$base.':first'] = $count > 0 ? $values[0] : '';
            $map[$base.':last'] = $count > 0 ? $values[$count - 1] : '';
            $map[$base.':empty'] = $count === 0 ? '1' : '0';
            $map[$base.':sum'] = ListItems::sum($list->slug, $values);
            $map[$base.':random'] = $count > 0 ? $values[array_rand($values)] : '';

            $expiresTs = $list->expires_at?->timestamp;
            $map[$base.':expires_at'] = $expiresTs !== null ? (string) $expiresTs : '';
            $map[$base.':countdown'] = $expiresTs !== null ? (string) max(0, $expiresTs - $now) : '';
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
