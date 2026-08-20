<?php

namespace App\Support;

use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\ListAppender;
use App\Models\ListMetaCommand;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The query pass behind /skills. Produces one subject per thing that can be
 * evaluated, each carrying a state per skill plus human-readable context.
 *
 * Deliberately NOT cached. The whole reason a skill is a query is that it
 * cannot go stale; a TTL would reintroduce exactly the drift that storing
 * completion rows would have.
 */
final class SkillFacts
{
    /**
     * A list slug is snake_case, and a read tag always closes with at least
     * `]`, so a trailing non-slug character is enough to stop list `q` from
     * matching `c:list:quotes`. Without the boundary every short slug would
     * report itself as read by whatever longer list happens to share a prefix.
     */
    private const READ_TAG_BOUNDARY = '[^a-z0-9_]';

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function for(User $user): array
    {
        return [
            'bot' => [self::botSubject($user)],
            'lists' => self::listSubjects($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function botSubject(User $user): array
    {
        $commandCount = BotCommand::where('user_id', $user->id)->where('enabled', true)->count()
            + ListAppender::where('user_id', $user->id)->where('enabled', true)->count()
            + ListMetaCommand::where('user_id', $user->id)->where('enabled', true)->count()
            + BotAlias::where('user_id', $user->id)->count();

        // No commands means the question does not arise. Telling someone with
        // an empty channel to add a bot is a suggestion, and this page does
        // not make suggestions.
        $state = match (true) {
            $commandCount === 0 => SkillCatalog::NOT_APPLICABLE,
            (bool) $user->bot_enabled => SkillCatalog::SATISFIED,
            default => SkillCatalog::MISSING,
        };

        return [
            'key' => 'account',
            'label' => 'Your channel',
            'context' => $commandCount > 0
                ? [$commandCount.' chat '.($commandCount === 1 ? 'command' : 'commands').' set up']
                : [],
            'states' => ['bot.in_chat' => $state],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listSubjects(User $user): array
    {
        // An enabled !list meta-command reads every list the user owns - its
        // vocabulary (count, first, last, random, search, draw) takes a slug -
        // so it satisfies readability for all of them at once rather than
        // per list.
        $hasMetaCommand = ListMetaCommand::where('user_id', $user->id)
            ->where('enabled', true)
            ->exists();

        $boundary = self::READ_TAG_BOUNDARY;

        // Every list is injected into every overlay payload regardless of use,
        // so the payload cannot say which are actually read. The template
        // SOURCE can. Kept as EXISTS subqueries so template HTML is never
        // hydrated just to answer a boolean.
        $rows = DB::table('option_sets as o')
            ->where('o.user_id', $user->id)
            ->orderBy('o.slug')
            ->select('o.id', 'o.slug', 'o.label', 'o.recipe_instance_id')
            ->selectRaw("COALESCE((o.event_feed->>'enabled') = 'true', false) as feed_enabled")
            ->selectRaw('EXISTS (SELECT 1 FROM list_appenders a WHERE a.target_list_id = o.id AND a.user_id = o.user_id AND a.enabled) as has_appender')
            ->selectRaw('(SELECT a.command FROM list_appenders a WHERE a.target_list_id = o.id AND a.user_id = o.user_id AND a.enabled ORDER BY a.id LIMIT 1) as appender_command')
            ->selectRaw(
                "EXISTS (SELECT 1 FROM overlay_templates t WHERE t.owner_id = o.user_id AND (
                    COALESCE(t.html, '') ~ ('c:list:' || o.slug || '{$boundary}')
                 OR COALESCE(t.head, '') ~ ('c:list:' || o.slug || '{$boundary}')
                 OR COALESCE(t.css,  '') ~ ('c:list:' || o.slug || '{$boundary}')
                )) as read_by_overlay"
            )
            ->selectRaw(
                "EXISTS (SELECT 1 FROM bot_commands b WHERE b.user_id = o.user_id AND b.enabled
                 AND COALESCE(b.reply, '') ~ ('c:list:' || o.slug || '{$boundary}')) as read_by_command"
            )
            ->get();

        $subjects = [];

        foreach ($rows as $row) {
            $readable = $hasMetaCommand || $row->read_by_overlay || $row->read_by_command;

            // What fills it, stated as fact rather than as a checklist. A list
            // with none of these is hand-curated from the dashboard, which is
            // a legitimate setup and not a finding.
            $context = [];
            if ($row->has_appender) {
                $context[] = 'Chat adds to it with !'.$row->appender_command;
            }
            if ($row->feed_enabled) {
                $context[] = 'Filled by the recent-events feed';
            }
            if ($row->recipe_instance_id !== null) {
                $context[] = 'Installed by a recipe';
            }
            if ($context === []) {
                $context[] = 'You fill this one from the dashboard';
            }

            if ($readable) {
                $context[] = match (true) {
                    (bool) $row->read_by_overlay => 'An overlay shows it',
                    (bool) $row->read_by_command => 'A chat command shows it',
                    default => 'The !list command can show it',
                };
            }

            $subjects[] = [
                'key' => 'list:'.$row->slug,
                // Falls back to the slug rather than a dash: an unlabelled
                // list still has a name the owner will recognise.
                'label' => filled($row->label) ? $row->label : $row->slug,
                'context' => $context,
                'states' => [
                    'lists.readable' => $readable ? SkillCatalog::SATISFIED : SkillCatalog::MISSING,
                ],
            ];
        }

        return $subjects;
    }
}
