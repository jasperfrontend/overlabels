## OL-2609-062 - feat(bot): built-in commands get a switch and a tier, per channel

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-062`

### Surface
- `app/Models/BotBuiltin.php` - `OWNER_*` constants, an `owner` key (and `service` for a product's commands) on every `DEFAULTS` entry, and the `declaration` / `ownerOf` / `serviceOf` / `isEditable` / `owner` / `service` / `editable` accessors
- `app/Http/Controllers/Settings/BotBuiltinsController.php` - new file, `index()` and `update()`
- `routes/settings.php` - `settings.bot.builtins.index` and `.update`
- `resources/js/layouts/settings/Layout.vue` - "Built-in commands" nav entry
- `resources/js/pages/settings/bot/builtins/Index.vue` - new file, the page
- `resources/help/pages/bot/commands.md` - a "Switching built-ins off" section, and `settings.bot.builtins.*` added to its `context:`
- `tests/Feature/Settings/BotBuiltinsPageTest.php` - new file, eight tests
- `CLAUDE.md` - two bullets in the bot-commands section

### Claims
- **C1** [code] Every entry in `BotBuiltin::DEFAULTS` carries an `owner` of `BotBuiltin::OWNER_USER`, `OWNER_PRODUCT` or `OWNER_PLATFORM`.
- **C2** [code] `stack`, `tower` and `checkin` are the only `OWNER_PRODUCT` entries, and each carries a `service` key (`tower`, `tower`, `checkin`).
- **C3** [code] `enablecontrols` and `disablecontrols` are the only `OWNER_PLATFORM` entries.
- **C4** [code] `ol`, `followage`, `accountage` and the six control verbs are `OWNER_USER`.
- **C5** [code] Ownership is stored nowhere: `bot_builtins` gains no column and this diff contains no migration. `BotBuiltin::ownerOf()` resolves it from `DEFAULTS` by command name.
- **C6** [code] `BotBuiltin::ownerOf()` returns `OWNER_USER` for a command absent from `DEFAULTS`.
- **C7** [code] `BotBuiltinsController::update()` writes only `enabled` and `permission_level`, and validates the tier against `BotBuiltin::PERMISSION_LEVELS`.
- **C8** [code] `BotBuiltinsController::update()` aborts 404 when the row belongs to another user and 403 when `BotBuiltin::editable()` is false, the 403 check applying to both fields.
- **C9** [code] `BotBuiltinsController::index()` lists only rows that exist for the authenticated user; it never synthesises a row from `DEFAULTS`.
- **C10** [code] `settings/bot/builtins/Index.vue` renders three groups keyed on `owner` and offers a `Switch` and a tier `<select>` in the `user` group only.
- **C11** [code] That page holds no local copy of the rows: `save()` PATCHes and the switch and select bind to the props.
- **C12** [test] `BotBuiltinsPageTest` asserts the page sends one entry per `DEFAULTS` command, with `owner`, `service` and `editable` for `followage`, `stack` and `enablecontrols`.
- **C13** [test] `BotBuiltinsPageTest` asserts a `user`-owned builtin accepts `enabled=false` with a new tier and both land on the row.
- **C14** [test] `BotBuiltinsPageTest` asserts a builtin switched off stops appearing in `GET /api/internal/bot/commands` for that channel.
- **C15** [test] `BotBuiltinsPageTest` asserts a PATCH on `stack` returns 403 and leaves `enabled` true and the tier `everyone`.
- **C16** [test] `BotBuiltinsPageTest` asserts a PATCH on `enablecontrols` returns 403 and leaves the tier `broadcaster`.
- **C17** [test] `BotBuiltinsPageTest` asserts another channel's row returns 404 and is unchanged.
- **C18** [test] `BotBuiltinsPageTest` asserts a tier outside `PERMISSION_LEVELS` fails validation.
- **C19** [test] `BotBuiltinsPageTest` asserts every `DEFAULTS` entry names a known owner, that a product entry's `service` is in `ExternalServiceRegistry::services()`, and that a non-product entry declares no `service`.
- **C20** [unverified] C15 and C16 were run with the `editable()` guard removed from `update()` and both failed; the other six tests passed in that state.

### Unchanged
- `BotCommandMapController::index()` already eager-loaded builtins as `->with(['botBuiltins' => fn ($q) => $q->where('enabled', true)])` and already emitted `permission_level`. It is not in the diff: the toggle and the tier reach the bot through filtering and fields that predate this change, which is why C14 holds with no bot-side edit.
- The bot repo is untouched. Its dispatcher drops a command absent from the map and enforces `entry.permission_level` in `canRun()`; both behaviours predate this change.
- `BotBuiltin::seedDefaults()` still writes only `permission_level` and `enabled`, so the new `owner` / `service` keys are never persisted.
- The four other readers of `DEFAULTS` (`BotAliasesController::knownCommandsForUser()`, `BotCommandsController`'s `reservedCommands`, `BotAliasValidator`, `BotCommandValidator`) read the `command` key only and are not in the diff.
- Builtins still have no `cooldown_seconds` of any kind. `!stack`, `!tower` and `!checkin` take their per-viewer pacing from their integration's own setting (OL-2609-061), and every builtin takes its channel-wide window from the bot's `COMMAND_COOLDOWN_MS`.

### Risk
A streamer can now switch off a builtin their own overlays or chat routines depend on; the bot goes
silent on it with no error, which is the pre-existing behaviour for any command missing from the map.
Nothing changes for an account that never opens the page: every seeded row is `enabled` with its
`DEFAULTS` tier.
