## OL-2609-023 - feat(twitch): subscribe to channel.chat.notification and store it, the ledger a Plus Points count is built from

**Shipped:** 2026-09-07
**Commit:** `git log --grep=OL-2609-023`

### Surface
- `app/Services/UserEventSubManager.php` - `channel.chat.notification` added to `SUPPORTED_EVENTS`; `buildCondition()` takes a third `$botUserId` argument and has an arm for it; the setup loop reads `services.twitchbot.user_id` once and fails that one event when it is empty; a label added to `getSupportedEventLabels()`
- `app/Services/TwitchScopeService.php` - `channel.chat.notification => channel:bot` added to `EVENT_TYPE_TO_SCOPE`
- `app/Http/Controllers/TwitchEventSubController.php` - `STORE_ONLY_EVENTS` constant added; `handleTwitchEvent()` returns after the store and meter when the type is in it
- `config/services.php` - `twitchbot.user_id` read from `TWITCHBOT_USER_ID`
- `config/deploy.yml` - `TWITCHBOT_USER_ID: "1130071166"` under `env.clear`
- `phpunit.xml` - `TWITCHBOT_USER_ID` set to a placeholder for the suite
- `.env.example` - `TWITCHBOT_USER_ID=` documented
- `resources/js/components/EventsTable.vue` - `channel.chat.notification` added to `nonReplayableTypes` and `twitchRowLabels`; `who()` reads `chatter_user_name` for it; `details()` shows its `notice_type`
- `tests/Feature/ChatNotificationSubscriptionTest.php` - new file
- `CLAUDE.md` - a "Chat notices and Plus Points" section under External Integrations
- `docs/private/help-twitch-tv-s-article-plus-program.md` - new file, Twitch's Plus Program help article saved as markdown (gitignored directory; listed for completeness)

### Claims
- **C1** [code] `UserEventSubManager::SUPPORTED_EVENTS['channel.chat.notification']` is version `1`, condition keys `['broadcaster_user_id', 'user_id']`, `required_scope` `channel:bot`.
- **C2** [code] `channel:bot` is already in `TwitchScopeService::REQUIRED_SCOPES`, so no scope is added by this change and no account is asked to re-authorize.
- **C3** [test] `ChatNotificationSubscriptionTest` asserts the payload sent to `createSubscription` for this type has condition `{broadcaster_user_id: <streamer twitch_id>, user_id: <services.twitchbot.user_id>}`.
- **C4** [test] The same file asserts an account whose scopes lack `channel:bot` never has this type sent to Twitch (the pre-existing `required_scope` gate).
- **C5** [test] The same file asserts that with `services.twitchbot.user_id` empty, `setupUserSubscriptions()` puts this type in the `failed` bucket with a message containing `TWITCHBOT_USER_ID`, sends every other supported event, and never sends this one.
- **C6** [code] `TwitchEventSubController::STORE_ONLY_EVENTS` is `['channel.chat.notification']`, and `handleTwitchEvent()` returns immediately after `TwitchEvent::create()` and `EventMeter::record()` for a type in it, before `refreshCachesForEvent()`, `StreamSessionService::handleEvent()`, `EventTemplateMapping::resolveForEvent()` and the `TwitchEventReceived` broadcast.
- **C7** [test] The same file posts a signed `channel.chat.notification` webhook and asserts a `twitch_events` row exists with the broadcaster's `user_id`, `alert_id` null and `outcome` null, that neither `AlertTriggered` nor `TwitchEventReceived` is dispatched, and that no "No enabled template mapping" warning is logged.
- **C8** [unverified] With the early return in C6 disabled, the test in C7 fails on its `outcome` assertion: the alert path ran and recorded a `DeliveryOutcome` (`no_mapping`) on the row.
- **C9** [test] `the store-only list is exactly the chat notice` pins `STORE_ONLY_EVENTS` to that single value.
- **C10** [test] `the chat notice is not offered as an alert trigger` asserts `EventTemplateMapping::EVENT_TYPES` has no `channel.chat.notification` key.
- **C11** [code] `TwitchScopeService::EVENT_TYPE_TO_SCOPE['channel.chat.notification']` is `channel:bot`.
- **C12** [code] `config('services.twitchbot.user_id')` reads `TWITCHBOT_USER_ID`; `config/deploy.yml` sets it to `1130071166` in `env.clear`, not in the secrets list.
- **C13** [unverified] `1130071166` is the Twitch user id of the `overlabels` account, read from the production `users` table on 2026-09-07; it is the account whose token `bot_tokens` holds with scopes `user:bot`, `user:read:chat`, `user:write:chat`.
- **C14** [unverified] Twitch creates `channel.chat.notification` with an app access token when the `user_id` in the condition has granted `user:bot` and the `broadcaster_user_id` has granted `channel:bot` (or the user is a moderator of that channel).
- **C15** [unverified] On 2026-09-07, 13 of the 19 connected production accounts hold `channel:bot`; the other 6 land in `skipped_missing_scope` for this event until they log in again.
- **C16** [code] `EventsTable.vue` lists `channel.chat.notification` in `nonReplayableTypes`, so the row renders no Replay action.

### Unchanged
- `MonitorEventSubHealth` (`eventsub:monitor --fix`) only dispatches setup for accounts with `eventsub_connected_at` null, and is not in the diff; it does not add a new event type to an already-connected account. Existing accounts pick this subscription up through `eventsub:backfill-goals` (generic: it re-dispatches `SetupUserEventSubSubscriptions` for every connected account, and already-enabled subscriptions short-circuit), which is also not in the diff.
- `StreamSessionService::EVENT_CONTROL_MAP` and `PER_STREAM_CONTROL_KEYS` gain nothing: the chat notice never reaches `handleEvent()` (C6), so `subs_this_stream` and the rest are fed by `channel.subscribe` exactly as before.
- `EventTemplateMapping::EVENT_TYPES` is untouched (C10), so the alert trigger picker offers the same list.
- `EventMeter::record()` still runs for every stored inbound event, this one included; the meter is observe-only and is not in the diff.
- `TwitchApiService::enrichEventWithUserAvatars()` still runs before the store for every event type, this one included; it is not in the diff.
- No Plus Points count, control, tag or reset exists yet. This change stores the rows it will be computed from and nothing reads them.

### Risk
Nothing starts flowing on its own for existing accounts: after the deploy, `php artisan eventsub:backfill-goals` has to be run once on production to dispatch setup for the 19 connected accounts. New connections get the subscription from the setup loop without that step. The 6 accounts without `channel:bot` (C15) get it on their next login. A local install without `TWITCHBOT_USER_ID` reports one failed subscription on every setup run, by design (C5).
