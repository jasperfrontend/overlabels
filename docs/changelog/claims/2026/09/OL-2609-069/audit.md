## Audit of OL-2609-069 - refactor(settings): give Twitch Alerts and the chat bot their own settings pages and flatten the integrations list

**Audited:** 2026-09-25
**Commit:** e06e1fff483750dbf6b2ccd7bb1977f4b1b2c537 (only commit carrying the trailer)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `routes/settings.php:141 @e06e1ff` - `Route::get('/twitch', [IntegrationController::class, 'showTwitch'])->name('twitch.show')` inside the `settings.integrations.` group (line 139); same at `routes/settings.php:157 @HEAD` |
| C2 | CONFIRMED | `routes/settings.php:188-189 @e06e1ff` - `GET /bot` -> `BotSettingsController@show` named `bot.show`, beside `PATCH /bot` named `bot.enabled`; same at `:204-205 @HEAD` |
| C3 | CONFIRMED | `IntegrationController.php:92-96 @e06e1ff` - renders `settings/integrations/twitch` with the single prop `eventsub => $this->eventSubState(auth()->user())`; file unchanged since @e06e1ff |
| C4 | CONFIRMED | `IntegrationController.php:102-121 @e06e1ff` - returns the five keys; `git grep supported_events e06e1ff -- app routes` finds it only in that method and in `index()` reading it back (line 74) |
| C5 | CONFIRMED | `IntegrationController.php:71-75 @e06e1ff` - `eventsub` is `connected`, `active_count`, `supported_count` only |
| C6 | CONFIRMED | `IntegrationController.php:81-82 @e06e1ff` - `BotCommand`/`BotAlias` `where('user_id', ...)->where('enabled', true)->count()` |
| C7 | CONFIRMED | `BotSettingsController.php:23-35 @e06e1ff` - renders `settings/integrations/bot` with `bot.enabled` and three counts, each `where('enabled', true)`; file unchanged since @e06e1ff |
| C8 | CONFIRMED | `app/Support/WiringCatalog.php:58 @e06e1ff` - `alerts.subscribed` route `settings.integrations.twitch.show`; same @HEAD |
| C9 | CONFIRMED | `WiringCatalog.php:90, 109, 125 @e06e1ff` - `bot.in_chat`, `product.bot_on`, `product.bot_modded` all `settings.integrations.bot.show`; same @HEAD. See F2 for what `bot.in_chat` pointed at before |
| C10 | CONFIRMED | `WiringCatalog.php:133 @e06e1ff` - `product.integration` route `settings.integrations.index`; same @HEAD (`:138`) |
| C11 | CONFIRMED | `resources/js/pages/settings/integrations/bot.vue:15,84 @e06e1ff` - `useProductTarget('bot-toggle')` and `:class="{ 'product-target': botIsProductTarget }"` on the div wrapping the Enable/Disable button; absent from `index.vue @e06e1ff`. @HEAD both are gone, replaced by `data-product-target="bot-toggle"` (OL-2609-072) |
| C12 | CONFIRMED | `resources/js/utils/integrationRows.ts:121-126 @e06e1ff` - `[twitchRow, botRow, ...product !== null && connected, ...product === null \|\| !connected]`; `buildIntegrationRows()` unchanged @HEAD |
| C13 | CONFIRMED | `index.vue:76 @e06e1ff` - one `v-for="row in filteredRows"`; the only `HeadingSmall` is the page heading (line 63); no `Dialog` import or element |
| C14 | CONFIRMED | `twitch.vue:44,93,147,157,182 @e06e1ff` - `TEST_CHEER_COOLDOWN_SECONDS = 60`, `/twitch/test-cheer` fetch, both `.eventsub.setup-*` listens, `/eventsub/connect` fetch; none in `index.vue @e06e1ff` |
| C15 | CONFIRMED | `bot.vue:72,95 @e06e1ff` contain `/mod overlabels`; `index.vue @e06e1ff` does not; same @HEAD |
| C16 | CONFIRMED | `settings/bot/commands/Index.vue:89`, `aliases/Index.vue:72-73`, `builtins/Index.vue:82-83 @e06e1ff` - `Link href="/settings/integrations/bot"` reading "Switch the bot on"; unchanged @HEAD |
| C17 | CONFIRMED | `integrationRows.test.ts:22-33 @e06e1ff` - uninstalled `tower` expected after `bmac` in `['twitch','bot','checkin','bmac','tower','kofi']`. `npm test -- resources/js/utils/integrationRows.test.ts`: 13/13 passed at @e06e1ff (file run from scratchpad), 14/14 @HEAD |
| C18 | CONFIRMED | `integrationRows.test.ts:69-76 @e06e1ff` - `connected: true, active_count: 0` asserts status `Not receiving Twitch events`, `statusAlert` true, `stalled` true; passes @e06e1ff and @HEAD |
| C19 | CONTRADICTED (second half) | First half CONFIRMED: `integrationRows.test.ts:131-138 @e06e1ff` - query `overlabels-mobile` matches only via the href built from `url_slug`. Second half: `:140-145 @e06e1ff` queries `twitch` and `bot`, which equal the rows' `key`s, and `matchesIntegration()` (`integrationRows.ts:131`) also matches `key`, so the test passes with name matching removed; it does not assert a find by name. Unchanged @HEAD |
| C20 | CONFIRMED | `tests/Feature/IntegrationsPageOrderTest.php` "twitch alerts has a settings page..." @e06e1ff - `assertOk`, component `settings/integrations/twitch`, `has('eventsub.supported_events')`. `php artisan test --filter=IntegrationsPageOrderTest` @HEAD: 6 passed |
| C21 | CONFIRMED | same file, "the chat bot has a settings page..." @e06e1ff - `assertOk`, component `settings/integrations/bot`, `has` for `bot.command_count`, `bot.alias_count`, `bot.builtin_count`; passes @HEAD |
| C22 | UNVERIFIABLE | tagged [unverified] (fail-first run against a tree that no longer exists) |

