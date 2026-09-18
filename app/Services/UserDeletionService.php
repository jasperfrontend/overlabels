<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\ExternalIntegration;
use App\Models\ImageUpload;
use App\Models\Kit;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\External\FourthwallApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserDeletionService
{
    /**
     * Metadata keys in an admin audit row that name the user the row is about.
     * Frozen here rather than derived: an audit row is written by hand at each
     * call site, so this is a list of what those call sites actually put in.
     */
    private const AUDIT_IDENTITY_KEYS = [
        'user_name',
        'twitch_id',
        'target_name',
        'target_twitch_id',
        'name',
        'login',
        'display_name',
    ];

    public function __construct(
        private readonly ImageUploadService $images,
        private readonly UserEventSubManager $eventSub,
        private readonly TwitchTokenService $twitchTokens,
        private readonly FourthwallApiClient $fourthwall,
    ) {}

    /**
     * Hard-delete a user and everything they own. Used by both the admin
     * "delete_all" strategy and the self-serve account deletion on
     * /settings/account. Caller is responsible for audit logging and for
     * making sure the user is not the ghost user.
     *
     * Ordering is deliberate and in three phases:
     *
     *  1. Read what the external cleanup will need, and hand back the Twitch
     *     subscriptions, while the rows still exist.
     *  2. Delete everything in one transaction. This is the authoritative step:
     *     if it commits, the account is gone.
     *  3. Release what lives outside our database, best effort. A failure here
     *     is logged and never re-raised - an unreachable third party must not
     *     leave a user with an account they asked us to destroy.
     *
     * @param  bool  $redactAuditTrail  True when the user asked to be erased, which
     *                                  takes their name out of admin audit rows about
     *                                  them. False for an admin-initiated deletion,
     *                                  where the record of who was removed and why is
     *                                  the point; those rows age out on the schedule
     *                                  in routes/console.php instead.
     */
    public function eraseAccount(User $user, bool $redactAuditTrail = false): void
    {
        $imageUrls = ImageUpload::where('user_id', $user->id)->pluck('url')->all();
        $fourthwallWebhook = $this->fourthwallWebhook($user);
        $twitchAccessToken = $user->access_token;

        // Hands the subscriptions back to Twitch and clears the local rows.
        // Before the transaction because it reads them and talks to the network.
        $this->attempt('twitch eventsub subscriptions', fn () => $this->eventSub->removeUserSubscriptions($user));

        DB::transaction(function () use ($user, $redactAuditTrail) {
            // The fork guard is lifted here on purpose: a kit someone else had
            // copied used to throw out of this transaction and abort the whole
            // erasure. See Kit::withoutForkGuard().
            Kit::withoutForkGuard(function () use ($user) {
                Kit::where('owner_id', $user->id)->each(function ($kit) {
                    $kit->templates()->detach();
                    $kit->delete();
                });
            });

            OverlayTemplate::where('owner_id', $user->id)->each(function ($template) {
                $template->targetStaticOverlays()->detach();
                $template->kits()->detach();
                $template->controls()->delete();
                $template->eventMappings()->delete();
                $template->delete();
            });

            OverlayControl::where('user_id', $user->id)->delete();
            ExternalIntegration::where('user_id', $user->id)->delete();

            $user->overlayAccessTokens()->delete();
            $user->eventsubSubscriptions()->delete();

            // twitch_events.user_id is nullOnDelete, so these rows would
            // otherwise survive the cascade and sit out their 90-day prune
            // holding this account's event history, including the display
            // names and avatars of everyone who followed, subscribed or
            // cheered. Nobody is left who could read them.
            TwitchEvent::where('user_id', $user->id)->delete();

            // sessions.user_id is a plain index with no foreign key, so these
            // are not swept by the cascade either. Each row holds an IP address
            // and a user agent.
            DB::table('sessions')->where('user_id', $user->id)->delete();

            if ($redactAuditTrail) {
                $this->redactAuditTrail($user);
            }

            $user->forceDelete();
        });

        // Everything past this point is outside the database and best effort.
        $this->attempt('stored images', fn () => $this->deleteImages($imageUrls));
        $this->attempt('fourthwall webhook', fn () => $this->deregisterFourthwall($fourthwallWebhook));
        $this->attempt('twitch token revocation', fn () => $this->revokeTwitchToken($twitchAccessToken));
    }

    /**
     * The Fourthwall integration's remote webhook id, read before the
     * integration row is deleted. Returns the pieces deregistration needs.
     *
     * @return array{integration: ExternalIntegration, webhook_id: string}|null
     */
    private function fourthwallWebhook(User $user): ?array
    {
        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'fourthwall')
            ->first();

        if (! $integration) {
            return null;
        }

        $webhookId = $integration->getCredentialsDecrypted()['webhook_id'] ?? null;

        if (! is_string($webhookId) || $webhookId === '') {
            return null;
        }

        return ['integration' => $integration, 'webhook_id' => $webhookId];
    }

    /**
     * Objects in the images bucket are not reachable by any cascade: the
     * image_uploads row goes with the user, but the object it points at stays,
     * and its URL keeps resolving publicly forever.
     *
     * deleteByUrl() re-checks for remaining references before removing
     * anything, which matters here because a copy of a template carries the
     * original screenshot URL verbatim. A URL another user's copy still points
     * at is left alone.
     *
     * @param  list<string|null>  $urls
     */
    private function deleteImages(array $urls): void
    {
        foreach (array_unique(array_filter($urls)) as $url) {
            $this->images->deleteByUrl($url);
        }
    }

    /**
     * @param  array{integration: ExternalIntegration, webhook_id: string}|null  $webhook
     */
    private function deregisterFourthwall(?array $webhook): void
    {
        if ($webhook === null) {
            return;
        }

        $this->fourthwall->deregisterWebhook($webhook['integration'], $webhook['webhook_id']);
    }

    /**
     * Hand the OAuth grant back to Twitch. Without this the connection stays
     * listed on the user's Twitch account after the Overlabels account it
     * belonged to has stopped existing, and only they can remove it.
     */
    private function revokeTwitchToken(?string $accessToken): void
    {
        if ($accessToken === null || $accessToken === '') {
            return;
        }

        $this->twitchTokens->revokeToken($accessToken);
    }

    /**
     * Take the user's name and Twitch id out of admin audit rows about them,
     * leaving the action, the administrator, the timestamp and everything else
     * the row recorded.
     *
     * The rows are not deleted. An append-only log that entries can vanish from
     * is not a log, and the fact that an action happened is the administrator's
     * record, not the user's personal data.
     */
    private function redactAuditTrail(User $user): void
    {
        AdminAuditLog::where('target_type', 'User')
            ->where('target_id', $user->id)
            ->each(function (AdminAuditLog $row) {
                $metadata = $row->metadata;

                if (! is_array($metadata)) {
                    return;
                }

                $redacted = false;

                foreach (self::AUDIT_IDENTITY_KEYS as $key) {
                    if (array_key_exists($key, $metadata)) {
                        $metadata[$key] = '[erased]';
                        $redacted = true;
                    }
                }

                if ($redacted) {
                    $row->metadata = $metadata;
                    $row->save();
                }
            });
    }

    /**
     * Run one cleanup step, swallowing and logging anything it throws.
     *
     * The account is already gone by the time most of these run. Letting a
     * failing third party bubble up would turn a completed deletion into a 500
     * and tell the user it did not work.
     */
    private function attempt(string $what, callable $step): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::warning('Account erasure: '.$what.' step failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
