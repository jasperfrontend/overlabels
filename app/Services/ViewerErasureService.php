<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\ExternalEvent;
use App\Models\ListAppendHistory;
use App\Models\OverlayControl;
use App\Models\TowerBlock;
use App\Models\TwitchEvent;
use Illuminate\Support\Facades\DB;

/**
 * Erases a viewer from the platform, and remembers not to store them again.
 *
 * A viewer is not a user. They have no account here, most of them have never
 * heard of Overlabels, and everything we hold about them arrived because they
 * typed something in somebody else's chat. So this runs on a Twitch id and
 * nothing else, and it runs platform-wide: they are asking us, not the streamer
 * whose channel they happened to be in.
 *
 * WHAT IT DOES NOT TOUCH, on purpose: the contents of a streamer's Lists. A
 * raffle pool or a quote wall is the streamer's own content, sitting on their
 * dashboard, and silently editing it from under them is not ours to do. The
 * viewer page tells people to ask the streamer for those, and the reply this
 * service produces says so too.
 */
class ViewerErasureService
{
    /**
     * Control keys that hold a single viewer's name. Blanked when the value
     * matches the person being erased, exactly, case-insensitively.
     *
     * A frozen list rather than a scan of every control: a streamer's own
     * text control might happen to contain a chatter's name, and a service
     * that goes looking for names to delete across arbitrary user content is
     * a worse thing than the problem it solves. These are the keys Overlabels
     * itself writes a name into.
     */
    public const NAME_CONTROL_KEYS = [
        'latest_chatter_name',
        'latest_checkin_name',
        'farthest_checkin_name_this_stream',
        'last_stacker_name',
        'last_topple_by',
        'latest_donor_name',
        'latest_cheerer_name',
    ];

    /**
     * Has this viewer asked not to be stored?
     */
    public function isSuppressed(?string $twitchId): bool
    {
        if ($twitchId === null || $twitchId === '') {
            return false;
        }

        return DB::table('viewer_erasures')->where('twitch_id', $twitchId)->exists();
    }

    /**
     * Delete everything keyed to this viewer and record the suppression.
     *
     * @return array<string, int> per-surface counts, for the reply and the log
     */
    public function erase(string $twitchId, ?string $login = null, ?string $displayName = null): array
    {
        $removed = [];

        $removed['checkins'] = Checkin::where('chatter_twitch_id', $twitchId)->delete();
        $removed['tower_blocks'] = TowerBlock::where('chatter_twitch_id', $twitchId)->delete();
        $removed['list_history'] = ListAppendHistory::where('chatter_id', $twitchId)->delete();

        // Twitch's own events: follows, subs, cheers, raids, redemptions, chat
        // notices. Twitch names the acting viewer differently per payload shape
        // - a raider is `from_broadcaster_user_id`, a chat notice's subscriber
        // is `chatter_user_id` - so match every place the id can sit, from the
        // same list the scrubber anonymises by. Field names are constants, never
        // input.
        $events = TwitchEvent::query();
        foreach (TwitchPayloadScrubber::ACTING_VIEWER_ID_FIELDS as $field) {
            $events->orWhereRaw("event_data->>'$field' = ?", [$twitchId]);
        }
        $removed['twitch_events'] = $events->delete();

        // Checkin and Tower write their own history here, keyed by chatter_id.
        $removed['external_events'] = ExternalEvent::whereRaw("raw_payload->>'chatter_id' = ?", [$twitchId])->delete();

        $removed['controls'] = $this->blankNameControls($login, $displayName);

        DB::table('viewer_erasures')->updateOrInsert(
            ['twitch_id' => $twitchId],
            ['erased_at' => now()],
        );

        return $removed;
    }

    /**
     * Blank any Overlabels-managed "latest name" control currently showing
     * this person. Exact match only: a partial match would blank a different
     * viewer whose name contains theirs.
     */
    private function blankNameControls(?string $login, ?string $displayName): int
    {
        $names = array_values(array_filter(array_unique([$login, $displayName])));

        if ($names === []) {
            return 0;
        }

        $controls = OverlayControl::whereIn('key', self::NAME_CONTROL_KEYS)
            ->where('source_managed', true)
            ->get()
            ->filter(fn (OverlayControl $control) => $this->matchesName((string) $control->value, $names));

        foreach ($controls as $control) {
            // writeValue() rather than update(): these are source_managed, and
            // OverlayControl::update() refuses those by design.
            $control->writeValue('');
        }

        return $controls->count();
    }

    /**
     * @param  list<string>  $names
     */
    private function matchesName(string $value, array $names): bool
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return false;
        }

        foreach ($names as $name) {
            if ($value === mb_strtolower(trim((string) $name))) {
                return true;
            }
        }

        return false;
    }
}
