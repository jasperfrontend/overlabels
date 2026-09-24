## Audit of OL-2609-026 - feat(twitch): the living title - a tag template kept true on Twitch, plus a category picker

**Audited:** 2026-09-24
**Commit:** f58bff84e20ca5b2a9141a13398a0abb4656c251
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/TwitchScopeService.php:50 @f58bff84` - `'channel:manage:broadcast'` in `REQUIRED_SCOPES`; `app/Services/LivingTitleService.php:65 @f58bff84` - `SCOPE = 'channel:manage:broadcast'`; same @HEAD |
| C2 | CONFIRMED | `app/Services/TwitchApiService.php:205-233 @f58bff84` - `Http::...->patch("$this->baseUrl/channels?broadcaster_id=$broadcasterId", $fields)` (line 211), 401 throws `TwitchTokenInvalidException`, `successful()` returns true, otherwise logs and returns false (a `ConnectionException` also returns false); unchanged @HEAD |
| C3 | CONFIRMED | `app/Services/TwitchApiService.php @f58bff84` has two `Http::` calls: line 72 `->get(...)` in `makeApiRequest()` and line 211 `->patch(...)` in `updateChannel()`; same two @HEAD (OL-2609-046 added only `getCachedFollowersTotal()`, a cached read) |
| C4 | CONFIRMED | `app/Services/LivingTitleService.php:85-106 @f58bff84` - returns `Conditionals::describeProblem()` of any `structuralProblem()` (its `match` always yields a string, `app/Support/Conditionals.php:177-187 @f58bff84`), then checks `BotTags::keys()` + `Conditionals::keys()` for `channel_title`, a `rand:` prefix, or `c:list:` + `:random`; returns null otherwise; unchanged @HEAD |
| C5 | CONFIRMED | `app/Services/LivingTitleService.php:134,137 @f58bff84` - `$this->resolver->resolve($user, $template)`, whose signature `resolve(User, string, array $botContext = [], bool $dryRun = false)` (`app/Services/Bot/BotCommandResolver.php:97 @f58bff84`) defaults to an empty context and `dryRun` false, then `self::fit($oneLine)`; same @HEAD (line 138) |
| C6 | CONFIRMED | `app/Services/LivingTitleService.php:150,155 @f58bff84` - `mb_substr(self::oneLine(...), 0, self::MAX_LENGTH)`, `oneLine()` = `trim(preg_replace('/\s+/u', ' ', ...))`; `MAX_LENGTH = 140` at line 68; unchanged @HEAD |
| C7 | CONFIRMED | `app/Services/LivingTitleService.php:213,217,233 @f58bff84` - returns before `updateChannel()` on `!enabled \|\| paused`, on missing `SCOPE`, and on `$title === '' \|\| $title === last_written` (also on a failed `ensureValidToken()`, line 223, which the claim does not list); unchanged @HEAD apart from `remember()` renamed `apply()` (OL-2609-028) |
| C8 | CONFIRMED | `app/Services/LivingTitleService.php:237-256 @f58bff84` - `remember(['last_written' => $title])` precedes `updateChannel()` at 241; both `catch` blocks (242, 244) set `$written = false`, and `! $written` restores `'last_written' => $previous` (251); same order @HEAD via `apply()` (OL-2609-028) |
| C9 | CONFIRMED | `app/Services/LivingTitleService.php:168-185 @f58bff84` - default delay `DEBOUNCE_SECONDS` (30), dispatch only after `Cache::add(self::pendingKey($user->id), 1, $delaySeconds)` succeeds (line 180); `app/Jobs/SyncLivingTitle.php:37 @f58bff84` - `Cache::forget(pendingKey)` before `sync()` at 42; unchanged @HEAD |
| C10 | CONFIRMED | `app/Services/LivingTitleService.php:276,282,286 @f58bff84` - returns unless enabled, not paused, `last_written !== null`, and `fit(event.title)` non-empty and different from `fit(last_written)`; then stores `paused` true and `paused_title`; logic unchanged @HEAD (`apply()` per OL-2609-028) |
| C11 | CONFIRMED | `app/Services/LivingTitleService.php:304-323 @f58bff84` - `updateChannel(..., ['game_id' => $gameId])` at 311; no `remember()`/`setPreference()` call in the method; unchanged @HEAD |
| C12 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:582-597 @f58bff84` - `STORE_ONLY_EVENTS` returns at 583, `refreshCachesForEvent()` at 587, then `handleChannelUpdate()` for `channel.update` else `schedule()` when `$user`; same block @HEAD lines 583-597 |
| C13 | CONFIRMED | `app/Models/OverlayControl.php:93-97 @f58bff84` - `booted()` registers `static::saved(... controlChanged($control))`; same lines @HEAD |
| C14 | CONFIRMED | `app/Http/Controllers/Settings/LivingTitleController.php:70-80 @f58bff84` - clears `paused`, `paused_title`, `last_error`; clears `last_written` only `if (! $enabled)`; `schedule($user, 0)` when enabled. @HEAD `update()` clears `last_written` on every save (OL-2609-028 C2, which cites this claim) |
| C15 | CONFIRMED | `app/Models/User.php:119 @f58bff84` - `$appends = ['locale', 'foreach_caps']`; same @HEAD line 120 |
| C16 | CONFIRMED | `tests/Feature/LivingTitleTest.php:204 @f58bff84` - `expect($patches)->toBe([['title' => 'Road to 2K \| 1234 followers \| playing Just Chatting']])` for that template; test unchanged @HEAD; `php artisan test --filter=LivingTitleTest` passed (35 passed, 129 assertions) |
| C17 | CONFIRMED | `tests/Feature/LivingTitleTest.php:337 @f58bff84` - three `channel.follow` posts, `Queue::assertPushed(SyncLivingTitle::class, 1)`; passed in the same run |
| C18 | CONFIRMED | `tests/Feature/LivingTitleTest.php:283 @f58bff84` asserts `paused` true, `paused_title` equal to the incoming title and `SyncLivingTitle` not pushed; `:298` asserts a whitespace-variant echo of `last_written` leaves `paused` false; both passed |
| C19 | CONFIRMED | `tests/Feature/LivingTitleTest.php:472 @f58bff84` - `expect($patches)->toBe([['game_id' => '27471']])` and `last_written` still `'Before'`; passed |
| C20 | CONFIRMED | `tests/Feature/EventSubExpansionTest.php:243 @HEAD` asserts every `REQUIRED_SCOPES` entry appears as a `'scope':` key in `SCOPE_LABELS`; it does not assert the label text, which is `'Stream title'` at `resources/js/components/ScopeUpdateBanner.vue:20 @f58bff84` and @HEAD; `php artisan test --filter="every scope the platform requires has a label"` passed (1 passed) |
| C21 | UNVERIFIABLE | tagged [unverified]; Twitch API behaviour |
| C22 | UNVERIFIABLE | tagged [unverified]; Twitch API behaviour |

### Surface
Complete.

### Findings
None.

### Notes
- Tests were run at HEAD. The five named `LivingTitleTest` tests have the same bodies at @f58bff84 and @HEAD; OL-2609-028 added three tests and narrowed two `assertNothingPushed` calls elsewhere in the file. "32 tests" in Surface is 27 `test()` calls expanded by datasets.
- Unchanged lines checked against the diff: `BotCommandResolver`, `Conditionals::describeProblem()`, `OverlayControl::writeValue()`/`resetValue()` (lines 122/153 @f58bff84), `makeApiRequest()`, `StreamStateMachineService` and `refreshCachesForEvent()` (clears channel info on `channel.update`, line 487-489 @f58bff84) are all absent from the diff.
- The `LivingTitleService` docblock @f58bff84 gives `[[[channel_game_name]]]` as its example, while the CLAUDE.md section added in the same commit says that tag does not exist. OL-2609-028 corrected the docblock to `channel_game`.
- `routes/settings.php` has since been touched by OL-2609-029 (rate limits on these settings endpoints), which is a separate claim.