### Surface
Complete. All 13 non-exempt paths in `git show --stat e06e1fff` are listed; no phantom paths. The `WiringCatalog.php` line misdescribes one of its four wires (F2).

### Findings
- **F1** declared unchanged but modified - Unchanged says "No test, factory or model was changed", but the diff adds three tests to `tests/Feature/IntegrationsPageOrderTest.php` (+36 lines, `@e06e1ff`) and creates `resources/js/utils/integrationRows.test.ts`, both listed in the claim's own Surface; a correcting claim should restate the line as "no factory or model was changed, and no pre-existing test was edited".
- **F2** inaccurate Surface / undisclosed repoint - Surface says "four wires repointed off `settings.integrations.index`", but `bot.in_chat` was `settings.bot.commands.index` before this commit (`app/Support/WiringCatalog.php:90 @e06e1ff^`); its "Add the bot" CTA moved off the commands page, which neither Surface nor Unchanged (whose `bot.present`/`product.bot_hears` line gives the reason those two stayed on the commands page) discloses. A correcting claim should record the true prior route.
- **F3** test narrower than claim - C19's "finds the Twitch and bot rows by name" is not asserted: `integrationRows.test.ts:140-145 @e06e1ff` (unchanged @HEAD) uses queries equal to the rows' keys, so key matching alone satisfies it; either add a query that hits only the name (e.g. `alerts` or `chat`) or restate C19.
- **F4** contradicts the record - C15 removes `/mod overlabels` from `index.vue`, reversing OL-2609-025 C2 ("`resources/js/pages/settings/integrations/index.vue` contains the instruction `/mod overlabels`") without citing it, and CLAUDE.md still says "`/settings/integrations` already tells streamers to run `/mod overlabels`; that instruction is the load-bearing one" - the instruction now lives at `/settings/integrations/bot`; CLAUDE.md should be updated to name that page.
- **F5** contradicts the record - the diff deletes the "Overlabels products" section and the external-only filter from `index.vue`, reversing OL-2609-063 C3, C4 and C6 ("`useCollectionFilter` on that page now takes `externalServices` as its source") without citing OL-2609-063 inline.

### Notes
- C11 drift is disclosed: OL-2609-072 (Surface line for `bot.vue`, C27) replaced `useProductTarget` with `data-product-target`.
- `twitchRow()` status/statusAlert changed after ship in OL-2609-070 (complete vs partial subscription); C18's stalled case is unaffected @HEAD. Test fixtures' product slugs changed to hyphenated in OL-2609-114.
- `integrationRows.test.ts:35-41 @e06e1ff` ("drops an uninstalled product back into the main band") uses a single service, so it cannot distinguish pinned from unpinned; C17 rests on the test at lines 22-33.
- Pest was run at HEAD only; Vitest was run at both the shipped file revision and HEAD.
