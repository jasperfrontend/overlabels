## OL-2609-040 - feat(products): the product checklist says whether the bot is a moderator, from the bot's own side of Twitch

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-040`

### Surface
- `app/Services/BotModeratedChannels.php` - new: `broadcasterIds()`, `moderates()`, `forget()`, one paginated Helix call cached for 300 s
- `app/Http/Controllers/Admin/AdminTwitchBotController.php` - `user:read:moderated_channels` added to `SCOPES`
- `app/Support/WiringCatalog.php` - `product.bot_modded` wire, third in the `products` circuit
- `app/Support/WiringFacts.php` - `productSubject()` computes `product.bot_modded`
- `resources/js/pages/admin/TwitchBot.vue` - a sentence when the stored token lacks the new scope
- `tests/Feature/BotModeratedChannelsTest.php` - new file, 6 tests
- `tests/Feature/ProductInstallTest.php` - the installed page carries 8 wires, was 7

### Claims
- **C1** [code] `BotModeratedChannels::fetch()` calls `GET https://api.twitch.tv/helix/moderation/channels` with `user_id` = `services.twitchbot.user_id`, the bearer from the `overlabels` `bot_tokens` row and `Client-Id` = `services.twitchbot.client_id`, following `pagination.cursor` until it is absent, and returns the `broadcaster_id` values.
- **C2** [code] `fetch()` returns null, without an HTTP call, when there is no `overlabels` bot token, when `services.twitchbot.user_id` or `client_id` is empty, or when the token's `scopes` lacks `user:read:moderated_channels`; it returns null after a non-2xx response.
- **C3** [code] `broadcasterIds()` caches the list, and caches the null answer as the string `unknown`, under `bot:moderated_channels` for `TTL_SECONDS` (300); `forget()` clears it.
- **C4** [code] `WiringFacts::productSubject()` sets `product.bot_modded` to NOT_APPLICABLE when `product.bot_on` is not SATISFIED or `moderates()` returns null, SATISFIED when it returns true, MISSING when it returns false, with `moderates()` given `users.twitch_id` as a string.
- **C5** [code] No scope is added to `TwitchScopeService::REQUIRED_SCOPES`; no streamer token is read by the new service.
- **C6** [test] `BotModeratedChannelsTest` asserts C1 across two pages with the expected headers and `user_id`, C2 for a missing scope (no request sent) and for a 401, C3 by a second call sending nothing, C4 in all three states on an installed product, and that a switched-off bot sends no request.
- **C7** [unverified] Twitch's Get Moderated Channels endpoint requires `user:read:moderated_channels` on the token of the user named in `user_id`.

### Unchanged
- `BotPresence` and the `product.bot_hears` wire are not in the diff; presence (the bot can read the chat) and mod status (Twitch delivers its replies) stay two separate questions with two separate sources.
- `CheckinIntegrationController`, `/settings/integrations` and the `/mod overlabels` instruction there are not in the diff; that instruction is what the new wire's CTA points at.

### Risk
The stored bot token on prod predates the scope, so every product page reads the new line as "not checked" until the @overlabels account is authenticated again on the admin Twitch Bot page, once. Nothing else about the bot changes; the old token keeps working for chat.
