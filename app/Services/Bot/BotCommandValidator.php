<?php

namespace App\Services\Bot;

use App\Models\BotBuiltin;
use App\Models\BotCommand;
use App\Models\User;
use App\Support\BotTags;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared validation for Bot Commands. Used by the web settings
 * controller (form posts) and the chat-driven `!ol cmd ...` admin path
 * so the same rules gate both surfaces. Returns a normalised payload
 * (lowercased command, '!' stripped); throws ValidationException with
 * field-keyed messages on any failure.
 */
class BotCommandValidator
{
    public function __construct(
        private readonly BotCounterService $counters,
    ) {}

    /**
     * @param  array<string,mixed>  $input  Raw input (command, permission_level, cooldown_seconds, reply, enabled, hidden).
     * @param  BotCommand|null  $existing  Set for updates so the duplicate check ignores the row being edited.
     * @return array<string,mixed> Normalised payload ready to feed into BotCommand::create() / ::update().
     */
    public function validateAndNormalize(int $userId, array $input, ?BotCommand $existing = null): array
    {
        $data = Validator::make($input, [
            'command' => [
                'required',
                'string',
                'max:30',
                'regex:/^!?[a-zA-Z0-9_-]{1,30}$/',
            ],
            'permission_level' => ['required', Rule::in(BotBuiltin::PERMISSION_LEVELS)],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'reply' => ['required', 'string', 'max:2000'],
            'enabled' => ['required', 'boolean'],
            'hidden' => ['required', 'boolean'],
            // Optional self-destruct timer, whole hours 1-8760 (one year);
            // null/absent means "no timer". Only the web form sends this - the
            // chat-admin path manages destroy_at through its own option flow.
            'destroy_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
        ])->validate();

        // Reject slash commands. The bot replies via the Send Chat Message API,
        // which transmits literal text only - Twitch drops a leading `/timeout`
        // (or any slash command) and posts the rest as a plain message, so the
        // reply silently does nothing useful. We check the raw template
        // pre-substitution, so chatter args can never inject the leading slash
        // (the single-pass resolver guarantees this). Kept to one sentence so it
        // reads under the form field and as a single relayed chat line.
        if (str_starts_with(ltrim($data['reply']), '/')) {
            throw ValidationException::withMessages([
                'reply' => "Replies can't start with '/'. Slash commands like /timeout only work in Twitch's own chat box; the overlabels bot sends plain text and powers your overlays, it doesn't moderate chat.",
            ]);
        }

        // rand: and counter: are the only tags validated at authoring time.
        // Every other tag family resolves empty when it is wrong, which is the
        // right default for a read. These two are different: a bad rand range
        // is a typo the streamer can't see (it just goes blank mid-stream), and
        // a bad counter key would have us silently not counting. Both are
        // cheaper to refuse at the point of writing than to debug live.
        $this->assertTagsAreReadable($data['reply']);
        $this->assertRandRangesAreValid($data['reply']);
        $this->assertCounterKeysAreValid($userId, $data['reply']);

        $command = strtolower(ltrim($data['command'], '!'));

        $reserved = array_column(BotBuiltin::DEFAULTS, 'command');
        if (in_array($command, $reserved, true)) {
            throw ValidationException::withMessages([
                'command' => "'!$command' is a built-in bot command and can't be reused as a custom command.",
            ]);
        }

        $duplicate = BotCommand::where('user_id', $userId)
            ->where('command', $command)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'command' => "You already have a command for '!$command'.",
            ]);
        }

        $data['command'] = $command;

        return $data;
    }

    /**
     * Catch the tags that would have saved cleanly and then done nothing.
     *
     * A misspelled namespace resolves to empty and a malformed bracket run is
     * printed to chat verbatim, so in both cases the streamer's first hint that
     * anything is wrong arrives live, in front of an audience, with nothing
     * pointing at the cause. Refusing at save time costs one retype instead.
     *
     * Checked before the rand and counter rules so `[[[rnd:0-69]]]` is reported
     * as the typo it is rather than as a stray unknown tag.
     *
     * @throws ValidationException
     */
    private function assertTagsAreReadable(string $reply): void
    {
        foreach (BotTags::malformedTags($reply) as $snippet) {
            throw ValidationException::withMessages([
                'reply' => "I can't read '$snippet', so chat would see it exactly as written. Tag names join up with a colon and no spaces, like [[[counter:wins]]].",
            ]);
        }

        foreach (BotTags::underBracketedTags($reply) as $snippet) {
            $inner = trim($snippet, '[]');

            throw ValidationException::withMessages([
                'reply' => "'$snippet' needs three brackets on each side. Try [[[$inner]]] instead.",
            ]);
        }

        foreach (BotTags::unknownNamespaces($reply) as $key => $suggestion) {
            $namespace = explode(':', $key)[0];

            $message = $suggestion !== null
                ? "There's no '$namespace' tag, so nothing would show up where you put [[[$key]]]. Did you mean '$suggestion'?"
                : "There's no '$namespace' tag, so nothing would show up where you put [[[$key]]]. The ones you can use in chat are: ".BotTags::namespaceList().'.';

            throw ValidationException::withMessages(['reply' => $message]);
        }
    }

    /**
     * Every `rand:` tag must carry two non-negative whole bounds.
     *
     * Negatives are refused rather than supported. `rand:-5-5` has three
     * readings and no streamer rolls a negative number, so the ambiguity buys
     * nothing. Kept to one sentence so it reads under the form field and as a
     * single relayed chat line.
     *
     * @throws ValidationException
     */
    private function assertRandRangesAreValid(string $reply): void
    {
        foreach (BotTags::randArgs($reply) as $arg) {
            if (BotTags::parseRange($arg) !== null) {
                continue;
            }

            throw ValidationException::withMessages([
                'reply' => "'[[[rand:$arg]]]' isn't a valid range - write two whole numbers low to high, like [[[rand:0-69]]]. Negative numbers aren't supported.",
            ]);
        }
    }

    /**
     * Every `counter:` tag must name a usable control key, and must not
     * collide with an existing control that can't hold a count.
     *
     * @throws ValidationException
     */
    private function assertCounterKeysAreValid(int $userId, string $reply): void
    {
        foreach (BotTags::counterKeys($reply) as $key) {
            if (! BotTags::isValidCounterKey($key)) {
                throw ValidationException::withMessages([
                    'reply' => "'$key' isn't a usable counter name - use lowercase letters, numbers and underscores, starting with a letter, like [[[counter:wins]]].",
                ]);
            }
        }

        $user = User::find($userId);

        if (! $user) {
            return;
        }

        foreach ($this->counters->conflictingTypes($user, $reply) as $key => $type) {
            throw ValidationException::withMessages([
                'reply' => "You already have a $type control named '$key', which can't hold a count. Pick another name for the counter.",
            ]);
        }
    }
}
