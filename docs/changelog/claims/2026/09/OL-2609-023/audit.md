## Audit of OL-2609-023 - feat(twitch): subscribe to channel.chat.notification and store it, the ledger a Plus Points count is built from

**Audited:** 2026-09-24
**Commit:** 71a413a6884e5c2c9b6230d5db86fdf8971a69f2
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/UserEventSubManager.php:177-181 @71a413a` - version `'1'`, `condition_keys` `['broadcaster_user_id', 'user_id']`, `required_scope` `'channel:bot'`; same at :177-180 @HEAD |
| C2 | CONFIRMED | `app/Services/TwitchScopeService.php:46 @71a413a~1` - `'channel:bot'` already in `REQUIRED_SCOPES` before the commit; the diff adds nothing to `REQUIRED_SCOPES`; still :46 @HEAD |
| C3 | CONFIRMED | `tests/Feature/ChatNotificationSubscriptionTest.php:102-120 @71a413a` - asserts condition `toBe(['broadcaster_user_id' => '12345', 'user_id' => '1130071166'])` with that config value; passed |
| C4 | CONFIRMED | same file :122-132 - scopes = `REQUIRED_SCOPES` minus `channel:bot`, asserts no sent payload of that type; passed |
| C5 | CONFIRMED | same file :134-160 - `failed` has the key, message contains `TWITCHBOT_USER_ID`, sent list lacks the type, `count($sent)` = `count(SUPPORTED_EVENTS) - 1`; passed |
| C6 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:51 @71a413a` constant; guard at :581-583 after `TwitchEvent::create()` (:558) and `EventMeter::record()` (:578), before `refreshCachesForEvent` (:586), `handleEvent` (:595), `resolveForEvent` (:603), `TwitchEventReceived` (:635); @HEAD constant :54, guard :583 |
| C7 | CONFIRMED | same test file :162-184 - asserts row `user_id` = broadcaster's user, `alert_id` null, `outcome` null, `AlertTriggered` and `TwitchEventReceived` not dispatched, no "No enabled template mapping" warning; passed |
| C8 | UNVERIFIABLE | tagged [unverified] (fail-first run against a tree that no longer exists) |
| C9 | CONFIRMED | same test file :186-191 - `STORE_ONLY_EVENTS` `toBe(['channel.chat.notification'])`; passed |
| C10 | CONFIRMED | same test file :193-197 - `EventTemplateMapping::EVENT_TYPES` `not->toHaveKey('channel.chat.notification')`; passed |
| C11 | CONFIRMED | `app/Services/TwitchScopeService.php:88 @71a413a` - `'channel.chat.notification' => 'channel:bot'`; same value at :108 @HEAD (moved by OL-2609-024) |
| C12 | CONFIRMED | `config/services.php:67 @71a413a` - `'user_id' => env('TWITCHBOT_USER_ID')`; `config/deploy.yml:174 @71a413a` under `clear:` (:160), absent from `secret:` (:249); @HEAD deploy.yml:193, unchanged value |
| C13 | UNVERIFIABLE | tagged [unverified] (production data) |
| C14 | UNVERIFIABLE | tagged [unverified] (Twitch behaviour); see Notes |
| C15 | UNVERIFIABLE | tagged [unverified] (production data) |
| C16 | CONFIRMED | `resources/js/components/EventsTable.vue:213 @71a413a` - type in `nonReplayableTypes`, read by `canReplay()` :217; same at :213 @HEAD |

Tests run: `php artisan test --filter=ChatNotificationSubscriptionTest` at HEAD - 7 passed, 24 assertions. The test file has no commits after 71a413a.

### Surface
Phantom: `docs/private/help-twitch-tv-s-article-plus-program.md` (gitignored by `.gitignore:19` `/docs/private`, not in the diff).

### Findings
- **F1** phantom path - Surface lists `docs/private/help-twitch-tv-s-article-plus-program.md`, which `git show --stat 71a413a` does not contain and git never tracked (`.gitignore:19`); a Surface line should name only paths in the diff, so a later claim should say the article is not part of the record.
- **F2** false statement in Unchanged - the first Unchanged line says `eventsub:monitor --fix` "only dispatches setup for accounts with `eventsub_connected_at` null", but `app/Console/Commands/MonitorEventSubHealth.php:99-110 @71a413a` also calls `SetupUserEventSubSubscriptions::dispatch($user, true)` for every user holding a subscription in a failed status, and that job (`app/Jobs/SetupUserEventSubSubscriptions.php:49-54 @71a413a`) re-runs `setupUserSubscriptions()` over all `SUPPORTED_EVENTS`, so a connected account with one failed subscription does pick up the new type; the CLAUDE.md line this commit added ("`eventsub:monitor --fix` only repairs accounts with NO subscriptions") repeats the same error and is still present @HEAD. A later claim should correct both.

### Notes
- C14 and the "no re-auth" wording were overtaken by OL-2609-024 (C6: Twitch returned 403 on all 13 `channel:bot` accounts; the bot account must log in via `?scopes=bot`). 024 cites this change inline in its Unchanged section, so this is disclosed drift, not a finding.
- `TwitchEventSubController.php` was later edited by OL-2609-097 (`8ad02ed3`) and others; the `STORE_ONLY_EVENTS` constant and guard are identical @HEAD.
- CLAUDE.md's rejection of a webhook subscription to `channel.chat.message` (Chat controls section) names a different, per-message event type. It is not contradicted by subscribing to `channel.chat.notification`.
