<?php

namespace App\Services;

use App\Models\User;
use App\Support\WelcomeFunnel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Finds and erases accounts that signed up and never used Overlabels.
 *
 * An account is idle once it is older than GRACE_HOURS and:
 *
 *  - never finished the welcome funnel (installed a product),
 *  - holds no active overlay access token,
 *  - owns nothing beyond what signing up created for it.
 *
 * Signing up creates the starter Kit copy (its overlays, their controls and
 * the alert mappings onboarding wires to them), the EventSub subscriptions,
 * a stream state row and the events Twitch sends. None of those count.
 * Editing the starter Kit copy does not count either: an edited Kit with no
 * token and nothing else is still an unused account.
 *
 * The starter copy is recognised as a kit forked from a kit that is flagged
 * starter today. If the starter flag ever moves to a different kit, older
 * copies stop being recognised and their owners are KEPT - the safe way for
 * this to be wrong.
 *
 * Why: every account holds EventSub subscriptions and broadcast channels for
 * nobody, it is user data with no use, and someone who did nothing is better
 * off starting fresh than landing back on a dashboard holding a stale Kit.
 *
 * Erasure goes through UserDeletionService, the same path as self-serve and
 * admin deletion, so Twitch subscriptions, the OAuth grant and stored images
 * are released too.
 */
class IdleAccountSweep
{
    public const GRACE_HOURS = 48;

    /**
     * Tables where any row owned by the user means they did something. Every
     * one of these is only ever written by a user action or by an integration
     * the user connected.
     */
    private const ACTIVITY_TABLES = [
        'bot_aliases',
        'bot_builtins',
        'bot_commands',
        'checkins',
        'external_event_template_mappings',
        'external_events',
        'external_integrations',
        'image_uploads',
        'list_appenders',
        'list_meta_commands',
        'option_sets',
        'pickers',
        'recipe_chat_triggers',
        'recipe_instances',
        'tower_blocks',
        'user_chat_presets',
        'user_freesound_sounds',
    ];

    public function __construct(private readonly UserDeletionService $deletion) {}

    /**
     * Every account that is idle right now.
     *
     * @return Builder<User>
     */
    public function idle(): Builder
    {
        $query = User::query()
            ->where('created_at', '<=', now()->subHours(self::GRACE_HOURS))
            ->where('is_system_user', false)
            ->where(fn (Builder $q) => $q->whereNull('role')->orWhere('role', '!=', 'admin'))
            // A ban must outlive the account, or deleting it hands a banned
            // streamer a clean signup.
            ->whereDoesntHave('bans')
            ->where('bot_enabled', false)
            ->whereNull('preferences->'.WelcomeFunnel::PREFERENCE.'->installed_at');

        foreach (self::ACTIVITY_TABLES as $table) {
            $query->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from($table)
                ->whereColumn($table.'.user_id', 'users.id'));
        }

        return $query
            // An active token: someone put an overlay in OBS, or is about to.
            ->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('overlay_access_tokens')
                ->whereColumn('overlay_access_tokens.user_id', 'users.id')
                ->where('overlay_access_tokens.is_active', true)
                ->where(fn (QueryBuilder $q) => $q->whereNull('overlay_access_tokens.expires_at')
                    ->orWhere('overlay_access_tokens.expires_at', '>', now())))
            // Any kit that is not the starter copy.
            ->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('kits')
                ->whereColumn('kits.owner_id', 'users.id')
                ->where(fn (QueryBuilder $q) => $q->whereNull('kits.forked_from_id')
                    ->orWhereNotIn('kits.forked_from_id', $this->starterKitIds())))
            // Any overlay that is not part of the starter copy.
            ->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('overlay_templates')
                ->whereColumn('overlay_templates.owner_id', 'users.id')
                ->whereNotIn('overlay_templates.id', $this->starterCopyTemplateIds()))
            // Any control outside the starter copy, user-scoped ones included.
            ->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('overlay_controls')
                ->whereColumn('overlay_controls.user_id', 'users.id')
                ->where(fn (QueryBuilder $q) => $q->whereNull('overlay_controls.overlay_template_id')
                    ->orWhereNotIn('overlay_controls.overlay_template_id', $this->starterCopyTemplateIds())))
            // Any alert wired to something outside the starter copy.
            ->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('event_template_mappings')
                ->whereColumn('event_template_mappings.user_id', 'users.id')
                ->whereNotNull('event_template_mappings.template_id')
                ->whereNotIn('event_template_mappings.template_id', $this->starterCopyTemplateIds()));
    }

    /**
     * Erase one account, checking again first that it is still idle: the
     * candidate list is read before the first erasure, and someone can
     * install a product in between.
     */
    public function erase(User $user): bool
    {
        if (! $this->idle()->whereKey($user->getKey())->exists()) {
            return false;
        }

        $this->deletion->eraseAccount($user);

        return true;
    }

    private function starterKitIds(): QueryBuilder
    {
        return DB::table('kits as starter')
            ->select('starter.id')
            ->where('starter.is_starter_kit', true);
    }

    /**
     * Overlays in the user's starter copy. Correlated on users.id, so it is
     * only valid inside the subqueries above.
     */
    private function starterCopyTemplateIds(): QueryBuilder
    {
        return DB::table('kit_templates')
            ->join('kits as copy', 'copy.id', '=', 'kit_templates.kit_id')
            ->select('kit_templates.overlay_template_id')
            ->whereColumn('copy.owner_id', 'users.id')
            ->whereIn('copy.forked_from_id', $this->starterKitIds());
    }
}
