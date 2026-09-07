## OL-2609-026 - feat(twitch): the living title - a tag template kept true on Twitch, plus a category picker

**Shipped:** 2026-09-07
**Commit:** `git log --grep=OL-2609-026`

### Surface
- `app/Services/LivingTitleService.php` - new: save gate, render, debounce, sync, pause, resume, category write
- `app/Jobs/SyncLivingTitle.php` - new: the debounced job, clears the window key then calls `sync()`
- `app/Http/Controllers/Settings/LivingTitleController.php` - new: show, update, preview, resume, categories, setCategory
- `app/Services/TwitchApiService.php` - `updateChannel()` (PATCH helix/channels) and `searchCategories()` added
- `app/Services/TwitchScopeService.php` - `channel:manage:broadcast` added to `REQUIRED_SCOPES`
- `app/Models/User.php` - `living_title` block in `PREFERENCE_DEFAULTS`; `livingTitle()` accessor
- `app/Models/OverlayControl.php` - `booted()` with a `saved` hook calling `LivingTitleService::controlChanged()`
- `app/Http/Controllers/TwitchEventSubController.php` - after the cache refresh: `channel.update` goes to `handleChannelUpdate()`, every other event to `schedule()`
- `routes/settings.php` - six routes under `settings/title`
- `resources/js/pages/settings/Title.vue` - new settings page
- `resources/js/layouts/settings/Layout.vue` - "Stream title" nav item
- `resources/js/components/ScopeUpdateBanner.vue` - label for the new scope
- `tests/Feature/LivingTitleTest.php` - new, 32 tests
- `CLAUDE.md` - new "Living Twitch Title" section recording the rules and hooks above

### Claims
- **C1** [code] `TwitchScopeService::REQUIRED_SCOPES` contains `channel:manage:broadcast`, and `LivingTitleService::SCOPE` is that string.
- **C2** [code] `TwitchApiService::updateChannel()` sends `PATCH {baseUrl}/channels?broadcaster_id={id}` with the given fields as the JSON body, returns true on a 2xx, throws `TwitchTokenInvalidException` on 401, and returns false on any other status.
- **C3** [code] `TwitchApiService` has no other method that issues a non-GET request.
- **C4** [code] `LivingTitleService::problem()` returns a non-null string for a template containing `channel_title` as a tag key or as a condition key, for any `rand:` key, for any `c:list:*:random` key, and for any structural problem `Conditionals::structuralProblem()` reports; it returns null otherwise.
- **C5** [code] `LivingTitleService::render()` resolves the template through `BotCommandResolver::resolve()` with an empty bot context and `dryRun` false, then applies `fit()`.
- **C6** [code] `LivingTitleService::fit()` collapses runs of whitespace to one space, trims, and cuts at `MAX_LENGTH` = 140.
- **C7** [code] `LivingTitleService::sync()` returns without an HTTP call when `enabled` is false, when `paused` is true, when the account lacks `SCOPE`, when the render is empty, or when the render equals `last_written`.
- **C8** [code] `sync()` writes `last_written` to preferences BEFORE calling `updateChannel()`, and restores the previous value when the call returns false or throws.
- **C9** [code] `LivingTitleService::schedule()` with the default delay dispatches `SyncLivingTitle` only when `Cache::add(pendingKey, 1, 30)` succeeds; `SyncLivingTitle::handle()` forgets that key before rendering.
- **C10** [code] `LivingTitleService::handleChannelUpdate()` sets `paused` true and stores the incoming title as `paused_title` only when `enabled` is true, `paused` is false, `last_written` is not null, and `fit(event.title)` differs from `fit(last_written)`.
- **C11** [code] `LivingTitleService::setCategory()` sends `['game_id' => ...]` and nothing else, and stores nothing in preferences.
- **C12** [code] `TwitchEventSubController::handleTwitchEvent()` calls `handleChannelUpdate()` for `channel.update` and `schedule()` for every other event that reaches the cache-refresh line; store-only events return before it.
- **C13** [code] `OverlayControl::booted()` registers a `saved` listener that calls `LivingTitleService::controlChanged()`.
- **C14** [code] `LivingTitleController::update()` clears `paused`, `paused_title` and `last_error` on every save, clears `last_written` when saving disabled, and calls `schedule($user, 0)` when saving enabled.
- **C15** [code] `User::livingTitle()` is not in `User::$appends`.
- **C16** [test] `LivingTitleTest` "sync renders the template against live values and writes it to Twitch" asserts the PATCH body is `['title' => 'Road to 2K | 1234 followers | playing Just Chatting']` for the template `Road to 2K | [[[followers_total]]] followers | playing [[[channel_game]]]`.
- **C17** [test] `LivingTitleTest` "a stored event queues one render, and a burst of events still queues one" posts three `channel.follow` webhooks and asserts `SyncLivingTitle` was pushed once.
- **C18** [test] `LivingTitleTest` "a channel.update carrying a foreign title pauses the feature and dispatches no render" and "a channel.update echoing our own title does not pause" pin C10 in both directions.
- **C19** [test] `LivingTitleTest` "setting a category writes game_id once and never the title" asserts the PATCH body is exactly `['game_id' => '27471']` and `last_written` is unchanged.
- **C20** [test] `EventSubExpansionTest` "every scope the platform requires has a label in the reconnect banner" passes with the new scope, because `ScopeUpdateBanner.vue` maps it to "Stream title".
- **C21** [unverified] Twitch answers `PATCH helix/channels` with 204, rejects a title over 140 characters with 400, requires `channel:manage:broadcast` on the user token, and fires `channel.update` to every subscriber including the caller.
- **C22** [unverified] `GET helix/search/categories?query=` is a substring match on the category name, which is what Twitch's own category picker uses.

### Unchanged
- `BotCommandResolver` renders the title and is not in the diff: a title is the same shape as a chat reply (one line, tags, if/else, pipes, no loops) and reads the same data.
- `Conditionals::describeProblem()` remains the one voice for structural save-gate sentences; `problem()` calls it and adds two title-specific sentences of its own rather than editing it.
- `OverlayControl::writeValue()` and `resetValue()` are the ten write sites' entry points and are not in the diff; the `saved` hook sits beneath both.
- `TwitchApiService::makeApiRequest()` is GET-only and is not in the diff; `updateChannel()` has its own `Http::patch()` call rather than generalising it.
- `StreamStateMachineService` is not in the diff: the go-live control reset writes controls, so the `saved` hook already covers the transition.
- `TwitchEventSubController::refreshCachesForEvent()` already clears the channel cache on `channel.update` and is not in the diff; the sync clears it again after its own write so the settings page shows the new title at once.

### Risk
Every existing account needs one re-authorization before the title can be written: the scope is new, the banner asks for it on the next page load, and until then `sync()` records "Reauthorize Twitch once" as `last_error` and sends nothing. The `saved` hook on `OverlayControl` runs on every control write on the platform; for an account with the feature off it costs one user lookup and one preference read.
