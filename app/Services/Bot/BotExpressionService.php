<?php

namespace App\Services\Bot;

use App\Models\BotChatOutbox;
use App\Models\BotExpression;
use App\Support\BotChatGate;
use Illuminate\Support\Carbon;

/**
 * Orchestrates a Bot Expression invocation.
 *
 * Lifecycle for one chat command:
 *   canFire()  - permission + cooldown gate, no side effects
 *   fire()     - bump counters, resolve template, write outbox row, stamp
 *                last_fired_at
 *
 * Both are intentionally thin. Resolution is delegated to BotExpressionResolver
 * so it can be reused (and tested) in isolation by the builder UI's preview.
 */
readonly class BotExpressionService
{
    public function __construct(
        private BotExpressionResolver $resolver,
        private BotCounterService     $counters,
    ) {}

    /**
     * @param  array<int,string>  $badges  Badge names from the chatter's IRC tags
     *                                     (subscriber, vip, moderator, broadcaster).
     *                                     Empty array = unbadged chatter.
     */
    public function canFire(BotExpression $expression, array $badges): bool
    {
        if (! $expression->enabled) {
            return false;
        }

        if (! BotChatGate::hasPermission($expression->permission_level, $badges)) {
            return false;
        }

        // Broadcaster bypasses cooldown to match the existing builtin-command pattern.
        if (BotChatGate::isBroadcaster($badges)) {
            return true;
        }

        return BotChatGate::isOffCooldown($expression->last_fired_at, $expression->cooldown_seconds);
    }

    /**
     * Resolve the expression and queue the result into bot_chat_outbox. Caller
     * is expected to have already gated via canFire(). Returns the resolved
     * string (mostly for tests / preview parity).
     *
     * @param  array<string,mixed>  $botContext  See BotExpressionResolver::resolve.
     */
    public function fire(BotExpression $expression, array $botContext): string
    {
        $user = $expression->user;

        // Apply the increments declared by any counter: tags BEFORE resolving,
        // so the tag reads the post-increment value (matching what streamers
        // expect from SE's ${count}). This is the only place a bot expression
        // writes: keeping it out of the resolver is what leaves the dry-run
        // preview, the validator and every re-resolve free of side effects.
        $this->counters->bump($user, $expression->expression);

        $message = $this->resolver->resolve($user, $expression->expression, $botContext);

        if ($message !== '') {
            BotChatOutbox::create([
                'user_id' => $user->id,
                'message' => $message,
            ]);
        }

        $expression->forceFill(['last_fired_at' => Carbon::now()])->save();

        return $message;
    }

    /**
     * Build the per-invocation bot context from the bot's POST payload. Lives
     * here (not in the controller) so the builder UI's preview can reuse it.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function buildBotContext(string $command, array $payload): array
    {
        $args = (string) ($payload['args'] ?? '');
        $tokens = $args === '' ? [] : preg_split('/\s+/', $args);

        $context = [
            'from_user' => (string) ($payload['chatter_display_name'] ?? $payload['chatter_login'] ?? ''),
            'from_user_login' => strtolower((string) ($payload['chatter_login'] ?? '')),
            'from_user_id' => (string) ($payload['chatter_id'] ?? ''),
            'command' => $command,
            'args' => $args,
            'channel' => strtolower((string) ($payload['channel_login'] ?? '')),
            // Unix seconds at the moment the bot dispatched this command.
            // Pipe-formatter friendly: [[[bot:fired_at|date:HH:mm]]]
            // resolves through the existing date formatter using the
            // streamer's locale.
            'fired_at' => (string) now()->timestamp,
        ];

        // Flat keys: bot:args.0 / .1 / ... map directly to "args.0" / "args.1"
        // string keys in $context. The resolver does literal key lookup, so
        // these don't collide with the "args" full-tail key above.
        foreach ($tokens as $i => $token) {
            $context["args.$i"] = $token;
        }

        return $context;
    }
}
