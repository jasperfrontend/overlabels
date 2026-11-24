<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Store every account's Twitch follower total on the user row, so
 * /admin/users can show which channels are big enough to drop a raid's
 * follow flood on the platform (#343). Scheduled daily in routes/console.php.
 *
 * One Helix call per account, with the account's own token (it holds
 * `moderator:read:followers`). An account Twitch will not answer for keeps
 * its last known total: an expired login is not a reason to forget a number.
 */
class RefreshFollowerCounts extends Command
{
    protected $signature = 'twitch:refresh-follower-counts';

    protected $description = 'Store each account\'s Twitch follower total for the admin users list';

    public function handle(TwitchApiService $twitch, TwitchTokenService $tokens): int
    {
        $updated = 0;
        $skipped = 0;

        User::query()
            ->where('is_system_user', false)
            ->whereNotNull('twitch_id')
            ->orderBy('id')
            ->each(function (User $user) use ($twitch, $tokens, &$updated, &$skipped) {
                try {
                    if ($user->token_expires_at?->isPast()) {
                        $tokens->refreshUserToken($user);
                    }

                    $total = $user->access_token
                        ? $twitch->getFollowerTotal($user->access_token, (string) $user->twitch_id)
                        : null;
                } catch (Throwable $e) {
                    Log::info('Follower total not refreshed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                    $total = null;
                }

                if ($total === null) {
                    $skipped++;

                    return;
                }

                $user->forceFill([
                    'twitch_followers_total' => $total,
                    'twitch_followers_checked_at' => now(),
                ])->saveQuietly();
                $updated++;
            });

        $this->line("follower totals updated: {$updated} | not refreshed: {$skipped}");

        return self::SUCCESS;
    }
}
