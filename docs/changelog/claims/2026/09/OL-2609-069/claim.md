## OL-2609-069 - refactor(settings): give Twitch Alerts and the chat bot their own settings pages and flatten the integrations list

**Shipped:** 2026-09-12
**Commit:** `git log --grep=OL-2609-069`

### Surface
- `routes/settings.php` - two routes added inside the `settings.integrations.` group: `GET /twitch` and `GET /bot`
- `app/Http/Controllers/Settings/IntegrationController.php` - `showTwitch()` added, private `eventSubState()` extracted, `index()` now sends a slimmed `eventsub` prop and the bot counts
- `app/Http/Controllers/Settings/BotSettingsController.php` - `show()` added
- `app/Support/WiringCatalog.php` - four wires repointed off `settings.integrations.index`
- `resources/js/pages/settings/integrations/twitch.vue` - new file
- `resources/js/pages/settings/integrations/bot.vue` - new file
- `resources/js/pages/settings/integrations/index.vue` - the Twitch card, bot card and product grid removed; one flat list of rows built from the new util
- `resources/js/utils/integrationRows.ts` - new file, row shape and band order
- `resources/js/utils/integrationRows.test.ts` - new file
- `resources/js/pages/settings/bot/commands/Index.vue` - bot-off banner link and label
- `resources/js/pages/settings/bot/aliases/Index.vue` - bot-off banner link and label
- `resources/js/pages/settings/bot/builtins/Index.vue` - bot-off banner link and label
- `tests/Feature/IntegrationsPageOrderTest.php` - three tests added

### Claims
- **C1** [code] `routes/settings.php` registers `settings.integrations.twitch.show` at `GET settings/integrations/twitch` pointing at `IntegrationController@showTwitch`.
- **C2** [code] `routes/settings.php` registers `settings.integrations.bot.show` at `GET settings/integrations/bot` pointing at `BotSettingsController@show`, alongside the pre-existing `PATCH settings/integrations/bot`.
- **C3** [code] `IntegrationController::showTwitch()` renders the Inertia component `settings/integrations/twitch` with one prop, `eventsub`, from the private `eventSubState()`.
- **C4** [code] `IntegrationController::eventSubState()` returns `connected`, `connected_at`, `subscription_count`, `active_count` and `supported_events`, and is the only place that assembles them.
- **C5** [code] `IntegrationController::index()` sends `eventsub` as exactly three keys - `connected`, `active_count`, `supported_count` - and no longer sends `connected_at`, `subscription_count` or `supported_events`.
- **C6** [code] `IntegrationController::index()` sends `bot.command_count` and `bot.alias_count`, each counting that user's rows filtered `where('enabled', true)`.
- **C7** [code] `BotSettingsController::show()` renders `settings/integrations/bot` with `bot.enabled`, `bot.command_count`, `bot.alias_count` and `bot.builtin_count`, the three counts filtered `where('enabled', true)`.
- **C8** [code] `WiringCatalog::WIRES['alerts.subscribed']['route']` is `settings.integrations.twitch.show`.
- **C9** [code] `WiringCatalog::WIRES['product.bot_on']['route']`, `['product.bot_modded']['route']` and `['bot.in_chat']['route']` are all `settings.integrations.bot.show`.
- **C10** [code] `WiringCatalog::WIRES['product.integration']['route']` is still `settings.integrations.index`.
- **C11** [code] `useProductTarget('bot-toggle')` is called in `resources/js/pages/settings/integrations/bot.vue` and nowhere in `index.vue`; the `product-target` class is bound on the element wrapping the enable/disable button.
- **C12** [code] `buildIntegrationRows()` in `utils/integrationRows.ts` returns the Twitch row first, the bot row second, then services with a non-null `product` that are connected, then every remaining service in the order given.
- **C13** [code] `index.vue` renders one `v-for` over the filtered rows and contains no `Dialog`, no `HeadingSmall` other than the page heading, and no second list.
- **C14** [code] `twitch.vue` owns the `/eventsub/connect` fetch, the `/twitch/test-cheer` fetch, the 60-second test-cheer cooldown and both `.eventsub.setup-*` Echo listeners; none of those appear in `index.vue`.
- **C15** [code] `bot.vue` contains the string `/mod overlabels`; `index.vue` does not.
- **C16** [code] The bot-off banner in `settings/bot/commands/Index.vue`, `settings/bot/aliases/Index.vue` and `settings/bot/builtins/Index.vue` links to `/settings/integrations/bot` and reads "Switch the bot on".
- **C17** [test] `integrationRows.test.ts` asserts the band order, including that an uninstalled product is not pinned above the main band.
- **C18** [test] `integrationRows.test.ts` asserts the Twitch row reports `Not receiving Twitch events` with `statusAlert` and `stalled` true when `connected` is true and `active_count` is 0.
- **C19** [test] `integrationRows.test.ts` asserts `matchesIntegration()` finds a service by `url_slug`, and finds the Twitch and bot rows by name.
- **C20** [test] `IntegrationsPageOrderTest` asserts `GET /settings/integrations/twitch` is 200 and renders `settings/integrations/twitch` with `eventsub.supported_events`.
- **C21** [test] `IntegrationsPageOrderTest` asserts `GET /settings/integrations/bot` is 200 and renders `settings/integrations/bot` with all three counts.
- **C22** [unverified] The band-order test in C17 was run against a tree with the product filter removed from `buildIntegrationRows()` and failed.

### Unchanged
- `IntegrationController::index()` still sorts `$services` not-connected-first then A-Z, and `IntegrationsPageOrderTest`'s three pre-existing tests over that `services` prop are untouched. The new pinning is applied on top of that order, client-side, and does not reorder the prop.
- `POST /eventsub/connect` and `POST /twitch/test-cheer` keep their existing definitions in `routes/web.php` and their controller methods; only the page calling them moved.
- `BotSettingsController::setEnabled()` and its `BotChannelsChanged` dispatch are not in the diff. `bot.vue` PATCHes the same `/settings/integrations/bot` URL `index.vue` did.
- `WiringCatalog::WIRES['bot.present']` and `['product.bot_hears']` still route to `settings.bot.commands.index`. Both are diagnostics about a bot that is already switched on, not about the toggle.
- `ProductSetup::STEPS` still names `bot-toggle` as the target for the three bot steps; the key did not change, only the page that matches it.
- No test, factory or model was changed. `BotCommand`, `BotAlias` and `BotBuiltin` are read through existing columns.

### Risk
`/settings/integrations` no longer contains the EventSub connect button, the test cheer button or the bot toggle. Anything bookmarked or linked to that URL expecting those controls now needs one more click. Every in-repo link that pointed there for those controls was repointed (C8, C9, C16).
