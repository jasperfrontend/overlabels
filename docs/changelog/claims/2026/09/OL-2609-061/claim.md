## OL-2609-061 - fix(tower): the per-viewer !stack cooldown goes down to 1s and starts at 5s

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-061`

### Surface
- `app/Http/Controllers/Settings/TowerIntegrationController.php` - validation floor and both read/write defaults for `cooldown_seconds`
- `app/Http/Controllers/Api/Internal/BotTowerController.php` - the server-side cooldown backstop's floor and default, plus a comment on why the floor is not 0
- `resources/js/pages/settings/integrations/tower.vue` - the number input's `min` and the form's fallback value
- `tests/Feature/BotTowerTest.php` - one new test for a 1-second cooldown
- `tests/Feature/TowerCooldownSettingTest.php` - new file, four tests over the settings route
- `CLAUDE.md` - Chat Tower section gains a bullet on the two cooldowns

### Claims
- **C1** [code] `TowerIntegrationController::save()` validates `cooldown_seconds` as `nullable|integer|min:1|max:600`.
- **C2** [code] `TowerIntegrationController::save()` stores `5` when the request omits `cooldown_seconds`.
- **C3** [code] `TowerIntegrationController::show()` sends `5` to the page when the integration's settings hold no `cooldown_seconds`.
- **C4** [code] `BotTowerController::store()` computes the backstop window as `max(1, (int) ($settings['cooldown_seconds'] ?? 5))`, so a stored `1` is enforced as 1 second rather than 5.
- **C5** [code] The `Cache::add` call in `BotTowerController::store()` still receives that window as its TTL, and the floor of 1 keeps it above the zero TTL that would store nothing.
- **C6** [code] `tower.vue` renders the `cooldown_seconds` input with `min="1" max="600"`.
- **C7** [code] `tower.vue`'s `useForm` falls back to `5` when the `integration.cooldown_seconds` prop is absent.
- **C8** [test] `BotTowerTest` > "a one-second cooldown lets the same viewer stack again after one second" asserts the same `chatter_id` is dropped immediately and lands a second block after `travel(2)->seconds()`.
- **C9** [test] `TowerCooldownSettingTest` asserts a posted `cooldown_seconds` of `1` redirects without errors and is stored as `1`.
- **C10** [test] `TowerCooldownSettingTest` asserts a posted `0` fails validation on `cooldown_seconds` and creates no `tower` integration row.
- **C11** [test] `TowerCooldownSettingTest` asserts a save omitting `cooldown_seconds` stores `5`.
- **C12** [test] `TowerCooldownSettingTest` asserts `GET /settings/integrations/tower` sends `integration.cooldown_seconds` of `5` for a user with no tower row.
- **C13** [unverified] C8, C9, C11 and C12 were run against the pre-change controllers and failed. C10 passed there too: `min:5` also rejected `0`, so it pins scope rather than new behaviour.
- **C14** [unverified] The bot enforces a second, channel-wide window on `!stack` that this diff cannot reach: `createCooldown` in the bot repo's `src/bot.js` keys on `${channel}:${command}`, so one `!stack` per window for the whole chat, broadcaster exempt. Its default is `COMMAND_COOLDOWN_MS` = 5000.

### Unchanged
- `BotCheckinController::store()` carries the identical `max(5, ... ?? 30)` expression for `!checkin`, and `CheckinIntegrationController` the identical `min:5` and `?? 30`. Chat Tower was the product asked about; none of the checkin paths are in the diff.
- `TowerPhysics` decides how far a block leans and when the tower topples. The cooldown gates whether a `stack` call reaches `TowerService::stack()` at all and touches no physics; `TowerPhysics` and `TowerPhysicsTest` are not in the diff.
- `TowerBlock::WINDOW`, the `tower.updated` delta and the eleven `c:tower:*` controls all sit behind that same gate and are not in the diff.
- The bot's `src/` is untouched in its own repo: `COMMAND_COOLDOWN_MS` was already read from the environment, and `config.js` keeps its 5000 fallback.

### Risk
Two things do not change on their own. A channel that already saved a cooldown keeps that stored
value (30 for anyone who connected Chat Tower before today) until the streamer saves the form again
- the new default applies only where no value was stored. And the channel-wide 1s window needs the
bot repo deployed; until then C14's 5-second window is the ceiling whatever the per-viewer setting
says.
