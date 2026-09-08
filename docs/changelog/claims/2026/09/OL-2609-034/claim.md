## OL-2609-034 - feat(gamejam): remove Chat Castle entirely

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-034`

### Surface
Deleted, whole files. Directories carry their tracked-file count; `git show --stat` lists each file.
- `app/Models/Game.php`, `GameJoiner.php`, `GameDoor.php`, `GameHiddenTile.php`, `GameHidingSpot.php`, `GameBlocker.php`, `GameZombie.php` - the seven game models
- `app/Services/Gamejam/` (4) - `ActionApplier`, `GameLog`, `RoomSeeder`, `ZombieTurnResolver`
- `app/Jobs/ResolveGameRound.php`, `app/Events/GameStateChanged.php`, `app/Events/GamejamDebugToggled.php`
- `app/Http/Controllers/GamejamAdminController.php`, `app/Http/Controllers/RoomBuilderController.php`, `app/Http/Controllers/Api/Internal/BotGamejamActionController.php`
- `app/Console/Commands/GamejamStart.php`, `GamejamEnd.php`, `GamejamAdvance.php`, `GamejamActive.php`, `GamejamDebug.php`
- `resources/js/pages/gamejam/` (5), `resources/js/pages/help/gamejam/Index.vue`, `resources/js/components/gamejam/` (3), `resources/js/lib/game-commands.ts`, `resources/js/rooms/` (5 room maps + gitignored `__history/`), `resources/js/layouts/HelpLayout.vue`
- `tests/Feature/ApplyActionTest.php`, `BotGamejamActionTest.php`, `GameLogTest.php`, `ResolveGameRoundTest.php`, `RoomSeederTest.php`
- `public/rooms/` (1105), `public/tile-icons/` (171), `public/Platformer 2D Game Tileset/` (44) - game art; `public/css classes.txt` was untracked and removed from disk
- `database/migrations/2026_04_18_120000_create_games_table.php`, `2026_04_18_120001_create_game_joiners_table.php`, `2026_04_18_130000_add_resolver_fields_to_games_table.php`, `2026_04_18_150000_add_physical_state_to_games_table.php`, `2026_04_18_150001_create_game_world_tables.php`, `2026_04_18_160000_refine_game_world_tables.php`, `2026_04_19_120000_create_game_zombies_table.php`, `2026_04_20_194231_add_lunged_this_turn_to_game_zombies_table.php`, `2026_04_25_120000_add_log_and_recap_to_games_table.php` - the nine schema migrations
- `database/migrations/2026_04_18_200000_seed_castlehelp_bot_command.php`, `2026_04_18_181000_backfill_stay_bot_command.php` - the two seeders that only wrote game verbs

Added:
- `database/migrations/2026_09_08_220000_drop_chat_castle.php` - drops the seven tables, deletes the six verbs from `bot_builtins`, forgets the per-user debug cache key

Edited:
- `routes/web.php` - five imports, the `gamejam.*` route block, `/help/gamejam`, the `dev.room-builder.*` group
- `routes/api.php` - the import, the `bot-gamejam-action` throttle group and its comment
- `app/Providers/AppServiceProvider.php` - the `bot-gamejam-action` rate limiter and a comment count
- `app/Models/BotBuiltin.php` - six entries removed from `DEFAULTS`
- `app/Support/HelpCorpus.php` - `SECTION_EXTRAS` loses `Bot & chat`; docblock reworded
- `app/Http/Controllers/SitemapController.php` - `/help/gamejam` entry removed; comment reworded
- `database/migrations/2026_04_18_120002_seed_gamejam_bot_commands.php` - four game verbs struck from its frozen list; docblock says why
- `app/Services/Bot/BotPushAnnouncer.php`, `config/metering.php`, `app/Http/Controllers/OverlayBroadcastingAuthController.php`, `app/Models/BotChatOutbox.php`, `app/Observers/BotChatOutboxObserver.php`, `database/migrations/2026_04_18_180000_create_bot_chat_outbox_table.php` - comment-only
- `resources/help/pages/index.md`, `resources/help/pages/bot/commands.md`, `resources/help/pages/markdown-endpoints.md`, `resources/help/reference/Wheel of Fortune.md`, `public/llms.txt` - the Chat Castle link, paragraph, bullet and aside removed; counts of Inertia help pages corrected
- `tests/Feature/HelpTaxonomyTest.php` - the `/help/gamejam` expectation removed
- `tests/Unit/BroadcastMeterTest.php` - the gamejam-channel test removed
- `tests/Feature/ListLiveChannelTest.php`, `tests/Feature/OverlayBroadcastingAuthTest.php` - the rejected channel name is `private-admin.*` instead of `private-gamejam.*`
- `tests/Feature/BotInternalApiTest.php` - a fixture message no longer quotes the game
- `tests/Feature/BotOutboxPushAndPruneTest.php` - comment-only
- `docs/commit-guide.md` - `gamejam` removed from the commit scopes
- `.gitignore` - two `docs/private` entries for deleted game documents removed
- `CLAUDE.md` - the `/help/gamejam` sentence replaced; a "Chat Castle is gone" note added

### Claims
- **C1** [code] No file under `app/`, `routes/`, `resources/`, `config/`, `database/`, `tests/` or `public/` (excluding `public/build`) contains `gamejam`, `Chat Castle`, `castlehelp` or a `Game*` model name, except the docblock of `2026_04_18_120002_seed_gamejam_bot_commands.php` and the new teardown migration, both of which describe the removal.
- **C2** [code] `php artisan route:list` returns no route whose URI or name contains `gamejam` or `room-builder`.
- **C3** [code] `BotBuiltin::DEFAULTS` has 15 entries and none of `join`, `p`, `h`, `a`, `s`, `castlehelp`.
- **C4** [code] `2026_09_08_220000_drop_chat_castle.php` calls `Schema::dropIfExists` on `game_zombies`, `game_blockers`, `game_hiding_spots`, `game_doors`, `game_hidden_tiles`, `game_joiners`, `games` in that order, deletes `bot_builtins` rows whose `command` is one of the six verbs, and forgets `gamejam.debug.{twitch_id}` for every user. Its `down()` is empty.
- **C5** [test] Run against the local database, which had the seven tables and the six verbs: afterwards `Schema::hasTable` is false for all seven, the verb count is 0, and the one opted-in user holds 15 builtin rows.
- **C6** [code] `HelpCorpus::SECTION_EXTRAS` has one key, `Live data`, with the single Integration Presets link; `SitemapController` lists `/help/integration-presets` as the only Vue help page.
- **C7** [code] `resources/js/layouts/HelpLayout.vue` is deleted; its only importer was the deleted `help/gamejam/Index.vue`. `IntegrationPresets.vue` imports `AppLayout` and is untouched.
- **C8** [code] `public/help-index.json` rebuilt by `help:build-index` contains no occurrence of `gamejam` or `Chat Castle`.
- **C9** [code] The four Chat Castle verbs are struck from the frozen list in `2026_04_18_120002_seed_gamejam_bot_commands.php`. The file keeps its name so its row in the `migrations` table still matches and it does not re-run on production.
- **C10** [unverified] The bot repository still carries handlers for `join`, `p`, `h`, `a`, `s`, `castlehelp` and a call to `POST /api/internal/bot/gamejam/action/{login}`. After this deploy the command map no longer lists those verbs, so the bot's dispatcher drops them before any call is made; the dead code is a separate change in that repository.

### Unchanged
- `three` and `@types/three` stay: they serve `resources/js/globe/checkinGlobe.ts`, not the game.
- `app/Models/BotRateLimitLog.php` and the `bot-internal` limiter are untouched; only the game's own bucket is gone.
- Changelog entries from April to August that describe the game are history and stand as written.
- `docs/MILESTONES.md` and `docs/design/lists-data-bus.md` each mention the game once in passing, as history.

### Risk
Production tables `games` through `game_zombies` and their rows are dropped on deploy and cannot be restored by rollback. Any opted-in streamer who typed `!join` after the deploy gets silence, which is the bot's designed response to a verb missing from the map.
