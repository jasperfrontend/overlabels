<?php

namespace App\Services\Recipes;

use App\Jobs\AutoPlayNext;
use App\Models\OptionSet;
use App\Models\RecipeInstance;
use App\Services\Lists\ListActionService;
use Illuminate\Support\Facades\Cache;

/**
 * The auto-play loop a product declares in its manifest:
 *
 *   "auto_play": { "list": "lane", "seconds": 15 }
 *
 * While the list has people in it, the next one in line is popped every
 * `seconds`, and nothing is said in chat. There is no on/off switch: a
 * product overlay is a full-screen scene that lives on its own OBS scene,
 * so it is on screen exactly when the streamer switches to it. The overlay
 * reads the same two fields `!list lane pop first` writes (`last_removed`,
 * `last_removed_at`), so a pop by hand still drives the same throw and
 * restarts the window.
 *
 * One hook starts it: a successful list append (ListAppendService::fire).
 * It lands in schedule(), which is a no-op unless the list belongs to an
 * install that declares a loop AND someone is in line.
 *
 * The cache key is the debounce: one pending job per list, whatever arrives
 * in between is absorbed, and the job forgets the key as its first act so a
 * change landing while it runs opens a new window. AutoPlayNext is
 * deliberately NOT ShouldBeUnique - a uniqueness lock on a delayed,
 * self-rescheduling job is the shape that silently killed the
 * VerifyStreamState chain.
 *
 * `seconds` is the recipe author's number and has to cover the overlay's
 * throw animation, or a pop lands on a throw still playing.
 */
class AutoPlayService
{
    public const string MANIFEST_KEY = 'auto_play';

    /** Below this a loop would outrun any animation; the manifest value is floored to it. */
    public const int MIN_SECONDS = 1;

    public function __construct(private readonly ListActionService $lists) {}

    /**
     * Hook: a list gained an item.
     */
    public function listAppended(OptionSet $list): void
    {
        $this->schedule($list);
    }

    /**
     * Arm the next pop for this list, if it belongs to a playing loop. The
     * delay is whatever is left of the window since the last pop, so a
     * bowler joining mid-throw waits for the throw to finish and a bowler
     * joining an idle lane goes at once.
     */
    public function schedule(OptionSet $list): void
    {
        $loop = $this->loopFor($list);
        if ($loop === null || ! $this->isPlaying($list)) {
            return;
        }

        $delay = $this->remainingWindow($list, $loop['seconds']);

        // TTL outlives the delay by a full window so a late worker cannot let
        // a second append arm a duplicate; the job forgets the key first.
        if (! Cache::add(self::pendingKey($list->id), 1, $delay + $loop['seconds'])) {
            return;
        }

        AutoPlayNext::dispatch($list->id)->delay(now()->addSeconds($delay))->afterCommit();
    }

    /**
     * The job's body: pop the next one in line if the loop is still playing
     * and the last throw has finished, then arm the next pop.
     */
    public function run(OptionSet $list): void
    {
        Cache::forget(self::pendingKey($list->id));

        $loop = $this->loopFor($list);
        if ($loop === null || ! $this->isPlaying($list)) {
            return;
        }

        if ($this->remainingWindow($list, $loop['seconds']) > 0) {
            // Someone popped by hand in the meantime; wait the throw out.
            $this->schedule($list);

            return;
        }

        $owner = $list->user;
        if ($owner === null) {
            return;
        }

        $this->lists->pop($owner, $list, 'first');

        $this->schedule($list->fresh());
    }

    /**
     * @return array{list: string, seconds: int}|null
     */
    public function loopFor(OptionSet $list): ?array
    {
        $instance = $list->recipeInstance;
        if (! $instance instanceof RecipeInstance) {
            return null;
        }

        $loop = $this->loopOf($instance);
        if ($loop === null) {
            return null;
        }

        $listId = $instance->primitive_map['lists'][$loop['list']] ?? null;

        return (int) $listId === (int) $list->id ? $loop : null;
    }

    /**
     * The list is enabled and someone is in line.
     */
    public function isPlaying(OptionSet $list): bool
    {
        return $list->disabled_at === null && array_values($list->items ?? []) !== [];
    }

    public static function pendingKey(int $listId): string
    {
        return "auto_play:pending:{$listId}";
    }

    /**
     * Seconds of the current throw still to play, 0 when the lane is idle.
     */
    private function remainingWindow(OptionSet $list, int $seconds): int
    {
        if ($list->last_removed_at === null) {
            return 0;
        }

        $age = (int) $list->last_removed_at->diffInSeconds(now());

        return max(0, $seconds - $age);
    }

    /**
     * @return array{list: string, seconds: int}|null
     */
    private function loopOf(RecipeInstance $instance): ?array
    {
        $declared = $instance->resolvedManifest()[self::MANIFEST_KEY] ?? null;
        if (! is_array($declared) || ! is_string($declared['list'] ?? null)) {
            return null;
        }

        return [
            'list' => $declared['list'],
            'seconds' => max(self::MIN_SECONDS, (int) ($declared['seconds'] ?? self::MIN_SECONDS)),
        ];
    }
}
