# CHANGELOG APRIL 2026

## April 27th, 2026 - Keyboard navigation across template show + edit pages

- The keyboard-shortcut affordances were minimal before this pass: only `ctrl+s` (save) and `ctrl+p` (preview) on the edit page, nothing on the show page. With the recent tab additions (Details / Controls / Values / Screenshot / Targeting on show; Code / Meta / Tags / Controls / Values / Screenshot / Targeting on edit), navigating with the mouse was getting tedious - especially in the round-trip flow of "edit something, jump back to show, click Add to OBS".
- New shortcuts on `/templates/{id}` (show.vue): bare digits `1`-`4` switch to the corresponding tab in the reactive `mainTabs` computed, `e` jumps to the edit page (`router.visit(route('templates.edit', id))`), `a` triggers the "Add to OBS" flow (calls the same `generateOBSUrl()` the button does, including the link-warning gate).
- New shortcuts on `/templates/{id}/edit` (edit.vue): bare digits `1`-`9` switch tabs (loop covers 9 in case `mainTabs` ever grows past 7), `s` jumps back to the show page, `alt+f` blurs the currently focused element so a CodeMirror-trapped focus can be released without reaching for the mouse. `ctrl+s` (save) and `ctrl+p` (preview) unchanged.
- Naming consistency: `e` on show goes to edit, `s` on edit goes to show. Bare letters everywhere (no ctrl) so Firefox keeps `ctrl+1..9` for browser-tab switching.
- Bare-key shortcuts rely on `useKeyboardShortcuts.ts`'s existing `inInput` guard, which suppresses any modifier-less shortcut while focus is in an `<input>`, `<textarea>`, or any `contentEditable` element. CodeMirror 6 uses a contenteditable surface, so digits and letters auto-skip while typing in the editor and resume the moment focus leaves - no explicit CodeMirror integration needed.
- Inertia router fix: `router.push(...)` was the original call for the `e`-to-edit shortcut and silently no-op'd because Inertia's router exposes `visit/get/post`, not `push` (Vue Router's API). Switched to `router.visit(...)`. Same fix applied to all new navigation shortcuts.
- Keyboard-shortcuts dialog (`KeyboardShortcutsDialog.vue`) reflowed: the per-page list of shortcuts is now long enough to overflow viewport on smaller screens. Switched from a single-column max-w-md modal to a responsive grid - 1 column under md, 2 columns at md, 3 columns at lg+, with `max-w-md` -> `md:max-w-2xl` -> `lg:max-w-4xl` so the dialog grows wider rather than taller. Outer wrapper capped at `max-h-[90vh]` with a sticky header and a `flex-1 overflow-y-auto` body, so on narrow viewports the body scrolls inside the dialog while the header (and close button) stay reachable. ESC now dismisses the dialog: a `watch` on the `show` prop wires a window-level `keydown` listener with the capture-phase flag while open, so ESC fires before any in-page handler can swallow it. Listener is removed on close and on unmount.

## April 26th, 2026 - Stop storing user emails, anywhere

- Twitch's OAuth payload includes the streamer's email address. We were storing it twice: once in `users.email` (with the original starter-kit unique constraint long since dropped) and again wholesale inside `users.twitch_data` JSON. Neither copy was load-bearing - we identify users solely by `twitch_id`, login flows are Socialite-only, password reset / email verification scaffolding from the Laravel Vue Starter Kit was dead code. Holding the data only created surface area we didn't want.
- OAuth callback in `routes/web.php` no longer writes `email` or `email_verified_at` to the column, and strips `email`, `email_verified`, `verified` keys out of the `twitch_data` payload before merge - both for the create branch and the update branch, so re-logins also scrub. `user:read:email` removed from `TwitchScopeService::REQUIRED_SCOPES` and `LEGACY_SCOPES`; the OAuth grant no longer requests it. `Str` import dropped from `web.php` since `bcrypt(Str::random(32))` placeholder password is gone.
- New migration `2026_04_26_140000_drop_email_columns_from_users` runs `UPDATE users SET twitch_data = twitch_data - 'email' - 'email_verified' - 'verified'` against existing rows first (jsonb minus operator scrubs in place), then drops the `email`, `email_verified_at`, `password`, and `remember_token` columns plus the entire `password_reset_tokens` table. No data preservation - existing emails get nuked. Existing users keep working: nothing in the app reads any of these fields.
- Template tag `[[[user_email]]]` is preserved but soft-disabled. The mapping in `TemplateDataMapperService::mapTwitchDataForTemplates()` now hard-overwrites the resolved value with the literal string `disabled@for-your-security` regardless of source data, so legacy templates that reference the tag render without breaking. Sample data and the available-tags description updated to match. Streamers who genuinely want an email on their overlay can put it in a Control.
- Deleted: `App\Services\SignupNotificationService` and `App\Listeners\SendSignupNotification` (admin-notification feature that didn't actually deliver), `App\Http\Requests\Auth\LoginRequest` (validated email/password for a flow that never executed), the `POST /login` route (no email/password form ever existed - the login page is just a Twitch button), `signup_notification_email` from `config/mail.php`, `SIGNUP_NOTIFICATION_EMAIL` from `.env.example`, the `passwords` broker block from `config/auth.php`, the `password_timeout` config, and the empty Telescope email allowlist (replaced with `$user?->isAdmin()`).
- `User` model: `email`, `password`, `remember_token`, `email_verified_at` dropped from `$fillable`, `$hidden`, `$casts`, and the `@property` PHPDoc. The corresponding `whereEmail` / `whereEmailVerifiedAt` / `wherePassword` / `whereRememberToken` magic-method docblocks gone too. Inertia shared `auth.user` props in `HandleInertiaRequests` no longer expose email.
- Admin pages: `AdminUserController::index` search filter, `AdminDashboardController::index` recent-signups select, and `AdminSessionController::index` user lookup all stop selecting email; the corresponding TypeScript interfaces in `admin/users/index.vue`, `admin/users/show.vue`, `admin/sessions/index.vue`, `admin/Dashboard.vue` shed the field. Mobile user-card row now shows `Twitch: {twitch_id}` instead of email; the search placeholder reads "Search name or twitch_id...".
- `UserInfo.vue` lost its dead `showEmail` prop; `AppSidebarHeader.vue` lost the `:show-email="false"` binding that was setting it.
- Tests / factories / seeders: `UserFactory` no longer fakes email/password/remember_token and the `unverified()` state is gone (there's nothing to be unverified about). `GhostUserSeeder` and `tests/Unit/GhostUserTest.php` drop the hardcoded `ghost@overlabels.internal` / hashed password; ghost user creation is now just `name` + `twitch_id` + `is_system_user`. `DatabaseSeeder` drops the email from the test-user creation. Test sweep: 446 passing post-change. The 3 still-failing tests (`ApplyActionTest > killing a zombie`, `ExpressionControlTest > creating expression control with no references`, `OverlabelsMobileWebhookTest > connect creates integration`) are pre-existing and unrelated - none of them touch email logic.
- Privacy posture: from the streamer's view, the only PII Overlabels now persists about them is what they consciously gave Twitch - their public Twitch profile data minus the email scrub. No way to look up an Overlabels account by email because the column doesn't exist. Removing this also let us drop the `user:read:email` scope ask from the consent screen, which is one less thing for cautious users to squint at on first login.

## April 26th, 2026 - Drop the Replace-image dropzone, Remove-then-paste only

- Earlier today's attempt to wire a `replaces_url` cleanup into the upload endpoint did not actually free the previous Cloudinary asset in practice - both the original and the replacement URL stayed live and resolvable on the CDN after a replace. The hardening from April 25 only worked through the Remove path (which sets `screenshot_url=null`, hits `updateScreenshot`, calls `deleteByUrl`).
- Rather than keep iterating on a UI affordance whose backend twin is fragile, removed the affordance: `ImageDropZone.vue` no longer renders the secondary "Replace image:" dropzone that appeared under the Remove button. The component now only exposes Remove. To swap a screenshot a user clicks Remove (which deletes the asset from Cloudinary via the established and tested path) and then pastes / drags / picks the new image into the now-empty dropzone.
- Reverted `44e80d6` (the failed `replaces_url` plumbing on `CloudinaryUploadController` + `ImageDropZone`) before the UI removal so the rollback is auditable as one commit.
- No backend route or controller changes needed - the upload endpoint and `OverlayTemplateController::updateScreenshot` both already handle the Remove-then-upload flow correctly. `TemplateScreenshot.vue`, `kits/create.vue`, and `kits/edit.vue` continue to consume `ImageDropZone` unchanged.

## April 26th, 2026 - Controls can carry a description

- New nullable `description` text column on `overlay_controls` (migration `2026_04_26_120000_alter_overlay_controls_add_description`), max 1000 chars. Lets streamers leave notes for their future selves on what each control is for - especially useful for expression controls and service-managed values whose purpose isn't obvious from the key alone.
- Wired through `OverlayControl::$fillable`, the `@property` docblock, and `createForTemplate()` so it persists through the standard creation path. Service-preset and expression branches in `OverlayControlController::store()` thread it explicitly; `update()` and `importForkedControls()` validate and persist it the same way. Validation: `nullable|string|max:1000` everywhere.
- Frontend: `OverlayControl` TS type gets `description: string | null`. `ControlFormModal.vue` adds a 2-row textarea directly under the name input, visible for every type (regular, expression, and service preset). `ControlsManager.vue` shows the description below the control title in the list view; `ControlPanel.vue` shows it between the title and the `[[[c:key]]]` tag inside each control card. Both render with `whitespace-pre-line` so user line breaks survive, and only render when the description is set (no empty placeholder).

## April 25th, 2026 - Cloudinary uploads now route through the backend, with orphan tracking and lifecycle cleanup

- Reported abuse vector: the previous flow had `ImageDropZone.vue` POSTing files directly to `https://api.cloudinary.com/v1_1/<cloud>/image/upload` from the browser. The asset existed in Cloudinary the moment the upload returned, *before any Laravel call*. So a user could paste an image, capture the `secure_url` from the network tab, close the tab without saving, and walk away with free image hosting. Worse: even saved screenshots/thumbnails were never cleaned up on template/kit deletion (`destroy` just called `$model->delete()` and left the asset). Compounded by `replicate()` copying `screenshot_url` to forks, so any naive cleanup risked orphaning shared references.
- Closed by routing every upload through Laravel. New `POST /cloudinary/upload` endpoint (auth-gated, rate-limited at 20/hour per user + 100/hour per IP via a new `cloudinary-upload` named limiter). Frontend now multipart-POSTs to our endpoint with CSRF; backend signs the upload to Cloudinary using the existing `CLOUDINARY_URL` config and applies the same upload presets the dashboard had configured (`overlabels-overlay-screenshots`, `overlabels-kit-thumbnails`).
- Server-side validation rejects non-image mimes, files >10MB, and (defense-in-depth) images smaller than 400x400 - the example abuse case from the issue report ("10x10 favicon as image hosting") gets bounced with a clean 422 before ever hitting Cloudinary. `getimagesize()` runs against the temp upload path before the Cloudinary call.
- New `cloudinary_uploads` table tracks every successful upload: `user_id`, `public_id`, `secure_url`, `kind` (`template_screenshot` or `kit_thumbnail`), `bytes/width/height/format` from Cloudinary's response, and a nullable `claimed_at`. Indexed on `(claimed_at, created_at)` for the sweeper and on `secure_url` for the claim lookup.
- Claim happens at every persistence point: `OverlayTemplateController::updateScreenshot`, `KitController::store`, `KitController::update` all call `$cloudinary->claim($url)` after the model save. The claim is an idempotent UPDATE that stamps `claimed_at = now()` only on rows where it's null, so re-claiming a forked URL is a no-op.
- New `CloudinaryUploadService` is the single choke point for uploads, claims, and deletions. `deleteByUrl()` walks `OverlayTemplate.screenshot_url` and `Kit.thumbnail` (with optional `excludeTemplateId` / `excludeKitId` for the pre-delete case) and refuses to destroy any asset still referenced by a fork, sibling template, or kit. `extractPublicId()` handles versioned and unversioned Cloudinary URLs and folder-prefixed `public_id`s.
- Tier 1 cleanups folded in while we were touching the same code: `OverlayTemplateController::destroy`, `KitController::update` (on thumbnail change), and `KitController::destroy` all call `deleteByUrl()` with the appropriate exclude. Saved-template deletes now actually delete the screenshot, with the fork-share guard preventing accidental orphan-on-delete.
- Orphan sweeper runs every 15 minutes via the Laravel scheduler in `routes/console.php`. Picks up to 200 unclaimed `cloudinary_uploads` rows older than 30 minutes per pass and deletes both the Cloudinary asset and the row. The 30-minute grace window lets a legitimate user paste, fill out the rest of the form, and save without their screenshot vanishing mid-flight.
- Frontend wiring: `ImageDropZone.vue`'s `uploadPreset` and `folder` props are gone, replaced by a single `kind: 'template_screenshot' | 'kit_thumbnail'` prop. The component now POSTs to `/cloudinary/upload` with `X-CSRF-TOKEN`, parses our standardized `{ url, width, height }` response, and surfaces backend validation errors verbatim (so a user who pastes a 10x10 PNG sees "Image must be at least 400x400px" instead of a generic "upload failed"). Three callers updated: `TemplateScreenshot.vue`, `kits/create.vue`, `kits/edit.vue`.
- Dropped `window.cloudinaryCloudName` from `app.blade.php` and `globals.d.ts` - the cloud name is no longer needed in the browser since uploads are server-side now. One less piece of config leaking into the page source.
- `OverlayTemplateController` lost its private `deleteCloudinaryAsset` method (it lived in this controller alone and used `adminApi()->deleteAssets()`); the new service uses `uploadApi()->destroy()` for single-asset deletion which is the more direct API. Behavior is the same; one place to maintain instead of N.
- Tests: new `CloudinaryUploadServiceTest` covers `extractPublicId` (versioned / unversioned / non-Cloudinary URLs), `claim` (idempotency, null-safe), and `deleteByUrl` (template-shared and kit-shared guards). 5 tests, 7 assertions, all green. The actual Cloudinary upload itself is not exercised in tests since it would require live API credentials - tested manually in dev against the real Cloudinary account.

## April 25th, 2026 - Joiner list overflow handling + auto-scrolling active players

- Follow-up to the live event ticker work earlier today. With the new game-log panel taking permanent residence in the left column under "Last Twitch chat vote", the old standalone "Inactive" section that lived below it was getting pushed off-screen on tall games. And the right-column active-player list had no height bound either - 50 active joiners would just keep extending the column past the viewport.
- Merged inactive into the right column as a faded follow-on to the active list. Active stays leading; inactive slot in below as a condensed 2-column grid with username + last-vote round, no vote icon and no energy dots, wrapped in `opacity-60` so they read as visually retired. Long Twitch handles get the standard `flex-1 min-w-0 overflow-hidden whitespace-nowrap text-ellipsis` recipe (and the active-row username got the same treatment, since `min-w-0` was missing on the row and ellipsis wasn't kicking in).
- Right column locked to `h-209.5` (838px) with `overflow-hidden`, and the flex chain inside (`section` -> `div` -> `ul`) all carry `min-h-0 flex-1 overflow-hidden` so only the active `<ul>` is allowed to scroll. The inactive grid is capped at `max-h-60` (240px) with `overflow-hidden shrink-0` so it can't starve the active list of vertical space - this was a real bug discovered in dev: 50 inactive items at natural height claimed ~800px and squeezed the active `<ul>` to a single visible row before the cap.
- Active list auto-scrolls. `autoScrollActiveStep` is a rAF loop that ping-pongs `scrollTop` at ~40 px/sec with a 1.5s pause at each end, started in `onMounted` and cancelled in `onUnmounted`. No-op when content fits the container. CSS overflow on the `<ul>` is `hidden` (not `auto`) so no scrollbar shows - the animation does the moving.
- Game-log ticker also adjusted from the multi-entry TransitionGroup to a single-entry `Transition`: top-right shows only the most recent *new* event for 4 seconds, then disappears. The full history lives in the permanent panel under the vote block.
- Dropped the `LOG_TICKER_LIMIT` cap on `GameLog::append` - both `log` and `recap` columns grow unbounded, since the permanent panel needs the full history and the volume is negligible per the user's read on game length.
- Left in a `FAKE_PREVIEW` const + 50 active / 50 inactive name pools as a dev visualization toggle (default `false`). Future layout work on this column can flip it on without re-fabricating fixtures. Kept distinct id prefixes (`fake-a-` / `fake-i-`) so Vue keys never collide if both are enabled.

## April 25th, 2026 - Live event ticker + recap log for the gamejam game

- Gap: chat couldn't see what just happened. Voted attack? Did it land? Was the boss in range? The `last_resolved_action` chip showed the verb but no consequence. We needed a Twitch-style ticker so chat understands cause and effect each tick.
- Schema: two new nullable JSON columns on `games` - `log` (rolling window, capped at `Game::LOG_TICKER_LIMIT = 30` entries, broadcast every tick) and `recap` (uncapped append-only history, never broadcast, exists for end-of-game review). Both are array-cast on the model. JSON column over a separate `game_logs` table because recap reads are always per-game and Postgres JSONB handles the volume comfortably for any plausible game length.
- New `App\Services\Gamejam\GameLog::append(Game, string $type, array $data = [])` writes both columns in one update: pushes to `recap`, pushes to `log` and trims `log` to the last N. Each entry has `id` (uuid), `at` (unix seconds), `type`, `data`. No actor field - all player events read as "You" / "chat" because the underlying mechanic is collective vote tallies, not individual actions, and Twitch optics make per-user attribution risky.
- Event types (constants on `Game`): `hide`, `hidden_reveal`, `hp_pickup`, `weapon_pickup`, `player_attack`, `zombie_killed`, `zombie_attack`, `door_damage`, `door_opened`, `boss_blocked`, `room_entered`, `game_won`, `game_lost`. Zombie *moves* and *brain-state changes* deliberately not logged - on-screen colour/direction already conveys those, and they would shred the ticker's signal-to-noise.
- Write points: `ActionApplier` instruments hide, reveal-and-pickup branches in `revealTile`, attack damage and kill in `attackZombiesInRange`, bump damage in `applyZombieBump`, door damage in `damageDoor` (now takes `Game $game` so it can log), boss-immunity short-circuit in `attack`, exit-door room transitions in `stepOnce`, and game-over status flips. `ZombieTurnResolver` collects all adjacency attackers, applies HP after the loop, and emits one `zombie_attack` per attacker with the post-damage HP plus a `game_lost` if it killed the player. Boss-blocked spam is intentional - chat gets a stream of "exit is sealed" lines while the boss is alive, then a triumphant kill, on user request.
- `GameStateChanged::snapshotFor()` includes `'log' => $game->log ?? []`. Recap is *not* broadcast - bloating every tick with the full history would be silly when the live ticker only needs the last few entries.
- Frontend: `live.vue` adds `GameLogEntry` interface, a `log` ref, a `Set<string>` of currently-visible ids, and per-entry timers that drop entries from the visible set after `TICKER_VISIBLE_MS = 6000`. `syncTicker(entries)` is called both on mount and on every snapshot. `formatLogEntry()` is a single switch over `type` that returns the human string ("You hit a boss zombie for 4 (26/30)", "Boss zombie hit you for 5 (12 hp)", "The boss is still alive - the exit is sealed"). Rendered as a `<TransitionGroup>` ticker absolutely-positioned top-right of the grid area, with per-type accent colours via `data-type` attribute selectors.
- Tests: new `tests/Feature/GameLogTest.php` covers reveal-then-pickup, attack-and-kill, boss-blocks-door, hide, and the cap (log trims at 30, recap keeps everything). Existing `ResolveGameRoundTest` and `BotGamejamActionTest` still green.
- Future hooks: a "Recap" panel that reads `recap` at game-end, and a delete/clear admin action for old games' JSON columns once the data lifecycle becomes a question. Not built yet.

## April 25th, 2026 - Push-notify the bot when a user toggles the chat integration

- Reported symptom: a friend authed a fresh account, flipped the chat-bot integration on at `/settings/integrations`, and the gamejam live page started rendering - but `@overlabels` never joined his chat and `!ping` got nothing back. Re-running the `@overlabels` OAuth flow at `/admin/twitchbot` "fixed" it, which made it look like the bot needed re-authentication per new user. It didn't.
- Real cause: the bot is a single shared `@overlabels` account; channel discovery happens by polling `/api/internal/bot/channels` (returns `users.bot_enabled = true`) on a 60-second cadence. `BotSettingsController::setEnabled` flipped the flag immediately but fired no event, so the bot didn't notice until the next poll tick. The admin "Reconnect" button looked causal because writing a new bot token in `bot_tokens` happens to make the Node side re-sync its full state, which incidentally re-polls channels - pure side effect, not by design.
- Laravel side: new `App\Events\BotChannelsChanged` (`ShouldBroadcastNow`, public channel `bot-channels`, `broadcastAs` `bot.channels.changed`, payload `{ login, enabled }`). `BotSettingsController::setEnabled` dispatches it after the user update, using the lowercased `twitch_data.login`. Public channel because the payload is just a Twitch login (already public) plus a boolean - no auth handshake needed on the bot side.
- Bot side (`overlabels-bot` repo): added `pusher-js`, new `src/reverbClient.js` connects to Reverb using `REVERB_APP_KEY` / `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME`, subscribes to `bot-channels`, binds `bot.channels.changed`. On receipt, runs `commandMap.refresh()` + `bot.syncChannels(...)` guarded by a `pushRefreshing` flag so a burst of toggles can't pile up overlapping syncs. Reverb config is all optional in `config.js` - if `APP_KEY` or `HOST` are missing, the bot logs a warning and falls back to poll-only behavior (no crash).
- The 60-second poll loop is intentionally preserved as a safety net for missed websocket messages; the broadcast just makes the common case feel instant.
- Deploy: `config/deploy.yml` gets `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_APP_KEY` in the `clear:` env block. App key is a public client identifier in Reverb (same one the Vite frontend ships), not a secret.
- Deliberately scoped narrowly: did not also dispatch on user soft-delete or Twitch unlink. Those paths are rare and the poll catches them within a minute. Fold in if it ever surfaces as a real problem.

## April 25th, 2026 - Fuzzy-searchable `/help/reference` backed by committed markdown

- Gap: the curated `/help/conditionals`, `/help/controls`, `/help/formatting` pages explain syntax well but aren't the right surface for "what's the exact tag for follower count again?" - 130+ template tags, EventSub events, and foreach fields don't fit in a narrative page. An Obsidian vault with one markdown file per tag proved that a fuzzy-search index over short reference docs is the right shape: type "followe" and every follower-related thing surfaces at once.
- Moved the vault into `resources/help/reference/` (135 files, ~1.6 MB on disk, <100 KB of actual body text) organized into four categories: `template-tags`, `eventsub-tags`, `eventsub-events`, `foreach-loops`. Files are plain markdown with no frontmatter - title is inferred from the filename, category from the folder. Committed to the repo so edits version with the code that produces the tags.
- Added `useHelpReference` composable at `resources/js/composables/useHelpReference.ts` that eagerly globs every `.md` under `resources/help/reference/**` via `import.meta.glob({ query: '?raw', eager: true })`, builds a Fuse.js index with weighted keys (title, slug, body), and exposes `search()`, `get()`, and `grouped()`. Markdown rendering goes through `marked` with a preprocess step that (a) protects literal `[[[tag]]]` template syntax with placeholders, (b) converts Obsidian-style `[[slug]]` wikilinks into real `/help/reference/<category>/<slug>` links using a build-time slug-to-category map, and (c) restores the triple-brackets as inline code. Order matters - without the placeholder step the wikilink regex would eat the inner `[[` of every `[[[` tag.
- `ReferencePalette.vue` mounted in `AppLayout.vue` gives every authed page a global `Ctrl+/` shortcut that opens a Fuse-backed search modal with keyboard nav (arrows + enter), a 140-char snippet per result that biases toward the query term when present, and category badges. Sits alongside the existing `Ctrl+Space` command palette (navigation) and `Ctrl+K` shortcut cheatsheet - three distinct palettes, three distinct jobs.
- `/help/reference/{category?}/{slug?}` route renders `pages/help/Reference.vue` with a two-pane layout: sidebar with the full grouped index (or search results when the top input has a query), main pane renders the selected entry's markdown. Clicks on internal `/help/reference/...` links intercept and use Inertia's `router.visit` to stay SPA; external `http(s)` links get `target="_blank"` + `rel="noopener noreferrer"` added on the fly.
- Help-page index card grid picks up a new "Reference" card (first position). CommandPalette gets a "Reference (Ctrl+/)" entry under the Learn section so users who forget the shortcut still find it.
- Deps: `fuse.js` (fuzzy search, ~4 KB gzipped) and `marked` (markdown to HTML, ~10 KB gzipped). Reference page codesplits into a 7 KB chunk; the markdown corpus lives in the AppLayout chunk because ReferencePalette is mounted globally.
- Styling: hand-rolled `.help-prose` scoped CSS instead of pulling in `@tailwindcss/typography` - the content is simple enough (paragraphs, lists, code, links, headings, occasional tables) that 80 lines of CSS covers every case in the vault.

## April 25th, 2026 - Document foreach item shapes and add `[[[raw]]]` debug tag

- Gap: `/help/conditionals` had a table listing the seven iterables that work with `[[[foreach:X as Y]]]` (event.choices, event.outcomes, event.top_contributions, subscribers, goals, channel_followers, followed_channels) but no docs on what fields each loop item actually exposes. A template author who wanted `choice.title` or `subscriber.user_profile_image_url` had to either guess or read through `TemplateDataMapperService` and `TwitchApiService::enrichWithProfileImages` to find the enriched field names.
- Documented the per-item field list for all seven iterables as a new "Fields available on each iteration item" subsection on the Conditionals help page. Includes aggregate tags for poll/prediction totals, clarifies the enriched `user_profile_image_url` / `broadcaster_profile_image_url` / `gifter_profile_image_url` fields that come from the Helix user-batch lookup, and lists valid `goals.type` values and `subscribers.tier` codes inline.
- Added a new `[[[raw]]]` template tag that dumps the current `[[[foreach]]]` iteration item as pretty-printed JSON. Implemented in `substituteScopedTokens` in `useConditionalTemplates.ts` - it short-circuits before the usual scoped-token path, runs `JSON.stringify(scoped[alias], null, 2)`, HTML-encodes the result, and then defuses `[` / `]` to `&#91;` / `&#93;` so a rare 3D-array payload can't sneak a `[[[...]]]` triple back into the outer tag-substitution pass (tags-never-reparse invariant). Pipe formatters on `[[[raw]]]` are intentionally ignored - it's meant as scaffolding. Outside a foreach the literal token falls through to the outer substitution pass and renders as an empty string.
- Help-page pairs the docs with a callout example: `[[[foreach:event.choices as choice]]]<pre>[[[raw]]]</pre>[[[endforeach]]]` is the shortest path from "what's in this iterable?" to "here's the JSON".

## April 24th, 2026 - Drop the ghcr.io/ prefix from the cache image path

- Immediate follow-up to the GHA-to-registry switch earlier today. Kamal auto-prepends `registry.server` (ghcr.io) to `builder.cache.image` the same way it does to the main `image:` field. Writing the full `ghcr.io/jasperfrontend/overlabels-buildcache` path produced `ghcr.io/ghcr.io/jasperfrontend/overlabels-buildcache` in the actual `--cache-from` / `--cache-to` flags. GHCR silently 404'd both sides; the deploy was slower than baseline (4:36 vs 4:30) because we paid the cache-write overhead for zero hits. The Kamal log had an explicit `ERROR: failed to configure registry cache importer: ... not found` that nobody was reading, which is how this snuck through.
- Fix: `image: jasperfrontend/overlabels-buildcache` (no `ghcr.io/` prefix). Same footgun as the accessory-vs-main-image path handling already documented in the Kamal memory reference.

## April 24th, 2026 - Switch Kamal build cache from GHA to registry

- Second-order fix to the Dockerfile cache ordering earlier today. The `builder.cache.type: gha` config in `config/deploy.yml` was silently not working - Kamal 2 creates its own buildx builder (`kamal-local-docker-container`) instead of using the one `docker/setup-buildx-action` sets up, and that builder doesn't have `ACTIONS_CACHE_URL` / `ACTIONS_RUNTIME_TOKEN` wired into its buildkit daemon. So `--cache-from type=gha --cache-to type=gha` was being passed to the build but the daemon couldn't reach the GHA cache service.
- Confirmed via the deploy log for `29303b5`: `install-php-extensions` ran for 74s, `npm ci` for ~20s, `composer install` for ~20s - none of them cached even though their inputs were unchanged from previous deploys.
- Switched to `type: registry` with `image: ghcr.io/jasperfrontend/overlabels-buildcache` and `options: mode=max`. Registry cache stores layers as a separate image in GHCR, which Kamal's builder can reach because it's already authenticated for the main image push. `mode=max` exports cache for every intermediate layer, not just the final stage, maximizing hit rate.
- First deploy after this change writes the cache image (slow-ish, same as before). Subsequent deploys pull from it and should cache everything up through `install-php-extensions`, `npm ci`, and `composer install`. Expected steady-state deploy time drop: ~90-110s of build work, on top of the ~30s `npm ci` saving already won by moving `APP_COMMIT_SHA` past the install step.

## April 24th, 2026 - Stop busting the npm ci cache layer every deploy

- `ARG APP_COMMIT_SHA=dev` + `ENV APP_COMMIT_SHA=${APP_COMMIT_SHA}` were declared at the top of the Vite assets stage in the Dockerfile, before `COPY package.json` and `RUN npm ci`. Every push has a different SHA, so the ENV layer invalidated on every deploy, which cascaded into `npm ci` re-running from scratch each time - easily 60-90 seconds of wasted install time per build.
- Moved the ARG/ENV block down past `npm ci`. Now `npm ci` hits the GHA buildx cache whenever `package-lock.json` is unchanged. `RUN npm run build` still invalidates on every deploy (expected: Vite needs to re-bundle on any source change) but it gets to start with a warm `node_modules` cache layer.
- The cache-from/cache-to wiring was already correct: listener images have explicit `cache-from: type=gha` in the workflow, and the main app has `builder.cache.type: gha` in `config/deploy.yml`. This was purely a Dockerfile-ordering issue.

## April 24th, 2026 - Hard reload after EventSub setup completes

- Follow-up to the Reconnect broadcast landed earlier today. `router.reload({ only: ['eventsub'] })` occasionally left the Active events dialog rendering stale prop state for a tick after the partial reload resolved - the counter updated but the individual ✓/✕ rows lagged behind. Swapped to `window.location.reload()` which also reconnects Echo fresh. Rare enough that a page refresh was a reliable tiebreaker, but shouldn't require the user to know that.
- Update: this is reverted as the hard refresh killed a still-pending connection job.

## April 24th, 2026 - EventSub verify pagination bug + hype train v2 + job-based Reconnect

- Reported symptom: the Integrations page showed "Active events (5)" on prod with 22 of 26 event types rendering with a ✕ mark, even right after clicking Reconnect. The actual state on Twitch was healthy - all 24 non-hype-train subs were `status=enabled` on their side. The DB and UI were lying.
- Root cause: `TwitchEventSubService::getSubscriptions` called `GET /helix/eventsub/subscriptions` with no filter and no pagination. Twitch caps the response at 100 subs per page and this app currently has 219 subs across all users, so the verify loop only saw page 1. Every user whose subs fell on pages 2+ got stamped `not_found_on_twitch` in `verifyUserSubscriptions`, even while those subs were actively delivering events on Twitch's side. Scoped the call with `user_id` (Twitch matches against any condition key, so raids-via-`to_broadcaster_user_id` and follows-via-`moderator_user_id` both come back). Passed the same filter in `removeUserSubscriptions` so the app-side cleanup pass also sees the full set, and dropped the now-unnecessary client-side filter-by-condition loop.
- Hype train on this account had been silently broken since 2026-01-15 (when Twitch withdrew the v1 subscription types). `setupUserSubscriptions` kept trying to create `channel.hype_train.begin/progress/end` with `version: '1'` and getting `invalid subscription type and version` back - three failures per reconnect, logged but otherwise invisible. Bumped all three to `version: '2'` in `UserEventSubManager::SUPPORTED_EVENTS`. v2's payload is backwards-compatible for the fields we surface (`top_contributions`, `last_contribution`, `level`, `progress`, `goal`, `started_at`, `expires_at`, `ended_at`, `cooldown_ends_at`), so `TemplateDataMapperService`, `EventsTable.vue`, and the Conditionals help page all pass through unchanged. v2 does add `is_golden_kappa_train`, `is_shared_train`, `shared_train_participants[]`, and `all_time_high_*` which the payload now carries but we don't yet expose as tags - those can be a follow-up.
- Restored the job-based dispatch path from `/eventsub/connect`. On April 8th it was reverted to a synchronous call inside `IntegrationController::connectEventSub` on the theory that the job wasn't running; real reason was that Twitch challenges were arriving while the same web worker was busy with the creation loop's outbound Helix calls, and the first ~20 challenges timed out against a starved worker. The queue worker now handles the Helix calls on its own process (`overlabels-queue` role) while the `web` role stays free to respond to challenges within Twitch's 10-second window. Controller returns 202 immediately; no inline try/catch because the job already handles retries.
- Surfacing the results payload through a broadcast: new `EventSubSetupCompleted` event on `alerts.{twitch_id}` with broadcastAs `eventsub.setup-completed`, carrying `created`, `failed`, `existing`, `skipped_missing_scope`, and a `success` flag. Fired from `SetupUserEventSubSubscriptions::handle` on success and from `failed()` on terminal failure. Settings page subscribes via Echo on mount, updates the results message and calls `router.reload({ only: ['eventsub'] })` so the active-events count and the dialog update without a full navigation. Tears down the listener in `onBeforeUnmount`.
- Post-deploy reconciliation is automatic: the next Reconnect (or any call path through `verifyUserSubscriptions`) sees the full set now and flips the 20 bogus `not_found_on_twitch` rows back to `enabled`. No manual fix needed on prod, no migration, no data touch.
- Verified with `php artisan test --filter=EventSub` (9 pass), `./vendor/bin/pint --test` on the five backend files, `npm run lint` (no new errors from these files), and a live `GET /helix/eventsub/subscriptions?user_id=...` call against prod that confirmed the user's 24 subs are all `enabled` on Twitch's side, establishing the pagination theory before touching code.

## April 24th, 2026 - Editor shows the wrapper tags so the document structure is explicit

- Follow-on to the display: contents hoist earlier today. The template editor has three tabs (HEAD / BODY / CSS) and was three bare CodeMirror panels with no visual cue that the user's content is wrapped in `<head>` / `<body>` / `<style>` at render time. Easy to type your own `<body>` tags inside the HTML tab, or to mis-model the CSS tab as page-level styles rather than "styles inside a `<style>` tag inside `<head>`".
- Each CodeMirror now sits between a non-editable header showing the opening tag and a footer showing the closing tag: `<head>` / `</head>`, `<body>` / `</body>`, `<style>` / `</style>`. Sidebar bg, muted monospace text, `select-none` so the frame reads as chrome not content. The CSS header carries a second line with a code-comment explainer of the hoist: "/* Your overlay renders directly as a child of `<body>` - flex and grid on body flow right through to your top-level elements. No need to dig up 3 wrappers. */" - the editor is where people learn the mental model, so the explanation lives next to the editor.
- Layout trick: kept `v-show` (not `v-if`) so tab switches preserve CodeMirror's internal state and scroll position. Each editor wrapper is `absolute inset-0 flex flex-col`, the header/footer are `flex-none`, and CodeMirror lives inside a `relative flex-1 min-h-0` intermediate div with `absolute inset-0` on the editor itself. Without the intermediate relative wrapper, long files push CodeMirror past its flex slot and the closing-tag footer gets clipped by the outer `overflow-hidden`. That was the first-cut bug the user caught; the intermediate div is what fixes it.
- Bonus: removed a stray `pb-10` on the outer content box in `edit.vue` that was adding dead space under the editor now that it has its own footer row.

## April 24th, 2026 - Hoist overlay content out of its layout wrappers

- Pain: writing an overlay that wants a full-viewport flex/grid layout (center a scene on screen, justify content to the bottom, split into two columns, etc.) was miserable because the user's top-level element wasn't a child of `<body>` - it was nested inside `body > #overlay-content > [staticContainer div] > their-element`. Flex on body did nothing; the user had to reverse-engineer the DOM from OBS devtools to know what to style.
- Both wrappers exist for structural reasons we can't drop: `#overlay-content` is the Vue app's mount point (Vue's `mount(el)` keeps the element and renders into it), and the staticContainer is where morphdom patches the user's HTML - it's a sibling of the health banner and the alert overlay, so losing it means restructuring `OverlayRenderer`.
- Fix: `display: contents` on both wrappers. The CSS spec's "layout-transparent" display mode makes an element invisible to layout - its children behave as if they were direct children of its parent - while keeping the DOM tree intact. Two lines in `authenticate.blade.php`, plus a class (`overlay-static-root`) on the staticContainer in `OverlayRenderer.vue` so the CSS has something to target. Net result: the user's top-level overlay elements behave as direct children of `<body>` for all layout purposes. `body { display: flex; justify-content: flex-end }` now works as expected.
- Caveats worth remembering: `display: contents` makes the element itself non-paintable, so if we ever want to put a background / border / transform / filter on `#overlay-content` we'd skip contents for that wrapper. Rarely an issue for overlays. Solid browser support in OBS (Chromium 127+).

## April 24th, 2026 - Fourthwall HMAC verification uses app-level secret

- During live testing of the Fourthwall integration, every inbound webhook hit 403 at our verify step. Root cause: Fourthwall's `POST /open-api/v1.0/webhooks` response doesn't include the per-webhook signing secret - it's only shown once in the FW dashboard and never exposed via API, including through `GET /webhooks/{id}` (which returns exactly four fields: id, url, allowedTypes, apiVersion). Our code stored `$webhookData['secret'] ?? null`, so verification was silently HMAC'ing with an empty string.
- Fourthwall actually sends two HMAC headers on every delivery: `X-Fourthwall-Hmac-Sha256` (per-webhook secret, which we can't obtain programmatically) and `X-Fourthwall-Hmac-Apps-Sha256` (app-level HMAC, signed with the FW_HMAC value set once when the app was created). The app-level header is shared across every user and every webhook, which makes it the only viable choice for a multi-tenant integration where we can't ask every streamer to paste a secret from a dashboard.
- Swapped `FourthwallServiceDriver::verifyRequest` to verify against `config('services.fourthwall.hmac')` (reads FW_HMAC) and the Apps header. Added `services.fourthwall.hmac` to config, wired FW_HMAC through deploy.yml + the GH Actions deploy workflow (env block + var loop) + `.kamal/secrets.example`, and set the GH Actions repo secret via `gh secret set`. Ripped `webhook_secret` out of the credentials bag written in the OAuth callback - `webhook_id` still lives there for deregistration on disconnect, but the secret was dead weight.
- Unit tests cover: empty FW_HMAC fails closed, missing Apps header fails closed, valid Apps signature verifies, tampered body fails, a present-but-wrong Hmac-Sha256 header doesn't accidentally short-circuit verification (guard against a regression where we pick the wrong header). Feature tests send `HTTP_X_FOURTHWALL_HMAC_APPS_SHA256` instead of the per-webhook header. Total: 37 tests pass across the Fourthwall suites.
- Trade-off accepted: FW_HMAC is a shared secret across every user's Fourthwall integration in our app. If it rotates, every integration needs re-sync at once. This is the same model Shopify embedded apps use for HMAC verification and is acceptable at our scale; per-webhook secrets would have been better rotation-hygiene but were architecturally impossible without forcing every user to paste a secret after OAuth.

## April 24th, 2026 - Fourthwall integration (donation MVP)

- New external service driver: Fourthwall. Sixth service alongside Ko-fi, StreamLabs, StreamElements, GPSLogger, and Overlabels GPS. MVP scope is the DONATION event only - ORDER_PLACED, GIFT_PURCHASE, SUBSCRIPTION_PURCHASED and the other 23 event types Fourthwall exposes are intentional follow-ups, not in this cut.
- Connect flow is full OAuth 2.0 against Fourthwall's Open API, not a user-pastes-a-token model like Ko-fi. The auth URL is shop-scoped (`https://my-shop.fourthwall.com/admin/platform-apps/{CLIENT_ID}/connect`) rather than a standard OAuth authorize endpoint - Fourthwall's doc is explicit that `my-shop` resolves to whichever shop the user is logged into. Access tokens "expire within a few minutes" (Fourthwall's wording), so the client does preemptive refresh with a 60-second safety window before any outbound API call.
- Unique bit: Fourthwall gives you a webhook management API, so we don't ask the user to paste a webhook URL into their dashboard - the callback handler registers the subscription for `['DONATION']` via `POST /open-api/v1.0/webhooks` immediately after the token exchange, stores the returned per-webhook `secret` on the integration credentials, and uses that secret for HMAC-SHA256 verification on every inbound payload (header: `X-Fourthwall-Hmac-SHA256`, base64 of HMAC over the raw body). Disconnect reverses it: `DELETE /open-api/v1.0/webhooks/{id}` first (best-effort, logged on failure so we never block a disconnect), then standard deprovision. Reconnects dedupe-by-replace: the old webhook is deregistered before a new one is created, so orphan subs don't accumulate on the shop side.
- Failure handling on fresh connects: if webhook registration fails (insufficient scope, network blip, etc.) the integration row is rolled back so the next attempt starts clean. Reconnects keep their previous row so a bad retry doesn't nuke a working integration.
- Event normalization: dedup key is `data.id` (the `don_...` business entity id) not the envelope `id`, because envelope ids change on retry while `data.id` is stable. Template tags follow the existing donation-family shape - `event.from_name`, `event.amount`, `event.currency`, `event.message`, `event.transaction_id` - with one new Fourthwall-specific field `event.status` exposing the donation lifecycle state (`OPEN`, etc., for templates that want to branch on it).
- Controls: six auto-provisioned on connect, mirroring Ko-fi and StreamLabs exactly - `donations_received`, `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`. Same test-mode toggle, same one-time donation-count seed. Template tag syntax: `[[[c:fourthwall:donations_received]]]`.
- Files: `FourthwallServiceDriver` (driver), `FourthwallApiClient` (thin Http wrapper for token exchange, refresh, webhook register, webhook delete), `FourthwallIntegrationController` (settings page + OAuth + test mode + seed + disconnect), `settings/integrations/fourthwall.vue`, plus registry + routes + preset + modal wiring. Callback path is `/auth/redirect/fw` to match what's registered with Fourthwall.
- Env vars: `FW_CLIENT_ID`, `FW_CLIENT_SECRET`, `FW_AUTH_URL`, `FW_REDIRECT_URL`. Optional `FW_API_BASE` defaults to `https://api.fourthwall.com`. `FW_HMAC` was considered - it's a separate app-level secret Fourthwall returns for their *embedded sections* feature (iframe apps loaded into Fourthwall's dashboard, à la Shopify embedded apps), which is a completely different mechanism from webhook verification. Left in `.env` for when/if we ever build an embedded section, but nothing in this integration reads it.
- Test coverage: 15 unit tests for the driver (HMAC happy/tamper/missing-header paths, DONATION parsing, normalization including the `data.id`-vs-envelope-`id` dedup semantics) and 13 feature tests (OAuth redirect with session state, callback happy path, state mismatch, token exchange failure, webhook registration failure rollback, webhook intake with valid HMAC, 403 on tampered HMAC, dedup, control updates, unsupported event types ignored, disconnect with/without remote deregister success). All 21 new tests pass; the two pre-existing failures in `OverlabelsMobileWebhookTest` and `ExpressionControlTest` on main are unchanged by this work.
- Open question for live testing: Fourthwall's sample DONATION payload shows `status: OPEN`, unclear whether a second DONATION fires when status transitions to CAPTURED. If alerts fire on OPEN only, we might alert for pledges that never settle. Will validate with a real test donation on the dev shop and add a status filter if needed.

## April 23rd, 2026 - Bump GH Actions past the Node 20 deprecation

- The deploy workflow's post-run log kept printing a deprecation warning for `actions/checkout@v4`, `docker/build-push-action@v5`, `docker/login-action@v3`, and `docker/setup-buildx-action@v3` all running on Node 20. GitHub's timeline: June 2nd 2026 forces Node 24 on the runner, September 16th 2026 removes Node 20 entirely. Deploys still work today but either deadline could surface action-side breakage if the maintainers of any of these haven't shipped a Node 24 build under the current tag. Bumped `actions/checkout@v4` → `@v5` and both `docker/build-push-action@v5` → `@v6` - those were the two flagged actions with a major-version bump required. The two `docker/*@v3` floating tags pick up Node 24 internals as their maintainers cut v3 minor releases, so no tag change needed there - the warning will stop firing once those minor releases publish on GitHub's side.

## April 23rd, 2026 - "Why Overlabels" help page + TW3 tab in the code editor

- New `/help/why-overlabels` page, wired into the help index above the nuts-and-bolts docs. Written for coders, not "pleb marketing" - leads with the third-party data normalization pitch, walks through `latest()` across four donation sources, shows an Expression Control that sums Ko-fi + StreamLabs + StreamElements + (Twitch Bits / 100) into a single USD number, unpacks the seven Control types, demos `[[[if:followers_total]]]`, and ends on the "data is ugly, that's why we have formatters" angle with the mandatory `0.1 + 0.2 = 0.30000000000000004` rant. Each section links deeper: `/help/controls`, `/help/conditionals`, `/help/formatting`, `/help/math`, `/kits`. Closing CTA is "bro, god knows" because it survived the editing pass and nothing else fit as well.
- Added a "TW3" tab to `TemplateCodeEditor.vue` sitting alongside HEAD/BODY/CSS - clicking it swaps the CodeMirror panel for an in-editor explainer about the per-template compiled utility CSS feature shipped earlier today. Calls out the two things that bite people: (1) Tailwind classes do NOT render in the Ctrl+P preview modal or the `/preview/{slug}` route because those previews only inline the CSS tab and sample-substituted HTML - the compile step runs on save so utilities only paint on the authenticated overlay URL; (2) dynamic class names like `class="text-[[[c:color]]]"` won't get CSS generated because UnoCSS only sees the pre-substitution source. Footer credits UnoCSS + presetWind3 and invites bug reports by email.
- Added `id="foreach-loops"` to the Foreach section in `Conditionals.vue` so the deep-link from Why Overlabels (`/help/conditionals#foreach-loops`) actually scrolls to the right block instead of landing at the top of the page.
- Also fixed a TS2339 in `pages/templates/edit.vue`: the `Props.template` interface was missing `compiled_css`, which the form initializer reads. Nullable because the column is nullable on templates that predate the compile feature.

## April 23rd, 2026 - Per-template compiled utility CSS replaces CDN Tailwind

- A user dropped `https://cdn.html-first.com/litewind-2.0.1.css` into an alert template and their alerts started arriving a second late because every OBS browser source was parsing a ~500 KB stylesheet on first paint. Fix: compile a minimal Tailwind-compatible utility stylesheet per template at save time and ship only what the template actually uses. Built on UnoCSS's `@unocss/preset-wind3` (Tailwind v3 syntax, which is what most snippets from the wild target).
- Compilation runs browser-side in the template editor, not server-side. The production FrankenPHP image has no Node runtime - the Socket.IO listeners live in separate Kamal accessory containers - so shelling out from PHP would have meant adding Node to the main image. Bundling UnoCSS into the editor sidesteps the deploy change, and the trust model is identical: the existing `css` field is already free-form user-authored CSS, so a client-submitted `compiled_css` is no weaker. The editor posts `compiled_css` alongside `html`/`css`/`head`, the controller sanitizes it through `HtmlSanitizationService::sanitizeTemplateFields()` like every other field, and it's stored on a new `overlay_templates.compiled_css` longText column. UnoCSS is lazy-imported via `resources/js/utils/compileTailwind.ts`, splitting as its own ~18 KB (6.8 KB gzipped) chunk - the overlay renderer bundle doesn't pay for it.
- For static overlays: `renderAuthenticated()` returns `template.compiled_css` alongside `template.css`, and `OverlayRenderer.vue` injects it as a separate `<style id="overlay-compiled-style">` tag placed **before** the user's `<style id="overlay-style">` so author-written CSS wins on conflicts (compiled utilities are scaffolding; user CSS is intent).
- For alerts, compiled_css is NOT inlined into the Reverb broadcast - that payload fires for every viewer on every alert, and a raid or gift-sub avalanche would multiply the cost. Instead the broadcast carries a slug reference (`alert_template_slug`), and the overlay preloads an `{ slug: compiled_css }` map on mount via a new `alert_css_preload` field in the render payload. The preload query pulls every alert template the user owns that either has no `targetStaticOverlays` targeting (fires on all overlays) or explicitly targets this static overlay's id. Typical cost: a handful of entries, a few KB each, one-time per overlay session. `handleAlertTriggered()` looks up compiled_css from the preload map by `alert_template_slug`; miss case (brand-new alert template created mid-session before the overlay reloaded) falls back to empty string, which still renders correctly with only the author's inline CSS. `template.updated` already hard-reloads the overlay, which refreshes the preload map naturally.
- `AlertTriggered` constructor gained an optional `alertTemplateSlug` argument and `broadcastWith()` exposes it as `alert.alert_template_slug`. All three dispatch sites updated (`TwitchEventSubController::renderEventAlert`, `ExternalAlertService::dispatch`, `ExternalEventController::replay`) - each already had `$template` in scope so the change is one line per call.
- Known limitation: class names computed at runtime through template tags (e.g. `class="text-[[[c:color]]]"`) won't get CSS generated because UnoCSS only sees the pre-substitution source. Hand-write those rules in the template's `css` field. The "tags never reparse" invariant is preserved - UnoCSS scans the authored HTML at save time, before any `[[[tag]]]` substitution happens, and the generated CSS is stored as data and injected at render time like any other stylesheet. No runtime re-scan of substituted output.

## April 23rd, 2026 - Morphdom-based static overlay rendering for animatable foreach children

- `channel.poll.progress` alerts exposed a limitation of the foreach template tag: the vote bars inside `[[[foreach:event.choices as choice]]]` never animated their width on subsequent EventSub updates. The bars have `width: var(--bar-width)` and a CSS transition, but neither transitions nor keyframe animations had a from-state to work with because the entire subtree was being thrown away and re-parsed on every data update. Root cause: `useConditionalTemplates.ts` is a string-concatenation preprocessor that produces HTML strings, and `OverlayRenderer.vue` was feeding those strings through `v-html` - which is just `element.innerHTML = newString` under the hood. No reconciliation happens; every child is a brand-new DOM node on every render, so there's no "previous" width for the browser to animate from.
- Fixed by swapping the static overlay's `v-html` for a template ref plus a `watchEffect` that morphs the container's children toward the newly-compiled HTML via `morphdom` (added as a dependency, ~5 kB). Each render builds an off-DOM `<div>` populated via `innerHTML`, then `morphdom(staticContainer, template, { childrenOnly: true, getNodeKey })` patches only the differences. Elements with stable `data-key` attributes are reused across renders instead of recreated, so CSS transitions and keyframe animations on those elements keep working. The alert overlay keeps `v-html` because alerts are ephemeral (mount/unmount driven by a `<transition>`) and alertData is frozen at `showAlert()` time - nothing to reconcile.
- `getNodeKey` reads `data-key` first, falls back to `id`, then returns undefined (morphdom's default positional matching). Template authors opt in per-element: adding `data-key="[[[choice.id]]]"` to the `<li>` inside a poll-progress foreach is all that's needed to get smooth bar animations. Without `data-key` morphdom still diffs structurally, so existing templates render the same as before - purely additive behavior.
- Note for template authors using this for animations: pair `data-key` with `transition: width 300ms ease-out` on the animated element rather than `animation: ... forwards`, because keyframe animations only run once on mount and won't re-trigger when the same DOM node's `--bar-width` custom property changes. Transitions will.
- Follow-up: the first cut only morphed the static overlay. Browser DevTools testing with CLI-triggered `channel.poll.progress` events made it obvious the `alert-content` div was still flashing on every payload - the alert overlay kept `v-html` on the assumption that alertData was frozen at `showAlert()` time. It isn't: live-updating alert templates (poll progress, hype train contributions) get repeated `alert.triggered` events with the same template + new data, and `showAlert()` just overwrites `currentAlert.value` in place while the wrapping `<transition>` stays mounted because the truthy→truthy transition doesn't re-render the outer div. Gave the `alert-content` container the same ref + `watchEffect` + morphdom treatment, so data-key'd `<li>`s inside alert foreach blocks now survive updates. Enter/leave transitions still work - morphdom operates on the container's children, Vue's `<transition>` operates on the outer v-if, the two don't collide.

## April 23rd, 2026 - Thread APP_COMMIT_SHA through Kamal build args

- The commit hash shown in the sidebar footer (`__COMMIT_HASH__`, replaced at Vite build time via a `define` in `vite.config.mts`) regressed to the literal string `dev` after the Linode cutover. The chain is: Vite reads `process.env.APP_COMMIT_SHA` at build time → Dockerfile declares `ARG APP_COMMIT_SHA=dev` and exposes it as an env var for Vite → on Railway this was auto-populated from `RAILWAY_GIT_COMMIT_SHA`, but Kamal has no equivalent auto-injection, and `config/deploy.yml`'s `builder.args` only passed the four `VITE_*` args. The Dockerfile default `dev` won every build. Fixed by adding `APP_COMMIT_SHA: <%= ENV['APP_COMMIT_SHA'] || 'dev' %>` to `builder.args` (Kamal 2 ERBs deploy.yml by default) and exporting `APP_COMMIT_SHA: ${{ github.sha }}` in the Kamal deploy step's env block. Local `kamal deploy` runs fall back to `dev` because there's no `APP_COMMIT_SHA` set on the laptop - fine, the commit hash only matters for production where it's the cache-busting signal.

## April 23rd, 2026 - Replace Railway deploy webhook with GH Actions notify

- `RailwayWebhookController` existed solely so Railway's "Deployment.deployed" webhook could broadcast `VersionUpdated` and trigger the "new version available" banner on the frontend. Linode has no equivalent concept because Linode is just a VM host - the "deploy" on the new stack is the GH Actions workflow itself. Swapped the inbound-from-provider webhook for an outbound-from-CI curl: renamed the controller to `DeployWebhookController`, renamed the route (`/api/webhooks/railway/{token}` -> `/api/webhooks/deploy/{token}`) + route name (`webhooks.railway` -> `webhooks.deploy`), renamed the config key (`services.railway.webhook_secret` -> `services.deploy.webhook_secret`) and env var (`RAILWAY_WEBHOOK_SECRET` -> `DEPLOY_WEBHOOK_SECRET`). The controller itself got simpler in the move - Railway's payload needed `type`/`details.status` gating to ignore non-success events, whereas GH Actions only hits the endpoint in a step that runs after `kamal deploy` has already succeeded, so the body is just `{sha}` and the controller just broadcasts.
- `.github/workflows/deploy.yml` got a final "Notify app of successful deploy" step that POSTs to `https://overlabels.com/api/webhooks/deploy/${DEPLOY_WEBHOOK_SECRET}` with the GitHub SHA. Because GH Actions steps default to `if: success()`, this only fires when every preceding step in the job succeeded - listener image builds, `kamal deploy`, all of it. The `concurrency: deploy-prod / cancel-in-progress: false` already in place on the job means two pushes queue rather than overlap, so no racing webhooks. `DEPLOY_WEBHOOK_SECRET` is added to Kamal's secret list in `config/deploy.yml` (so the container sees it when `config('services.deploy.webhook_secret')` reads it) and to `.kamal/secrets.example` (for local `kamal deploy` from the laptop - both places need the same value).
- One subtlety worth flagging for future-me: `kamal deploy` returns success as soon as containers are up and passing Kamal's readiness check, which happens before any post-boot work (queue migrations, cache warming) finishes settling. The version-updated broadcast therefore fires slightly ahead of the app being fully warm - fine for a "new version available, refresh when convenient" banner, not fine if we ever repurpose this signal for anything that assumes post-deploy state is already consistent.

- Railway has bitten us with five hundred outages this year (give or take), and a $100 Linode coupon is sitting there waiting. Scaffolded the entire Linode + Kamal 2 deploy so we can flip the switch as a discrete step rather than a marathon. Target: a single Linode Shared 4 GB VM running one FrankenPHP-based Laravel image across four Kamal roles (web / queue / scheduler / reverb), with Postgres + Redis + the two Socket.IO listeners + the Twitch bot all as Kamal accessories on the same host. Domain stays `overlabels.com` / `ws.overlabels.com` so Twitch OAuth redirects and EventSub callbacks don't need to change - DNS flip is the only inbound-facing change at cutover.
- New `Dockerfile` is a three-stage build: `node:22-alpine` builds the Vite assets, `composer:2` resolves the no-dev vendor tree, and `dunglas/frankenphp:1-php8.4` is the runtime. Extensions installed via `install-php-extensions` (pcntl, pdo_pgsql, redis, intl, gd, bcmath, exif, zip, opcache). FrankenPHP serves `public/` directly via the new `docker/frankenphp.Caddyfile` (auto_https off because kamal-proxy terminates TLS), and `docker/docker-entrypoint.sh` rebuilds the config/route/view/event caches at boot so they reflect the actual env Kamal injects per-role. The web role additionally runs `php artisan migrate --force` via `ENTRYPOINT_RUN_MIGRATIONS=1`. Queue/scheduler/reverb roles share the same image and override `cmd:` in `config/deploy.yml`.
- `config/deploy.yml` is a full Kamal 2 spec: four roles with role-specific cmd + env, two `proxy:` blocks (one for `overlabels.com` on the web role, one for `ws.overlabels.com` on the reverb role with WS-friendly buffering disabled), Postgres and Redis accessories with named volumes and memory caps, and the two listener accessories pointing at GHCR-hosted images that the deploy workflow builds in parallel. `docker/postgres-tuning.conf` keeps Postgres at ~768 MB on a 4 GB box (shared_buffers 256MB, max_connections 50). `.kamal/secrets.example` is the committed template; `.kamal/secrets` is gitignored.
- `.github/workflows/deploy.yml` runs on every push to `main`: builds + pushes the two listener images to GHCR with GitHub Actions cache, then runs `kamal deploy` which builds + pushes the main app image and rolls all four roles. SSH key, registry password, and all Laravel/Reverb/Twitch/StreamLabs/StreamElements/Cloudinary secrets are sourced from GH Actions repo secrets and injected into the Kamal env at deploy time.
- Removed: `Dockerfile.reverb` (the Railway main-app shim - obsoleted by the unified FrankenPHP image), `Procfile` (Kamal roles supersede), `build-app.sh` and `run-worker.sh` (Railway/Heroku-style helpers nobody calls outside of Railway). Listener Dockerfiles `Dockerfile.streamlabs-listener` and `Dockerfile.streamelements-listener` stayed put - they already work and the Kamal accessories reference them via the GHCR-built images.
- Tweaks alongside: `bootstrap/app.php` lost the SIGINT/SIGTERM/SIGTSTP pcntl shim that existed solely because Railway's containers were missing pcntl - the FrankenPHP image has pcntl, the shim is dead weight. `vite.config.mts` extended its commit-hash fallback to read `APP_COMMIT_SHA` (clean name) before `RAILWAY_GIT_COMMIT_SHA` (back-compat). `.dockerignore` rewritten to exclude tests/docs/IDE noise/storage caches/backup dumps so the image stays small. `.env.example` notes that production needs `SESSION_DOMAIN=.overlabels.com` so cookie-auth private channels work across the apex and the `ws.` subdomain. `.gitignore` now ignores `.kamal/secrets`.
- Bot repo (`jasperfrontend/overlabels-bot`) got its own `Dockerfile` (tiny `node:20-alpine`, prod-only deps), `config/deploy.yml` for a SEPARATE Kamal project on the SAME VM (no proxy block - bot only does outbound Twurple WebSocket + HTTPS to the main app), and a matching `.kamal/secrets.example`. Kamal-proxy is a singleton per host and cheerfully multiplexes multiple services as long as their `service:` names differ.
- Nothing in this commit goes to production. The whole deploy stack is configurable but not yet pointed at a real VM (`<VM_IP>` placeholder throughout `config/deploy.yml`). Next step is provisioning the Linode, populating `.kamal/secrets` from current Railway env vars, dry-running `kamal setup`, and dumping/restoring Postgres into the new VM. Migration plan with the full step-by-step lives at `~/.claude/plans/hey-claude-after-outage-streamed-nygaard.md`.

## April 22nd, 2026 - Event list totals for progress bars

- Stress-tested `channel.poll.progress` with 50 back-to-back CLI triggers hitting a 2-minute alert window - the overlay redrew every payload cleanly, which was the green light to add the one thing missing for poll-progress alerts: a tally so templates can render progress bars. Previously `event.choices.count` gave the denominator for "how many choices" but not "how many votes across all choices", so there was no way to compute each choice's share without either a per-overlay Expression Control or some frontend-side arithmetic. Added a generic sum-across-items pass in `TemplateDataMapperService::mapEventDataForTemplates()` that sits inside the existing `INDEXED_LIST_FIELDS` branch: for each item in the list, any numeric subkey gets summed and emitted as `event.<field>.total_<subkey>`.
- For `channel.poll.progress` / `channel.poll.end` this produces `event.choices.total_votes`, `event.choices.total_channel_points_votes`, and `event.choices.total_bits_votes`. For `channel.prediction.progress` / `lock` / `end` it produces `event.outcomes.total_users` and `event.outcomes.total_channel_points`. Hype train `top_contributions` also benefits as `event.top_contributions.total_total` (awkward name, same mechanism) - consistent enough that future list-shaped events will pick up totals for free without further code.
- Totals sum across the **full** list, not the capped slice - matters for hype train where only top 3 contributors render but the aggregate should include everyone. Non-numeric / object subkeys (like money objects) are skipped, which is the right thing for Twitch's current shapes and leaves room for future fields without breakage. Added the new tags to the Poll Progress / Poll End / Prediction Progress / Lock / End cards on `/help/conditionals` and to the allowlist returned by `getAvailableEventTags()`. Existing templates unaffected - purely additive.

## April 22nd, 2026 - Enrich Twitch user arrays with profile_image_url + jsonb `twitch_data`

- `followed_channels`, `channel_followers`, and `subscribers` are the three Twitch arrays where every row references a *different* Twitch user (broadcaster, follower, subscriber), and Helix stubbornly doesn't include that user's avatar in any of the three responses. Added a shared enrichment pass in `TwitchApiService::enrichWithProfileImages()` that walks the `data` rows of each response, collects the referenced user ids, and batch-resolves them against `GET helix/users?id=...&id=...` (up to 100 per call, so the 20-row default fits in a single extra request). Each row gets a `<prefix>_profile_image_url` field alongside the existing id/login/name triple: `broadcaster_profile_image_url` on follows, `user_profile_image_url` on followers, `user_profile_image_url` + `gifter_profile_image_url` on subs. Empty gifter ids stay empty strings so ungifted rows don't incur a spurious lookup.
- New `TwitchApiService::getUsersInfo(accessToken, ids[])` is the batch primitive - chunks ids into groups of 100, dedupes/strips empty strings, returns a `[twitch_id => user]` map for O(1) enrichment lookup. The enrichment runs inside the three collection getters (before caching) so the Laravel cache, the `/twitchdata` payload, and the `users.twitch_data` column all see the enriched shape without any extra plumbing. Existing caches need one `cache:clear` or a "Refresh" click per collection after deploy; the `users.twitch_data` row only rewrites on login (the OAuth callback is the sole writer), so stored snapshots catch up when each user next signs in.
- `users.twitch_data` converted from `json` to `jsonb` in the same batch. Single-statement `ALTER COLUMN ... TYPE jsonb USING twitch_data::jsonb` migration, gated on `pgsql` driver and column existence so sqlite test envs short-circuit cleanly. `jsonb` costs slightly more on write but reads faster and gains index/operator support (`@>`, `->`, `->>`, GIN); the two downsides - loss of key-order preservation and duplicate-key deduplication - don't matter for a modelled snapshot. Left `twitch_scopes` as plain `json` - it's a 200-byte scope list with no querying needs.

## April 22nd, 2026 - User-scope foreach + preferences jsonb column

- `[[[foreach:X as Y]]]` shipped yesterday against event lists (poll choices, prediction outcomes, hype-train contributors). Tried to use it against the four non-event array collections - `subscribers`, `goals`, `channel_followers`, `followed_channels` - and nothing rendered. Turned out the `GenerateTemplateTags` job wrote those arrays into the `template_tags` catalog but the runtime overlay payload came from `TemplateDataMapperService::mapForTemplate`, which only mapped the single `data.0.*` entry of each array into scalar "latest_*" flat keys. There was no indexed data for foreach to iterate over. Extended `TemplateDataMapperService` with a `buildUserScopeIndexedKeys` pass that emits `subscribers.{i}.{key}` / `channel_followers.{i}.{key}` / `goals.{i}.{key}` / `followed_channels.{i}.{key}` plus `.count` for each collection, alongside the existing scalar mapping. `OverlayTemplate::extractTemplateTags` now accepts a user-caps override and merges it with the hardcoded Twitch-limit event caps (choices=5, outcomes=10, top_contributions=3), so a `[[[foreach:subscribers as s]]]` block expands to the right allowlist entries for the user's configured cap.
- Sensible defaults can't be my defaults: one streamer wants to list 20 recent followers on-screen, another wants to list 3. Added 4 per-user caps to the new "Foreach loop limits" card on `/settings/appearance` (subscribers/goals/followers/followed) with defaults 10/3/5/5 and a hard ceiling of 50 per loop. The cap controls both how many entries the server flattens into the payload AND how many the extractor writes into the allowlist - same number at both points so no empty trailing iterations.
- New `users.preferences jsonb NOT NULL DEFAULT '{}'` column replaces the standalone `users.locale` column. Single migration backfills `preferences->>'locale'` from the old column and drops it; the `down()` reverses all three steps. Future user-scoped settings (custom locale format strings, per-overlay defaults, whatever) can land under `preferences` without another schema change. `User::preference($key, $default)` and `setPreference($key, $value)` are the accessor pair - both support dot-notated nested keys via Laravel's `data_get` / `data_set`. `$user->locale` still works via an accessor that reads `preferences.locale`; `$appends` surfaces it so existing `page.props.auth.user.locale` callers keep working without frontend changes. Exposed `$user->foreach_caps` the same way so the settings UI can bind to it without a separate endpoint.
- `OverlayTemplateController` threads `$user->foreachCaps()` into both `extractTemplateTags` (render/store/update - 3 callsites) and `mapForTemplate` (render only). `RefreshTemplateTags` artisan command eager-loads `owner` to avoid an N+1 during batch refreshes. Full test suite coverage: `UserPreferencesTest` (9 cases covering accessors, defaults, clamping, clobbering), `TemplateDataMapperForeachTest` (4 cases covering indexed emission, cap behavior, missing-data fallback), `SettingsForeachCapsTest` (6 cases covering PATCH validation, preservation of sibling prefs, the locale route rewrite), plus 3 new `TemplateTagExtractionTest` cases covering user-scope expansion and fixed event caps.
- Help page updated: added an "Iterable collections" table on `/help/conditionals` listing all 7 iterables (3 event-scope, 4 user-scope) with their scope and cap source, plus a note that `[[[alias.count]]]` shows the real total even when the cap truncates the iterated items.

## April 22nd, 2026 - Foreach loops in the template DSL

- Polls and predictions ship indexed flat keys (`event.choices.0.title`, `event.choices.1.title`, ...) plus an `event.choices.count`. Before this change, a template that wanted to list every choice had to spell out one `[[[if:event.choices.count = N]]]` branch per possible count - the repetition was bad enough that it motivated rewriting the DSL. The parser now recognises `[[[foreach:event.choices as choice]]] ... [[[endforeach]]]`, scoped to the loop body. A 20-line if/elseif/endif ladder collapses to 3 lines.
- Inside the body you get the aliased item (`[[[choice.title]]]`) plus three loop-metadata tokens: `[[[loop.index]]]` (zero-based), `[[[loop.first]]]` / `[[[loop.last]]]` (booleans, useful with `[[[if:...]]]`), and `[[[loop.count]]]` (total). Nested `[[[foreach]]]` and nested `[[[if]]]` work normally; the existing depth-10 guard covers nesting across both constructs. Iteration data resolves from flat dotted keys (the existing server-side shape) or from actual nested arrays, whichever is present.
- All implementation sits in `useConditionalTemplates.ts` - same token-scanner architecture that powers `[[[if]]]`, with `findMatchingEndforeach` mirroring `findMatchingEndif`. Scoped tokens (`alias.*`, `loop.*`) resolve during iteration so they don't leak into the outer tag-substitution pass; HTML encoding and pipe formatters still apply, so user-sourced values like a poll choice title get the same XSS defenses as any other substituted value. `OverlayTemplate::extractTemplateTags` expands each `[[[foreach:X as Y]]]` block to the concrete indexed keys its body references (`event.choices.0.title` through `event.choices.4.title`, etc.) so the server-side data mapper includes them in the overlay payload; scope-local aliases are stripped from the final tag list.
- Documented on `/help/conditionals` alongside the existing conditional syntax, with a usage example that mixes `[[[foreach]]]`, `[[[if:loop.first]]]`, and `[[[loop.index]]]`. Existing conditional-only templates keep working - `foreach` is purely additive.

## April 22nd, 2026 - Document Ko-fi's shared control schema on the conditionals page

- Ko-fi, StreamLabs, and StreamElements auto-provision the exact same six control keys (`donations_received`, `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`). Previously the conditionals page documented the `[[[c:streamlabs:...]]]` and `[[[c:streamelements:...]]]` variants but not the `[[[c:kofi:...]]]` equivalents, even though they have been valid since the integration shipped. Added a Ko-fi Auto-provisioned Controls card to the conditionals page that mirrors the StreamLabs and StreamElements ones.
- All three control cards now carry a cross-reference info note calling out the unified schema: streamers can swap the prefix (`c:kofi:`, `c:streamlabs:`, `c:streamelements:`) without editing anything else, so the same alert template works across all three donation services.

## April 22nd, 2026 - Conditionals help page: filter bar + full EventSub + StreamElements coverage

- The `/help/conditionals` page had two problems at once: it was a 729-line static HTML wall with no way to narrow it down, and it hadn't kept up with the recent EventSub expansion or the StreamElements integration. Rewrote it as a data-driven Vue page (~30 event/integration cards in an array, rendered in a loop) with a search input and a row of family filter chips across the top (same filter pattern as `ControlsManager.vue` and the new `/testing` page). Each chip shows the family's card count and highlights when active; clicking a chip or typing in the search box narrows the visible set live.
- Added per-event documentation for every new EventSub type: `channel.update` (stream info change), hype train (begin/progress/end with `event.top_contributions.*`, `event.last_contribution.*`, `event.total.formatted`), charity campaigns (donate/start/progress/stop with `event.amount.formatted`, `event.current_amount.formatted`, `event.target_amount.formatted`), channel goals (begin/progress/end), polls (begin/progress/end with `event.choices.0.*` through `event.choices.4.*` plus `event.choices.count`), and predictions (begin/progress/lock/end with `event.outcomes.0.*` through `event.outcomes.9.*` plus `event.outcomes.count`, `event.winning_outcome_id`). Each card documents which tags to USE and which to avoid (`v` raw minor-unit money fields, list fields that JSON-encode, etc.), with a realistic template example most streamers will recognize.
- StreamElements now has its own section, duplicated 1-to-1 from the existing StreamLabs section with every `[[[c:streamlabs:...]]]` swapped to `[[[c:streamelements:...]]]` (both integrations auto-provision the same 6 controls, so the docs map cleanly). Added an SE-specific note covering the JWT rotation UX - if the token gets revoked in the SE dashboard the listener starts erroring and the streamer has to paste a fresh JWT on the settings page.
- Preserved the static Conditional Template Syntax section (boolean / numerical / string / event-based / nested conditionals) and the Tips & Best Practices block verbatim - those describe syntax, not events, and didn't need rewriting. Also preserved the special gift-bomb-detection example on the `channel.subscription.gift` card as a `specialBlock` (the only card that renders extra content beyond tag tables).

## April 22nd, 2026 - Add channel.update (stream info change) EventSub

- `channel.update` v2 fires whenever the streamer edits their title, category, language, or content-classification labels. A lot of streamers want a subtle "now playing $game" chyron the moment the category changes mid-stream, and this is the EventSub type that backs it. Registered in `UserEventSubManager::SUPPORTED_EVENTS` with `required_scope => null` (the event is public broadcaster info, no Twitch scope to gate on) - meaning every already-connected user picks it up for free on the next setup pass.
- Wired through the same event-type-agnostic path everything else uses: added the label ("Stream Info Updated"), slotted it into `EventTemplateMapping::EVENT_TYPES` so the builder page renders it under Twitch Events, and extended `refreshCachesForEvent()` to clear the channel-info cache alongside the existing `stream.online` / `stream.offline` branch (so the next render of `[[[stream.title]]]` etc. hits fresh Helix data instead of a stale snapshot).
- `/testing` page gained a `channel.update` entry in the Stream family so streamers can fire the CLI trigger (`twitch event trigger stream-change`) to see the alert flow. Existing `eventsub:backfill-goals` command is already generic - re-run it on deploy and every connected user gets the new subscription without needing to reauthorize.

## April 22nd, 2026 - Testing page covers the full EventSub arsenal

- The `/testing` page predated the EventSub expansion and only listed 8 triggers. Bumped to 27: the original 8 (follow/sub/gift/resub/cheer/raid + stream online/offline + the two channel-points redemption variants) plus the 17 new hype train / charity / goals / polls / predictions types added yesterday. Each trigger carries the exact `twitch event trigger` command a streamer can paste into the CLI, pre-filled with their webhook URL, their per-user webhook secret, and their Twitch ID.
- First pass grouped events by family with a pre-rendered command block per row - turned out to be overkill. Slimmed down to a single flat clickable list: one row per event with label, event-type code, family tag, and description. Clicking a row copies the full unredacted command to the clipboard. A "Show command" toggle reveals the pre-filled command as a read-only input under each row for users who want to eyeball what they're about to paste; clicking the input copies too.
- Search filter bar at the top matches against label, event type, description, and family (same pattern as `ControlsManager.vue`'s Filter Controls). Kept the warning strip reminding streamers not to paste these commands into a terminal while on-camera - the clipboard contains the real webhook secret regardless of whether the preview input is visible.

## April 22nd, 2026 - Scope banner copy no longer lies about the session

- The banner claimed "Reauthorizing takes a few seconds and does not sign you out." In practice the Twitch -> callback round trip does appear to drop the Laravel session for some users (DB state update succeeds, but the browser lands on a logged-out overlabels.com and has to relog manually). Until that session loss is root-caused, honest copy is better than confident-sounding copy - the updated string warns users they may need to log in again and reassures them the new permissions persist regardless.

## April 21st, 2026 - Twitch EventSub: hype train, charity, goals, polls, predictions

- Added 17 new EventSub types in five families: hype train (begin/progress/end), charity campaigns (donate/start/progress/stop), channel goals (begin/progress/end), polls (begin/progress/end), predictions (begin/progress/lock/end). `UserEventSubManager::SUPPORTED_EVENTS` gained a `required_scope` field per entry and is now the single source of truth for which scope a given event needs; `setupUserSubscriptions()` skips (and buckets under `skipped_missing_scope`) any event whose scope the user hasn't granted, so Twitch 403s don't pollute the `failed` bucket.
- `TemplateDataMapperService::mapEventDataForTemplates()` got indexed-list flattening for the three payload shapes that ship lists of objects: `top_contributions` (capped at 3), `choices` (5), `outcomes` (10). Each emits `event.{field}.count` plus `event.{field}.{i}.{key}`, so templates can read `[[[event.choices.0.title]]]` or `[[[event.top_contributions.1.user_name]]]` instead of getting a JSON-encoded blob. Twitch's `{value, decimal_places, currency}` money objects on `amount`, `target_amount`, `current_amount`, `total`, and `last_contribution` now emit a derived `event.{field}.formatted` string that divides by 10^decimal_places and adds a currency symbol for USD/EUR/GBP/JPY (falls back to `EUR 15.23` style for others). Tag registry in `getAvailableEventTags()` and `getTagCategories()` extended so the template editor's tag picker surfaces the new fields.
- `EventTemplateMapping::EVENT_TYPES` gained the 17 entries grouped by family (the Vue page renders in iteration order, so grouping is purely visual). `refreshCachesForEvent()` clears goals caches on `channel.goal.*` events; everything else already flows through the event-type-agnostic `handleTwitchEvent()` -> `renderEventAlert()` path, so webhook handling needs zero new code per type.
- New artisan command `eventsub:backfill-goals` (with `--dry-run`) re-dispatches `SetupUserEventSubSubscriptions` for every connected user. `channel:read:goals` is already in the legacy scope set, so every user gets the three new goal subscriptions for free without having to reauthorize. The setup path is idempotent (existing subs short-circuit), so re-running is safe.

## April 21st, 2026 - Detect stale Twitch scopes and prompt reauthorization

- Added `users.twitch_scopes` (nullable JSON). Null = legacy user (authenticated before the column existed); array = authoritative list captured from Twitch. `TwitchScopeService` centralizes the bookkeeping: `REQUIRED_SCOPES` (12 scopes the platform currently asks for), `LEGACY_SCOPES` (the 8 scopes every pre-migration user implicitly carries), `EVENT_TYPE_TO_SCOPE` (17 entries so `UserEventSubManager` can check before subscribing), plus `getUserScopes()`, `getMissingScopes()`, `hasScope()`, and a static `sanitizeScopeList()` that handles Twitch's array-or-space-string + empty-string weirdness.
- OAuth callback (`/auth/callback/twitch`) now persists `$twitchUser->approvedScopes` via `sanitizeScopeList()`. On update, the empty-array case is guarded - Socialite sometimes returns an empty scope list on token-only refreshes, and blowing away the stored scopes would falsely trigger the stale-scope banner. `TwitchTokenService::refreshUserToken()` captures Twitch's `scope` on refresh too and logs a warning if the scope set shrank between tokens.
- `/auth/redirect/twitch` now pulls its scopes from `TwitchScopeService::REQUIRED_SCOPES` (single source of truth instead of a hardcoded list) and adds 4 new scopes: `channel:read:hype_train`, `channel:read:charity`, `channel:read:polls`, `channel:read:predictions`. A `?reauth=1` query param sets `force_verify=true` on the Socialite redirect, forcing Twitch to re-prompt even when a token is still valid. The callback compares pre/post missing-scope sets and, if the reauth just unlocked one or more scopes and `eventsub_auto_connect` is on, re-dispatches `SetupUserEventSubSubscriptions` so newly-grantable subscriptions get created without the user having to click anything.
- `HandleInertiaRequests` shares `twitchScope.missing` lazily on every request. New `ScopeUpdateBanner.vue` mounts in `AppSidebarLayout` alongside the existing lockdown/impersonation banners - shows a yellow warning bar listing which feature families are locked and linking to `/auth/redirect/twitch?reauth=1`. The banner reads the missing-scope list and maps each scope to a user-facing family label ("Hype Train", "Charity", "Polls", "Predictions") rather than dumping raw scope strings on the streamer.
- Feature tests: `EventSubExpansionTest` covers scope fallback for legacy users, `getMissingScopes()` emptiness when fully granted, `sanitizeScopeList()` edge cases, indexed-list flattening for `top_contributions` / `choices`, and the charity `amount.formatted` derivation.

## April 21st, 2026 - Chat Castle: room builder gets a CSS filter + overlay layer

- Author-controlled recoloring for painted rooms. The original nudge was noticing that slapping `filter: hue-rotate(180deg)` on the builder's grid wrapper instantly gave the room a completely different palette. Rather than committing to a single filter, expose it as room-level state so each room can opt into its own mood without new tile assets.
- Three new inputs in the builder sidebar under Paint/Erase: a free-form CSS Filter string (e.g. `hue-rotate(180deg) saturate(1.2)`), a color picker for an overlay tint, and an opacity slider for that tint. Preview updates live while typing, both in the left-hand tile browser and on the grid canvas, so the author sees the same thing the player will see.
- `RoomBuilderController::save` picks up three optional top-level fields (`filter`, `overlayColor`, `overlayOpacity`) and persists them into `resources/js/rooms/{N}.json`. Filter is validated with a whitelist regex that only allows the characters CSS filter functions need - no quotes, semicolons, or braces - so nothing authored in the builder can escape into other CSS rules. Color is `#rrggbb`/`#rrggbbaa` hex only, opacity is clamped 0-1.
- `themes.ts` threads the new fields from the painted JSON through `themeFor()` onto `RoomTheme.layout`. `live.vue` reads them into CSS custom properties on `.grid` (`--room-filter`, `--room-overlay-color`, `--room-overlay-opacity`).
- Rendering uses a two-layer split inside every `.tile` so items stay untinted. `.tile::before` paints the floor image with the filter applied; `.tile::after` paints the overlay color with the opacity. Sprites, glyphs, coords, and the separate `.zombies-layer` sit above both pseudo-elements with higher z-indices, so the filter only recolors the floor - the player scarecrow, pickups, and zombies render in their original palette. Floor image URL moved from `background-image` on `.tile` into a `--tile-floor` CSS var so `::before` can consume it without duplicating the inline style.
- Empty filter string and zero opacity both short-circuit to `none` / fully transparent, so rooms authored before this change keep rendering identically. The overlay `<div>` in the builder is `v-if="overlayOpacity > 0"` for the same reason - no empty tinted div when it isn't being used.

## April 20th, 2026 - Chat Castle: web control panel at /gamejam/admin

- The three `gamejam:*` artisan commands (start, end, debug toggle) worked fine for me running locally, but every other bot-enabled streamer needed to ping me to start or end a game. The goal of the panel is to remove that dependency - streamers should be able to run their own session without shelling into anything.
- `GamejamAdminController` replicates the mutations from `GamejamStart`, `GamejamEnd`, and `GamejamDebug`. Scope is intentionally narrower than the commands: the panel operates on `$request->user()`'s own active game, not an arbitrary login. 403 if the logged-in user isn't `bot_enabled`. Commands are untouched - they still resolve any login via `--login` for dev flexibility.
- Logic is duplicated between the commands and the controller (roughly 15 lines of `Game::create` / `->update` / cache writes). Considered extracting a `GameLifecycleService` up front, decided against it - with only two call sites the abstraction would be heavier than the duplication. If a third caller appears (queue job, webhook, whatever), extract then.
- Vue page at `resources/js/pages/gamejam/admin.vue` uses `AppLayout`, has four sections: current game status (id, round, HP, round duration, live badge), start form (HP + round duration inputs, disabled while a game is running), end form (won/lost radio, disabled when no active game), debug panel toggle (always active, reflects current cache state).
- Flash messages use the project's `{message, type}` shape via `HandleInertiaRequests`, not my initial `{success, error}` draft. Single flash banner with conditional color classes instead of two separate v-ifs.
- Route is under `auth.redirect` middleware, grouped as `gamejam.admin.*` with subroutes `index`, `start`, `end`, `debug.toggle`. No admin role required - a streamer with `bot_enabled = true` can fully control their own game.

- Follow-up to the stationary lunge commit, same day. The keyframe animation correctly retriggered every turn - but since an adjacent zombie attacks every turn, the zombie would pull back, thrust past the tile edge, settle, repeat... for as long as the player stood still. User described it, accurately, as the zombie humping the player to death. The wind-up-and-lash-out motif is right for the opening beat of an engagement; it is very wrong as a loop.
- Fix is frontend-only in `lungeModeFor`. `syncZombieViews` now captures `zombieViews.value` before overwriting it with the snap phase, and passes the prior view into `lungeModeFor`. Stationary lunges only fire if the zombie's previous `lungeMode` was `'none'` (fresh engagement). If the prior mode was `'moving'` or `'stationary'`, the engagement is already visually established - the pulsing idle animation plus the player's falling HP bar carry the ongoing-attack reading, no new thrust needed.
- Moving lunges are unchanged and always fire: if the zombie moved, the attack is visibly at a new spot, so the short ease-out tween always makes sense.
- Reset behavior is implicit. If the player moves away, the zombie becomes non-adjacent and `lunged_this_turn` flips to `false` on the server. Next turn the frontend sees `lungeMode: 'none'`, and whenever the zombie re-engages, the prior state is `'none'` again - so the opening lunge fires fresh. No extra backend state needed; the existing flag plus last-frame comparison is enough.
- Edge case accepted: reloading the page mid-engagement will replay the stationary lunge once, because `prior[z.id]` is undefined. Acceptable - reload is already disruptive and a single extra lunge frame is a small cost.
- ESLint clean, all 13 zombie tests still pass.

## April 20th, 2026 - Chat Castle: stationary zombies wind up and lash out

- Follow-up to the earlier lunge commit. That one only fired for zombies that *moved* into their adjacency. A zombie already adjacent to the player on the previous tick would deal damage with no visual cue at all - just the pulsing idle loop while the HP bar dropped. User wanted a distinct animation for that case: elastic wind-up in the opposite direction, lunge forward past the tile edge, then settle back.
- Backend: dropped the `$moved` gate in `ZombieTurnResolver` so every adjacency-damage contributor gets `lunged_this_turn = true`. Added `facingToward()` helper and force `$z->facing` toward the player at attack time - stationary adjacent zombies weren't reliably facing the player (chase/drift only update facing on a successful move), so without this the lash-out could aim the wrong way. Bumped zombies still skip (they already hit via `ActionApplier`).
- Frontend: `lungeMode: 'none' | 'moving' | 'stationary'` replaces the simple boolean on `ZombieView`. Moving lunges keep the short ease-out tween; stationary lunges leave the outer tile transform alone and play a 450ms keyframe animation on the inner `.zombie-body`. Four direction-specific `@keyframes` (up/down/left/right) translate the body to +12% (wind-up, opposite to facing), then to -45% (past the tile edge toward the player), then back to 0. Specificity beats `.zombie-chasing`'s pulse loop during the animation; the pulse resumes naturally when the class is removed next tick.
- Snap-then-rAF pattern in `syncZombieViews` retriggers the CSS animation each turn: snap sets `lungeMode: 'none'` (removing the class), rAF sets the real mode (adding it back), which is what CSS needs to re-fire a keyframe animation with the same name.
- All 13 gamejam tests still pass, ESLint clean.

## April 20th, 2026 - Chat Castle: zombies lunge into their damage-dealing tile

- Visual issue observed in live play: when a zombie drifted to the tile directly adjacent to the player, the damage was applied pre-movement (per design) but the zombie was still mid-drift through the tween. Readers saw a zombie "one tile away" losing the player a heart with no contact, because the linear drift across the full round duration had not visually landed yet. The underlying resolver order is correct and deliberate, so the fix is purely presentational.
- Backend: added `lunged_this_turn` boolean to `game_zombies` (migration + `$fillable`/`$casts`). `ZombieTurnResolver::resolve` clears the flag on every zombie in the current room at the top of the turn, and flips it to `true` only when the zombie contributed to adjacency damage AND actually moved this turn (`prev_x/y !== x/y`). Zombies that were already adjacent last round and stood still keep drifting normally - no ghost lunge-in-place. Bumped zombies (already skipped for adjacency damage) are unaffected. `GameStateChanged::snapshotFor` includes the flag in the broadcast payload.
- Frontend: extended `ZombiePayload` and `ZombieView` with the new flag. `syncZombieViews` uses a 180ms `cubic-bezier(0.2, 0.8, 0.3, 1)` ease-out tween for lunging zombies instead of the usual full-round linear drift. Non-lunging zombies in the same broadcast keep their slow drift untouched - only the attacker snaps forward, which reads as a lash-out instead of a creep.
- All 13 gamejam-adjacent tests still pass; ESLint clean on `live.vue`. No gameplay logic changed.

## April 20th, 2026 - Chat Castle: painted rooms now render in live.vue

- Follow-up to the room builder commit. The painted JSON was sitting unused at `resources/js/rooms/{N}.json`. Wired it into `themes.ts` so the game actually renders what the author painted.
- Added `RoomCell` and `RoomFile` TS types alongside the existing `RoomLayout`. `RoomLayout` now has `grid` and `tiles` as optional (were required) plus a new optional `cells` field, so the existing GRAVEYARD ASCII-grid layout stays valid and painted rooms layer on top without a breaking rewrite.
- `themes.ts` eagerly loads all `resources/js/rooms/*.json` via `import.meta.glob('../../rooms/*.json', { eager: true })` and builds a `PAINTED_ROOMS` lookup keyed by room number (parsed from the filename, so filename is the source of truth even if the JSON omits the `room` field). Vite HMR re-imports on save, so painting in the builder reflects in the running game without a manual reload.
- `themeFor(room)` now merges the painted cells into the returned theme's `layout.cells` if a JSON room exists for that number.
- `floorFor(theme, x, y)` gets a new top-precedence branch: if a painted cell has `bg`, use it. If the cell exists but has no `bg` (author hasn't painted it yet), fall through to the ASCII grid layout (if any), then the hash-based random floor. This lets the author paint partial rooms without breaking unpainted cells.
- Production build passes (15.4s), vue-tsc clean, ESLint clean. Did not click through a live game to visually confirm the painted tiles render - user will observe on next live session.

## April 20th, 2026 - Chat Castle: dev-only room builder (paint + save)

- The existing `themes.ts` had tile layouts stored as an ASCII-grid string where each char (letters, numbers, punctuation) mapped to a PNG path via a large flat `tilesObject` lookup. One room in, already unmaintainable: to place even a single tile the author had to scroll to the lookup table, find the char that corresponded to the wanted edge/corner variant, then come back and remember which char meant what. Four more rooms to go with their own tilesets made the trend obvious - the grid string had zero semantic content, just pixel hashes.
- Considered autotiling (paint grass/dirt/water codes, code picks the transition tile from a neighbor bitmask) and Tiled editor (industry-standard visual tool, JSON export). User rejected external-tool dependency, went with a self-hosted visual builder. Autotiling left on the table for later.
- Built `/dev/room-builder/{room}` at `app/Http/Controllers/RoomBuilderController.php` + `resources/js/pages/gamejam/builder.vue`. Left sidebar is a tile palette reading from `public/rooms/{room}/tiles/` via a manifest endpoint; right side is a paintable grid. Click a tile, click/drag cells, hit Save - JSON is written straight to `resources/js/rooms/{room}.json` and git is the undo stack.
- Triple-gated for safety: Laravel `admin.role` middleware + explicit `abort_unless(app()->environment('local'), 404)` in every action + asset-path allowlist (`/rooms/...` only, no `..` traversal) on the save action. Non-admins and non-local envs both 404.
- JSON shape is layered-ready (`cells[y][x] = { bg, overlay?, trigger?: { sound? } }`) so overlays and entry-triggered sounds slot in without a breaking rewrite. v1 UI only paints `bg`; the other layers are reserved fields.
- Scaffolded `public/rooms/1/{tiles,objects,sounds}/` with a README pointing users at the expected folder layout. `resources/js/rooms/` created with `.gitkeep`.
- Scope intentionally kept to "paint cells and save" per the go-ahead. No undo, no floodfill, no multi-select, no rotate/flip, no overlay editing, no sound triggers. Drag-to-paint kept because cell-by-cell clicking on an 11x11 grid would have been miserable. Wiring the JSON output into `live.vue` is the next step.

## April 20th, 2026 - Donation drivers: decode HTML entities in donor name and message

- Bug caught during StreamElements mock tip: a donation message containing `i haven&#39;t been here` rendered exactly like that in `[[[c:streamelements:latest_donation_message]]]` output. Same dirty string was stored in `external_events.normalized_payload` and in the `latest_donation_message` control value.
- Root cause: StreamElements sends tip `message` and `displayName` with HTML entities pre-encoded (`&#39;`, `&amp;`, `&lt;`). Their dashboard/widgets re-render as HTML so users never notice, but our plain-text consumers (control values + alert broadcasts) leak the entities straight through.
- Fix at the driver boundary in `StreamElementsServiceDriver::normalizeEvent`: `message` and `displayName`/`username` pass through a private `decodeHtml()` helper using `html_entity_decode(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`. Raw payload intentionally left untouched so the audit trail still reflects exactly what StreamElements sent.
- Mirrored the same decode to `StreamLabsServiceDriver` (same widget-rendered ecosystem, likely has the same habit) and `KofiServiceDriver` (docs show plain UTF-8, so this is a defensive no-op for them but keeps all three donation-family drivers uniform - if Ko-fi ever changes their encoding, no second round of debugging).
- Unit tests added to each of the three driver suites covering `&#39;`, `&amp;`, `&lt;` in both `message` and donor-name fields. 74 tests pass across Kofi/StreamLabs/StreamElements unit + feature suites. Existing dirty rows already in the DB are not back-populated; fix applies to new events from here on.

## April 20th, 2026 - Chat Castle: dead zombies stay as corpses on the playfield

- Problem from live play: when the player attacked and killed a zombie, the zombie vanished from the broadcast payload and `advancePlayerTowardKill` popped the player one tile toward the (now-invisible) death spot. Visually this read as an unexplained teleport - no cause-and-effect linking the attack to the player movement. Player reported it as a bug (thought the attack AoE was being "shoved" back into bounds).
- Fix at the data layer: `GameStateChanged::snapshotFor` no longer filters zombies by `active=true` when building the world payload. All zombies in the current room are broadcast, with a new `active` boolean field. Dead zombies still sit on the schema exactly as before (they just were not being sent to the frontend). `ZombieTurnResolver` already queries `active=true` separately, so corpses do not participate in LoS, movement, or adjacency damage.
- Fix at the kill site: `ActionApplier::attackNearestZombie` now freezes `prev_x`/`prev_y` to the death tile alongside `hp=0, active=false`. Without this, the Vue animator would re-slide the corpse from its last pre-death position back to the death tile on every subsequent broadcast (two-phase rAF snap in `syncZombieViews`). Now the body stays put.
- Frontend: extended `ZombiePayload` with `active: boolean`, swapped `zombie-${brain_state}` for `zombie-dead` when `!active` in the class binding, and gated the HP label to live zombies only. Added a `.zombie-dead` block in the existing CSS pile at the bottom of `live.vue` - greyed radial, 80-degree rotation so the body reads as fallen, no pulse, dimmer eye. Slotted next to `.zombie-boss` and `.zombie-weakling` so all the kind/state variants live in one place. No refactor of `live.vue` yet; deferred until after the gamejam when it can happen on its own.
- Corpses persist for as long as the player is in the room. On room advance, `GameStateChanged` filters by `current_room` so corpses from cleared rooms drop out of the broadcast naturally without any explicit cleanup.

## April 19th, 2026 - Chat Castle: hiding now persists across ticks if the player stays put

- Follow-up to the earlier hiding-LoS fix. Symptom reported from actual play: after the player hides (`!h`), the zombie correctly flips to drifting (green) that same tick, but on the very next tick it flips back to chasing (orange) even though the player did not move from the hiding spot. Root cause: `player_hiding_this_round` was reset to `false` at the top of every `ResolveGameRound` transaction and only set back to `true` by an explicit `!h` vote in that same tick. Standing on a hiding spot with no vote (or any non-hide vote) left the flag at `false`, so the zombie's LoS check in the next tick saw the player normally.
- Fix: derive `player_hiding_this_round` from the player's final position inside the `ResolveGameRound` transaction, right after `ActionApplier::apply()` and before `ZombieTurnResolver::resolve()`. If the player's current tile is any of the current room's hiding spots, the flag is `true`; otherwise it is `false`. Matches the help doc wording: "Zombies flip to drifting the moment you are on a hiding spot."
- Removed the now-redundant direct set of `player_hiding_this_round => true` inside `ActionApplier::hide()` - the centralised derivation in `ResolveGameRound` handles it uniformly for all vote types (explicit hide, stay, null vote, attack without movement, etc). Removed the `$wasHidingFromLastRound` reset branch at the top of the transaction for the same reason.
- Tests: two new cases in `ApplyActionTest` - `player standing on a hiding spot stays hidden on the next tick without re-voting hide` (votes `!s`) and `... even with no vote` (no voter at all). Both start with the player already on a hiding spot and expect `player_hiding_this_round` to remain `true` after the tick. Existing `hide is cleared on the following round` case still passes because its `makeWorldGame` setup has no hiding spots seeded, so the derivation correctly yields `false` once the player moves. All 43 cases in `ApplyActionTest` pass, no regressions in `ResolveGameRoundTest` or `RoomSeederTest`.

## April 19th, 2026 - `/help/gamejam` diagram for "orthogonally adjacent"

- Added a small 9x9 CSS-grid diagram inline in the Zombies > "How a zombie actually hits you" section, sitting between the adjacency-damage callout and the same-zombie skip rule. Player in the centre (violet with ring), the four orthogonal tiles filled rose ("hits you"), the four diagonals dashed rose ("does not hit"), rest of the grid neutral. Legend to the right of the grid at desktop width, wraps below on mobile via `flex-wrap`. Small caption under the grid explains the word itself ("orthogonal = on a right-angle axis") for readers who, like the author, had not encountered it before today.
- `adjacencyTiles` computed inline from `Array.from({length: 81})` using `(col-4, row-4)` deltas - one `v-for` covers the whole grid, no 81 hand-written divs. `aria-hidden` on the grid so screen readers skip the decorative pixels and get the legend instead.

## April 19th, 2026 - `/help/gamejam` documents zombie behaviour + tick ordering

- Help page had zero references to zombies; it predates the zombie implementation by a few days. Added a full "Zombies" section between "Rooms, doors, and the exit" and "What is in the chests" so the natural reading order is geography → enemies → loot → combat → consequences.
- New content covers: per-room zombie matrix (counts, HP, damage, flavour note), the two-state brain (chasing vs drifting) with the exact rules for each - including the clockwise drift rotation and the "cannot step onto the player tile" guard that makes chasing zombies stall-and-hit instead of walk-through.
- The core clarification the user asked for: a dedicated "How a zombie actually hits you" block that calls out the two distinct damage moments inside one tick - <strong>bump damage</strong> during step 2 (your action resolves into a zombie tile, you stop and take the hit) and <strong>adjacency damage</strong> during step 3 (after every zombie has moved, each orthogonal neighbour of the player deals damage). The same-zombie skip rule is called out explicitly so players understand bumps cannot double-hit.
- Updated the existing "The tick" ordered list to mention the zombie turn as its own explicit step (previously the list was silent about zombies). This keeps the zombie section's tick-step references pointing at visible anchors on the same page.
- Added a "Killing zombies" sub-block with reach math (fists/regular = reach 1 orthogonal; double-edged = Manhattan 2), zombie damage-per-hit (fists 2, regular 3, DE 4 - higher than the door damage numbers in the existing weapons section, which is why a sword feels so much better in a fight), and the kill-and-advance behaviour. Explicit footer reminds the reader that fists still cost 1 HP per swing and the regular sword still consumes a use when you connect on a zombie.
- Hiding caveat called out in plain language: hiding breaks line of sight completely, zombies drift instead of chase, BUT adjacency damage doubles if a drifting zombie stumbles into the tile next to you - so hiding is only actually safe when nothing is close enough to blunder in. Standalone "+1 HP if you survive the tick untouched" is mentioned here rather than buried.
- No changes to `help/Index.vue` (Chat Castle card description still accurate at the headline level).

## April 19th, 2026 - Chat Castle: fix zombies still chasing hiding players

- `ZombieTurnResolver::hasLineOfSight` had a dead-code hiding-spot check: it tried to mark an interior Bresenham tile opaque if that tile was also the endpoint (player position), but `array_slice` on the Bresenham path already excludes endpoints, so the match could never fire. Net effect: hiding did nothing for LoS and zombies continued to chase hiding players as if they were out in the open.
- Fix: short-circuit `hasLineOfSight` to `false` whenever `$playerHiding` is true. Matches the game's simplification ("you're either on a hiding spot and the zombie cannot see you, or you're not"). Removed the unreachable interior-hiding-spot check. Non-hiding LoS queries (used by the "safe hide → +1 HP" gate to confirm a zombie has no clear sightline at all) still run the full Bresenham + blocker check.
- Zombies still double damage when they end up adjacent to a hiding player (GDD "caught while hiding" rule) - that path doesn't use LoS, just Manhattan-1 adjacency - so the "truly scary" edge case remains: if a zombie drifts into you while you're in a hiding spot, you take 2x damage.
- Tests: replaced the now-impossible `hide with zombie in line of sight doubles damage taken` case with two tighter cases: `zombie drifts instead of chasing a hiding player in clear line of sight` (asserts brain_state stays drifting and the zombie moves by its facing, not toward the player) and `adjacent zombie doubles damage against a hiding player` (zombie trapped by blockers next to the hiding spot, can't drift away, deals 2x damage on its turn). 41 tests pass in ApplyActionTest.

## April 19th, 2026 - Chat Castle: zombies

- Full zombie integration per the GDD's "Zombie behaviour v2": zombies now spawn per room, hold state between rounds, chase the player when LoS is open, drift clockwise against walls when it isn't, and deal damage on adjacency and on player-initiated bumps. Bosses (room 5) and weaklings share the same pipeline and differ only in kind/stats.
- New `game_zombies` table + `GameZombie` model. Fields: `game_id`, `room`, `x`, `y`, `prev_x`, `prev_y`, `facing` (up/right/down/left), `hp`, `max_hp`, `damage`, `kind` (regular/weakling/boss), `brain_state` (drifting/chasing), `active`. `prev_x`/`prev_y` get snapshotted at the start of every zombie turn so the frontend can lerp the token between tiles during the round window without needing tweened server state.
- `RoomSeeder` now seeds zombies per the GDD matrix: room 1 (1 regular, 3HP/1dmg, min dist 3 from spawn), room 2 (1 regular, 4HP/2dmg), room 3 (1 regular, 6HP/3dmg), room 4 (4 regulars, 8HP/4dmg), room 5 (1 boss at center (5,5), 30HP/4dmg, plus 4 corner HP-restore tiles). `seedZombieAtDistance` filters all grid tiles by Manhattan distance and reserved-tile exclusions, falling back to `randomFreeTile` if no candidate meets the distance constraint.
- `ActionApplier` rewired for zombie interactions: stepping into a zombie's tile triggers `applyZombieBump` (player takes zombie damage, does not move, zombie id appended to `bumpedZombieIds`); attacking now resolves nearest zombie first (reach 2 for DE sword, reach 1 otherwise) and only falls back to the door AoE if no zombie is in range; killing blow sets `active=false` and advances the player 1 tile toward the zombie's former position on the greater-delta axis. Damage table: fists 2, regular sword 3, DE sword 4.
- New `ZombieTurnResolver` service: runs after `ActionApplier` inside the `ResolveGameRound` transaction. Snapshots `prev_x`/`prev_y` for every zombie in the current room, then for each zombie runs a Bresenham LoS check - blockers are always opaque; hiding spots are opaque only when the player is on that same spot hiding. If LoS is open, zombie flips to `chasing` and steps on its greater-delta axis (secondary axis as fallback); if blocked, it flips to `drifting` and tries its current facing, rotating clockwise up to 4 times to find a walkable tile (burns the turn if surrounded). After movement, adjacency damage is applied - orthogonal neighbours only, zombies already in `bumpedZombieIds` skipped so bumps don't double-hit. Per GDD, adjacent damage doubles while the player is hiding. If the player hides AND no zombie holds LoS AND no adjacent zombie attacks, the player regenerates 1 HP.
- `ResolveGameRound` eager-loads `zombies` + `blockers`, captures `bumpedZombieIds` from `ActionApplier::apply()`, and reloads `zombies`/`blockers`/`hidingSpots` after the player action so room transitions mid-tick see the correct world before the zombie phase runs. `GameStateChanged` now broadcasts `zombies` in the world payload (active + current-room only) with the fields needed for the lerp: `id`, `x`, `y`, `prev_x`, `prev_y`, `facing`, `hp`, `max_hp`, `damage`, `kind`, `brain_state`.
- Frontend (`resources/js/pages/gamejam/live.vue`): new zombies layer inside the grid. `syncZombieViews()` uses a two-phase requestAnimationFrame - write prev positions with `transition: none`, then in the next frame write current positions with `transition: transform {round_duration}s linear` - so zombie tokens slide from their previous tile to their new tile across the full round window instead of snapping. Kind/brain_state/facing classes drive visuals: green regulars, red pulsing chasers, purple boss, olive weakling; small HP pip bottom-right, facing "eye" pseudo-element per direction.
- Tests: 10 new cases in `ApplyActionTest` (bump non-movement, bump lethal, fist/regular/DE-sword damage, kill-and-advance, door AoE fallback, adjacency attack in zombie phase, hide with visible zombie = double damage, hide fully occluded = +1 HP) and 5 new cases in `RoomSeederTest` (one per room's zombie matrix). All 50 tests in the two suites pass; no regressions in the existing 40 gamejam tests.

## April 18th, 2026 - Help page for Chat Castle + `!castlehelp` bot command

- New `/help/gamejam` page walks viewers through the whole raid: every command (`!join`, `!p <dir> [steps]`, `!h`, `!a`, `!a:2`, `!s`), how the tick works, energy-block mechanics (3 blocks, -1 per skipped round, voting resets to 3, 0 = inactive + pool -1 HP, `!join` to rejoin), room progression (5 rooms, exit tile advances, room 5 = win), chest contents (regular sword, DE sword, iron fists, HP restore, bomb, empty), weapon costs, and the shared HP pool. Copy mirrors `ActionApplier` and `BotGamejamActionController` mechanics exactly so it does not drift from the code.
- Public page - no auth, no plan - uses the existing `HelpLayout` pattern and lives under the main help hub (new card added with the `Swords` icon).
- New bot command `!castlehelp` (tier: everyone). Added to `BotCommand::DEFAULTS` and backfilled to all opted-in streamers via `seed_castlehelp_bot_command` so existing channels get the row without a re-seed. Bot-side handler still needs to land in `overlabels-bot` - thin reply with the canonical URL.

## April 18th, 2026 - Gamejam: debug panel flips live without a page reload

- `gamejam:debug on/off/toggle` now broadcasts a `GamejamDebugToggled` event on the existing `gamejam.{broadcasterId}` Reverb channel after writing the cache. `live.vue` binds `.gamejam.debug` on the same channel it already uses for `.gamejam.state`, so the panel appears/disappears immediately - no refresh, no session reload.
- Frontend-side the `debugEnabled` Inertia prop is still read once on mount to seed a local `debugEnabledLive` ref; the listener mutates that ref on every broadcast. Template gate is `v-if="debugEnabledLive"`. `stopListening('.gamejam.debug')` is paired with the existing unmount cleanup so the binding doesn't leak across SPA navigations.

## April 18th, 2026 - Gamejam: `php artisan gamejam:debug` toggles the live-board debug panel

- New artisan command `gamejam:debug {on|off|toggle} {login?}` flips a per-broadcaster cache flag that gates the temporary tile-class debug panel on the live board. Default broadcaster when no login is passed: the user with an active game (latest by updated_at), falling back to the first bot-enabled user - mirrors the `gamejam:start` resolve convention.
- Storage is `Cache::forever('gamejam.debug.{twitch_id}', true)` / `Cache::forget(...)`; no DB migration, no `.env` restart, flips immediately on the next page render.
- Single source of truth: `GamejamDebug::cacheKey(User)` and `GamejamDebug::isEnabledFor(User)` are static helpers imported by the live route, so the command and the controller never disagree on the key shape.
- `live.vue` gains a `debugEnabled: boolean` Inertia prop and wraps the debug panel in `v-if="debugEnabled"`. When off, the panel is not rendered at all - no leftover orange border, no JSON dump of tile state.

## April 18th, 2026 - Gamejam: autoplay gate on the live overlay

- Browsers (Chrome/Edge/Firefox) block audio until a user gesture on the page, and the gamejam guy runs `gamejam/live.vue` as a bare tab with no prior interaction. Without a gate, the first sound effect the game tries to play is silently dropped, and subsequent ones may or may not recover depending on the browser's policy escalation. Added a detection layer that reads `document.autoplayPolicy` in `onMounted()` and flips `needsAudioUnlock` when the policy is `disallowed` or `allowed-muted`. If the API is undefined (Safari / older Chromium), we also show the gate - one extra click beats a silent failure.
- On click, `unlockAudio()` instantiates (or reuses) an `AudioContext` (with `webkitAudioContext` fallback), calls `resume()` if it's in the `suspended` state, then plays a 1-sample zero-amplitude buffer so the browser marks the context as gesture-initiated. The overlay hides in `finally` so even a thrown error on older browsers still dismisses the UI. The `audioCtx` lives at script scope for the page lifetime, so any future sound-effect code in `live.vue` can reuse the same unlocked context - no second gesture needed.
- UI: fixed-position modal centered over the whole viewport (z-index 9999, backdrop-blur, dimmed background), panel with a short explanation ("Your browser is preventing this overlay from playing sound until you interact with the page.") and a prominent teal button. Single click dismisses. Copy uses hyphens, not em dashes.

## April 18th, 2026 - Per-channel controls-access gate for the Twitch bot

- All controls-manipulation chat commands (`!control`, `!set`, `!increment`, `!decrement`, `!reset`, `!enable`, `!disable`, `!toggle`) are now gated behind a per-user `controls_enabled` flag. Default is **off** - a streamer opting into the bot no longer automatically exposes their controls to chat. The flag is flipped via two new broadcaster-only commands: `!enablecontrols` and `!disablecontrols`.
- Motivation: the bot is currently being used for a 50+ player gamejam channel, and there's no reason chatters there should be able to touch that streamer's overlay counters / booleans / text values. Previously any channel that opted the bot in was wide-open to the full controls API surface. The failure mode has to be **silent**: chat should not discover that the commands exist - when the flag is off, the bot returns no reply at all.
- Transport: new `POST /api/internal/bot/settings/{login}/controls-access` endpoint with body `{enabled: bool}`. `BotSettingsController` validates, resolves the streamer, and calls `$user->setBotSetting('controls_enabled', ...)`. Returns `{reply: "chat control commands are now enabled"}` / `"... disabled"`. Unknown channel returns 404 with `{reply: null}` so the bot silently drops the call.
- Storage: new `users.bot_settings` JSONB column (default `{}`, nullable in migration for existing rows). `User` model gets `getBotSetting($key, $default)` and `setBotSetting($key, $value)` helpers; settings key shape is namespaced-by-feature so later bot features can hang their flags off the same column without another migration.
- Gate: `BotControlController::show()` and `::update()` both now early-return `response()->json(['reply' => null], 403)` if `$user->getBotSetting('controls_enabled', false)` is false. On the bot, `handleApiError()` treats 403 as a silent drop (returns true, no reply sent) - which is what makes the commands feel like they simply don't exist in chat.
- Bot: new `setControlsAccess(login, enabled)` in `overlabelsApi.js`; new `makeControlsAccess(enabled)` handler factory in `commands/handlers.js`; `enablecontrols` and `disablecontrols` registered in the handlers Map. The existing error-handling call sites (`control`, `set`, `makeAdjust`, `reset`, `makeBoolean`) were refactored to share a single `handleApiError(err, reply, context)` helper that owns its own replies and returns a bool for "handled, stop processing" - this is how the 403-is-silent behavior lands uniformly across all controls commands.
- Seeder: `BotCommand::DEFAULTS` gains `enablecontrols` and `disablecontrols` with `permission_level: 'broadcaster'`. Backfill migration `seed_controls_access_bot_commands` ensures existing opted-in users get the two new rows.
- Tests: `BotInternalApiTest` gets 5 new cases (show/update return 403 when flag off, null bot_settings also 403s, setControlsAccess 404 for unknown channel, validation, enable/disable confirmation replies), plus the shared `makeOptedInUser` helper now defaults `controls_enabled=true` so the 40-odd existing controls tests keep passing without individual edits. 56 tests, 132 assertions, all green.

## April 18th, 2026 - Fix: !s never reached BotCommand::DEFAULTS, silently dropped for new streamers

- `BotCommand::DEFAULTS` now includes `['command' => 's', 'permission_level' => 'everyone']` so streamers opting into the bot after this change get the full gamejam command set seeded automatically.
- Backfill migration `backfill_stay_bot_command` runs `firstOrCreate` per opted-in user so existing streamers who missed the original !s rollout pick it up.
- Previously: `!s` routed fine on the backend controller, but the bot's `commandMap.lookup(login, 's')` returned null because no `bot_commands` row existed, so the bot silently ignored the message. The only reason it appeared to work on the one test channel was a manually-inserted row that never got productionized.

## April 18th, 2026 - Gamejam: quieter vote handlers + bot mentions for newly-inactive players

- `BotGamejamActionController::handleVote` now returns `{reply: null}` on accepted votes. The bot's `silentOnSuccess: true` branch kicks in and skips the "ok" reply, so `!p`, `!a`, `!h`, `!s` no longer spam chat with "ok" after every vote. Players can watch the live board to see their action landed. With 50+ players each voting every round that noise was the dominant chat signal.
- End-of-round: `ResolveGameRound` now collects the usernames of every joiner whose `blocks_remaining` dropped to 0 (i.e., got flipped to inactive this tick) and enqueues a single `@alice, @bob, @cindy you became inactive due to lack of input. Type !join if you want to play again next round!` message into a new `bot_chat_outbox` table. The bot polls `GET /api/internal/bot/outbox` every 2 seconds, posts each message to the referenced channel, and the outbox row is marked `sent_at` in the same transaction that claims it (SELECT-lock + bulk UPDATE) so two concurrent polls can't double-send.
- Transport rationale: bot currently only reads from Twitch EventSub and makes outbound HTTP calls - it has no pusher-js / Reverb client. Rather than pull in a websocket stack just for this one message type, we reused the existing pull model. The 1-2s polling lag is fine because the mention fires between rounds.
- Bot changes: `createBot()` now tracks `channelsByLogin` so `sendToChannel(login, text)` can find the right broadcaster context for a given outbox row. Poller lives in `index.js` with a mutex flag (`outboxPolling`) so a slow fetch can't stack up overlapping polls.
- Tests: `ResolveGameRoundTest` gets `enqueues bot mention for joiners flipped to inactive` (asserts exact message format, verifies only blocks-hit-zero slackers get mentioned, not blocks-decremented-but-still-alive ones) and `does not enqueue mention when nobody flipped to inactive`. `BotInternalApiTest` gets three outbox cases: 403 without secret, claim-and-mark-sent, and skips already-sent rows.

## April 18th, 2026 - Gamejam: room progression (1-5) with reseeded layouts per room

- The room-1 exit door no longer ends the game. Stepping through any room's open exit door now runs `RoomSeeder::advanceTo($game, current_room + 1)`, which seeds a fresh layout for the new room and teleports the player to that room's spawn at (5, 9). Only the room-5 exit sets `STATUS_WON`, so a full run is now 5 rooms. HP and weapons carry over between rooms (they don't reset at each boundary - that's the progression incentive).
- `game_doors.is_exit` (boolean, default false) replaces the old "any door on row 1 is the exit" heuristic. `ActionApplier::stepOnce()` now branches on `$door->is_exit` directly; the `isExitDoor()` helper is gone. Every `baseRoom()` call inserts the room's exit at (5, 1) with `is_exit=true`, so the schema is the source of truth and rooms can later add internal doors without tripping the exit branch.
- `game_hiding_spots.open_sides` is dropped (migration + model + frontend type + tests). Hiding spots are now binary "the zombie can't see you here" cover - the directional-cover idea was scrapped as too fiddly for rooms 1-4. Room 5 gets real cover via the new `game_blockers` table (room-tall pillars at (3,3), (3,7), (7,3), (7,7)) which `stepOnce()` treats as permanent walls.
- `RoomSeeder` refactored: `seedRoom1..seedRoom5` each call a shared `baseRoom($game, $room, $exitTurns)` (resets player to spawn + exit-door insert), then layer room flavor: room 1 regular-sword + 1 hiding spot, room 2 DE-sword + 2 hiding spots, room 3 iron-fists + 1 hiding spot + escalated bomb ratio, room 4 just 1 hiding spot with tougher exit door, room 5 pillars + no hiding spots. `advanceTo($game, $n)` is the public dispatcher; `seedRoom1` is still called by `GamejamStart` for game genesis (it also resets weapons/iron-fists to the starting state, which the other seedRoomN methods deliberately skip).
- `GameStateChanged` snapshot: door entries now carry `is_exit`, hiding-spot entries dropped `open_sides`, and the `world` object gains a `blockers` array (filtered to `current_room` like the other world collections).
- Frontend `live.vue` types updated to match (DoorPayload.is_exit, BlockerPayload, WorldPayload.blockers, dropped HidingSpotPayload.open_sides). Blockers render as diagonal-hatched stone pillars via a new `.tile-blocker` class with glyph `#`; `tileAt()` / `tileClasses()` / `tileGlyph()` all consider blockers (priority higher than doors so blockers never read as walkable).
- Tests: new `RoomSeederTest` covers advance + spawn reset + hiding-spot counts + blocker pillars + weapon carry-over vs seedRoom1's weapon reset. `ApplyActionTest` updated: the old "open exit door wins" test now asserts advancement to room 2 with a freshly-seeded exit, a new "room 5 exit wins" test covers the win condition, and a new "blocker blocks movement" test covers the wall semantics. Pre-existing attack-then-walk win and multi-step-exit win tests re-pointed at `current_room=5` so they still exercise the win path.

## April 18th, 2026 - Gamejam polish: !p step range, !s stay, friendly step-range error

- `!p <dir> N` is now limited to 1-3 steps (was 1-8). `!p up 4` returns a 422 with `{reply: "steps must be between 1 and 3"}` that the bot relays verbatim to chat - previously any `steps` over the cap fell through Laravel's default validation JSON, which the bot renders as "something went wrong". The range check now runs after loose integer validation so the error is shaped for chat relay.
- `!s` (stay) is now properly wired end-to-end. The pre-existing short-circuit in the controller returned `{reply: "ok"}` before the game/joiner lookup, so the vote never got stored and never participated in the tally - effectively `!s` did nothing on the wire, not just at resolve. Now routed through `handleVote` with `'s'` encoding; `ActionApplier` falls through cleanly on unrecognized actions, giving `s` the correct "player stays put" semantics.
- `live.vue` readable-vote formatter now renders `'s'` as "stay" in the joiner sidebar and tally entries.
- Tests: controller step-range test re-pointed at values 4 and 0 (was 9 and 0) and now asserts the exact `{reply: ...}` shape. Added `vote_stay stores s` bot controller test and `stay resolves without moving, hiding, or attacking` apply-action test.

## April 18th, 2026 - !h teleports player to nearest hiding spot, hiding flag survives into snapshot

- `ActionApplier::hide()` used to only flip `player_hiding_this_round = true` if the player was already standing on the hiding tile - otherwise it was a no-op. Per the GDD "Auto-moves player toward nearest hiding spot", `!h` now relocates the player to the nearest hiding spot in the current room (Manhattan distance) and sets the hiding flag in one step.
- Found a pre-existing bug along the way: `ResolveGameRound` was clearing `player_hiding_this_round = false` in the same update that bumps `current_round`, which meant the hiding flag never appeared as true in any broadcast snapshot - the resolver's own tail-end update always clobbered what `ActionApplier::hide()` had just set. Moved the clear to the *start* of the next resolve instead, so the snapshot after a hide-round correctly shows `player_hiding_this_round = true` for the UI animation, and the flag is cleared at the top of the following resolve before that round's action runs.
- Tests: added 3 new `ApplyActionTest` cases (single-spot teleport + hiding flag, manhattan tie-breaker with multiple spots, no-op when no spots in current room). Existing `hide is cleared on the following round` test continues to pass since the new "clear at top of next resolve" has the same observable end-state.

## April 18th, 2026 - AoE flash feedback on attack resolve

- When an `!a` action is the winning vote, the 8 tiles around the player briefly pulse red in `gamejam/live.vue` so viewers can see exactly which tiles the attack covered. Duration ~900ms, then the flash fades.
- Client-derived from `last_resolved_action` + `player_x/y` in the incoming snapshot; no new server fields. The attack doesn't move the player, so the player position in the just-resolved snapshot is the position at which the attack happened.
- Re-triggered on every new `last_resolved_at`, so back-to-back attacks across rounds each get their own pulse. An initial page load with a pre-existing "last action was attack" does not flash - we only animate on a newly-arrived timestamp.

## April 18th, 2026 - Doors open via attack, not by walking into them

- `!p` no longer progresses closed or opening doors - bumping them now halts movement the same way a wall does. Opening a closed door is exclusively an `!a` action, which matches the GDD: doors are obstacles, not reverse-proxies for the move command.
- `!a` gained a 3x3 AoE around the player (8 surrounding tiles, excluding the player's own tile). Any closed/opening door sitting anywhere in that ring gets hit. This sidesteps the "player can be left, right, or below the door" geometry: the attack always lands as long as the player is adjacent (orthogonal or diagonal).
- Door damage is weapon-dependent:
  - DE-sword (`!a 2`): 2 damage per hit - instantly opens a fresh `turns_remaining=2` door. Free of charge (DE-sword has no durability per the GDD).
  - Regular sword (`!a` or `!a 1` with sword equipped): 1 damage per hit, consumes 1 `weapon_slot_1_uses`. When uses hit 0 the sword breaks and slot 1 reverts to fists.
  - Fists (`!a` with no sword): 1 damage per hit, costs 1 HP of self-damage. If `wears_iron_fists` is true, the self-damage is negated. Hitting a door with fists at 1 HP ends the game.
- Cost/self-damage applies once per attack action, not once per door hit - relevant later when multiple doors or zombies can share an AoE.
- `ActionApplier::progressDoor()` is gone; replaced by `damageDoor(GameDoor, int $damage)` so DE-sword's 2-damage case is just a parameter.
- Tests: 10 new attack cases cover each weapon x door interaction, AoE reach (adjacent + diagonal, miss at 2 tiles), and an end-to-end DE-sword-then-walk win. Three existing tests were updated: closed/opening-door bumps now assert no progression, and the multi-step version does the same.

## April 18th, 2026 - Multi-step movement votes

- Chat can now vote `!p up 2` to move the player up to 8 tiles in one round. Encoding: `p:dir` for a single step (backward-compatible), `p:dir:N` for N >= 2. Bot-side parser needs to forward `steps` in the action payload.
- `BotGamejamActionController` validates `steps` as `integer|min:1|max:8` and encodes the vote via a tiny `encodeMoveVote()` helper so the 1-step case stays `p:dir` on the wire.
- `ActionApplier::move()` iterates `stepOnce()` and short-circuits on any interaction: wall bumps, closed/opening doors (which still progress once), open exit doors (win), and bomb-kill mid-path (loss) all halt remaining steps. One vote = one "turn" of momentum.
- `live.vue` readable-vote formatter shows `^ up x3` for multi-step votes; single-step renders unchanged.
- Tests: 5 new `ApplyActionTest` cases (3-tile move, edge-stop, door-stop, exit-win, mid-path bomb kill) plus 3 new `BotGamejamActionTest` cases (encoding with/without suffix, out-of-range validation).

## April 17th, 2026 - Playground preview isolated via Shadow DOM

- The playground's live preview now mounts user output into a shadow root (`attachShadow({ mode: 'open' })`) so `<style>` blocks in the user's snippet can no longer leak CSS onto the marketing page.
- Each preset now ships its own `<style>` block instead of relying on `:deep()` rules in the component. The starter Channel Card, Goal Bar, and Latest Follower presets all include scoped CSS, which is closer to how a real Overlabels template is authored anyway.
- A tiny reset is injected into the shadow root so `color` and `font-family` inherit from the host and `* { box-sizing: border-box; }` holds by default.

## April 17th, 2026 - Interactive tag playground on the marketing page

- New `SectionPlayground.vue` between `SectionSyntax` and `SectionControls` on the Welcome page. CodeMirror editor on the left, live rendered preview on the right, and a strip of one-click tag chips + presets above.
- Sample data comes from `TemplateDataMapperService::getSampleTemplateData()` - the same service that feeds real preview renders - passed through as an Inertia `sampleData` prop on the `/` route. Type `[[[channel_name]]]` on the marketing page and it resolves to `wilko_dj` in the preview, identical to what the authenticated editor does.
- Extracted the tag parser out of `OverlayRenderer.vue` into `resources/js/utils/tagParser.ts` (`TAG_REGEX`, `encodeHtml`, `replaceTagsWithFormatting`) so the renderer and the marketing playground share one implementation. `replaceTagsWithFormatting` now takes `locale` as an explicit argument instead of closing over `userLocale`.
- Conditionals are intentionally not part of the playground - the homepage demo stays focused on "type a tag, watch it resolve" without pulling the full expression engine onto the marketing page.

- Each GPS session card on `/dashboard/gps-sessions` now has an "Open full view" button that opens `/map/{twitch_id}/{session_id}` in a new tab.
- Only rendered when `map_sharing_enabled` is true on the user's overlabels-mobile integration. If the user hasn't opted into public maps, the button is absent - no accidental link from private dashboard to public URL.
- `GpsSessionController::index` now threads `mapSharingEnabled` and `twitchId` into the page props.

## April 17th, 2026 - Expose GPS accuracy as a control

- New `gps_accuracy` control on the overlabels-mobile integration. Value is the raw accuracy float in meters, the same field the app was already sending on every location ping (extracted as `event.accuracy` for alert templates but never provisioned as a control).
- Enables conditional tags like `[[[if:c:overlabels-mobile:gps_accuracy < 60]]]reliable fix[[[endif]]]` so overlays can gate content on GPS quality.
- Added to `getAutoProvisionedControls()`, `getControlUpdates()`, and `OVERLABELS_MOBILE_PRESETS`. Backfill migration (`2026_04_17_110000_backfill_overlabels_mobile_accuracy_control`) provisions the control for every existing integration.

## April 17th, 2026 - Category-aware preset search

- The "Add Control" preset combobox (`ControlFormModal.vue`) now filters by category as well as item label. Typing "overla" finds every Overlabels Mobile preset, "elem" finds StreamElements, "labs" finds StreamLabs, "kofi" or "ko-fi" both match Ko-fi, etc.
- Implementation: Reka's default combobox filter is disabled (`ignore-filter`); a fuzzy subsequence matcher runs against a haystack of `preset.label + SERVICE_LABELS[source] + source` for each preset. The fuzzy matcher is the same style as fzf / VS Code quick-open - "kofi" matches "Ko-fi" because the characters appear in order even across the hyphen.
- `fuzzyMatch()` and `presetHaystack()` live in `resources/js/utils/services.ts`. No curated alias list - everything derives from `SERVICE_LABELS` + the source key, so adding a new service is still a single edit.

## April 17th, 2026 - DRY up SERVICE_LABELS across frontend

- Extracted the `SERVICE_LABELS` display-name map into `resources/js/utils/services.ts` so `ControlPanel.vue`, `ControlsManager.vue`, and `ForkImportWizard.vue` all read from one source of truth.
- Fixes a grouping bug where `overlabels-mobile` controls on the Controls tab (ControlsManager) fell into type-based groups ("Number", "Text") instead of the "Overlabels Mobile" collapsible, because that file's local copy of `SERVICE_LABELS` hadn't been updated when the service was added.
- Also pulls the wizard's stale `'StreamLabs'` / `'GPS Logger'` labels into line with the canonical `'Streamlabs'` / `'GPSLogger'`, and adds the missing `twitch`, `streamelements`, and `overlabels-mobile` entries there.
- New service integrations now only need one edit (the constants file) instead of three.

## April 17th, 2026 - Per-session GPS stats as controls

- Four new auto-provisioned controls on the overlabels-mobile integration: `gps_session_distance`, `gps_session_max_speed`, `gps_session_avg_speed`, `gps_session_duration`. Values are raw (km for distance, m/s for speed, seconds for duration) so templates format them through pipes against the user's locale.
- The driver's `beforeControlUpdates` now maintains per-session running state in `integration.settings` (`session_id`, `session_started_at_unix`, `session_distance_km`, `session_max_speed_ms`, `session_speed_sum_ms`, `session_speed_count`). State resets on `session_start`. Location updates accumulate. `session_end` freezes the final duration.
- Avg speed is the arithmetic mean of per-ping speed samples (matches the GPS Sessions dashboard aggregation).
- Max speed is a running max across the session's samples.
- Session-id drift detection: if a `location_update` arrives with a session_id that doesn't match what we've been tracking (session_start lost, stale state, first deploy), the driver treats it as a fresh session and resets counters.
- Backfill migration (`2026_04_17_100000_backfill_overlabels_mobile_session_controls`) provisions the four new controls for every existing overlabels-mobile integration. Idempotent via `firstOrCreate`.
- `gps_distance` kept unchanged (cumulative across all pings, same as before). Label updated to "GPS Distance (km, cumulative)" so the per-session one is clearly the session-scoped counterpart.
- Added the four new session controls to `OVERLABELS_MOBILE_PRESETS` so they appear in the ControlFormModal "Add Control" dropdown on static templates. The tags themselves already resolve against the auto-provisioned user-scoped rows, but the preset entries give the discoverability + read-only display cards in ControlsManager / ControlPanel.

## April 17th, 2026 - Distance and speed pipe formatters

- New `|distance:km` and `|distance:mi` pipes. Input assumed km; output is locale-formatted with up to 2 decimals. Unit label is NOT appended (add it in your template).
- New `|speed:kmh` and `|speed:mph` pipes. Input assumed m/s; output is locale-formatted with 1 decimal. Unit label is NOT appended.
- Both added to `/help/formatting` with examples and tables comparing en-US / nl-NL locale output.
- Legacy `c:overlabels-mobile:gps_speed` stays pre-converted server-side based on the user's `speed_unit` setting. The new `gps_session_max_speed` / `gps_session_avg_speed` controls store raw m/s and require the new `|speed:` pipe to render.

## April 16th, 2026 - Genericize offline map page (no identity leaks)

- Offline panel no longer mentions the streamer's name or their safe zone. Previous copy ("This map will come to life as soon as {name} starts streaming GPS from outside their safe zone.") confirmed three things on a bare URL visit: that the account exists on Overlabels, that the streamer has a safe zone configured, and by implication that they might currently be inside it. New copy is fully generic: "Nothing to show right now. This map will come to life as soon as a live stream begins broadcasting GPS."
- Header title and `<title>` tag also swap to a generic "Live location" when offline, and switch to the streamer's name only once the map goes live (via `document.title` watcher).

## April 16th, 2026 - Harden position API against chat shenanigans

- Extracted the GPS liveness check into `App\Services\GpsLivenessService`.
- `GET /api/map/{twitchId}/position` now returns `position: null` when the user is not broadcasting (no active session with a location_update), instead of the most recent historical ping. Previously a chatter could curl this endpoint directly and get the streamer's last-known position regardless of whether the stream was running, bypassing the frontend's offline panel.
- Frontend-only `isLive` gating is a UX hint, not a security boundary; the server is now authoritative on both the page render path and the polling API.

## April 16th, 2026 - Live map: soft offline state + safe-zone-aware liveness

- Liveness check now also requires at least one `location_update` inside the active session. Previously the map would render centered on the last known position the moment a `session_start` event arrived, even if the user hadn't left their safe zone yet (the app suppresses location broadcasts inside the safe zone but still creates the session). This would leak the safe-zone area (e.g. the streamer's home).
- Replaced the hard 404 with a soft in-page "Nobody broadcasting right now" panel. Dark theme, purple accent, styled like the 404 page but with a broadcast-off icon.
- The page now transitions automatically: offline -> live when the first `location_update` arrives over WebSocket (via `overlabels-mobile:gps_lat` / `gps_lng` updates), and live -> offline when `session_end` fires (via `overlabels-mobile:gps_tracking = '0'`). Trail and marker are cleared on session end so the next session starts fresh.
- The WebSocket connection is now established regardless of the delay setting so session-state transitions are detected in realtime even for delayed maps (delay still applies to displayed coordinates via polling).
- `useMapWebSocket` now exposes a `trackingActive` ref fed by `gps_tracking` control updates.

## April 16th, 2026 - Live map requires active GPS session

- `/map/{twitch_id}` now returns 404 unless there is an unfinished GPS session (a `session_start` event in `external_events` without a matching `session_end` for the same `session_id`).
- Closes a potential location-doxxing vector: previously, after a stream ended the live map would remain stuck on the last broadcast position indefinitely. Now the map is only accessible while the user is actively broadcasting GPS.
- `map_sharing_enabled` is still respected; the session check is additive.
- Saved session maps (`/map/{twitch_id}/{session_id}`) are unaffected.

## April 16th, 2026 - Overlabels GPS integration (replaces GPSLogger)

- New `overlabels-mobile` external service driver, cloned from the GPSLogger driver with its own controls namespace (`overlabels-mobile:gps_speed`, `overlabels-mobile:gps_lat`, `overlabels-mobile:gps_lng`, `overlabels-mobile:gps_distance`).
- One-click connect with auto-generated authentication token - no manual token entry needed.
- QR code on the settings page encodes an `overlabels://gps-setup?endpoint=...&token=...` deep link so the Overlabels GPS Android app configures itself in a single scan.
- GET on the webhook URL (`/api/webhooks/overlabels-mobile/{token}`) shows a mobile-friendly landing page with an "Open in Overlabels GPS app" deep link button and manual setup fallback.
- Regenerate Token action lets users rotate the shared secret and re-scan the QR.
- Existing GPSLogger integration is untouched - both can coexist during migration.
- 14 feature tests covering the full webhook pipeline, control updates, distance accumulation, speed conversion, token regeneration, and the landing page.

## April 16th, 2026 - Safe zone support

- New `settings_sync` event type for the overlabels-mobile driver. The Android app can POST `{"event": "settings_sync", "safe_zone_lat": ..., "safe_zone_lng": ..., "safe_zone_radius": ...}` to store the safe zone in the integration's settings jsonb. No `external_events` row is created.
- Sending null values for the safe zone fields clears it.
- Settings page shows the configured safe zone (lat, lng, radius) with a "Clear safe zone" button.
- The actual GPS filtering happens app-side - the backend just stores and displays the config.

## April 16th, 2026 - Delete GPS sessions

- DELETE `/dashboard/gps-sessions/{sessionId}` removes all events (location_update, session_start, session_end) for the given session UUID, scoped to the authenticated user.
- Clears the cached GeoJSON for the deleted session.
- Delete button on each session card with confirmation dialog and RekaToast feedback.
- Session list reloads automatically after deletion.

## April 16th, 2026 - Map integration (Leaflet + OpenStreetMap)

### Session maps
- "View map" button on each GPS session card lazy-loads an inline Leaflet map showing the route as a purple polyline with green (start) and red (end) circle markers.
- GeoJSON API endpoint (`/api/gps-sessions/{sessionId}/geojson`) with Ramer-Douglas-Peucker route simplification and caching for completed sessions.

### Public live map
- `/map/{twitch_id}` shows a full-screen live map updated via the existing Reverb WebSocket channel (`alerts.{twitch_id}`). Marker moves smoothly as GPS pings arrive, with a trailing polyline.
- When a location delay is configured, the live map polls the position API instead of WebSocket, returning the position from N seconds ago.

### Public session maps
- `/map/{twitch_id}/{session_id}` shows a completed session's route on a full-screen map with auto-fitting bounds.

### Privacy controls
- "Public live map" toggle and "Location delay" dropdown (0/1/2/5 minutes) added to the Overlabels GPS settings page.
- Map pages return 404 when map sharing is not enabled. All settings stored in the integration's jsonb settings column (no migration).
- Public map URL shown with copy button when sharing is enabled.

### Architecture
- Standalone Vite entry point (`resources/js/map/app.ts`) following the overlay pattern: Blade template injects config into `window.__MAP__`, Vue mounts LiveMap or SessionMap component.
- Leaflet code-split into its own chunk (~43KB gzipped). WebSocket reuses the existing `websocket` chunk.
- `RouteSimplifier` service implements Ramer-Douglas-Peucker for server-side polyline simplification.
- Tiles from OpenStreetMap (free, zero config). One-line switch to MapTiler for dark theme later.

## April 16th, 2026 - GPS Sessions page

- New `/dashboard/gps-sessions` page showing per-session stats: duration, distance, avg/max speed, elevation range, battery start/end with delta, and ping count.
- All numbers honor the user's locale setting (via `Intl.NumberFormat`) and speed unit preference (km/h or mph). Distance also converts to miles when mph is selected.
- Session data aggregated from `external_events` jsonb using PostgreSQL `FILTER`, `array_agg`, and `BOOL_OR` - no new tables.
- Distance computed per session via server-side haversine on ordered pings with 1m jitter filter.
- `overlabels-mobile` events removed from the Recent Events stream to prevent GPS ping spam.
- Sidebar nav link added under Alerts group.

## April 16th, 2026 - GPS session lifecycle (session_start / session_end)

- The `overlabels-mobile` driver now recognizes three event types via the `event` payload field: `session_start`, `session_end`, and `location_update` (default when absent, backward compatible).
- `session_start` sets the new `gps_tracking` boolean control to `1`; `session_end` sets it to `0`. Overlays can use `[[[c:overlabels-mobile:gps_tracking]]]` to show/hide GPS widgets.
- Session events are deduped on `session_start_{session_id}` / `session_end_{session_id}` - safe to retry.
- Session events skip GPS control updates and distance accumulation (no coordinates to process).
- Every `location_update` ping can carry `session_id` in the payload, stored in jsonb for grouping via `raw_payload->>'session_id'`.
- No new database tables - sessions are inferred from the event stream.
- 5 new tests covering start/stop lifecycle, dedup, control isolation, and session_id persistence.

## April 16th, 2026 - Migrate external_events JSON columns to jsonb

- `raw_payload` and `normalized_payload` on `external_events` converted from `json` to `jsonb`.
- Enables future deep queries on event data (e.g. route replay, top-speed analytics, filtering by coordinate range) using PostgreSQL's indexed `jsonb` operators.
- Zero-downtime cast - existing rows are converted in-place.

## April 16th, 2026 - Add bearing, battery, charging controls to Overlabels GPS

- Three new auto-provisioned controls: `gps_bearing` (degrees 0-360), `gps_battery` (percentage), `gps_charging` (boolean).
- Existing integrations pick up the new controls on next "Save changes" - provisioning is idempotent.
- Template tags: `[[[c:overlabels-mobile:gps_bearing]]]`, `[[[c:overlabels-mobile:gps_battery]]]`, `[[[c:overlabels-mobile:gps_charging]]]`.
- Presets, settings page, and landing page updated to show all 7 controls.

## April 16th, 2026 - Fix tag resolution for hyphenated service names

- `TAG_REGEX` in `OverlayRenderer.vue` and PHP `extractTemplateTags()` in `OverlayTemplate.php` now include hyphens in the tag-key character class so `[[[c:overlabels-mobile:gps_speed]]]` (and any future hyphenated service) resolves correctly.
- Added `overlabels-mobile` to `OverlayControl::RESERVED_KEYS` to prevent namespace collisions.
- Added `OVERLABELS_MOBILE_PRESETS` to `controlPresets.ts` and wired into `ControlFormModal.vue` so the preset picker shows Overlabels GPS controls when the integration is connected.
- Two new unit tests for hyphenated tag extraction (plain and with pipe formatter).

## April 14th, 2026 - Gave Twitch Controls their own namespace c:twitch:controls

- Twitch Controls are now usable in the frontend under the `c:twitch:x` namespace.
- Added a separate accordion dropdown for Twitch Controls in the `ControlPanel.vue`
- Tested it by faking online state, then firing a fake Bits Cheers from the Integrations page.
- The test resolved fine to the newly namespaced control `c:twitch:cheers_this_stream`.
- I can't oversee the further implications of this change, but it's a good start.


## April 15th, 2026 - Fix: expression preview now shows real `t.*` values

- The expression-builder preview was stuck on mock values (e.g.
  `t.followers_total + t.subscribers_total` previewed as `84` instead of the
  real `1525`). Root cause: the route that powers the live tag fetch was
  named `api.expression.tags`, which matched the blanket `api.*` exclusion in
  `config/ziggy.php`. Ziggy therefore never exposed it to the frontend, and
  `route('api.expression.tags')` threw at runtime before any network request
  was made - silently falling back to the shape-aware mocks.
- Renamed the route to `expression.tags` (path unchanged at
  `/api/expression/tags`). Ziggy picks it up, the fetch fires on modal open,
  and `liveTwitchTags` populates with real Helix-sourced values.

## April 15th, 2026 - Fix: sweep remaining user-facing dates onto the user locale contract

- Follow-up to the earlier ControlPanel/ControlsManager locale fix. A
  repo sweep turned up nine more user-facing spots still calling
  `toLocaleString()` / `toLocaleDateString()` with no locale argument
  (or, in the case of `kits/show.vue` and `KitCard.vue`, hardcoded
  to `'en-US'`). Each one followed the same
  `usePage().props.auth.user.locale` pattern we already use elsewhere
  and got the same three-line `userLocale` computed + arg threading.
- Fixed: `TemplateMeta.vue` (created/updated dates on template cards),
  `KitCard.vue` + `kits/show.vue` (kit dates), `overlaytokens/index.vue`
  (token created/last-used), and the five integration settings pages
  (`gpslogger`, `index`, `kofi`, `streamelements`, `streamlabs`) which
  all had a shared `formatDate()` last-event helper. The three donation
  drivers also had a Number `toLocaleString()` on the "Starting count
  set to X" line - fixed those too so Dutch users see `1.000` instead
  of `1,000`.
- Left intentionally alone: admin panel (raw timestamps are fine
  there), `utils/formatters.ts` (its `DEFAULT_LOCALE` is the fallback
  constant), `settings/Appearance.vue` (the `'en-US'` is the default
  option value of the locale picker itself), and `help/Formatting.vue`
  (the `en-US` strings are column headers in docs tables).

## April 15th, 2026 - Fix: ControlPanel + ControlsManager honor user locale for date formatting

- Countto timer targets in `ControlPanel.vue` and both countto targets
  and raw datetime values in `ControlsManager.vue` were calling
  `toLocaleString()` with no arguments, so they defaulted to browser
  locale. For a user with `users.locale = 'nl-NL'`, their own timer
  showed `5/7/2026, 12:00:00 AM` instead of `7-5-2026 00:00:00` -
  same bug, two places.
- Both components now read `auth.user.locale` from the Inertia shared
  props (already wired up in `HandleInertiaRequests:56`) via a
  `userLocale` computed, and pass it into `toLocaleString(locale)`.
  Fallback to `undefined` (browser default) if the field is empty so
  nothing breaks for users without a locale set.
- Matches the locale contract already in place for the overlay
  renderer and appearance settings.

## April 15th, 2026 - Feat: Number Control cards show constraints + out-of-range warning

- Number Controls in `ControlPanel.vue` now display a muted-foreground
  line under the input listing whatever constraints are configured -
  `Min`, `Max`, `Step` (hidden when it's the default 1), `Reset`,
  joined with middle-dots. Previously you had to remember the settings
  or open the edit modal; entering `698` into a `min=0 max=9` control
  looked like a save, but the server silently clamped back to `9` and
  the overlay didn't update the way the streamer expected.
- When the live-typed value falls outside `min`/`max`, the card picks
  up the same red gradient (`bg-linear-to-br from-red-500/15 to-background`)
  that a stopped timer uses, fading in/out with the card's existing
  500ms transition. Purely visual - the server still authoritatively
  sanitizes on save - but it makes the constraint breach immediately
  obvious before the user hits Save.
- New helpers `numberConstraintsText()` and `isNumberOutOfRange()` in
  the component. The range check uses `getLocalValue()` so it reacts
  as you type, not only after save.

## April 15th, 2026 - Fix: Enter in ControlPanel inputs now submits instead of toggling Collapsible

- Since the ControlPanel rewrite wrapped value inputs in Reka Collapsible
  groups, pressing Enter inside an input bubbled up to the Collapsible
  root, which treats Enter/Space as a toggle. Result: Enter collapsed the
  group instead of saving the value. Streamer muscle-memory broken.
- Added `@keydown.enter.stop` on the text/number/datetime forms so the
  Enter keydown stops at the form before reaching Collapsible.
- Bonus fix: number and datetime inputs were never in a `<form>` - just
  a `<div>` - so even without the Collapsible regression, Enter could
  never have submitted anything. Wrapped both in proper
  `<form @submit.prevent="saveTextValue(ctrl)">` so they behave like the
  text control.
- Removed the `@click="saveTextValue(ctrl)"` from the submit buttons now
  that the form's `@submit.prevent` handler covers the same path -
  otherwise Enter would have fired `saveTextValue` twice (synthesized
  submit-button click + form submit event). Click-to-save still works
  because the button is `type="submit"`.

## April 15th, 2026 - Refactor: ControlsManager replaces Table with Collapsible groups

- `ControlsManager.vue` was still a wide shadcn `<Table>` with Order / Key /
  Label / Type / Settings / Snippet / Actions columns. Overwhelming on
  templates with many Controls, and visually out of step with
  `ControlPanel.vue` and `TemplateTagsList.vue` which both use collapsible
  groups.
- Rewrote as filter bar + Collapse/Expand-all toggle + one `<Collapsible>`
  per category, grouping user Controls by type (Counter, Timer, Number,
  Text, Toggle, Expression, Date/Time) and service-managed Controls by
  source (Twitch, Ko-fi, StreamElements, Streamlabs, GPSLogger). Per-group
  expanded state persists to `localStorage` under
  `controls_manager_expanded`, separate from `ControlPanel`'s key so the
  two panels toggle independently.
- Row layout adapted from `TemplateTable.vue`: bordered card, click or
  Enter/Space to edit, hover reveals the snippet pill (desktop) plus
  edit / duplicate / delete buttons. Source-managed rows get a lock pill
  showing the service name and drop the duplicate action. All action
  handlers use `@click.stop` so they don't bubble to the row's edit
  handler.
- Footer now references the lock icon instead of the old `*` footnote,
  and the `N/50` counter stays in place.

## April 15th, 2026 - Fix: bot `!enable`/`!disable` on already-set booleans now errors

- When a boolean Control was already `1` and chat ran `!enable <key>`, the
  bot replied "<key> enabled", which was a lie - nothing changed. Same story
  for `!disable` against a `0` value. Fixed in the Laravel API (bot is a
  dumb relay per project rules) in `BotControlController::update()`: if
  every matching control already sits at the target value, short-circuit
  with `409 Conflict` and `{"error": "<key> already <enabled|disabled>"}`
  so the bot relays the real state to chat.
- Partial-state scenarios aren't blocked: if a user has the same key on
  both a template-scoped and a user-scoped Control and they disagree, the
  action still proceeds and flips only the ones that differ. `toggle`
  untouched since it always changes state.
- Two Pest tests cover the new 409 responses.

## April 15th, 2026 - Fix: boolean Control switch + realtime ControlPanel updates

- `ControlFormModal.vue`: the Reka `<Switch>` used when creating a boolean
  Control did not remember its state and toggling it did nothing. Replaced
  with the same native-checkbox-plus-Tailwind "fake switch" pattern already
  used in `pages/events/index.vue`, so `booleanValue` now round-trips
  correctly between create, edit, and save. Dropped the unused Switch import.
- `ControlPanel.vue`: the panel used to only update when the user clicked a
  button in it. External sources (the Twitch bot's `!toggle`/`!set`
  commands, Ko-fi, StreamElements, Streamlabs) would change the stored
  value but the UI stayed stale until a page reload. The panel now
  subscribes to the same `alerts.{twitch_id}` Echo channel that
  `OverlayRenderer.vue` uses, listens for `.control.updated`, and mutates
  the matching local control by comparing `event.key` against each
  control's `source ? "source:key" : "key"` broadcast key. Timer events
  merge `timer_state` into `ctrl.config` and restart the tick; everything
  else sets `ctrl.value` directly. Empty `overlay_slug` means user-scoped
  and applies everywhere; a non-empty slug must match
  `props.template.slug`. Listener is torn down on unmount via
  `stopListening`.

## April 15th, 2026 - Feat: `now_ms()` + 250ms expression tick for sub-second math

- New expression function `now_ms()` returning `Date.now()` (integer
  milliseconds). Pairs with a tighter 250ms shared ticker in
  `useExpressionEngine` so sub-second formulas like
  `mod(floor(now_ms() / 250), 3)` or `sin(now_ms() / 500)` animate smoothly
  without a CSS keyframe. `now()` stays integer-seconds to preserve the
  timestamp contract with `_at` companions - existing expressions unchanged.
- AST walker generalised to a `TIME_FUNCTIONS` set (currently `now`, `now_ms`)
  so any call to either flags the expression as time-dependent and subscribes
  it to the shared ticker.
- Help page (`pages/help/Math.vue`): added `now_ms()` row to the function
  table and replaced the outdated "expressions are reactive, not scheduled"
  pitfall with an accurate explainer of integer-seconds vs millisecond
  resolution and when to reach for each.

## April 15th, 2026 - Feat: time-based expressions self-tick at 1s

- Expressions that call `now()` now re-evaluate every second on their own.
  Previously a pure-time formula like `mod(floor(now() / 8), 3)` evaluated
  once at overlay load and then froze, because `watchEffect` had no reactive
  dependency to trigger on. Streamers worked around this by adding a throwaway
  random/counter control purely as a heartbeat. No more.
- `useExpressionEngine` now walks the parsed AST at register time looking for
  a `now()` call (recursing through binary, unary, conditional, member, and
  nested call nodes). Expressions flagged time-dependent subscribe to a
  shared `timeTick` ref, and a single `setInterval` ticks it at 1s while at
  least one such expression is registered. Ref-counted: when the last
  time-dependent expression unregisters, the interval clears.
- Non-time expressions are unaffected - zero overhead, no interval, no extra
  reactive read. Mixed expressions (e.g. `now() - c.stream_started_at`) get
  both paths: ticker-driven re-eval every second, control-broadcast re-eval
  the moment a referenced control changes.
- Resolution choice: 1s is enough for clocks, uptimes, banner rotations, and
  anything else streamers reasonably want to drive from `now()`. Sub-second
  animation belongs in CSS/JS, not the expression engine.

## April 15th, 2026 - Fix: allow expressions that don't reference anything

- The expression save path in `OverlayControlController` required at least one
  `c.*` or `t.*` reference, rejecting pure-math formulas like
  `mod(floor(now() / 8), 3)` that are legitimately useful as scratchpad values
  for other expressions to consume. Removed the check from both `store` (line 113) and `update` (line 184) along with the now-unused
  `extractTwitchTagReferences` calls. Cycle detection and scope validation
  still run; the sandboxed AST evaluator remains the only thing that gets to
  touch the string.

## April 15th, 2026 - Feat: live Twitch values in the expression preview

- Builds on the mock-data preview from earlier today. The save-dialog now
  fetches the user's real `t.*` tag map from a new endpoint when the
  expression builder mounts, so previews show <em>actual</em> values -
  `t.followers_total` reads 1523 instead of the 42 placeholder,
  `t.channel_name` reads "JasperDiscovers" instead of "(channel_name)".
- New endpoint: `GET /api/expression/tags`, handled by
  `ExpressionTagController::index`. Calls the same
  `TwitchApiService::getExtendedUserData` + `TemplateDataMapperService::mapForTemplate`
  pipeline the overlay renderer uses, but with no template-allowlist, so it
  returns every resolvable tag the user has. Auth via sanctum,
  rate-limited 60/min.
- Performance: because `getExtendedUserData` already caches Twitch snapshots,
  the endpoint typically responds in tens of milliseconds. Cold (first call
  after cache expiry) is a few hundred ms worth of Helix round-trips.
- Preview watcher now reruns when either the expression text OR the live
  tag map changes, with `{ immediate: true }` so the initial preview kicks
  off before user input. Falls back to the shape-aware mocks
  (`42` / timestamp / `false` / `(name)`) while the fetch is in flight or
  for tags the response didn't include.

## April 15th, 2026 - UX: mock `t.*` values in the expression preview

- Expression preview in the save dialog would show nothing when an expression
  only referenced Twitch tags, because the mock context was built from
  available controls only. Now the preview scans the expression for
  `t.<name>` references and injects a plausible placeholder based on the
  tag's suffix - `42` for counters (`*_total`, `*_count`, `*_bits`, etc.),
  a fresh Unix timestamp for `*_at` / `*_date`, `false` for `*_is_*`, and
  the tag name in parens for anything else. Real values still resolve
  server-side at render time; this is purely so the dialog preview stops
  looking suspiciously empty.

## April 15th, 2026 - Fix: allow expressions that only reference `t.*` tags

- The expression-control save path required at least one `c.*` dependency,
  rejecting otherwise-valid formulas that only read Twitch template tags
  (e.g. `t.channel_name`, `t.followers_total + t.subscribers_total`). Working
  around it by adding a dummy text control named `channel_name` didn't help -
  that control is reachable as `c.channel_name`, not `t.channel_name`.
- `OverlayControl` gains `extractTwitchTagReferences(string)` - mirror of
  `extractExpressionDependencies`, but matching `\bt\.([a-z][a-z0-9_]*)` and
  returning the list of tag names.
- `OverlayControlController::store` and `::update` now accept an expression if
  it references <em>either</em> a control (`c.*`) or a Twitch tag (`t.*`).
  The cycle detection and "does this control exist?" check still applies to
  `c.*` dependencies only, because `t.*` values can't participate in cycles.
  Error message updated to show both syntaxes.
- `OverlayTemplateController::renderAuthenticated` DRY'd to call the new
  helper instead of duplicating the regex inline.

## April 15th, 2026 - Fix: `t.*` actually resolves now

- Shipping `t.*` earlier today left two silent failures in the pipeline:
  - Server: `mapForTemplate` took its allowlist from `$template->template_tags`,
    which is built from `[[[...]]]` occurrences in the HTML. An expression that
    referenced `t.followers_total` without a matching `[[[followers_total]]]`
    in the HTML would never receive a value - the server would simply not ship
    it.
  - Client: the seed loop in `OverlayRenderer.vue` was scoped to the ~14 tag
    names declared in `EVENT_RULES`. Even if the server had shipped
    `channel_name` or `user_display_name`, the mirror pass would ignore them
    and `t:channel_name` would never land in `data.value`.
- Server fix (`OverlayTemplateController::renderAuthenticated`): after
  collecting `$expressionControls`, scan every expression string for
  `\bt\.([A-Za-z_]\w*)` matches and union those names into the allowlist
  passed to `mapForTemplate`. `not_t.fake` is safely ignored thanks to the
  word boundary. `mapForTemplate` moved below the control loop so this unioned
  list is what the mapper sees.
- Client fix (`OverlayRenderer.vue`): the seed pass now iterates every bare
  scalar key in the initial `json.data`, mirroring each into `t:*`. Excludes
  `c:*`, already-prefixed `t:*`, the `user_twitch_id` meta key, dotted keys
  (raw EventSub fields like `event.user_name`), and non-scalar values. Pinia
  seeding stays scoped to `EVENT_RULES` so the live counter watch still only
  fires on mutated tags.
- Together: any tag that exists in the user's `template_tags` table can now be
  read as `t.tag_name` in an expression, whether or not the template HTML also
  references it.

## April 15th, 2026 - Feat: `t.*` namespace in the expression engine

- Expression controls can now read live Twitch event-tag values directly, with
  no re-parsing of `[[[tag]]]` syntax inside expression strings. Use
  <code>t.followers_total</code>, <code>t.subscribers_latest_user_name</code>,
  <code>t.last_raid_viewers_peak</code>, and every other tag declared in
  <code>useTwitchEventRules.ts</code>. The day-one "tags never reparse" invariant
  holds: expressions never see `[[[...]]]` syntax, and template interpolation
  never sees expression AST nodes.
- Implementation is tiny. `buildContext()` in <code>useExpressionEngine.ts</code>
  now runs a shared <code>extractNamespace()</code> helper against two prefixes -
  <code>c:</code> (controls, unchanged) and <code>t:</code> (twitch, new). One
  function, two prefixes, no duplicated nesting/collision logic.
- Wiring lives in <code>OverlayRenderer.vue</code>:
  - On mount, for every tag name declared in <code>EVENT_RULES</code>, seeds the
    Pinia <code>eventStore.tags</code> with the server-side snapshot value (only
    if Pinia's slot is empty), and mirrors that value into <code>data.value</code>
    under <code>t:tag</code> so expressions can read it immediately.
  - A deep watch on <code>eventStore.tags</code> mirrors every subsequent
    mutation into <code>data.value[`t:${key}`]</code>. Follow fires, Pinia
    increments, watch mirrors, every expression that references
    <code>t.followers_total</code> re-evaluates on the next reactivity tick.
- Help page (<code>/help/math</code>) updated: Section 1 now documents
  <code>c.*</code> and <code>t.*</code> side-by-side, Section 9 is rewritten
  around real live examples (milestone progress bar, latest-follower fade,
  raid hype meter, sub/gift copy switch) instead of the aspirational
  `[[[tag]]]`-inside-expression pseudocode that shipped yesterday.

## April 15th, 2026 - Help: the Math Engine page

- New `/help/math` page and `help.math` route. Lives at
  `resources/js/pages/help/Math.vue`, linked from the help hub with a new Sigma
  icon card.
- Documents every whitelisted primitive in `useExpressionEngine.ts` (operators,
  constants, scalar math, arg-pair family), then walks through the classic
  overlay-math patterns: sine-wave breathing, Lissajous pairs, sawtooth ramps
  from `fract()`, the triangle-wave trick, modulo wheels for cyclic indexing,
  and cross-service timestamp racing via `latest()`/`argmax()` with the
  automatic `_at` companions.
- Includes a step-by-step teardown of the shader-style pseudo-random one-liner
  `floor(fract(sin(now() / 2) * 1000) * 9) + 1`, explaining why each layer
  exists and how to vary the recipe for a d20, a 3-way rotator, or a stable
  roll that only changes every N seconds.
- Honest pitfalls section: expressions are reactive not scheduled (so
  `sin(now())` alone does not animate), trig takes radians, no `**`/`sqrt`/
  `exp`/`tan`, division by zero returns zero, and the arg-family functions
  return an error string on odd argument counts.
- New dep: `katex` + `@types/katex` for proper display-math typography on this
  page. Loaded via a tiny `MathEquation.vue` wrapper and code-split by Inertia,
  so only visitors to `/help/math` pay the ~86 KB gzip cost.

## April 14th, 2026 - Fix: template tag list empty after onboarding

- New accounts running through `OnboardingWizard` could land on `/templates/edit`
  before the queued `GenerateTemplateTags` job finished. `TemplateTagsList.vue`
  would hit `tags.api.all`, get `{ tags: {} }`, and cache that empty object in
  localStorage under a global key for a full hour. Subsequent visits kept
  showing "No tags available" even though the DB had tags and the live overlay
  rendered them correctly (the renderer reads from the DB on every request, not
  from the browser cache).
- Three small fixes in `TemplateTagsList.vue`:
  - User-scoped cache key (`template_tags_cache_user_{id}`) plus a version bump
    to `v2` so old global-keyed caches are ignored. Prevents one user's cached
    view from leaking to another account on the same browser.
  - Don't cache empty responses - if the onboarding job hasn't populated the
    user's categories yet, we no longer pin that emptiness in localStorage.
  - Stale-while-revalidate on mount: if a cache exists, render it immediately,
    but always re-fetch from the API and overwrite if the server has newer data.
    Adds one background request per page load; removes the 1-hour "why are my
    tags gone" window entirely.

## April 14th, 2026 - Bot: !enable / !disable / !toggle for boolean controls

- Three new bot actions on the control pipeline: `enable` -> `'1'`, `disable` -> `'0'`,
  `toggle` -> flips current value. Only valid against `boolean` controls. Non-boolean
  targets 422 with `"action 'X' requires a boolean control"`, mirroring the existing
  numeric guard.
- `BotControlController::update`:
  - Validation enum expanded to `set,increment,decrement,reset,enable,disable,toggle`.
  - New `allBoolean()` helper alongside `allNumeric()`.
  - Match arms added for the three actions. `toggle` reads `$control->value` which is
    always `'1'` or `'0'` for boolean controls (via `OverlayControl::sanitizeValue`).
- `BotCommand::DEFAULTS` now includes `enable`, `disable`, `toggle` at `moderator` tier,
  matching `set`. Same trust boundary: broadcaster and mods can flip state, VIPs and
  below cannot.
- Data migration `2026_04_14_220000_seed_boolean_bot_commands.php` loops
  `bot_enabled=true` users and calls `BotCommand::seedDefaults($user)` so existing
  opted-in streamers pick up the new commands without having to disable/re-enable.
  Idempotent thanks to `firstOrCreate`.
- Tests: happy paths for enable/disable/toggle, 422 guards for enable on counter and
  toggle on text. The existing seeding tests use `count(BotCommand::DEFAULTS)` so they
  auto-updated; only the one explicit `toContain(...)` assertion needed the three new
  command names.

## April 14th, 2026 - Bot enable toggle on integrations page

- The `users.bot_enabled` column and `UserObserver` have existed since the initial bot plumbing
  (April 13th), but there was no UI to flip the flag - you had to open tinker to opt in. First UI
  surface now lives on `settings/integrations`, just below the Twitch alerts card and above the
  external donation services.
- New `App\Http\Controllers\Settings\BotSettingsController` with a single `setEnabled(Request)`
  action that validates `enabled: bool` and writes to `$user->bot_enabled`. The observer already
  handles seeding `BotCommand::DEFAULTS` on the false-to-true transition, so the controller
  deliberately stays thin - one validated field update, back() response, no side effects.
- Route: `PATCH /settings/integrations/bot` under the existing `auth.redirect` +
  `settings.integrations.` prefix group. Named `settings.integrations.bot.enabled`.
- `IntegrationController::index` now passes `bot.enabled` to the Inertia page alongside the
  existing `services` and `eventsub` props.
- Frontend: `settings/integrations/index.vue` gains a `BotInfo` prop, a `toggleBot()` handler
  using `router.patch(..., { preserveScroll: true })`, and an "Overlabels Bot" card with an
  Enable/Disable button. When enabled the copy prompts the streamer to run
  `/mod overlabels` in their Twitch chat and test with `!ping` - the two steps that matter
  for the bot to actually work in a given channel.
- Deliberately not included: bot control panel page for editing per-command permission tiers
  and cooldowns. That's the next step; this commit is just the on/off switch so streamers can
  stop editing the DB by hand.

## April 14th, 2026 - Breaking: rename Ko-fi `kofis_received` to `donations_received`

- When Ko-fi was the only external integration, its auto-provisioned counter was playfully called
  `kofis_received`. Now that Streamlabs and StreamElements also live in the system and both use the
  generic `donations_received`, Ko-fi's cute pun became a naming inconsistency that leaked into
  documentation, expression control examples, and the new landing-page integration tab (which had
  to show three pipes of identical shape plus one cute outlier).
- Renamed in the driver, controllers, frontend settings page, control presets, tests, and comments:
  - Control key: `kofis_received` -> `donations_received` (source=kofi)
  - Settings JSON keys: `kofis_seed_set` / `kofis_seed_value` ->
    `donations_seed_set` / `donations_seed_value`
  - Vue refs: `kofisSeedSet` / `kofisSeedValue` -> `donationsSeedSet` / `donationsSeedValue`
- Added data migration `2026_04_14_120000_rename_kofi_donations_received.php` that rewrites:
  1. `overlay_controls.key` for rows with `source=kofi` and `key=kofis_received`.
  2. `overlay_controls.value` for expression controls referencing `c.kofi.kofis_received`.
  3. `overlay_controls.config.dependencies` for expression controls referencing
     `kofi:kofis_received`.
  4. `overlay_templates.html`, `css`, `js`, and `template_tags` for any occurrences of
     `[[[c:kofi:kofis_received]]]`.
  5. `external_integrations.settings` JSON for `service=kofi` - renames `kofis_seed_*` keys to
     `donations_seed_*`.
- Because the Welcome.vue integration tab now has genuinely identical control names across all
  three donation services, the code block drops its `counterKey` override and just uses
  `donations_received` directly - "only the namespace word changes" is now literally true.
- Historical changelog entries keep the old `kofis_received` naming - they're a frozen record.

## April 14th, 2026 - Welcome.vue: unify external integrations into tabs

- Previously the Integrations section had three side-by-side cards. Two of them also claimed "six
  auto-provisioned controls" but only showed three, which was plainly wrong. The cards also made
  the three services look like different products when the whole point of the section is that
  they are interchangeable.
- Replaced the 3-column grid with a single tab strip (Ko-fi / Streamlabs / StreamElements) sharing
  the sky-underline pattern used by the Tags section's Live data / Live CSS / Alerts tabs. Below
  the tabs, one card swaps service name, tagline, description, and icon based on the active tab.
  The six auto-provisioned controls render in a 2-column grid with the namespace word accented in
  sky, so the "only this word changes" story is visible at a glance.
- Net effect: less real estate, more accurate (six controls shown, not three), and the unity of
  the three services reads visually.

## April 14th, 2026 - Welcome.vue: reverse subathon case study + Twitch bits in the `latest()` block

- A user wired up a reverse subathon (clock ticks down, donations subtract
  time, stream ends at zero) on top of three number controls plus a single
  `clamp()` expression. It's the cleanest demonstration of what expression
  controls actually enable, so it earns a dedicated case-study block on the
  landing page right below the `latest()` highlight.
- New "Case study" block walks through the three driving controls
  (`donathon_timer`, `deduction_per_donation`, `total_donations`) and shows
  the formula as the punchline:
  `clamp(c.donathon_timer - (c.deduction_per_donation * c.total_donations), 0, c.donathon_timer)`.
- Closing sky-tinted callout notes that swapping the `-` for a `+` converts
  the same expression into a classic add-time subathon. One-liner conversion,
  zero new controls.
- While in there, extended the existing `latest()` example code block from
  three pipes (Streamlabs, Ko-fi, StreamElements) to four by appending the
  Twitch bits pair (`c.twitch.latest_cheerer_name` / `latest_cheer_amount`).
  The H3 and supporting prose already said "three donation services plus
  Twitch bits" - the code block now matches.

## April 14th, 2026 - Welcome.vue: correct the `_at` caption in the latest() block

- The caption under the `latest()` highlight block previously said the pattern
  "works for anything you can pair with an `_at` field," which implied `_at`
  was a selective suffix on certain controls. Not true: every control in
  Overlabels automatically exposes an `_at` companion, and every timestamp on
  the platform is normalized to Unix seconds.
- Updated caption spells that out so the copy doesn't misrepresent the
  platform's timestamp contract.

## April 14th, 2026 - Welcome.vue: highlight latest() as the cross-service killer feature

- New highlighted block at the end of the Integrations section, right after the
  "shared alert template" example, featuring `latest()` as the single most
  differentiating feature on the landing page.
- Framing: every other overlay tool on the market is owned by a donation
  platform (Streamlabs, StreamElements, Ko-fi), so they all hide each other's
  donations by design. Overlabels is a neutral third party - so one `latest()`
  call across all three `_at` pairs gives the actual most-recent donor across
  the whole stream.
- Uses a real two-control example (the one a user wired up themselves):
  `c:latest_donator` and `c:last_donation_amount`, each a `latest()` call
  fanning across all three service namespaces.
- Visual treatment matches the existing sky-accent "Power combo" block in the
  Controls section (sky border + sky-tinted header, card-colored body) so it
  reads as a first-class highlight rather than an afterthought.
- Caption explains the mechanics in plain terms: `latest()` takes `(timestamp,
  label)` pairs, picks the highest timestamp, returns its paired label.
  Reactive, so the overlay catches up the instant a donation hits any pipe.

## April 14th, 2026 - Welcome.vue hero rewrite: lead with the substrate, not the task

- Prompted by the observation that the old hero ("Live overlays, for Twitch" +
  feature list) was pitched at an audience that doesn't exist for this product:
  non-devs get scared off by "HTML and CSS" on the same page, and the devs who
  stick around get nothing to latch on to. The rewrite leads with what kind of
  system Overlabels actually is.
- H1 now reads "Your overlay is a webpage. / We make it reactive." with the
  blue accent on the verb instead of the platform name.
- Hero subcopy names the three primary abstractions out loud - template tags,
  reactive expressions, pipe formatters - with inline code samples
  (`[[[tag]]]`, `c.wins / (c.wins + c.losses) * 100`) as proof-of-existence.
  People who recognise the syntax feel seen; people who don't get a soft
  self-select-out signal.
- Second paragraph reframes the product as "the reactive substrate" rather
  than something passively keeping a page live.
- `<Head>` title, meta description, OG, and Twitter card copy all updated to
  mirror the hero tone. Title is now
  "Overlabels - Reactive Twitch overlays for people who code".
- Nothing below the hero touched - the Controls / Conditionals / Events /
  Integrations sections already address technical readers.

## April 13th, 2026 - Expression controls: round() takes an optional decimals arg

- `useExpressionEngine.ts`: `round(x)` unchanged (returns a number via
  `Math.round`). New 2-arg form `round(x, n)` returns a string via
  `toFixed(n)`, so `round(0.1 + 0.2, 2) === "0.30"` with the trailing zero
  preserved - same semantics as the `|round:N` pipe formatter. `n` clamped
  to `[0, 100]` to stay within `toFixed`'s native range.
- Consequence of returning a string: math operators after a 2-arg
  `round()` concatenate rather than add. Help text in both surfaces calls
  this out and recommends putting `round(..., n)` at the end of an
  expression - or reaching for the `|round:n` pipe when the result is
  text-only.
- Controls help page links the inline "|round:2 pipe" mention to
  `/help/formatting` so users land on the full formatter docs.

## April 13th, 2026 - Expression controls: mod() is floor-based, not JS remainder

- `useExpressionEngine.ts`: `mod(a, b)` now evaluates as `a - b * floor(a / b)`
  instead of `a % b`. Caught by local testing: `mod(-1, 5)` was returning `-1`
  (JS remainder) when the animation-math expectation is `4` (GLSL/mathematical
  modulo). Floor-based mod always returns a result with the same sign as `b`.
- Help text in `ExpressionBuilder.vue` and `/help/controls` updated: `mod()` no
  longer claims parity with `%`. Each surface now spells out the distinction and
  points at the `%` operator for anyone who actually wants JS remainder.
- Divide-by-zero still returns `0` (unchanged).

## April 13th, 2026 - Expression help: float-precision note on fract / sin / cos

- `ExpressionBuilder.vue` Help dialog and `/help/controls`: added a short paragraph
  under the animation helpers calling out that `fract(10.2)` evaluates to
  `0.19999...993`, not `0.2`, because IEEE 754. Pitched as "expected, invisible for
  animation math, pipe through `|round:n` for display".
- Both notes link to `/help/formatting`. The in-builder dialog uses a plain anchor
  with `target="_blank"` so opening the formatter docs doesn't close the template
  editor; the public help page uses an Inertia `<Link>` matching the two existing
  "formatting pipes" references on the same page.

## April 13th, 2026 - Expression controls: sin, cos, fract, mod, PI

- `useExpressionEngine.ts`: added four functions and one constant to the evaluator
  whitelist. Requested by a web-animation dev who wanted to drive overlay values with
  trig/fract math.
  - `sin(x)`, `cos(x)` - radians, matching JS `Math.sin`/`cos`.
  - `fract(x)` - GLSL-style fractional part (`x - floor(x)`), so `fract(-0.3) === 0.7`.
  - `mod(a, b)` - identical to the `%` operator, including the divide-by-zero returns 0
    safety net. Kept as a function for readability in shader-style expressions.
  - `PI` - bare identifier, not a call. Added at the context root in `buildContext`;
    safe because user control keys live under the `c.` namespace.
- `SUPPORTED_FUNCTIONS` set extended so the in-builder preview validator accepts the
  new calls without falsely flagging them as unknown.
- `ExpressionBuilder.vue` Help dialog and `/help/controls` page: new chip row listing
  the animation helpers, with a note that `sin`/`cos` are radians and `PI` is a bare
  identifier.
- Drive-by: builder's Help dialog said `now()` returns milliseconds; it returns seconds.
  Corrected. Public help page already had it right.

## April 13th, 2026 - Help docs: bot section and shared help layout

- New `HelpLayout.vue` under `resources/js/layouts/` - wraps the `<Head>` meta block
  (title/description, OG, Twitter card, fixed OG image) and the `AppLayout` + container
  chrome that every help page was repeating. Pages now pass `breadcrumbs`, `title`,
  `description`, and `canonical-url` as props and render everything else into a default slot.
- New `HelpCardGrid.vue` under `resources/js/components/help/` - the icon-badged card grid
  from the `/help` landing, extracted so the bot landing can reuse it. Typed via a local
  `HelpCard` interface (title/description/href/icon).
- `/help/bot` landing page: short intro + a "How it works, in one paragraph" callout that
  summarises the chat -> bot -> API -> broadcast -> overlay loop for streamers, plus a
  card grid (currently one card, linking to Commands).
- `/help/bot/commands`: lists the five seeded commands (`!control`, `!set`, `!increment`,
  `!decrement`, `!reset`) with a color-coded permission-tier badge per command, a one-line
  summary, and one chat / bot-reply example each. Mentions that `!increment`/`!decrement`
  take an optional numeric amount.
- `/help` landing refactored onto the new layout - lost ~60 lines of duplicated meta
  boilerplate, gained a "Twitch Chat Bot" card linking to `/help/bot`.
- `routes/web.php`: added named routes `help.bot` and `help.bot.commands`.

## April 13th, 2026 - Milestone 5 Phase 2: bot commands + chat-writable controls

- New `bot_commands` table (user_id FK, command, permission_level, enabled, unique on
  user_id+command). Bot-side holds the response templates; we just store which commands
  exist per streamer and the minimum Twitch permission tier required to invoke them
  (everyone / subscriber / vip / moderator / broadcaster).
- `BotCommand::DEFAULTS` = the five seed commands: `!control` (everyone), `!set`,
  `!increment`, `!decrement` (moderator), `!reset` (broadcaster). Fixed response
  templates live in the bot repo; this side only enforces existence and permission.
- `UserObserver` seeds the default set on `bot_enabled` transitioning true -> via the
  idempotent `BotCommand::seedDefaults($user)`. Also handles the `created` case for
  users created with `bot_enabled=true`. `firstOrCreate` keeps it safe to re-run, so
  permission overrides survive a toggle-off/toggle-on cycle (verified by tests).
- `users.bot_enabled` added to `$fillable` - was the reason the observer looked dead
  under `->update(['bot_enabled' => true])` during the first test run.
- Three new internal endpoints under `/api/internal/bot`:
  - `GET /commands` - returns `{channels: {<lowercase_login>: [{command, permission_level}, ...]}}`,
    only enabled rows, only opted-in users with a resolvable `twitch_data.login`.
  - `GET /controls/{login}/{key}` - returns `{key, type, value, label}` for the first
    matching non-source-managed control. Uses `resolveDisplayValue()` so timers and
    random-mode controls return the right thing, not the raw stored value.
  - `POST /controls/{login}/{key}` - validates `action` (set|increment|decrement|reset)
    + optional `value`/`amount`. Applies to every non-source-managed control with that
    key for the user (a key can exist on multiple templates), dispatches
    `ControlValueUpdated` for each.
- Service-managed controls (Ko-fi `donations_received`, StreamLabs counters, etc.) are
  invisible to the bot: the `source_managed=false` filter in the read/write queries
  means chat commands 404 on them instead of leaking the kofi:-namespaced value or
  allowing chat to bump a donation counter. When we want to expose those to chat later,
  it's a deliberate addition rather than an accident.
- Route constraints pin `login` to `[a-z0-9_]+` and `key` to `OverlayControl::KEY_PATTERN`
  so malformed chat input falls out at routing instead of hitting the controller.
- `php artisan test` -> 241 passed (24 new tests in `BotInternalApiTest.php`:
  observer seed-on-opt-in + idempotency, commands shape + filtering, controls show
  shape + source-managed hidden, all four write actions, validation, 404 paths,
  ControlValueUpdated dispatch).

## April 13th, 2026 - Milestone 5 Phase 1: Twitch bot foundation (Laravel side)

- New `bot_tokens` table (single-row by `account` unique constraint) storing the @overlabels
  account's OAuth tokens. `BotToken` model uses Laravel's `'encrypted'` cast on `access_token`
  and `refresh_token` so they're at-rest encrypted in Postgres - verified by a test that asserts
  the raw column does not contain the plaintext.
- `users.bot_enabled` boolean (default false, indexed) - per-user opt-in for the bot to join
  their channel. Streamers will toggle this in a settings page in Phase 3; for now the column
  exists and the channel-list endpoint already filters on it.
- `VerifyBotListenerSecret` middleware (alias `bot.internal`) checks the `X-Internal-Secret`
  header against `config('services.twitchbot.listener_secret')`, matching the StreamLabs/SE
  pattern. Three internal endpoints behind it: `GET /api/internal/bot/channels` (lowercase
  Twitch logins of opted-in users), `GET /api/internal/bot/tokens`, and `POST /api/internal/bot/tokens`
  (for refresh persistence from the bot service - returns 204).
- Admin-only OAuth flow at `GET /auth/twitchbot` -> `GET /auth/twitchbot/callback` (callback URL
  matches the Twitch app's registered redirect URI). Exchanges the authorization code for tokens,
  stores them via `BotToken::updateOrCreate`, redirects to a small `/admin/twitchbot` status page.
  Scopes requested: `user:read:chat user:write:chat user:bot` - exactly what Twurple's EventSub
  chat client needs. `force_verify=true` so the admin can sign into a different Twitch account
  (the bot account) than they're currently logged into on twitch.tv.
- `config/services.php` gains a `twitchbot` block with `client_id`, `client_secret`,
  `redirect` (defaults to `${APP_URL}/auth/twitchbot/callback`), and `listener_secret`.
- `MILESTONES.md`: collapsed MS5a/b/c into a single MS5 entry reflecting the architectural
  decisions (shared @overlabels account, separate Node repo on Railway, Twurple/EventSub Chat,
  internal API contract).
- Bot service (overlabels-bot) lives in a separate repo and Railway service. Pulls tokens at
  startup and POSTs back after Twurple refreshes them - so once the admin completes OAuth here,
  the bot is unblocked without anything pasted into Railway env vars except the listener secret
  and the Twitch app credentials.
- `php artisan test` -> 217 passed (14 new tests in `BotInternalApiTest.php` covering 403 paths,
  empty-list, lowercase logins, missing-login skip, 404-when-no-tokens, encrypted-at-rest,
  validation errors, upsert).

## April 13th, 2026 - Cleanup: remove dead OverlayHash code path

- `OverlayHash` was an older hash-based public-link scheme for overlays that was fully superseded
  by `OverlayAccessToken` (64-char hex token in the URL fragment, sha256 stored server-side). The
  model, controller, and factory were still sitting in the repo but no routes referenced them -
  confirmed via grep on `routes/`. Removed:
  - `app/Models/OverlayHash.php`
  - `app/Http/Controllers/OverlayHashController.php` (also contained a `Log::info($hash)` that
    would have leaked hash_key values to logs had the controller ever been wired up again)
  - `database/factories/OverlayHashFactory.php`
  - `DefaultTemplateProviderService::getCompleteDefaultHtml()` and `getPreviewHtml()` -
    only ever called from the dead controller.
- Migrations for `overlay_hashes` are kept intact (they represent historical DB state on existing
  deployments). The table is unused going forward.
- Updated `CLAUDE.md` Overlay System section to reflect the single auth mechanism.
- Part of Milestone 4.5 (Security Audit & Dead Code Removal). `php artisan test` -> 203 passed.

## April 13th, 2026 - Security: HTML-encode substituted tag values in OverlayRenderer

- Fixed an indirect XSS vector where donor-supplied strings (Ko-fi / StreamLabs / StreamElements
  donor names, donation messages, etc.) were substituted into the overlay HTML string without
  HTML-encoding before being rendered via `v-html`. A donor could craft a name or message
  containing `<script>` or attribute-breaking quotes and execute script in the OBS browser source
  context. `strip_tags()` on the server side is not sufficient (it doesn't encode `"`, `'`, `=`).
- `OverlayRenderer.vue`: added `encodeHtml()` helper and an `encode` flag to
  `replaceTagsWithFormatting` / `parseSource`. The HTML paths (`compiledHtml`, `compiledAlertHtml`)
  encode `&`, `<`, `>`, `"`, `'` on substituted values; the CSS path (`compiledCss`) skips
  encoding because `style.textContent` is not HTML-parsed.
- Impact bounded to the isolated overlay document (no session, no dashboard pivot), but the fix
  closes the attribute-break / script-injection surface for all donor-controlled control values.

## April 13th, 2026 - UX: Welcome page copy polish, Twitch "Connect" button rework

- Welcome page: small copy tweak under the "Event tags are merged with your static overlay data" paragraph,
  and swapped one stray muted paragraph to `text-foreground` for better contrast.
- `LoginSocial.vue`: switched the "Login with Twitch" button to a constrained anchor styled as a "Connect"
  CTA so it can be dropped into marketing copy inline. Keeps the same `loginWithTwitch` handler, but now
  stops event propagation so a parent click-handler doesn't swallow the navigation.

## April 13th, 2026 - UX: one-test-cheer-per-minute cooldown with live countdown

- "Send test cheer" is now rate-limited client-side to one fire per minute. On success the button label flips
  to `Wait 59s`, `Wait 58s`, ... and disables itself until the cooldown clears - matching the 60s lifetime of
  the `DeleteTestTwitchEvent` job so there's only ever one synthetic event row alive at a time.
- The status line under the button now explains what just happened: "Thanks for testing! Fired N bits from
  TestCheerer. This event will disappear from your logs in ~60 seconds, and you can only fire one test cheer
  per minute to keep things tidy." Amber warnings still append when the mapping is missing or the stream
  isn't live.
- Uses `setInterval` with `onBeforeUnmount` cleanup so leaving the settings page doesn't leak timers.

## April 13th, 2026 - UX: test cheers vanish from event logs after 1 minute

- "Send test cheer" still persists a `TwitchEvent` row so the cheer appears briefly in the activity feed
  (useful confirmation that the fire actually happened), but now dispatches a `DeleteTestTwitchEvent` queued
  job with a 60-second delay to remove that row afterwards. Matches StreamElements' UX where test tips show
  up in the dashboard until you leave the page or refresh - here they just physically disappear from the DB
  after a minute so the activity log doesn't fill up with synthetic entries over time.
- New `App\Jobs\DeleteTestTwitchEvent` (2 tries, idempotent - delete-by-id no-ops if the row is already gone).

## April 13th, 2026 - Feature: "Send test cheer" button on the Twitch integration card

- Added a "Send test cheer" button to the Twitch section of the integrations settings page, visible once
  EventSub is connected with at least one active subscription. Clicking it fires a synthetic `channel.cheer`
  event with a random bits amount between 100 and 1000 from a `TestCheerer` donor, without requiring the
  Twitch CLI or a real fan. Useful for iterating on cheer alert templates and bits-driven controls.
- New `TwitchEventSubController::testCheer()` method builds the same payload shape Twitch EventSub uses
  (`broadcaster_user_id`, `user_name`, `is_anonymous`, `message`, `bits`) and runs it through the identical
  pipeline a real event takes: writes a `TwitchEvent` row, calls `StreamSessionService::handleEvent()` (which
  still gates control updates on `isConfidentlyLive()`), looks up the enabled `channel.cheer` mapping and
  renders the alert, and broadcasts `TwitchEventReceived` so the activity feed reflects it.
- Wired at `POST /twitch/test-cheer` (auth-protected via the web route group).
- Response JSON reports back what happened: `alert_fired` is false if the user hasn't mapped a template to
  `channel.cheer`, `controls_updated` is false if the stream isn't confidently live. The UI surfaces both
  conditions as amber hints (the live-state hint points to `php artisan stream:fake-live {twitch_id}`) so the
  streamer knows why an overlay might have stayed silent.

## April 13th, 2026 - Tooling: `stream:fake-live` and `stream:fake-offline` artisan commands

- Added two artisan commands in `routes/console.php` (following the existing `lockdown:engage` / `lockdown:release`
  closure-command pattern) to make Twitch CLI testing actually usable. The controls pipeline gates on
  `StreamState::isConfidentlyLive()`, so `twitch event trigger channel.cheer ...` never updates per-stream counters
  unless the state machine believes the channel is live - which a faked payload cannot achieve on its own.
- `php artisan stream:fake-live {twitch_id}` opens a real `StreamSession` via `StreamSessionService::openSession()`
  (which also resets twitch source_managed controls), then flips the `StreamState` row to `live` with confidence
  1.0, stamps `last_event_at`/`last_verified_at` to now, and clears `grace_period_until`. `channel.cheer` and the
  other countable events will now update controls as if you were really streaming.
- `php artisan stream:fake-offline {twitch_id}` calls `StreamSessionService::closeSession()` and flips the row back
  to `offline` with confidence 1.0, clearing `current_session_id`, `helix_stream_id`, and the grace timer.
- The commands include a nudge that the safety-net scheduler (every 5 min) will dispatch `VerifyStreamState` for
  rows with a stale `last_verified_at`, so a forgotten fake-live session will eventually get reconciled against
  Helix and fall back to offline on its own - the companion `fake-offline` call is still the clean way to end it.

## April 13th, 2026 - UX: Combobox replaces the massive preset picker in the Add Control modal

- Replaced the huge native `<select>` in `ControlFormModal.vue` (Stream Controls > preset picker) with a searchable
  Combobox built on Reka UI primitives. Users can now type to filter across every preset from every connected service
  instead of scrolling a wall of optgroups - for example, typing "bits" immediately narrows to the new Twitch
  bits/cheer presets, and typing "latest" surfaces `latest_donor_name`, `latest_cheerer_name`, etc. across Ko-fi,
  StreamLabs, StreamElements, and Twitch.
- Added a new shadcn-style wrapper set under `resources/js/components/ui/combobox/`: `Combobox`, `ComboboxAnchor`,
  `ComboboxInput`, `ComboboxTrigger`, `ComboboxContent`, `ComboboxEmpty`, `ComboboxGroup`, `ComboboxLabel`,
  `ComboboxItem` (plus barrel `index.ts`). Styling matches the rest of the app (Overlabels violet focus ring on the
  anchor, `data-[highlighted]` and `data-[state=checked]` item states, `rounded-sm` popover, `max-h-72` scrolling
  viewport) and the content uses Reka's `--reka-combobox-trigger-width` CSS variable so the dropdown matches the
  anchor's width.
- Filtering is Reka's built-in `useFilter` `contains` (case/accent-insensitive), so no custom filter code was needed.
  The input displays the selected preset's human label via a `display-value` function that splits the stored
  `source:key` composite.

## April 13th, 2026 - Feature: Twitch Bits/Cheer preset controls (parity with donation services)

- Added five new Twitch stream controls so `channel.cheer` payloads can drive overlays the same way Ko-fi,
  StreamLabs, and StreamElements donations already do: `cheers_this_stream` (counter, +1 per cheer event),
  `bits_this_stream` (number, accumulates the bits amount), `latest_cheerer_name`, `latest_cheer_amount`,
  and `latest_cheer_message`. Available from the Stream Controls preset picker on any static template.
- `StreamSessionService::EVENT_CONTROL_MAP` now includes `channel.cheer => cheers_this_stream`, and
  `CONTROL_PRESETS` gained the five new entries (also surfaced in `OverlayControlController::store` for
  preset-based provisioning and in `controlPresets.ts` for the Controls Manager UI).
- `StreamSessionService::handleEvent()` now accepts the full event payload and has a dedicated
  `channel.cheer` branch that accumulates bits on top of `bits_this_stream`, and `set`s the latest-cheer
  trio from `event.user_name` (falling back to "Anonymous" when `is_anonymous`), `event.bits`, and
  `event.message`. The increment/broadcast boilerplate was extracted into a private
  `applyTwitchControl()` helper so the counter path and the cheer path share one code path.
- `TwitchEventSubController@handleTwitchEvent` now passes the `$event` array when forwarding to
  `handleEvent()`, so the cheer handler can read `bits`, `user_name`, `is_anonymous`, and `message`.
- Like the other per-stream counters, all five cheer controls reset when the stream goes live via
  `resetControls()` (no changes needed - it already loops every twitch source_managed control for the user).
- Template tag syntax: `[[[c:twitch:cheers_this_stream]]]`, `[[[c:twitch:bits_this_stream]]]`,
  `[[[c:twitch:latest_cheerer_name]]]`, `[[[c:twitch:latest_cheer_amount]]]`,
  `[[[c:twitch:latest_cheer_message]]]`.

## April 13th, 2026 - Feature: duplicate controls from the Controls Manager

- Added a Duplicate button to each row in `ControlsManager.vue` (new `CopyPlusIcon` next to edit/delete). Clicking it
  opens `ControlFormModal.vue` in add mode with every field (type, value, full config, expression text, boolean state)
  pre-populated from the source control. The label becomes `"<original> (copy)"` so the auto-slugify watcher derives a
  fresh key (e.g. `reel_1` -> `reel_1_copy`); the user can tweak anything and save.
- New `copyFrom` prop on `ControlFormModal.vue`; dialog title switches to "Duplicate Control". The service preset
  picker is hidden while copying so selecting a preset can't silently wipe the duplicated values. `source_managed`
  rows don't show the Duplicate button since those need to go through the service preset flow.
- Fixed a broken link on the StreamElements settings page: the "fire a few test tips" CTA pointed to
  `streamelements.com/dashboard/activities` (404); corrected to `/dashboard/activity`.

## April 12th, 2026 - Fix: align StreamElements with donation-family naming and wire Preset Controls

- Aligned StreamElements with Ko-fi and StreamLabs donation-family naming so a single alert template
  (`[[[if:event.type = donation]]]`) fires for every donation source. `parseEventType()` maps SE's `tip` to
  `donation`; driver, controller, settings page, tests, and changelog all now use donation-family keys.
- Fixed a silent bug in `StreamElementsServiceDriver::getControlUpdates()`: it checked `!== 'tip'` while
  `parseEventType()` returned `'donation'`, so no control updates ever ran. Now checks `!== 'donation'`.
- Fixed a copy-paste bug in `ControlFormModal.vue` where the StreamElements optgroup carried `label="StreamLabs"`.
- Renamed controller method `seedTipCount()` -> `seedDonationCount()`, settings keys `tips_seed_set`/`tips_seed_value`
  -> `donations_seed_set`/`donations_seed_value`, and control keys `tips_received` -> `donations_received`,
  `latest_tipper_name` -> `latest_donor_name`, `latest_tip_*` -> `latest_donation_*`, `total_tips_received` ->
  `total_received`.
- Existing StreamElements integrations need a one-time disconnect + reconnect from the settings page to drop the
  old tip-family controls and reprovision under the new donation-family keys.

## April 12th, 2026 - Feature: StreamElements tipping integration

- Added StreamElements as a full External Integration, authenticated via user-supplied JWT tokens. StreamElements does
  not offer self-serve OAuth app registration, so the integration uses the JWT path that every user can generate from
  their StreamElements dashboard: Account > Channels > Show secrets > JWT Token.
- New `StreamElementsServiceDriver` mirrors the StreamLabs pattern: verifies listener requests, normalizes the SE tip
  payload (`_id`, `data.displayName`/`username`, `amount`, `message`, `currency`, `tipId`), maps SE's `tip` event type
  to `donation` (aligning with Ko-fi and StreamLabs so one alert template can target `[[[if:event.type = donation]]]`
  for all three sources), and auto-provisions six controls (`donations_received`, `latest_donor_name`,
  `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`).
- Internal API endpoint `GET /api/internal/streamelements/integrations` is polled by the listener every 60s and
  returns the per-user JWT plus listener secret for each active integration. Authenticated with
  `STREAMELEMENTS_LISTENER_SECRET`.
- New Node.js listener (`streamelements-listener.mjs`) bridges `realtime.streamelements.com` Socket.IO events to the
  Laravel webhook endpoint. Authenticates with `{ method: 'jwt', token: jwtToken }`, reconnects when a user rotates
  their JWT, and drops sockets on `unauthorized` (JWT revoked server-side).
- New settings page at `/settings/integrations/streamelements` with a password-type JWT input, save/replace button,
  test mode toggle, one-time starting tip count seed, and a link to the StreamElements dashboard.
- Template tag syntax: `[[[c:streamelements:donations_received]]]`, `[[[c:streamelements:latest_donor_name]]]`, etc.
- Added `STREAMELEMENTS_LISTENER_SECRET` to `.env.example` and `config/services.php`. Registered in
  `ExternalServiceRegistry` and `ExternalEventTemplateMapping::SERVICE_EVENT_TYPES`.
- 29 new tests (driver, JWT save/disconnect, webhook) covering payload normalization, dedup, listener secret
  generation, JWT replacement, and control provisioning.

## April 11th, 2026 - Feature: sum, avg, now() and help page docs for expression functions

- Added `sum()`, `avg()`, and `now()` to the expression engine function set.
- `now()` returns Unix epoch seconds (matching `_at` companion values), enabling patterns like
  `now() - max(c.kofi.latest_donor_at, c.streamlabs.latest_donor_at)`.
- Updated `/help/controls#type-expression` with new code examples (`latest()`, `now() - max()`), a full "Available
  functions" reference, and two new best practices: "Use functions instead of nested ternaries" and "Use now() to track
  time since an event".
- Changed ExpressionBuilder help dialog example action from insert-at-cursor to copy-to-clipboard with "Copied!"
  feedback.

## April 11th, 2026 - Feature: function calls in Expression Engine

- Added `CallExpression` support to the expression engine evaluator, enabling function calls in expressions.
- New **arg-family functions**: `latest()`, `oldest()`, `argmax()`, `argmin()` - accept pairs of `(value, label)`
  arguments and return the label paired with the highest/lowest value. Handles numeric values and ISO date/timestamp
  strings. First pair wins on ties.
- New **scalar math functions**: `max()`, `min()`, `abs()`, `round()`, `floor()`, `ceil()`.
- `latest()` and `oldest()` are aliases for `argmax()` and `argmin()` - named to match Twitch vocabulary ("latest
  subscriber", "latest donor").
- `ExpressionBuilder.vue` now validates function calls at parse time: unknown function names and odd argument counts on
  arg-family functions show clear inline errors.
- Updated the Expression help dialog with a new "Functions" section documenting all available functions.
- Replaced the cross-service comparison example with the cleaner `latest()` syntax.

## April 11th, 2026 - Refactor: extract PublicToggle component

- Extracted the duplicated public/private toggle block from `templates/create.vue`, `templates/edit.vue`, and
  `kits/edit.vue` into a shared `PublicToggle.vue` component.
- Component accepts `v-model` (boolean) and a `label` prop ("Overlay" or "Kit") to customize the displayed text.
- Removed unused `CheckCheck` and `Square` icon imports from all three pages.
- Removed outdated `rounded-sm` from the kits/edit usage.

## April 11th, 2026 - UX: collapsible and searchable control groups in ControlPanel

- Control groups in `ControlPanel.vue` are now collapsible (using Reka Collapsible) with expand/collapse all toggle.
  Collapse state is persisted to localStorage.
- Added a search bar that filters controls by label, key, tag key, or group name, with a count summary and empty-state
  message.
- Styled collapsible group headers with chevron icon, group name, and control count badge.
- Polished control card styling: sidebar backgrounds, softer source-managed badge colors, tooltip on Offline badge.
- Added `for`/`id`/`name` attributes to control labels and inputs for accessibility.
- Removed hardcoded `rounded-l-md` from the shared `.input-border` class so border radius can be set per-use.
- Swapped `<Input>` component to plain `<input>` with `input-border` in the TemplateTagsList search bar for consistency.
- Changed body copy color from `text-muted-foreground` to `text-foreground` in ControlPanel, ControlsManager, and
  TemplateTagsList descriptions.

## April 11th, 2026 - UX: ControlPanel rewrite with grouped controls and managed state

- Rewrote `ControlPanel.vue` to organize controls into labeled groups by type (Counter, Timer, Number, Text, Toggle,
  Expression, Date/Time), with external service controls (Ko-fi, StreamLabs, GPS Logger) displayed in their own named
  sections.
- Fixed template tag keys for external service controls: now correctly displays `c:kofi:kofis_received` instead of the
  incorrect `c:kofis_received`. The `tagKey()` helper builds the proper namespaced key for any source.
- Source-managed controls (updated automatically by external services) now render as read-only value displays instead of
  interactive widgets. They get a dashed border, muted background, and a lock icon "Managed" badge to clearly
  communicate that values are externally controlled.
- Removed leftover debug `<pre>` output from control cards.
- Fixed `$request->locale` in `routes/settings.php` - Symfony's base Request class has a protected `$locale` property,
  so magic `__get` access collides. Changed to `$request->input('locale')`.

## April 11th, 2026 - Security: comprehensive XSS sanitization for overlay templates

Overlabels lets users write raw HTML/CSS for their overlays - that's the whole point. But with great `v-html` comes
great responsibility. After the axios prototype pollution vulnerability (CVE score 10.0/10, patched via Dependabot in
#99) prompted a broader security review, we ran a full XSS audit against the overlay template system. The existing
sanitizer only stripped `<script>` tags. That's it. Everything else walked right through.

Here's what we tested and what we found:

**Previously blocked (by the old script-only sanitizer):**

- `<script>` tags and variants - stripped on save

**Previously NOT blocked (now fixed):**

- `<form action="javascript:alert(1)">` - **executed perfectly**. This was the big one.
- `<svg onload=alert(1)>`, `<img onerror=...>`, `<div onclick=...>`, and every other inline event handler (`on*`
  attributes) - all stripped now
- `javascript:` URIs in `href`, `action`, `src`, `data`, `formaction`, `xlink:href` attributes - all stripped now
- HTML-entity-encoded `javascript:` URIs (`&#106;&#97;&#118;...`) that browsers silently decode back to `javascript:` -
  caught via entity decoding before pattern matching
- `<meta http-equiv="refresh" content="0;url=javascript:...">` - stripped now
- `javascript:` inside CSS `url()` expressions - replaced with `url(about:blank)`
- `<form>` blocks stripped entirely - overlays are display-only and should never submit data anywhere. This is a
  philosophical decision: overlays are "dumb by nature."

**Already safe (browser won't execute):**

- `<div style="width: expression(...)">` - CSS expressions are dead in modern browsers
- `<object data="javascript:...">` - ignored by browsers
- `<iframe src="data:text/html,...">` - script content inside stripped, leaving inert empty data URI

**What changed:**

- Rewrote `resources/js/utils/sanitize.ts` from a single `<script>`-only regex into a multi-layer sanitizer covering
  event handlers, javascript URIs (plain and entity-encoded), form blocks, meta refresh tags, and CSS url() expressions.
  Removed unused `stripScripts` function.
- Created `app/Services/HtmlSanitizationService.php` - server-side sanitizer with the same coverage. This is the
  authoritative security layer since client-side sanitization can always be bypassed with curl/Postman.
- Wired `HtmlSanitizationService::sanitizeTemplateFields()` into both `store()` and `update()` in
  `OverlayTemplateController`.
- Updated `create.vue` and `edit.vue` to use the new `sanitizeHtmlFields` function with improved toast messaging.
- Stripped all interactive/input elements entirely: `<form>`, `<button>`, `<input>`, `<textarea>`, `<select>`,
  `<object>`. Overlays are "dumb by nature" - they display data, they never submit it. There is no legitimate reason for
  any of these elements to exist in an overlay template.
- Nuked `<iframe>` and `<embed>` entirely. Instead of maintaining a safelist of "approved" embed domains (maintenance
  nightmare, bypass potential, subdomain sprawl, support burden), we removed all embeds and added an "integration
  suggestion" flow: when a user tries to embed external content and saves, the toast offers a "Suggest integration" link
  that opens a modal. Users can submit the service URL, a description, and optional context. Submissions are forwarded
  to a configurable Discord webhook (`INTEGRATION_SUGGESTION_WEBHOOK_URL` in .env) with a violet embed showing who
  suggested it and what they want. Rate limited to 3 suggestions per hour per user.
- 27 unit tests covering all attack vectors plus safe HTML preservation.

Normal overlay HTML (divs, styles, images, links, forms with real actions, template tags) passes through completely
untouched.

## April 10th, 2026 - UX improvements to overlay show page and template tags

- Replaced native browser `confirm()` on OBS URL generation with the styled LinkWarningModal used elsewhere in the app.
- OBS URL dialog can no longer be closed by clicking outside, pressing Escape, or the X button. Users must check a
  confirmation checkbox before a Close button appears, preventing accidental loss of the one-time token URL.
- Added OBS Browser Source settings screenshot modal accessible from the setup steps.
- Improved button styles: new `.btn-plain`, `.btn-secondary` (blue), `.btn-tertiary` (pink, formerly secondary), and
  size classes `.btn-md`, `.btn-l`, `.btn-xl`.
- Template tags in TemplateMeta are now interactive: click to copy the tag (wrapped in `[[[...]]]` syntax) to clipboard
  with a brief "Copied!" indicator that preserves button width to prevent layout shift.
- Template tags can be sorted by order of appearance (default) or alphabetically.
- TemplateMeta now shows slug and owner fields.

## April 9th, 2026 - Fix: Ko-fi Shop Orders and Commissions not updating controls

- Ko-fi Shop Order webhooks only incremented `kofis_received` and set `latest_donor_name`, skipping
  `latest_donation_amount`, `latest_donation_currency`, `latest_donation_message`, and `total_received` despite the
  payload containing all those fields.
- Ko-fi Commission webhooks were not handled at all by `getControlUpdates()`.
- All four Ko-fi event types (Donation, Subscription, Shop Order, Commission) now update the full set of controls
  identically.

## April 9th, 2026 - Feature: Copy wizard warns about missing integrations

- When copying a template that uses external service controls (Ko-fi, StreamLabs, etc.), the import wizard now detects
  which services the template HTML references.
- Compares against the destination user's connected integrations and shows a clear notice: amber warning for missing
  services with a link to Settings, or a green confirmation if everything is connected.
- The wizard now opens even when a template has no regular controls but does reference external services, so users are
  never left wondering why service tags don't resolve.

## April 9th, 2026 - Fix: Copy from edit page now shows the import wizard

- Copying a template from the edit page skipped the ForkImportWizard and went straight to the new template without
  importing controls.
- Added the ForkImportWizard component and wired up the wizard state from `useTemplateActions`, matching the show page
  behavior.

## April 9th, 2026 - Fix: Copying a template now includes control values

- Copying a template previously created controls with empty values, requiring users to re-enter everything manually.
- The fork flow now threads `value` through the entire pipeline: model query, wizard UI, and backend import endpoint.
- All control values (text, number, etc.) are carried over from the source template.

## April 9th, 2026 - Fix: Overlays now auto-refresh expired Twitch tokens

- Overlays were losing Twitch auth because the `/api/overlay/render` endpoint never refreshed expired Twitch tokens. The
  dashboard did this automatically via `EnsureValidTwitchToken` middleware, but the overlay path (token-based,
  stateless) bypassed it entirely.
- Added `TwitchTokenService::ensureValidToken()` call to `renderAuthenticated()` so overlays silently refresh tokens the
  same way the dashboard does.
- This eliminates the health status banner that appeared after Twitch token expiry, especially after server restarts.

## April 9th, 2026 - Feature: Prune unused tokens on admin panel

- Added a prune bar to the admin tokens page, matching the existing event/log pruning pattern.
- Prunes tokens with 0 uses older than 6, 12, or 24 months (or all unused tokens).
- Only deletes tokens that have never been used (`access_count = 0`) - active tokens are always safe.
- Audit logged as `tokens.pruned` with period and count.

## April 8th, 2026 - Feature: Add to OBS generates a ready-to-use URL

- The "Add to OBS" button on the template page now generates a fresh secure token and returns a complete OBS URL with
  the token already embedded.
- Users no longer need to find, save, or manually stitch their token into a URL - just click, copy, paste into OBS.
- The URL is shown once in a dialog with clear steps: copy, add Browser Source, paste, done.
- Each click creates a new token named "OBS - [template name]" for easy identification on the Access Tokens page.

## April 8th, 2026 - Copy: Use text-foreground for body copy on help pages

- Replaced text-muted-foreground with text-foreground on all body copy across help pages.
- Muted is now reserved for genuinely secondary text like card descriptions and event type subtitles.

## April 8th, 2026 - Docs: Gift bomb detection explained on help page

- Added a detailed explanation of the gift bomb detection system to the Subscription Gifts section on the Conditionals
  help page.
- Explains why the system exists (Twitch sends individual events per gift), how it works (8-second collection window,
  live counter updates), and display durations per gift count tier.
- Includes a conditional template example for styling large gift bombs differently.

## April 8th, 2026 - Feature: Active events modal on integrations page

- The "Listening to 9 events" text on the integrations page now has a clickable link that opens a dialog showing all
  supported Twitch EventSub events with their human-readable labels.
- Each event shows a checkmark or cross indicating whether it's currently active for the user.
- Added `getSupportedEventLabels()` to `UserEventSubManager` to map event types to friendly names, kept in sync with
  `SUPPORTED_EVENTS`.

## April 8th, 2026 - Fix: Remove stale Twitch-side subscriptions before recreating

- `removeUserSubscriptions` only deleted subscriptions tracked in the local DB. If the DB was out of sync (e.g. from the
  broken queue job), Twitch-side subscriptions remained, causing 409 Conflict on recreation.
- Now also fetches all subscriptions from Twitch's API and deletes any belonging to the user by matching
  `broadcaster_user_id` / `to_broadcaster_user_id` in the condition.

## April 8th, 2026 - Fix: EventSub Connect button not working

- The Connect button dispatched a `SetupUserEventSubSubscriptions` job that never ran (likely serialization issue with
  the private `$user` property).
- Changed the connect endpoint to run setup synchronously via `UserEventSubManager` instead of dispatching a queue job.
  The user now gets immediate feedback with actual results (created/existing/failed counts).
- Removed the 3-second delay before page reload - the page now reloads immediately after setup completes.
- The queued job is still used for auto-connect on login (where synchronous execution would block the login flow).

## April 8th, 2026 - Fix: EventSub subscription status stuck at pending

- Race condition: Twitch sends the challenge verification request before the queue worker finishes storing the
  subscription record in the database. The challenge handler's `WHERE twitch_subscription_id = ...` update silently
  matches 0 rows, so the status stays `webhook_callback_verification_pending` forever.
- Added a `verifyUserSubscriptions` call at the end of `setupUserSubscriptions` that reconciles local status with
  Twitch's actual status. By the time all 9 subscriptions are created, earlier challenges have completed, so the
  verification picks up the correct `enabled` status.
- This fixes the Settings > Integrations page showing "No active subscriptions" and the yellow reconnect warning despite
  subscriptions working correctly.

## April 8th, 2026 - Fix: align webhook secrets across subscription creation paths

- `TwitchEventSubService` subscribe methods now accept an optional `$webhookSecret` parameter instead of always
  hardcoding the global secret.
- `TwitchEventSubController::connect()` now passes the user's per-user `webhook_secret`, matching what
  `UserEventSubManager` already does.
- Previously, subscriptions created via `connect()` used the global secret while `UserEventSubManager` used the user
  secret, causing duplicate subscriptions with mismatched secrets and Twitch retry storms on every event.
- After deploying, do a disconnect + reconnect from Settings > Integrations to recreate subscriptions with the correct
  secret.

## April 8th, 2026 - Debug: improved logging for invalid webhook signature

- Replaced the non-functional `Log::warning('Twitch webhook signature', $request)` with structured diagnostic data:
  subscription ID, message ID, event type, broadcaster ID, whether the user has a per-user webhook secret, and whether
  the global secret is configured.

## April 8th, 2026 - Fix: Dashboard stream status not updating in real-time

- The dashboard app did not initialize Echo/WebSocket, so `StreamStatusChanged` broadcasts from the stream state machine
  were never received by the frontend.
- Added Echo/Reverb initialization to `app.ts` (matching the existing overlay app setup).
- Updated `useStreamState` composable to listen for `.stream.status` events on the user's `alerts.{twitchId}` channel,
  so the live dot, transitioning indicator, and uptime counter update in real-time without requiring a page refresh.

## April 7th, 2026 - Fix: EventSub webhook challenge response

- Replaced the `exit()` hack in the webhook challenge handler with a proper Laravel `response()`.
- The `exit()` call was killing the PHP process before the challenge response could be properly flushed through
  Railway's reverse proxy, causing all EventSub subscriptions to get stuck at `webhook_callback_verification_pending`.
- Challenge handler now marks the local subscription record as `enabled` immediately after responding, so the UI
  reflects the correct status without waiting for a health check or manual refresh.
- Connect/Reconnect button now always force-recreates subscriptions, cleaning up stale/pending ones first.
- This fixes EventSub subscription creation for all users.

## April 7th, 2026 - UI: EventSub connection management on Integrations settings page

- Added a Twitch EventSub card to the Settings > Integrations page showing subscription count, active count, and
  connected-since date.
- Connect/Reconnect button dispatches the `SetupUserEventSubSubscriptions` job to create or recreate all EventSub
  subscriptions.
- Refresh button verifies existing subscriptions with Twitch and renews any that have failed.
- Shows a yellow warning when `eventsub_connected_at` is set but no active subscriptions exist (e.g. after user deletion
  and re-registration).

## April 7th, 2026 - Feature: Stream state machine with confidence-based verification

- Implemented a deterministic state machine (offline -> starting -> live -> ending) that confidently determines whether
  a user is currently streaming.
- EventSub `stream.online` and `stream.offline` events now only trigger state transitions, not define truth. The Twitch
  Helix API (`GET helix/streams`) is the authoritative source.
- Confidence score (0.0-1.0) builds in 0.25 increments through Helix verification. Transitions to "live" or "offline"
  require confidence >= 0.75.
- `VerifyStreamState` queue job polls Helix every 10-60 seconds depending on state: 10s during starting/ending, 60s
  heartbeat during live.
- 120-second grace period in "ending" state handles OBS crashes and connection drops - if the stream comes back, the
  session is stitched (reused) instead of creating a new one.
- Session stitching: if a stream goes offline and comes back within 5 minutes, the existing session is reopened rather
  than creating a new session.
- Retroactive repair: session `started_at` is corrected to match Twitch Helix's `started_at` for accuracy.
- New `stream_states` table as the single source of truth for stream status per user.
- Added `stream_session_id` FK to `twitch_events` and `external_events` tables for future event grouping (query all
  events that happened during a stream session).
- Added `helix_stream_id` to `stream_sessions` table to store the Twitch stream ID.
- `StreamSessionService::isLive()` and `handleEvent()` now check confidence-based state instead of raw session
  existence.
- Per-stream controls (follows, subs, raids, etc.) only increment when confidence >= 0.75 and state is "live".
- `StreamStatusChanged` broadcast now includes `state`, `confidence`, and `startedAt` fields (backward-compatible -
  existing overlay listeners unaffected).
- Cached app access token in `TwitchEventSubService` (50-minute cache) to avoid redundant token requests during
  verification loops.
- Safety-net scheduler runs every 5 minutes to catch stuck states (if verification job chain breaks).
- Minimal frontend: green dot on user avatar when live, pulsing orange dot when transitioning, uptime tooltip.
- New `useStreamState` composable for reactive stream state in the frontend.
- Edge cases handled: missed offline events (heartbeat catches them), offline without prior online, OBS crash, quick
  restarts, overlapping sessions, Twitch API downtime.

## April 7th, 2026 - Fix: Test mode toggle now resets all service controls

- Disabling test mode for Ko-fi and StreamLabs now resets all source-managed controls to their defaults, not just the
  donation counter.
- Resets `total_received`, `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, and
  `latest_donation_currency` alongside the count key.
- Counter/number controls reset to '0' (or the seed value for the count key), text controls reset to empty.

## April 7th, 2026 - Fix: Timer controls not working in expressions

- Timer controls (and their `:running` companion) caused expression evaluation to silently fail, producing no output on
  the overlay.
- Root cause: `buildContext()` in the expression engine treated `c:timer:running` as a namespaced key (namespace
  `timer`, subkey `running`), overwriting the scalar `c:timer` value with a namespace object. This made `c.timer + 2`
  throw a TypeError that was silently caught.
- Fixed `buildContext()` to skip namespaced keys when a scalar value already occupies that namespace, and to let scalar
  keys overwrite any pre-existing namespace object.
- Fixed ExpressionBuilder preview showing wrong values for timer controls (always used empty string instead of computing
  elapsed seconds from config).

## April 7th, 2026 - UI: Timer running indicators and light mode contrast

- Timer cards in ControlPanel now show a green/red gradient background and ring border indicating running/stopped state,
  with a colored dot next to the timer display. Countto timers are excluded (always running).
- ControlPanel cards get `bg-accent/70` in light mode for visibility against the white page background; dark mode keeps
  `bg-background`.
- ControlsManager table rows now use zebra striping (`odd:bg-accent/50`) for better readability in light mode.
- Added `backup-*.sql` to `.gitignore`.

## April 7th, 2026 - Fix: Pipe arguments with spaces break tag rendering

- Template tags with spaces in pipe arguments (e.g. `[[[c:datetime_thing|date:dd-MM-yyyy HH:mm]]]`) were not rendering
  at all - the raw tag text was output in the overlay.
- Fixed both the frontend TAG_REGEX in `OverlayRenderer.vue` and the PHP `extractTemplateTags()` regex in
  `OverlayTemplate.php` to allow spaces in the pipe argument character class.

## April 7th, 2026 - Feature: Date formatter now includes time and named presets

- Default `|date` output now includes time (e.g. "Apr 5, 2026, 7:00 PM") instead of date only.
- Added named presets: `|date:short` (compact), `|date:long` (full weekday), `|date:date` (date only), `|date:time` (
  time only).
- Custom token patterns still work as before (`|date:dd-MM-yyyy HH:mm`).
- Updated Formatting help page with examples for all presets and updated quick reference.

## April 6th, 2026 - Fix: Datetime control values now persist

- Datetime control values were silently dropped on every save due to three separate blocks:
  - Frontend `buildPayload()` excluded datetime from the value payload.
  - Backend `update()` unset the value for datetime controls.
  - `sanitizeValue()` returned empty string for the datetime type (fell through to the timer default).
- Added explicit `datetime` case in `sanitizeValue()` that preserves the value via `strip_tags()`.
- Replaced Shadcn Input with native `<input type="datetime-local">` for datetime controls to avoid reactivity issues.

## April 6th, 2026 - Chore: Wire up sample data and fix preview modal on create page

- Replaced the hardcoded 70-line sample data object in `create.vue` with the canonical
  `TemplateDataMapperService::getSampleTemplateData()` passed as an Inertia prop.
- Updated sample data to use `wilko_dj` for user/channel name fields.
- Replaced the broken custom Modal component with Shadcn Dialog for the preview modal - now properly closes on ESC and
  click-outside.
- Added a Close button and ESC hint to the preview footer.

## April 6th, 2026 - UI: Show control values and config summary in ControlsManager table

- The Settings column now shows the current value for text, boolean, and other controls that have no type-specific
  config.
- Long values are truncated with `max-w-48` and hoverable via a `title` attribute to see the full value.
- Source-managed controls show "Managed by source" alongside any other config info.

## April 6th, 2026 - Fix: Undo and cursor-aware inserts in ExpressionBuilder

- Fixed Ctrl+Z not working in the expression formula textarea. The computed get/set round-tripped every keystroke
  through the parent prop, causing Vue to programmatically reset the textarea value and wipe the browser's undo stack.
  Replaced with a local ref and sync watchers.
- Clicking a control button now inserts at the cursor position instead of appending to the end.
- Programmatic inserts use `execCommand('insertText')` so they are also undoable with Ctrl+Z.

## April 6th, 2026 - UI: Redesign ExpressionBuilder panel

- Moved syntax reference and usage help into a dedicated Help dialog (accessed via a Help button), decluttering the main
  panel.
- Added a filter input for the Available Controls list so you can quickly find controls.
- Controls are now grouped by source (Your controls, Twitch, Ko-fi, StreamLabs, etc.) with sticky headers.
- Each control button shows its label as a subtitle for easier identification.
- Better contrast: buttons use solid backgrounds instead of dashed muted borders, with violet highlight on hover.
- Help dialog includes organized sections for syntax, operators, 4 examples (with "insert this" for the cross-service
  example), and string tips.

## April 6th, 2026 - Docs: Expanded Controls help page with per-type sections

- Restructured the Control Types overview into clickable links to detailed per-type sections.
- Added detailed sections for all 7 control types: Text, Number, Counter, Timer, Boolean, Datetime, and Expression.
- Each section includes usage examples, code snippets, and type-specific features (random mode, timer modes, expression
  syntax).
- Documented the new Timer `:running` companion value with conditional examples.
- Added Boolean and Expression to the Control Panel "How each type works" section.
- Updated TOC with nested type links.

## April 6th, 2026 - Feature: Timer :running state in overlay conditionals

- Timer controls now expose a `:running` virtual value in the overlay data, accessible as `[[[c:my_timer:running]]]` (
  outputs `1` or `0`) and in conditionals: `[[[if:c:my_timer:running]]]`.
- `countto` timers are always considered running since they tick continuously.
- No backend changes needed - the running state is injected into the data object alongside the timer seconds in
  `OverlayRenderer.vue`.

## April 6th, 2026 - Fix: Control preset duplicates and key display

- Fixed being able to add a service preset control that already exists on the template. The preset dropdown now filters
  out already-added presets per source.
- Fixed validation errors being invisible when adding duplicate presets - the key error was hidden behind
  `v-if="!selectedServicePreset"`. Now surfaced as a general error.
- Fixed the Key column in ControlsManager showing bare keys (`total_received`) instead of namespaced keys (
  `kofi:total_received`) for service-managed controls.

## April 6th, 2026 - Tribute: wilko_dj - first live follower notification

- Changed the Welcome page alert example from a cheer event to a follow event, with an HTML comment tribute:
  `<!-- First ever: wilko_dj -->`.
- Added a dedication line in the Welcome page footer linking to wilko_dj's Twitch profile.
- Replaced the `SampleStreamer` placeholder in the template create preview with `wilko_dj`.

## April 5th, 2026 - Feature: Random mode for number controls

- Number and counter controls can now be set to "random mode" via a checkbox in the control config.
- When enabled, the control generates a random integer between min and max on a configurable interval (default 1000ms,
  minimum 100ms).
- Works with template tags (`[[[c:my_random]]]`), expressions (`c.my_random + 10`), and the comparison engine.
- Random state is broadcast via WebSocket so the overlay picks up config changes (min/max/interval) in real-time.
- Follows the existing timer pattern: backend resolves an initial random value, frontend runs a periodic interval.
- Enables creative use cases like slot machines, whack-a-mole, and randomized choices.

## April 5th, 2026 - Revert: Remove Railway webhook debounce

- Removed the `BroadcastVersionUpdate` debounce job and cache nonce logic.
- Railway webhook now broadcasts `VersionUpdated` immediately on receipt.
- The debounce delayed broadcasting by 30 seconds, which coincided with Reverb restarting during deploys - the broadcast
  fired into a Reverb instance with no connected clients.

## April 5th, 2026 - Reorganize help pages under /help/* and add logged-out sidebar menu

- Moved all help/learn pages under `/help/*`: conditionals, controls, formatting, resources, why-kofi, manifesto.
- Created `/help` landing page linking to all sub-pages.
- Added "Learn" nav section in sidebar for logged-out users.
- Replaced user menu "Learn" submenu with a single link to `/help`.
- Updated all internal links and breadcrumbs across the app.

## April 5th, 2026 - Fix: Inertia link clicks while unauthenticated now redirect to login

- Fixed unauthenticated Inertia navigation (clicking links) returning a raw JSON 401 instead of redirecting to the login
  page.
- `RedirectIfUnauthenticated` middleware now uses `Inertia::location()` for Inertia requests, triggering a full-page
  visit to `/login?redirect_to=...` instead of breaking with "All Inertia requests must receive a valid Inertia
  response".

## April 5th, 2026 - Docs: Formatting Pipes help page and currency locale fix

- Created `/help/formatting` page with full documentation for all 8 pipe formatters, example tables with locale
  comparisons, quick reference, and tips.
- Added "Formatting Pipes" to the Learn submenu in the user menu and the command palette.
- Cross-linked from the Help page intro and the Controls page timer description.
- Fixed currency preview in Appearance settings showing USD for all locales - now maps each locale to its typical
  currency (EUR for Dutch, GBP for British, etc.).
- Fixed `|currency` pipe without explicit code defaulting to USD regardless of user locale - now uses locale-aware
  default via `LOCALE_CURRENCY_MAP`.

## April 5th, 2026 - Feature: Pipe formatting system for template tags (Milestone 5d)

- Added pipe syntax for template tags: `[[[c:timer|duration:hh:mm:ss]]]`, `[[[c:amount|currency:EUR]]]`,
  `[[[c:score|round]]]`.
- Built-in formatters: `|round`, `|duration`, `|currency`, `|date`, `|number`, `|uppercase`, `|lowercase`. All accept
  optional format-string arguments.
- Duration formatter supports auto-format (smart unit selection) and explicit patterns (`hh:mm:ss`, `mm:ss`,
  `dd:hh:mm:ss`, etc.).
- Currency, date, and number formatters use native `Intl` APIs - zero external dependencies.
- Added global locale setting (user settings > Appearance) with live preview of number, currency, and date formatting.
- Overlay renderer uses a single-pass regex replacement that resolves tags and applies formatters in one step.
- PHP `extractTemplateTags()` updated to recognize pipe syntax and correctly strip formatters when extracting tag names.
- Migration adds `locale` column to users table (default: `en-US`).
- Locale is shared via Inertia and passed to the overlay renderer API response.

## April 5th, 2026 - Docs: Major README update

- Rewrote intro to drop "DSL" jargon, matching the new GitHub repo description.
- Added Expression controls section with formula syntax and reactive evaluation.
- Added External Integrations section covering Ko-fi and StreamLabs, including control namespaces, auto-provisioned
  controls, and the shared webhook pipeline.
- Added Alert Targeting section explaining per-overlay alert routing.
- Added Control Timestamps (`_at` companion values) section.
- Updated Timer section with the new "Count to" datetime mode.
- Replaced all Pusher references with Reverb (self-hosted WebSocket).
- Added Overlay Health subsection (reconnection, backoff, error banners).
- Renamed "Forking" to "Copying" throughout to match UI terminology.
- Updated Tech Stack table and Self-Hosting env vars.
- Updated self-hosting clone URL to `jasperfrontend/overlabels`.

## April 4th, 2026 - UX: Contextual breadcrumbs for template show/edit pages

- Breadcrumbs on show and edit pages now reflect the filtered list you navigated from (e.g. "My static overlays" or "My
  event alerts") instead of always showing "My overlays".
- Clicking the breadcrumb navigates back to the exact filtered route, preserving filter, type, sort, and direction.
- Filter context is persisted to `sessionStorage` from `index.vue` and read by `show.vue` / `edit.vue`. Falls back to "
  My overlays" on direct navigation.

## April 4th, 2026 - UI: Redesign TemplateTagsList component

- Replaced the grid-of-boxes layout with collapsible category sections and horizontal flow-wrapped tag badges - all tags
  visible at once without excessive scrolling.
- Added a search/filter input that matches against tag names, descriptions, and category names.
- Removed the "Other" category entirely (contained only array-type data and generic count tags that don't render in
  templates).
- Replaced the all-caps orange "IMPORTANT INFO" button with a subtle full-width amber callout banner explaining `user_*`
  tag behavior.
- Tag descriptions now appear on hover via tooltips instead of a global checkbox toggle.
- Category expand/collapse state is persisted to `localStorage` - survives tab switches and page navigation.
- Added "Collapse all" / "Expand all" toggle with chevron icons, reactively derived from per-category state.
- Cleaned up the info dialog copy (hyphens instead of em dashes, consistent amber theming).

## April 4th, 2026 - Remove: User dashboard icon, greeting, and per-section limit

- Removed the per-user icon feature: deleted `UserIconPicker.vue`, the `PATCH /settings/icon` route, the icon picker
  from Appearance settings, and the `icon` column from the `users` table (migration included).
- Removed the random greeting bar from the dashboard (greeting text, icon, and "Show X per section" dropdown).
- Dashboard now shows 5 items per section with no user override.
- Deleted unused `Icon.vue` (was already unreferenced).

## April 4th, 2026 - Perf: Tree-shake Lucide icons (976 KB -> 82 KB)

## April 4th, 2026 - Chore: Install barryvdh/laravel-ide-helper for PhpStorm support

- Installed `barryvdh/laravel-ide-helper` as a dev dependency.
- Ran `ide-helper:models --write --reset` to generate `@property` and `@method` PHPDoc blocks for all 19 Eloquent
  models.
- Generated `_ide_helper.php` for facade method resolution (gitignored).
- Removed unused `$request` parameter from `OverlayControlController::index()`.
- Used `::query()->where()` instead of `::where()` in controller for explicit builder typing.

## April 4th, 2026 - Fix: Expressions can now reference timer/datetime controls

- Removed the `timer`/`datetime` type filter from `OverlayControl::getAvailableControls()` and the frontend
  `availableWatchControls` computed. Expressions like `c.count_to / 3600` now validate and evaluate correctly.
- Fixed 422 errors from `abort()` being silently swallowed in the ControlFormModal - these now display as a visible
  error message in the modal instead of only appearing in the browser console.

## April 4th, 2026 - Feature: Distraction-free code editor and HEAD CodeMirror upgrade

- Added distraction-free fullscreen mode (Ctrl+Shift+F or "Focus" button in sidebar). Editor takes over the full
  viewport with just tabs and code. Exit with Escape or the same shortcut.
- HEAD tab now uses CodeMirror with HTML syntax highlighting instead of a plain textarea.
- Dark/light mode switching now updates CodeMirror instantly without page refresh, using a MutationObserver on the HTML
  class. Removed the broken `isDark` prop - the editor handles it internally.

## April 3rd, 2026 - UX: Auto-generate control key from label

- Control key field now auto-derives from the label as the user types (e.g. "Death Counter" becomes `death_counter`).
- Users can manually override the key; auto-derive stops once the key field is edited directly.
- Live validation warnings (amber) for invalid key patterns: spaces, uppercase, leading/trailing underscores, starting
  with a number.
- Shows a live template tag preview (`[[[c:death_counter]]]`) as the key forms.
- Service preset controls skip auto-derive (unchanged behavior).

## April 3rd, 2026 - Feature: Command palette and keyboard shortcuts overhaul

- Added a command palette (Ctrl+Space) with fuzzy search over all navigable routes, grouped by section (Navigation,
  Settings, Learn, Tools, Admin). Admin routes only shown to admins.
- Consolidated keyboard shortcuts into a single system: rewrote `useKeyboardShortcuts` composable with per-component
  scoped ownership (shortcuts auto-cleanup on unmount), a shared global listener, and reactive `getAllShortcuts()` for
  the shortcuts dialog.
- Deleted duplicate `lib/keyboardShortcuts.ts` singleton (was imported by nothing).
- Moved sidebar toggle (Ctrl+B) from a standalone `useEventListener` into the composable.
- Made Ctrl+K shortcuts dialog global (available on every page, not just template editor). Page-specific shortcuts (
  Ctrl+S, Ctrl+P) still register on their pages and appear context-dependently.
- Ctrl+modifier shortcuts now fire even when focused in inputs/textareas (so Ctrl+S works inside the code editor).
- Added keyboard shortcut hints in the sidebar below navigation.

## April 3rd, 2026 - Feature: Profile dropdown in header

- Replaced the static avatar/name link in the header with a dropdown menu triggered by an Avatar component.
- Moved Learn items, Settings, Sensitive Data, and Log out from the sidebar into the profile dropdown.
- Removed `NavUser` from the sidebar footer; sidebar is now cleaner with just core navigation.

## April 3rd, 2026 - UI: Theming and visual fixes

- Changed dark mode background from neutral gray to a purple-tinted dark (`hsl(270 16% 6%)`).
- Added CSS custom properties for page gradient (`--gradient-spot-1/2/3`, `--gradient-base`) so gradient colors are
  theme-swappable.
- Added `bg-popover text-popover-foreground shadow-md` to tooltip content (was invisible with no background).
- Fixed collapsed sidebar menu items (My overlays, Alerts builder, Overlay kits) being unclickable by adding
  `pointer-events-none` to hidden group labels.

## April 3rd, 2026 - Fix: Welcome page mobile and light mode issues

- Moved the Login with Twitch button to its own full-width row below the header on mobile (was cramped and overlapping).
- Favicon now swaps between `favicon-light.svg` (light mode) and `favicon.png` (dark mode).
- Closed Beta banner now has proper light mode colors (solid purple-100 background, purple-800 text) instead of
  translucent dark-only colors.

## April 3rd, 2026 - Fix: twitchdata.refresh.all returning 404

- Changed `router.visit()` (GET) to `router.post()` to match the `Route::post()` definition.

## April 3rd, 2026 - Fix: Expression formula edits not updating overlay in real-time

- Editing an Expression control's formula saved to the database but never broadcast a change to the overlay, requiring a
  hard refresh to pick up the new expression.
- `ControlValueUpdated` event now accepts an optional `expression` parameter, included in the broadcast payload.
- `OverlayControlController` broadcasts the updated expression after saving.
- `OverlayRenderer` re-registers the expression via `expressionEngine.registerExpression()` when it receives a
  `control.updated` event with a new expression, triggering immediate re-evaluation.

## April 2nd, 2026 - Removed: Computed control type

- Removed the `computed` control type entirely. Everything it did (simple if/else logic) is better handled by Expression
  controls, which evaluate client-side with zero latency.
- Deleted `ComputedControlService`, `ComputedFormulaBuilder.vue`, and all computed-related tests.
- Removed cascade logic from `OverlayControlController`, `StreamSessionService`, and `ExternalControlService` - computed
  controls were the only consumer.
- Moved `getAvailableControls()` and `detectExpressionCycle()` to static methods on `OverlayControl` model (still needed
  for expression validation).
- Also improved `ControlsManager` config summary to show "Count to" mode and target datetime for timer controls.

## April 2nd, 2026 - Feature: Timer "Count to date/time" mode

- Added `countto` as a third Timer mode alongside `countup` and `countdown`.
- User picks a target date/time via a `datetime-local` picker; the timer counts down the remaining seconds until that
  moment.
- Stored entirely in the Timer's own config (`target_datetime`) - no dependency on other controls.
- `countto` timers always tick (no start/stop needed); the ControlPanel shows the target datetime instead of
  play/pause/reset buttons.
- Output is raw seconds, same as other timer modes - will benefit from the pipe/formatter system (Milestone 5d) when
  that ships.

## April 2nd, 2026 - Docs: Added Milestone 5d (Output Formatting)

- Added Milestone 5d to the roadmap: a pipe/formatter system for template tags (`[[[c:key|format]]]`).
- Covers duration, date, and number formatters that work for all control types.
- Deleted unused `Icon.vue` which imported `* as icons from 'lucide-vue-next'`, pulling in all ~1,500 icons.
- Rewrote `UserIconPicker.vue` to lazy-load individual icon files via `defineAsyncComponent` + dynamic `import()`
  instead of importing the entire library.
- Removed the `lucide-icons` manual chunk from `vite.config.mts` since tree-shaking now works correctly.
- Net bundle reduction: ~894 KB raw / ~161 KB gzipped.

## April 1st, 2026 - UI: Redesign TemplateTable + shared TemplateMeta component

- Replaced the heavy dual table+card layout in `TemplateTable.vue` with a clean card-based list matching the
  `EventsTable` pattern.
- Removed Owner, Views, Forks, Updated columns from the main view - dates and owner moved to kebab dropdown.
- Only shows a "Private" pill when the template is not public; removed type and public badges.
- Event dots now use the `useEventColors` composable with support for both Twitch and external (Ko-fi, StreamLabs) event
  mappings.
- Added `externalEventMappings` relationship on `OverlayTemplate` model and eager-loaded it in the index controller.
- Extended `useEventColors` composable with `eventTypeDotClass(eventType, source?)`,
  `eventTypeHoverBorderClass(eventType, source?)`, and exported `EVENT_TYPE_LABELS`.
- Created shared `TemplateMeta.vue` component (meta grid + template tags card) used by both show and edit pages.
- On `/templates/show`: source code expanded by default, "Forked from" moved into meta grid as "Copied from".
- Renamed "Forks" to "Copies" across meta displays.

## April 1st, 2026 - Fix: Event color classes missing in production

- Replaced dynamic Tailwind class construction (`bg-${color}`) with full literal class strings so Tailwind's scanner
  includes them in the production build.
- Simplified external event color lookup to match on source instead of repeating per event type.

## April 1st, 2026 - Refactor: Extract event color composable

- Moved event color logic and `UnifiedEvent` interface from `EventsTable.vue` into `useEventColors` composable for
  reuse.

## April 1st, 2026 - Fix: StreamLabs listener Dockerfile Node version

- Bumped `Dockerfile.streamlabs-listener` from `node:20-alpine` to `node:22-alpine` to match the `engines` constraint in
  `package.json`.

## April 1st, 2026 - Refactor: Split ControlFormModal into smaller components

- Extracted `ExpressionBuilder.vue` - expression formula panel (textarea, variable buttons, live preview).
- Extracted `ComputedFormulaBuilder.vue` - computed formula builder (watch control, operator, compare/then/else).
- Extracted `controlPresets.ts` - service preset constants and `getPresetsForSource()` helper.
- `ControlFormModal.vue` reduced from 783 to 530 lines with no behavior changes.

## April 1st, 2026 - Fix: Reverb broadcasting CA verification for local TLS

- Added configurable CA bundle path for Reverb's Guzzle client via `REVERB_CA_BUNDLE` env var.
- Fixes curl error 56 (connection reset) when Herd auto-starts Reverb in secure/TLS mode but the broadcasting client
  can't verify the self-signed certificate.

## April 1st, 2026 - UI: Two-column layout for expression controls in ControlFormModal

- Expression controls now use a wider two-column layout on desktop (max-w-4xl) so the formula editor has its own
  dedicated column alongside the standard form fields.
- Non-expression types keep the existing single-column narrow layout (max-w-lg).
- On mobile, the layout collapses to a single stacked column.
- Fixes Cancel/Save buttons being pushed off-screen when editing expression controls.

## April 1st, 2026 - Fix: Expression validation recognizes _at companion values

- Expression dependency extraction now strips `_at` suffixes to resolve to the base control, since `_at` values are
  virtual companions that don't exist as database rows.
- Fixes 422 error when saving expressions referencing `c.streamlabs.latest_donor_name_at` or similar `_at` values.

## April 1st, 2026 - Feature: Control _at timestamps

- Every control now has a companion `_at` value containing the Unix timestamp of its last update.
- Available as template tags (`[[[c:kofi:latest_donor_name_at]]]`) and in expressions (`c.kofi.latest_donor_name_at`).
- Enables cross-service comparisons like:
  `c.streamlabs.latest_donor_at > c.kofi.latest_donor_at ? c.streamlabs.latest_donor_name : c.kofi.latest_donor_name`.
- Injected at initial overlay load from the control's `updated_at` and on every real-time broadcast.
- No database schema changes - timestamps are virtual companion values derived from existing data.
