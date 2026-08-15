<?php

namespace App\Services\Bot;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\User;
use App\Support\BotTags;
use Illuminate\Database\Eloquent\Collection;

/**
 * The writing half of the `counter:` namespace.
 *
 * A counter is not a new kind of storage - it is an ordinary `counter`
 * OverlayControl, user-scoped and not source-managed. That is the whole point
 * of the design: the same row the chat command bumps is the one an overlay
 * renders via `[[[c:wins]]]` and the one `!set wins 40` / `!reset wins` already
 * reach through BotControlController. Chat and the on-screen graphic move
 * together, which is the thing a chat-only counter cannot do.
 *
 * WHERE THE INCREMENT LIVES MATTERS. bump() is called from
 * BotCommandService::fire() - before the resolver runs and outside it
 * entirely. The resolver stays a pure read, so:
 *   - the builder's dry-run preview and the validator never mutate anything,
 *   - a tag used twice in one message still counts once (keys are deduped),
 *   - `[[[counter:wins]]]` reads the POST-increment value, like SE's ${count}.
 * Do not move the increment into BotCommandResolver::lookup().
 */
class BotCounterService
{
    /** Types a counter tag may point at. Anything else is left alone. */
    private const array NUMERIC_TYPES = ['number', 'counter'];

    /**
     * Create any counter control named by $reply that does not exist yet.
     *
     * Idempotent, and called on every save rather than only the first, for the
     * same reason integration provisioning is (see DonationIntegrationController):
     * a command that gains a counter later picks it up on the next edit.
     *
     * @return array<int,string> Keys actually created, for the chat reply.
     */
    public function provision(User $user, string $reply): array
    {
        $created = [];

        foreach (BotTags::counterKeys($reply) as $key) {
            if (! BotTags::isValidCounterKey($key)) {
                continue;
            }

            if ($this->controlsFor($user, $key)->isNotEmpty()) {
                continue;
            }

            OverlayControl::create([
                // User-scoped, so the counter is readable from every one of
                // this user's overlays rather than one template.
                'overlay_template_id' => null,
                'user_id' => $user->id,
                'key' => $key,
                'label' => ucfirst(str_replace('_', ' ', $key)),
                'type' => 'counter',
                'value' => '0',
                'config' => null,
                'sort_order' => 0,
                'source' => null,
                // Never source-managed: setValue()/update() 403 on those, which
                // would put the counter out of reach of !set and !reset.
                'source_managed' => false,
            ]);

            $created[] = $key;
        }

        return $created;
    }

    /**
     * Increment every counter named by $reply by one and broadcast each
     * new value, so overlays update in the same instant chat does.
     *
     * Provisions first, so a counter deleted from the UI after the command was
     * written silently comes back instead of breaking the command.
     */
    public function bump(User $user, string $reply): void
    {
        $keys = BotTags::counterKeys($reply);

        if ($keys === []) {
            return;
        }

        $this->provision($user, $reply);

        foreach ($keys as $key) {
            if (! BotTags::isValidCounterKey($key)) {
                continue;
            }

            foreach ($this->controlsFor($user, $key) as $control) {
                // A counter tag pointed at a text or boolean control is
                // nonsense. The validator rejects it at authoring time; here
                // on the live path we skip it, per the bot's silent-on-block
                // policy - a chat command is no place to raise.
                if (! in_array($control->type, self::NUMERIC_TYPES, true)) {
                    continue;
                }

                $newValue = (string) ((float) ($control->value ?? 0) + 1);
                $control->update(['value' => $newValue]);

                ControlValueUpdated::dispatch(
                    $control->overlay_template_id ? ($control->template?->slug ?? '') : '',
                    $control->broadcastKey(),
                    $control->type,
                    $newValue,
                    $user->twitch_id,
                );
            }
        }
    }

    /**
     * The user's controls for $key that chat is allowed to touch.
     *
     * Mirrors BotControlController::userControlsQuery exactly - same key, same
     * source_managed exclusion - so `counter:wins` and `!increment wins` can
     * never disagree about which rows they mean. Returns a collection because
     * a key may exist both user-scoped and on a template.
     *
     * @return Collection<int,OverlayControl>
     */
    private function controlsFor(User $user, string $key): Collection
    {
        return OverlayControl::where('user_id', $user->id)
            ->where('key', $key)
            ->where('source_managed', false)
            ->with('template')
            ->get();
    }

    /**
     * Types of any existing non-source-managed controls for each counter key
     * in $reply, keyed by key. Used by the validator to refuse a counter
     * tag that points at, say, an existing text control.
     *
     * @return array<string,string> key => the first conflicting control's type
     */
    public function conflictingTypes(User $user, string $reply): array
    {
        $conflicts = [];

        foreach (BotTags::counterKeys($reply) as $key) {
            foreach ($this->controlsFor($user, $key) as $control) {
                if (! in_array($control->type, self::NUMERIC_TYPES, true)) {
                    $conflicts[$key] = $control->type;
                    break;
                }
            }
        }

        return $conflicts;
    }
}
