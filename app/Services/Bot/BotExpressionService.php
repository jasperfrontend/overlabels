<?php

namespace App\Services\Bot;

use App\Models\BotChatOutbox;
use App\Models\BotExpression;
use Illuminate\Support\Carbon;

/**
 * Orchestrates a Bot Expression invocation.
 *
 * Lifecycle for one chat command:
 *   canFire()  - permission + cooldown gate, no side effects
 *   fire()     - resolve template, write outbox row, stamp last_fired_at
 *
 * Both are intentionally thin. Resolution is delegated to BotExpressionResolver
 * so it can be reused (and tested) in isolation by the builder UI's preview.
 */
class BotExpressionService
{
    /**
     * Permission tiers in least-to-most-privileged order, matching
     * BotCommand::PERMISSION_LEVELS exactly.
     */
    private const array TIER_ORDER = [
        'everyone' => 0,
        'subscriber' => 1,
        'vip' => 2,
        'moderator' => 3,
        'broadcaster' => 4,
    ];

    public function __construct(
        private readonly BotExpressionResolver $resolver,
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

        $isBroadcaster = in_array('broadcaster', $badges, true);

        if (! $this->hasPermission($expression->permission_level, $badges)) {
            return false;
        }

        // Broadcaster bypasses cooldown to match the existing builtin-command pattern.
        if ($isBroadcaster) {
            return true;
        }

        return $this->isOffCooldown($expression);
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
        ];

        // Flat keys: bot:args.0 / .1 / ... map directly to "args.0" / "args.1"
        // string keys in $context. The resolver does literal key lookup, so
        // these don't collide with the "args" full-tail key above.
        foreach ($tokens as $i => $token) {
            $context["args.$i"] = $token;
        }

        return $context;
    }

    /**
     * @param  array<int,string>  $badges
     */
    private function hasPermission(string $required, array $badges): bool
    {
        $requiredTier = self::TIER_ORDER[$required] ?? 0;

        $highest = 0;
        foreach ($badges as $badge) {
            $tier = self::TIER_ORDER[$badge] ?? null;
            if ($tier !== null && $tier > $highest) {
                $highest = $tier;
            }
        }

        return $highest >= $requiredTier;
    }

    private function isOffCooldown(BotExpression $expression): bool
    {
        if ($expression->cooldown_seconds <= 0 || $expression->last_fired_at === null) {
            return true;
        }

        $expiresAt = $expression->last_fired_at->copy()->addSeconds($expression->cooldown_seconds);

        return Carbon::now()->greaterThanOrEqualTo($expiresAt);
    }
}
