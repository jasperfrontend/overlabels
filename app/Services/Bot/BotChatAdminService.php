<?php

namespace App\Services\Bot;

use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Backs the `!ol <subverb>` chat-admin meta-command. The bot relays a
 * structured payload here; we mutate the user's expressions / aliases /
 * options and return a single chat-ready reply string. The controller is
 * responsible for queueing that reply into bot_chat_outbox.
 *
 * All paths return prose-y replies because chat doesn't render structured
 * errors. Validation failures get flattened into one human-readable line
 * (multiple field errors joined by " - "); the bot speaks it verbatim.
 *
 * Naming convention used inside replies: commands are shown with their `!`
 * prefix ("!w") so the streamer can copy-paste from chat into a follow-up
 * command without thinking about it.
 */
readonly class BotChatAdminService
{
    public function __construct(
        private BotExpressionValidator $exprValidator,
        private BotAliasValidator $aliasValidator,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     *                                        Required keys depend on (subject, action). The bot is the only caller;
     *                                        its handler shapes the payload, so we trust the structure but still
     *                                        null-coalesce for safety.
     */
    public function dispatch(User $owner, array $payload): string
    {
        $subject = (string) ($payload['subject'] ?? '');
        $action = (string) ($payload['action'] ?? '');

        return match ("$subject:$action") {
            'cmd:add' => $this->cmdAdd($owner, $payload),
            'cmd:edit' => $this->cmdEdit($owner, $payload),
            'cmd:delete' => $this->cmdDelete($owner, $payload),
            'cmd:options' => $this->cmdOptions($owner, $payload),
            'alias:add' => $this->aliasAdd($owner, $payload),
            'alias:edit' => $this->aliasEdit($owner, $payload),
            'alias:delete' => $this->aliasDelete($owner, $payload),
            'alias:options' => $this->aliasOptions($owner, $payload),
            'list:' => $this->listAll($owner, $payload),
            'help:' => $this->showHelp($payload),
            default => 'unknown !ol command - try !ol help',
        };
    }

    // ------- cmd (Bot Expressions) -------

    private function cmdAdd(User $owner, array $payload): string
    {
        $name = $this->stripBang($payload['name'] ?? '');
        $body = trim((string) ($payload['payload'] ?? ''));

        try {
            $data = $this->exprValidator->validateAndNormalize($owner->id, [
                'command' => $name,
                'expression' => $body,
                'permission_level' => 'everyone',
                'cooldown_seconds' => 0,
                'enabled' => true,
                'hidden_from_commands' => false,
            ]);

            BotExpression::create([
                'user_id' => $owner->id,
                ...$data,
            ]);

            return "added !{$data['command']}";
        } catch (ValidationException $e) {
            return $this->formatValidationError($e);
        }
    }

    private function cmdEdit(User $owner, array $payload): string
    {
        $name = $this->stripBang($payload['name'] ?? '');
        $body = trim((string) ($payload['payload'] ?? ''));

        $existing = BotExpression::where('user_id', $owner->id)
            ->where('command', $name)
            ->first();
        if (! $existing) {
            return "no expression named !$name to edit";
        }

        try {
            $data = $this->exprValidator->validateAndNormalize($owner->id, [
                'command' => $existing->command,
                'expression' => $body,
                'permission_level' => $existing->permission_level,
                'cooldown_seconds' => $existing->cooldown_seconds,
                'enabled' => $existing->enabled,
                'hidden_from_commands' => $existing->hidden_from_commands,
            ], $existing);

            $existing->update($data);

            return "updated !{$existing->command}";
        } catch (ValidationException $e) {
            return $this->formatValidationError($e);
        }
    }

    private function cmdDelete(User $owner, array $payload): string
    {
        $name = $this->stripBang($payload['name'] ?? '');

        $existing = BotExpression::where('user_id', $owner->id)
            ->where('command', $name)
            ->first();
        if (! $existing) {
            return "no expression named !$name to delete";
        }

        $existing->delete();

        return "deleted !$name";
    }

    private function cmdOptions(User $owner, array $payload): string
    {
        $name = $this->stripBang($payload['name'] ?? '');
        $option = strtolower(trim((string) ($payload['option'] ?? '')));
        $rawValue = trim((string) ($payload['value'] ?? ''));

        $existing = BotExpression::where('user_id', $owner->id)
            ->where('command', $name)
            ->first();
        if (! $existing) {
            return "no expression named !$name";
        }

        return $this->applyOption(
            $existing,
            $option,
            $rawValue,
            label: "!$name",
        );
    }

    // ------- alias (Bot Aliases) -------

    private function aliasAdd(User $owner, array $payload): string
    {
        $name = $this->stripBang($payload['name'] ?? '');
        $target = trim((string) ($payload['payload'] ?? ''));

        try {
            $data = $this->aliasValidator->validateAndNormalize($owner->id, [
                'command' => $name,
                'target_template' => $target,
                'permission_level' => 'moderator',
                'cooldown_seconds' => 0,
                'enabled' => true,
                'hidden_from_commands' => false,
            ]);

            BotAlias::create([
                'user_id' => $owner->id,
                ...$data,
            ]);

            return "added alias !{$data['command']} -> !{$data['target_template']}";
        } catch (ValidationException $e) {
            return $this->formatValidationError($e);
        }
    }

    private function aliasEdit(User $owner, array $payload): string
    {
        $name = $this->stripBang($payload['name'] ?? '');
        $target = trim((string) ($payload['payload'] ?? ''));

        $existing = BotAlias::where('user_id', $owner->id)
            ->where('command', $name)
            ->first();
        if (! $existing) {
            return "no alias named !$name to edit";
        }

        try {
            $data = $this->aliasValidator->validateAndNormalize($owner->id, [
                'command' => $existing->command,
                'target_template' => $target,
                'permission_level' => $existing->permission_level,
                'cooldown_seconds' => $existing->cooldown_seconds,
                'enabled' => $existing->enabled,
                'hidden_from_commands' => $existing->hidden_from_commands,
            ], $existing);

            $existing->update($data);

            return "updated alias !{$existing->command} -> !{$existing->target_template}";
        } catch (ValidationException $e) {
            return $this->formatValidationError($e);
        }
    }

    private function aliasDelete(User $owner, array $payload): string
    {
        $name = $this->stripBang($payload['name'] ?? '');

        $existing = BotAlias::where('user_id', $owner->id)
            ->where('command', $name)
            ->first();
        if (! $existing) {
            return "no alias named !$name to delete";
        }

        $existing->delete();

        return "deleted alias !$name";
    }

    private function aliasOptions(User $owner, array $payload): string
    {
        $name = $this->stripBang($payload['name'] ?? '');
        $option = strtolower(trim((string) ($payload['option'] ?? '')));
        $rawValue = trim((string) ($payload['value'] ?? ''));

        $existing = BotAlias::where('user_id', $owner->id)
            ->where('command', $name)
            ->first();
        if (! $existing) {
            return "no alias named !$name";
        }

        return $this->applyOption(
            $existing,
            $option,
            $rawValue,
            label: "alias !$name",
        );
    }

    // ------- list / help -------

    /**
     * `!ol list` with no filter returns expressions + aliases; `!ol list cmd`
     * or `!ol list alias` filters one. Twitch caps chat at 500 chars; we
     * truncate with a "..." tail rather than splitting across messages.
     */
    private function listAll(User $owner, array $payload): string
    {
        $filter = strtolower(trim((string) ($payload['name'] ?? '')));

        $parts = [];

        if ($filter === '' || $filter === 'cmd') {
            $exprs = BotExpression::where('user_id', $owner->id)
                ->orderBy('command')
                ->pluck('command')
                ->map(fn (string $c) => "!$c")
                ->all();
            if (! empty($exprs)) {
                $parts[] = 'commands: '.implode(' ', $exprs);
            }
        }

        if ($filter === '' || $filter === 'alias') {
            $aliases = BotAlias::where('user_id', $owner->id)
                ->orderBy('command')
                ->pluck('command')
                ->map(fn (string $c) => "!$c")
                ->all();
            if (! empty($aliases)) {
                $parts[] = 'aliases: '.implode(' ', $aliases);
            }
        }

        if (empty($parts)) {
            return $filter === '' ? 'no custom commands yet - try !ol help' : "no $filter yet";
        }

        $reply = implode(' | ', $parts);

        return mb_strlen($reply) > 480 ? mb_substr($reply, 0, 477).'...' : $reply;
    }

    private function showHelp(array $payload): string
    {
        $topic = strtolower(trim((string) ($payload['name'] ?? '')));

        return match ($topic) {
            'cmd' => '!ol cmd add|edit|delete|options <name> [payload]. example: !ol cmd add lol HAHA',
            'alias' => '!ol alias add|edit|delete|options <name> [target]. example: !ol alias add w !inc wins {1}',
            'options' => 'options: cooldown <secs> | permission everyone|sub|vip|mod|broadcaster | enabled true|false | hidden true|false | destroy <hours> (0 cancels, cmd only)',
            default => '!ol cmd <add|edit|delete|options> ; !ol alias <add|edit|delete|options> ; !ol list ; !ol help <cmd|alias|options>',
        };
    }

    // ------- option dispatch (shared expr/alias) -------

    /**
     * Applies a single option to an existing expression or alias. Returns a
     * chat reply describing what happened, including for failed lookups so
     * the streamer sees why nothing changed.
     */
    private function applyOption(BotExpression|BotAlias $row, string $option, string $rawValue, string $label): string
    {
        $option = $this->canonicalOption($option);

        switch ($option) {
            case 'cooldown':
                if (! is_numeric($rawValue)) {
                    return 'cooldown must be a number 0-86400';
                }
                $n = (int) $rawValue;
                if ($n < 0 || $n > 86400) {
                    return 'cooldown must be between 0 and 86400 seconds';
                }
                $row->update(['cooldown_seconds' => $n]);

                return "$label cooldown is now {$n}s";

            case 'permission':
                $level = $this->canonicalPermissionLevel($rawValue);
                if ($level === null) {
                    return 'permission must be one of: everyone, sub, vip, mod, broadcaster';
                }
                $row->update(['permission_level' => $level]);

                return "permission on $label is now $level";

            case 'enabled':
                $bool = $this->parseBoolean($rawValue);
                if ($bool === null) {
                    return 'enabled must be true or false';
                }
                $row->update(['enabled' => $bool]);

                return "command $label is now ".($bool ? 'enabled' : 'disabled');

            case 'hidden':
                $bool = $this->parseBoolean($rawValue);
                if ($bool === null) {
                    return 'hidden must be true or false';
                }
                $row->update(['hidden_from_commands' => $bool]);

                return "command $label is now ".($bool ? 'hidden' : 'visible').' in !commands listings';

            case 'destroy':
                // Self-destruct timer. Expressions only - aliases have no
                // destroy_at column and the friend's use case is temporary
                // commands. Whole hours, 1-8760 (one year); 0 cancels.
                if ($row instanceof BotAlias) {
                    return 'destroy only works on commands, not aliases';
                }
                if (! ctype_digit($rawValue)) {
                    return 'destroy must be a whole number of hours (0 to cancel, max 8760)';
                }
                $hours = (int) $rawValue;
                if ($hours === 0) {
                    $row->update(['destroy_at' => null]);

                    return "destroy timer on $label cancelled";
                }
                if ($hours > 8760) {
                    return 'destroy must be between 1 and 8760 hours';
                }
                $row->update(['destroy_at' => now()->addHours($hours)]);

                return "$label will be destroyed in {$hours}h from now";

            default:
                return 'unknown option - try !ol help options';
        }
    }

    // ------- value parsing helpers -------

    private function canonicalOption(string $raw): string
    {
        return match ($raw) {
            'cd', 'cooldown_seconds' => 'cooldown',
            'perm', 'permission_level' => 'permission',
            'enable', 'enabled' => 'enabled',
            'hide', 'hidden_from_commands' => 'hidden',
            'selfdestruct', 'self_destruct', 'kill', 'destroy_at' => 'destroy',
            default => $raw,
        };
    }

    /**
     * Map short forms to the canonical BotCommand::PERMISSION_LEVELS values.
     * Returns null for anything we don't recognise.
     */
    private function canonicalPermissionLevel(string $raw): ?string
    {
        $key = strtolower(trim($raw));

        $map = [
            'everyone' => 'everyone',
            'all' => 'everyone',
            'sub' => 'subscriber',
            'subs' => 'subscriber',
            'subscriber' => 'subscriber',
            'vip' => 'vip',
            'vips' => 'vip',
            'mod' => 'moderator',
            'mods' => 'moderator',
            'moderator' => 'moderator',
            'bc' => 'broadcaster',
            'broadcaster' => 'broadcaster',
            'owner' => 'broadcaster',
        ];

        return $map[$key] ?? null;
    }

    private function parseBoolean(string $raw): ?bool
    {
        return match (strtolower(trim($raw))) {
            'true', 'on', 'yes', '1' => true,
            'false', 'off', 'no', '0' => false,
            default => null,
        };
    }

    private function stripBang(string $raw): string
    {
        return strtolower(ltrim(trim($raw), '!'));
    }

    /**
     * Flatten a ValidationException into one chat line. Field names are
     * dropped (chat doesn't have a form to underline); we just join the
     * messages with " - " so the streamer sees every problem at once.
     */
    private function formatValidationError(ValidationException $e): string
    {
        $messages = [];
        foreach ($e->errors() as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $messages[] = $message;
            }
        }

        if (empty($messages)) {
            return 'error: invalid input';
        }

        return 'error: '.implode(' - ', $messages);
    }
}
