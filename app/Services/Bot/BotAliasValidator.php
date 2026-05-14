<?php

namespace App\Services\Bot;

use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\BotExpression;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared validation for Bot Aliases. Used by the web settings controller
 * and the chat-driven `!ol alias ...` admin path so the same rules gate
 * both surfaces. Returns a normalised payload (lowercased command,
 * single-spaced target_template); throws ValidationException with
 * field-keyed messages on any failure.
 *
 * Rules enforced here that go beyond plain field validation:
 *  - command can't collide with builtins or the user's own expressions.
 *  - target_template can't start with the alias's own command (no self-loop)
 *    nor with another alias for the same user (no alias->alias chains; the
 *    bot does one hop only).
 *  - placeholders are restricted to {1}, {2}, ..., {*}.
 */
class BotAliasValidator
{
    /**
     * @param  array<string,mixed>  $input  Raw input (command, target_template, permission_level, cooldown_seconds, enabled, hidden_from_commands).
     * @param  BotAlias|null  $existing  Set for updates so the duplicate check ignores the row being edited.
     * @return array<string,mixed> Normalised payload ready to feed into BotAlias::create() / ::update().
     */
    public function validateAndNormalize(int $userId, array $input, ?BotAlias $existing = null): array
    {
        $data = Validator::make($input, [
            'command' => [
                'required',
                'string',
                'max:30',
                'regex:/^!?[a-zA-Z0-9_-]{1,30}$/',
            ],
            'target_template' => [
                'required',
                'string',
                'max:200',
                'regex:/^!?[a-zA-Z0-9_-]{1,30}(\s.*)?$/u',
            ],
            'permission_level' => ['required', Rule::in(BotCommand::PERMISSION_LEVELS)],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'enabled' => ['required', 'boolean'],
            'hidden_from_commands' => ['required', 'boolean'],
        ])->validate();

        $command = strtolower(ltrim($data['command'], '!'));
        $target = $this->normalizeTargetTemplate($data['target_template']);

        $reservedBuiltins = array_column(BotCommand::DEFAULTS, 'command');
        if (in_array($command, $reservedBuiltins, true)) {
            throw ValidationException::withMessages([
                'command' => "'!$command' is a built-in bot command and can't be used as an alias name.",
            ]);
        }

        $clashesWithExpression = BotExpression::where('user_id', $userId)
            ->where('command', $command)
            ->exists();
        if ($clashesWithExpression) {
            throw ValidationException::withMessages([
                'command' => "You already have an expression for '!$command'. Pick a different alias name.",
            ]);
        }

        $duplicate = BotAlias::where('user_id', $userId)
            ->where('command', $command)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'command' => "You already have an alias for '!$command'.",
            ]);
        }

        $targetCommand = $this->extractTargetCommand($target);

        if ($targetCommand === $command) {
            throw ValidationException::withMessages([
                'target_template' => "An alias can't point to itself.",
            ]);
        }

        $chains = BotAlias::where('user_id', $userId)
            ->where('command', $targetCommand)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($chains) {
            throw ValidationException::withMessages([
                'target_template' => "'!$targetCommand' is itself an alias. Point this alias at the underlying command instead.",
            ]);
        }

        if (preg_match_all('/\{([^}]+)\}/u', $target, $matches)) {
            foreach ($matches[1] as $placeholder) {
                if (! preg_match('/^([1-9][0-9]*|\*)$/', $placeholder)) {
                    throw ValidationException::withMessages([
                        'target_template' => "Invalid placeholder '{{$placeholder}}'. Use {1}, {2}, ... or {*}.",
                    ]);
                }
            }
        }

        $data['command'] = $command;
        $data['target_template'] = $target;

        return $data;
    }

    private function normalizeTargetTemplate(string $raw): string
    {
        $trimmed = trim($raw);
        $trimmed = ltrim($trimmed, '!');

        return preg_replace('/\s+/u', ' ', $trimmed);
    }

    private function extractTargetCommand(string $normalizedTarget): string
    {
        $token = strtok($normalizedTarget, " \t");

        return strtolower($token === false ? '' : $token);
    }
}
