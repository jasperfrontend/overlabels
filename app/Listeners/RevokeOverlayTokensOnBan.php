<?php

namespace App\Listeners;

use App\Models\OverlayAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mchev\Banhammer\Events\ModelWasBanned;

/**
 * A ban must kill the person's overlays, not just their login.
 *
 * `CheckBanned` inspects the *requester*. An overlay render arrives from OBS
 * carrying an OverlayAccessToken and no session, so `$request->user()` is null
 * and a user ban never fires: the banned streamer kept a fully working overlay
 * on screen while locked out of the site. Revoking the tokens closes that,
 * because it works at the only identity the render request actually presents.
 *
 * IP bans need nothing here - `IP::isBanned($request->ip())` already rejects
 * those at request time, wherever they land.
 *
 * Deliberately NOT queued. "Immediately" is the whole point; a queued listener
 * would leave the overlay live until a worker picked the job up.
 *
 * Deliberately NOT reversed on unban. Tokens are shown once and stored as
 * sha256, so nothing here can hand a plaintext token back; an unbanned user
 * mints a new one and repoints their OBS source. Restoring silently would also
 * mean a ban leaves no lasting trace on the thing it was meant to stop.
 */
class RevokeOverlayTokensOnBan
{
    public function handle(ModelWasBanned $event): void
    {
        $user = $this->resolveUser($event);

        if (! $user instanceof User) {
            return;
        }

        $userId = $user->id;

        OverlayAccessToken::where('user_id', $userId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Both admin ban flows already delete sessions inline, but the CLI ban
        // in routes/console.php does not, and neither would any future call
        // site. Doing it here means every path that reaches `->ban()` ends the
        // session, rather than each caller having to remember. The deletes are
        // idempotent, so the existing inline ones stay as defence in depth.
        DB::table('sessions')->where('user_id', $userId)->delete();
    }

    /**
     * Banhammer's BanObserver dispatches `new ModelWasBanned($ban->bannable(), $ban)`
     * with parentheses, so `$event->model` is the MorphTo *relation* rather than
     * the banned model - `instanceof User` on it is always false. Verified
     * against v2.4: the payload is a `Relations\MorphTo` while
     * `$event->ban->bannable` is the `App\Models\User`.
     *
     * Read the ban instead, and still prefer `$event->model` when it ever does
     * hold a User, so a fixed upstream keeps working.
     */
    private function resolveUser(ModelWasBanned $event): ?User
    {
        if ($event->model instanceof User) {
            return $event->model;
        }

        $bannable = $event->ban?->bannable;

        return $bannable instanceof User ? $bannable : null;
    }
}
