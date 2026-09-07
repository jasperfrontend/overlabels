## OL-2609-024 - fix(twitch): the bot account can grant user:read:chat and user:bot to this app, which channel.chat.notification needs

**Shipped:** 2026-09-07
**Commit:** `git log --grep=OL-2609-024`

### Surface
- `app/Services/TwitchScopeService.php` - `BOT_ACCOUNT_SCOPES` constant added
- `routes/web.php` - `/auth/redirect/twitch` merges `BOT_ACCOUNT_SCOPES` into the requested scopes and sets `force_verify` when the query string has `scopes=bot`
- `tests/Feature/TwitchLoginScopesTest.php` - new file
- `CLAUDE.md` - the "Chat notices and Plus Points" section gains the per-client-id finding and the authorization step

### Claims
- **C1** [code] `TwitchScopeService::BOT_ACCOUNT_SCOPES` is `['user:read:chat', 'user:bot']` and shares no entry with `REQUIRED_SCOPES`.
- **C2** [code] `REQUIRED_SCOPES` is not in the diff; a login without `scopes=bot` requests exactly what it requested before.
- **C3** [test] `TwitchLoginScopesTest` asserts a plain `GET /auth/redirect/twitch` redirects to a Twitch URL whose `scope` contains every `REQUIRED_SCOPES` entry, none of `BOT_ACCOUNT_SCOPES`, and no `force_verify=true`.
- **C4** [test] The same file asserts `GET /auth/redirect/twitch?scopes=bot` redirects to a URL whose `scope` contains every entry of both lists and carries `force_verify=true`.
- **C5** [code] The callback at `/auth/callback/twitch` is not in the diff; it stores whatever scope list Twitch approves in `users.twitch_scopes`, so a bot-account login through `?scopes=bot` stores the superset and `getMissingScopes()` on that account is empty.
- **C6** [unverified] After OL-2609-023 deployed and `eventsub:backfill-goals` ran on production on 2026-09-07, Twitch answered 403 "subscription missing proper authorization" for `channel.chat.notification` on exactly the 13 connected accounts holding `channel:bot` (user ids 2, 39, 52, 57, 61, 62, 63, 64, 65, 66, 67, 69, 71), and the other 6 were skipped by the scope gate.
- **C7** [unverified] On the production host, `TWITCH_CLIENT_ID` and `TWITCHBOT_CLIENT_ID` in the web container are different values, and the bot container's `TWITCH_CLIENT_ID` equals `TWITCHBOT_CLIENT_ID`. The bot account's `user:bot` / `user:read:chat` grant in `bot_tokens` was made to that second app, not to the one whose app access token creates EventSub subscriptions.
- **C8** [unverified] Twitch evaluates the scopes an app-token EventSub condition requires against the client id of that token; a grant to a different client id does not satisfy it.

### Unchanged
- `UserEventSubManager` is not in the diff: the subscription payload, its `channel:bot` gate and the `TWITCHBOT_USER_ID` condition from OL-2609-023 are as shipped; only the grant on Twitch's side was missing.
- `AdminTwitchBotController::SCOPES` and the `bot_tokens` flow are not in the diff. They authorize the bot's own app, which the bot uses to send chat, and stay as they are.
- `TwitchEventSubService`, `SetupUserEventSubSubscriptions` and `eventsub:backfill-goals` are not in the diff; the same command is re-run after the grant.

### Risk
Data does not flow until two manual steps happen, in order: the @overlabels account logs in to Overlabels through `/auth/redirect/twitch?scopes=bot` and approves the two extra scopes, then `eventsub:backfill-goals` is run once more on production. The first step swaps the browser's Overlabels session to the bot account. Any other account can use `?scopes=bot`; it would grant two scopes the platform never uses for it, nothing more.
