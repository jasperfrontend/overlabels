## Audit of OL-2609-040 - feat(products): the product checklist says whether the bot is a moderator, from the bot's own side of Twitch

**Audited:** 2026-09-25
**Commit:** 5929b84e2656e4e546ceabe65c14b6f79bb07565
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/BotModeratedChannels.php:69-111 @5929b84` - `BotToken::where('account', 'overlabels')`, `Http::withToken($token->access_token)` :86, `Client-Id` = `services.twitchbot.client_id` :87, `get('https://api.twitch.tv/helix/moderation/channels', ['user_id' => $botId, ...])` :89-90, collects `broadcaster_id` :104, loops while `pagination.cursor` is a non-empty string :107-108. File unchanged @HEAD |
| C2 | CONFIRMED | `BotModeratedChannels.php:74` returns null on no token / empty `user_id` / empty `client_id`, :78 on `scopes` lacking `SCOPE`, both before the `Http` call at :86; :95-100 returns null on `! successful()` @5929b84. Unchanged @HEAD |
| C3 | CONFIRMED | `BotModeratedChannels.php:27` `TTL_SECONDS = 300`, :31 `CACHE_KEY = 'bot:moderated_channels'`, :48 `Cache::put(..., $ids ?? 'unknown', now()->addSeconds(self::TTL_SECONDS))`, :44 maps `'unknown'` back to null, :62 `forget()` is `Cache::forget(self::CACHE_KEY)` @5929b84. Unchanged @HEAD |
| C4 | CONFIRMED | `app/Support/WiringFacts.php:384-391 @5929b84` - `$botOn !== SATISFIED` -> NOT_APPLICABLE, else `moderates((string) $user->twitch_id)` true/false/null -> SATISFIED/MISSING/NOT_APPLICABLE; same at :385-392 @HEAD |
| C5 | CONFIRMED | `TwitchScopeService::REQUIRED_SCOPES` (`app/Services/TwitchScopeService.php:23 @5929b84`) does not contain `user:read:moderated_channels` and the file is not in the diff; the only token `BotModeratedChannels` reads is `BotToken` `account = 'overlabels'` (:70 @5929b84) |
| C6 | CONTRADICTED | `tests/Feature/BotModeratedChannelsTest.php` @5929b84, run @HEAD (6 passed, 16 assertions). C1 (two pages, Bearer, Client-Id, `user_id`, 2 requests), C2 (missing scope with `assertNothingSent`; 401 -> null), C4 in three states, and the switched-off bot sending nothing are all asserted. Of C3, only the cached LIST is asserted (test 2: two `moderates()` calls, `assertSentCount(1)`) and `forget()` is exercised (test 5). The cached null/`'unknown'` answer is NOT asserted: test 3's second call would send nothing without the cache too, because `fetch()` returns at the scope check (:78) before any request. `TTL_SECONDS` = 300 is not asserted anywhere |
| C7 | UNVERIFIABLE | tagged [unverified]; third-party API behaviour |

### Surface
Complete.

### Findings
- **F1** [test] claim narrower than stated - C6 says `BotModeratedChannelsTest` asserts C3, but no test in it can fail if the null answer stops being cached as `'unknown'` (`BotModeratedChannels.php:48 @5929b84`), since test 3 ("answers unknown, and caches that, when the token lacks the scope") reaches no HTTP call on either path; a remedy should either add a test where the uncached path would send a request (token with the scope, a 401 fake, two calls, `assertSentCount(1)`) or record that only list caching and `forget()` are pinned.

### Notes
- `WiringCatalog::WIRES['product.bot_modded']['route']` was `settings.integrations.index` @5929b84 and is `settings.integrations.bot.show` @HEAD, disclosed by OL-2609-069 C9; the Unchanged line saying the CTA points at the `/settings/integrations` instruction is superseded by it.
- `tests/Feature/BotModeratedChannelsTest.php` changed after ship only in the recipe slug literal (`chat_checkin` -> `chat-checkin`), disclosed by OL-2609-114. Tests were run @HEAD, not @5929b84.
- The Unchanged line on `product.bot_hears`: the `products` circuit `wires` array line (`WiringCatalog.php:203 @5929b84`) that names it was edited to insert `product.bot_modded`; the `bot_hears` entry and its computation are not in the diff and it remains second in the list.
- `ProductInstallTest` (18 passed @HEAD) was also run for the 7 -> 8 wire count.
