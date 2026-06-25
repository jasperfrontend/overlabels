<?php

namespace App\Services\Bot;

use App\Models\BotCommand;
use App\Models\BotExpression;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared validation for Bot Expressions. Used by the web settings
 * controller (form posts) and the chat-driven `!ol cmd ...` admin path
 * so the same rules gate both surfaces. Returns a normalised payload
 * (lowercased command, '!' stripped); throws ValidationException with
 * field-keyed messages on any failure.
 */
class BotExpressionValidator
{
    /**
     * @param  array<string,mixed>  $input  Raw input (command, permission_level, cooldown_seconds, expression, enabled, hidden_from_commands).
     * @param  BotExpression|null  $existing  Set for updates so the duplicate check ignores the row being edited.
     * @return array<string,mixed> Normalised payload ready to feed into BotExpression::create() / ::update().
     */
    public function validateAndNormalize(int $userId, array $input, ?BotExpression $existing = null): array
    {
        $data = Validator::make($input, [
            'command' => [
                'required',
                'string',
                'max:30',
                'regex:/^!?[a-zA-Z0-9_-]{1,30}$/',
            ],
            'permission_level' => ['required', Rule::in(BotCommand::PERMISSION_LEVELS)],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'expression' => ['required', 'string', 'max:2000'],
            'enabled' => ['required', 'boolean'],
            'hidden_from_commands' => ['required', 'boolean'],
            // Optional self-destruct timer, whole hours 1-8760 (one year);
            // null/absent means "no timer". Only the web form sends this - the
            // chat-admin path manages destroy_at through its own option flow.
            'destroy_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
        ])->validate();

        $command = strtolower(ltrim($data['command'], '!'));

        $reserved = array_column(BotCommand::DEFAULTS, 'command');
        if (in_array($command, $reserved, true)) {
            throw ValidationException::withMessages([
                'command' => "'!$command' is a built-in bot command and can't be reused as an expression.",
            ]);
        }

        $duplicate = BotExpression::where('user_id', $userId)
            ->where('command', $command)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'command' => "You already have an expression for '!$command'.",
            ]);
        }

        $data['command'] = $command;

        return $data;
    }
}
