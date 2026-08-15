# Claude Code Memory Export

Last updated: 2026-08-06

---

## Project Overview

This is a Laravel 12 + Vue 3 application for managing Twitch overlays. It uses Inertia.js for seamless frontend/backend integration, TypeScript for type safety, and TailwindCSS v4 with Shadcn/Vue components for UI.

## Essential Commands

### Development
```bash
composer run dev          # Full dev environment (server + queue + vite), aliased as `crd`
php artisan serve         # Laravel server
npm run dev              # Vite dev server
php artisan queue:work   # Queue worker
```

### Testing & Quality
```bash
php artisan test         # PHP tests (Pest framework)
npm test                 # Frontend unit tests (Vitest, run once)
npm run test:watch       # Vitest in watch mode
npm run typecheck        # vue-tsc --noEmit
npm run lint             # ESLint with auto-fix
npm run format           # Prettier formatting
php artisan pint         # PHP code style fixes
```

CI runs the check-mode variants (`pint --test`, `format:check`, `lint:check`) plus `npm test`,
`npm run typecheck` and `pest`. All of them must pass before a PR merges.

### Build & Deploy
```bash
npm run build            # Production build
php artisan migrate      # Run database migrations
php artisan optimize     # Cache configuration
```

## Environment

- Windows 10, PHP 8.4 via Herd (exe at `/c/Users/jmstu/.config/herd/bin/php84/php.exe`)
- `php` is on PATH - use `php artisan ...` directly
- `gh` is available as a GitHub CLI tool
- PostgreSQL database
- Repo: [jasperfrontend/overlabels](https://github.com/jasperfrontend/overlabels) on GitHub
- Do NOT use Linux commands for file manipulation - use Windows equivalents

### Environment Variables

Critical variables:
- `TWITCH_CLIENT_ID`, `TWITCH_CLIENT_SECRET`: Required for Twitch integration
- `APP_URL`: Must be correct for webhooks
- `DB_CONNECTION`: sqlite (default) or pgsql
- `TELESCOPE_ENABLED`: Enable debugging tools (dev only)

## Architecture

### Core Systems

**Twitch Integration**: Deep integration through OAuth and EventSub webhooks. User authentication is based on `twitch_id` (not email). `TwitchApiService` handles all API interactions including token refresh.

**Overlay System**: Templates stored in `overlay_templates` table with a custom tag system that parses Twitch data dynamically. Access controlled through `OverlayAccessToken` - 64-char hex token lives in the URL fragment (never sent to server), server stores sha256(plainToken). Render pipeline: `authenticate.blade.php` -> `overlay/app.js` (creates Echo/Reverb) -> `OverlayRenderer.vue`.

**Frontend Stack**: Vue 3 components in `/resources/js/`. Inertia.js eliminates separate API endpoints for most operations. Pages in `/Pages/`, reusable components in `/components/`, UI primitives in `/components/ui/`. Components follow Shadcn/Reka-UI/Vue patterns. Composables in `/composables/`, TypeScript types in `/types/`. Tailwind v4 with CSS layers.

**Route Organization**: Routes split across `/routes/`: `web.php` (main), `api.php` (public API), `auth.php` (authentication), `settings.php` (user settings), `admin.php` (admin panel - must load BEFORE the catch-all route).

**API Endpoints**:
- Public overlay rendering: `/api/overlay/render` (rate-limited)
- Twitch webhook: `/api/twitch/webhook`
- External webhooks: `POST /api/webhooks/{service}/{webhook_token}` (no auth/CSRF)
- Template operations require authentication through Inertia

**Testing**: Feature tests in `/tests/Feature/`, unit tests in `/tests/Unit/`. Pest framework with Laravel-specific helpers. Tests use `RefreshDatabase`.

Frontend unit tests are Vitest, co-located as `resources/js/**/*.test.ts`, configured in
`vitest.config.mts` (deliberately separate from `vite.config.mts`, which loads the Vue/Tailwind
plugins and shells out to `git rev-parse` at module scope). Scope is the pure TypeScript Pest cannot
reach - the DSL, tag parser, two-pass template renderer, formatters. `environment: 'node'`, no jsdom:
component testing would mean adding both and is an open decision, not an oversight. Added Aug 2026
alongside the foreach tag-injection fix (PR #230), which had no automated coverage to catch it.

### Key Architecture Notes

- `useEventSub.ts` reuses `window.Echo` instead of creating a duplicate connection
- `useOverlayHealth.ts` composable handles: retry with backoff, WebSocket monitoring, periodic health checks, auto-reload
- Banner styles live in the blade template (not in Vue) so they're available before Vue mounts
- Overlay auth uses 64-char hex tokens in URL fragments (never sent to server)
- Two broadcast channels: `twitch-events` (global) and `alerts.{user_twitch_id}` (per-user)
- OBS browser sources can't show console errors - visual banners are the only way to communicate errors to streamers

### Important Services

- `TwitchApiService`: All Twitch API interactions (including `getStreamStatus()` for Helix stream checks)
- `StreamStateMachineService`: Deterministic stream state machine with confidence-based Helix verification
- `StreamSessionService`: Stream session lifecycle (open/close sessions, reset controls, per-stream counters)
- `TemplateParserService`: Template tag parsing and validation
- `OverlayAccessService`: Access control for overlays
- `AdminAuditService`: Append-only audit logging
- `ExternalControlService`: External service control updates
- `ExternalAlertService`: External service alert dispatch
- Queue workers handle background tasks (EventSub processing)

## Stream State Machine (Implemented Apr 2026)

- Deterministic state machine: `offline` -> `starting` -> `live` -> `ending` -> `offline`
- `stream_states` table: user_id (unique), state, confidence (float 0-1), last_event_at, last_verified_at, helix_stream_id, current_session_id (FK), grace_period_until
- EventSub events only trigger transitions (set state to starting/ending with confidence 0.25). Helix API (`GET helix/streams`) is the source of truth.
- `StreamStateMachineService`: core service with `handleEventSubOnline()`, `handleEventSubOffline()`, `verify()`, `transitionToLive()`, `transitionToOffline()`
- `VerifyStreamState` job: polls Helix, updates confidence (+/- 0.25), evaluates transitions. Self-dispatches with delays (10s for starting/ending, 60s heartbeat for live).
- Confidence threshold: 0.75 required for live/offline transitions. `StreamState::isConfidentlyLive()` checks both state and confidence.
- Grace period: 120 seconds in `ending` state before finalizing. Handles OBS crashes - if Helix shows stream is back, reverts to `live`.
- Session stitching: if stream goes offline and comes back within 5 minutes, existing session is reopened (ended_at cleared) instead of creating new.
- Retroactive repair: session `started_at` corrected to match Helix `started_at`.
- Event grouping: `stream_session_id` FK on `twitch_events` and `external_events`. Stamped via `stampEventsWithSession()` once at the go-live transition - it only covers the ~seconds between `started_at` and Helix confirmation, so events arriving DURING the live stream keep a null FK and are never re-stamped. The FK is near-empty in practice: do NOT group per-session with `WHERE stream_session_id = ...`. Aggregate by a per-session time window over `created_at`/`twitch_timestamp` instead (see `StreamSessionController::buildWindowsCte()`).
- `StreamSessionService::isLive()` and `handleEvent()` now use confidence-based check instead of raw session existence.
- Broadcasting handled by state machine (removed from `openSession`/`closeSession`). `StreamStatusChanged` includes state, confidence, startedAt.
- Safety-net scheduler: every 5 minutes, re-dispatches VerifyStreamState for stuck states (last_verified_at > 5 min ago).
- App access token cached for 50 minutes in `TwitchEventSubService::getAppAccessToken()`.
- Frontend: `useStreamState` composable, green/orange dot on avatar in `AppHeader.vue`, uptime counter.

## Controls System (Implemented Feb 2026)

- `overlay_controls` table: id, overlay_template_id (nullable!), user_id, key, label, type, value, config (json), sort_order, source, source_managed
- `OverlayControl` model: `sanitizeValue()`, `resolveDisplayValue()`, `createForTemplate()`, `provisionServiceControl()`, `broadcastKey()`
- Carbon `diffInSeconds` bug: use `$start->diffInSeconds($now)` not `$now->diffInSeconds($start)` (latter returns negative)
- Template syntax: `[[[c:key]]]` or namespaced `[[[c:kofi:donations_received]]]` - colon already in regex char class
- Broadcast: `ControlValueUpdated` -> `alerts.{twitch_id}` channel, broadcastAs `control.updated`
- Service-managed controls: `source_managed=true` -> `setValue()` and `update()` return 403
- User-scoped controls: `overlay_template_id=null`, available in all user's overlays
- Namespaced broadcast key: "kofi:donations_received" -> stored in data as "c:kofi:donations_received"
- Empty `overlay_slug` in broadcast = user-scoped; OverlayRenderer applies to all overlays

### Control reset lifecycle (fixed Aug 2026)

- **The go-live reset is scoped by KEY, never by source.** `StreamSessionService::resetControls()` selects on `PER_STREAM_CONTROL_KEYS`, the eight `*_this_stream` counters. Do not widen it back to `where('source', 'twitch')` with no key filter.
- A key belongs on that list only if its label promises per-stream scope. The reset is the *only* thing implementing that promise: `handleEvent()` increments purely additively (`$current + $step`) and nothing else ever zeroes a counter.
- The three `latest_cheer*` presets deliberately do NOT reset. They are most-recent values, and all 25 equivalents across the five donation services persist across streams. They were swept into the reset for four months because they share `source='twitch'` with controls the filter predated - which broke the bits/donation parity they were added (`c6be780`) to provide.
- `latest_cheerer_name` is a `text` control with no `config`, so the old reset wrote `(string) ($control->config['reset_value'] ?? 0)` = the literal string `"0"` into it. `"0"` is falsy in conditionals (`useConditionalTemplates.ts`) but a bare `[[[c:key]]]` renders it verbatim - `replaceTagsWithFormatting` blanks only on undefined/null/object/`''`, and `?? default` backstops absence only.
- `_at` is `$control->updated_at` (`OverlayTemplateController` render query), so ANY write moves it. That is why a control being reset at go-live won every `latest()` race at stream start. `latest()`/`toComparable()` are correct and are not the place to fix anything.
- `GpsIntegrationController::resetControls()` is the reference shape: explicit key list, per-session keys only, cumulative `distance` left alone behind a separate manual endpoint.
- Pinned by `tests/Feature/StreamControlResetTest.php` (9 tests). Three assert the behaviour and were verified to fail against the old filter; the rest pin scoping (other users, other sources, user-created controls sharing a key name).
- A per-control "should this survive the reset" toggle was discussed and deliberately parked. If revisited: `config['reset_on_stream_start']` needs no migration, but `update()`/`setValue()` 403 on `source_managed` - which is every control the reset touches - so it needs a carve-out or a separate surface.

## Pipe Formatting System (Implemented Apr 2026)

- Syntax: `[[[tag_name|formatter]]]` or `[[[tag_name|formatter:args]]]`
- Built-in formatters: `round`, `duration`, `currency`, `date`, `number`, `uppercase`, `lowercase`
- Duration patterns: `hh:mm:ss`, `mm:ss`, `dd:hh:mm:ss` etc. - units overflow into the largest present unit
- Formatter utility: `resources/js/utils/formatters.ts` - pure functions, zero dependencies, uses native `Intl` APIs
- `OverlayRenderer.vue` uses `TAG_REGEX` for single-pass replacement: matches tag + optional pipe, resolves value, applies formatter
- `TAG_REGEX`: `/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?]]]/g` - group 1 = tag key (hyphens supported for any future hyphenated service names), group 2 = pipe expression
- PHP `extractTemplateTags()` regex: `/\[\[\[([a-zA-Z0-9_.][a-zA-Z0-9_.:\-]*?)(?:\|[a-zA-Z0-9_.:% -]+)?]]]/` - tag key includes hyphens; pipe char class includes space for patterns like `date:dd-MM-yyyy HH:mm`
- PHP `extractTemplateTags()` strips pipe expressions to extract clean tag names for the allowlist
- Global locale stored on `users.locale` (default `en-US`), passed via API response as `json.locale`
- Settings UI: Appearance page has locale picker with live number/currency/date preview

## Bot Commands, and what "expression" is allowed to mean (renamed Aug 2026)

- **"Expression" means a jsep formula in an Expression Control. Nothing else.** It previously meant six
  things. If you are about to name something "expression", it had better be evaluated by
  `expression-engine.mjs`.
- `BotCommand` / `bot_commands` = the user's custom chat commands (was `BotExpression` /
  `bot_expressions`). `BotBuiltin` / `bot_builtins` = the per-user registry of which BUILT-IN verbs are
  enabled and at what tier (was `BotCommand` / `bot_commands`). The two swapped names in one migration.
- Columns: `bot_commands.reply` is the templated text the bot speaks (was `expression`).
  `bot_commands.hidden` and `bot_aliases.hidden` (were `hidden_from_commands`).
- `overlay_templates.tts_message` and `overlay_templates.chat_message` (were `tts_expression` and
  `bot_message_expression`). `App\Services\Messages\PipeFormatter` (was `Expressions\ExpressionFormatter`)
  and `AlertMessageRenderer` (was `AlertExpressionRenderer`).
- `BotCommandMapController` serves the whole command map (`GET /internal/bot/commands`).
  `BotCommandController` is the fire endpoint. Do not conflate them.
- **The command map `type` discriminator and every `/internal/bot/*` URL are cross-repo contracts.** The
  bot lives in its own repo and deploys independently, so changing one is a four-step rollout, in order:
  the bot learns the new value while still accepting the old, the app switches to emitting the new one
  behind a compatibility alias, the bot drops the old value, the app deletes the alias. Skipping ahead
  breaks every custom chat command in every channel for the length of the gap. `type` is
  `builtin | custom | alias | recipe_trigger | list_append | list_meta`; `custom` was `expression` until
  Aug 2026, and the rename above is the worked example of the full sequence.
- In the bot's `src/bot.js`, a local dispatcher must never share a name with the API function it
  imports from `overlabelsApi.js` - the inner call resolves to the dispatcher itself and recurses
  forever. Convention there is API `fireX`, local `fireXInvocation`. The bot repo has no test or lint
  script, so `node --check` every file you touch.
- Migrations and past changelog entries were deliberately NOT rewritten by the rename. They record what
  was true when they ran. Same for the dated design specs in `resources/help/reference/*.md` (depth 0,
  not served - `HelpReferenceService` scans `depth == 1` only).

## External Integrations (Implemented Mar 2026)

- Pipeline: ExternalWebhookController -> verifyRequest -> parsePayload -> normalizeEvent -> ExternalEvent (dedup on service+message_id) -> ExternalControlService.applyUpdates -> ExternalAlertService.dispatch
- `ExternalServiceDriver` interface in `app/Contracts/` - getServiceKey, verifyRequest, parseEventType, normalizeEvent, getSupportedEventTypes, getAutoProvisionedControls, getControlUpdates
- `ExternalServiceRegistry` maps service key -> driver class
- `ExternalIntegration`: UUID webhook_token (routing key), encrypted credentials (Crypt::encryptString), settings (json), enabled, last_received_at
  - Use `setCredentialsEncrypted(array)` / `getCredentialsDecrypted()` - NOT raw $fillable assignment
  - In tests: pass pre-encrypted credentials directly to factory->create(['credentials' => Crypt::encryptString(...)])
- `ExternalEvent` append-only model (UPDATED_AT = null), global dedup on (service, message_id)
- `ControlValueUpdated::dispatch()` uses variadic `...$arguments` - use POSITIONAL args, not named args

### Donation integration controllers (consolidated Aug 2026)

- The five donation integrations (kofi, streamlabs, fourthwall, bmac, throne) all extend `DonationIntegrationController`. It owns `show()`, `setTestMode()`, `seedDonationCount()` and `disconnect()`; subclasses supply `service()` and their connect flow only. GPS deliberately does NOT extend it (telemetry, not donations - no test mode, no seed).
- Subclass hooks: `credentialFlags()` (prop name => credential key, for `has_token`-style booleans), `showsWebhookUrl()` (false for the OAuth pair), `beforeDisconnect()` (Fourthwall deregisters its remote webhook).
- **Every connect flow MUST go through `connectIntegration()`.** That is the single place `provision()` is called, which is what makes "connect a service, get its controls" true. Before this, only streamlabs/fourthwall/gps provisioned - kofi/bmac/throne gave you a working webhook and zero controls to read it from, because the render payload is built from control rows (`OverlayTemplateController` render query).
- `provision()` is idempotent, so it is called on EVERY connect, not just the first. A driver that gains a control later picks it up on the next reconnect.
- `IntegrationProvisioningTest` pins this; 6 of its 8 tests were verified to fail when the `provision()` call is removed. It also asserts structurally that every donation service's show route resolves to a `DonationIntegrationController` subclass, so a sixth integration cannot reintroduce the bug with a hand-rolled controller.

### Ko-fi Integration

- Ko-fi driver: payload is form-encoded body with `data` JSON field - use `$request->input('data')` to get string
  - In tests: use `$this->post(url, ['data' => json_encode($payload)])` NOT `postJson`
- Ko-fi controls ARE auto-provisioned on connect, like every other integration (fixed Aug 2026 - it previously provisioned nothing). Provisioning and template-authoring are separate concerns: provisioning creates the user-scoped rows, the presets modal is how you discover which keys exist while writing a template.
- `ControlFormModal.vue` shows Ko-fi presets when `connectedServices` includes 'kofi' AND template.type === 'static'
- `ExternalControlService::applyUpdates()` uses `->with('template')->get()` (not `->first()`); loops all matching controls
- `OverlayControl` relationship is `template()` not `overlayTemplate()` - use `$control->template?->slug`
- `renderAuthenticated()` uses `c:` + `broadcastKey()` for source_managed controls -> `c:kofi:donations_received`
- `connectedServices` prop threaded: OverlayTemplateController::show() -> show.vue -> ControlsManager.vue -> ControlFormModal.vue

### StreamLabs Integration

- OAuth-based: user clicks "Authenticate with StreamLabs" button, standard OAuth 2.0 Authorization Code flow
- StreamLabs tokens never expire (per their docs) - no refresh logic needed
- API version: v1.0 (NOT v2.0 - their docs are misleading, dashboard confirms v1.0)
- Scopes: `socket.token`, `donations.read`, `donations.create`
- Only `donation` event type supported in v1
- Uses Socket.IO (pull model) via server-side Node.js listener, NOT webhooks
- `streamlabs-listener.mjs` bridges StreamLabs Socket.IO -> POST to `/api/webhooks/streamlabs/{webhook_token}`
- Listener fetches active integrations from `GET /api/internal/streamlabs/integrations` (authenticated by `STREAMLABS_LISTENER_SECRET`)
- Verification: `X-Listener-Secret` header checked against per-integration `listener_secret` credential
- Auto-provisions 6 controls: `donations_received`, `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`
- Env vars: `STREAMLABS_CLIENT_ID`, `STREAMLABS_CLIENT_SECRET`, `STREAMLABS_LISTENER_SECRET`
- OAuth callback: `GET /auth/callback/streamlabs` (in web.php with `auth.redirect` middleware)
- Settings routes: under `settings/integrations/streamlabs` prefix
- App approval: unapproved apps limited to 10 whitelisted users - closed beta banner shown on settings page
- Template syntax: `[[[c:streamlabs:donations_received]]]`
- In tests: use `postJson` with `X-Listener-Secret` header (NOT form-encoded like Ko-fi)

### StreamElements: REMOVED (Aug 2026)

The StreamElements integration was built in Apr 2026 and removed entirely on 2026-08-04, after
Razer (which acquired StreamElements) changed the privacy policy to claim ownership of
user-generated content, with an accept-or-delete-your-account dialog. Nobody was using it.

Do NOT rebuild it, and do not propose it when listing candidate donation integrations. The
decision is about the counterparty, not the code. Removed: driver, settings controller and page,
the Socket.IO listener + its Dockerfile + Kamal accessory, the internal integrations endpoint,
`STREAMELEMENTS_LISTENER_SECRET`, and all provisioned control data (via migration
`2026_08_04_120000_purge_streamelements_integration_data`).

Donation services are now five: Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee, Throne. Copy that
counts them ("five donation services", "five pipes") lives in `resources/views/welcome/`.

## Database Backups (Implemented Aug 2026)

- Daily `pg_dump` at 16:00 UTC from the scheduler role to Cloudflare R2 and Scaleway. Moved off 03:00 on 2026-08-14: the dump is ~1.5 MB and takes seconds, so there was no load window to hide in, and the only thing 03:00 reliably achieved was timing the failure alert to arrive while the one person who can act on it was asleep. 16:00 UTC puts the alert at ~16:30, early evening in Amsterdam. Pinned by `BackupDatabaseTest`. See `docs/deploy/database-backups.md` - it is the restore procedure too.
- `BackupDatabase` command: dump -> implausibility floor (10 KB) -> stream to `r2` disk -> read size back to verify -> delete local. Any failure posts to `BACKUP_ALERT_WEBHOOK_URL` (Discord, optional).
- The app image carries `postgresql-client-16` from **PGDG**, and the pin is deliberate. The FrankenPHP base is **Debian 13 (trixie)**, which ships client 17 and would have worked against the 16 server unaided; PGDG exists here to keep the client major explicit instead of drifting with the base image. Bumping prod Postgres past 16 means bumping the Dockerfile the same day, or backups fail that night. The apt line uses `$VERSION_CODENAME` - never hardcode a suite.
- Dumps open with `\restrict` and close with `\unrestrict` (pg_dump 16.14+). That is a psql meta-command from PG 18, backpatched only to 17.6. **Restore with the psql 18 binaries**: local psql 17.5 warns and continues by default, but aborts with exit 3 under `ON_ERROR_STOP=on`, which the restore procedure sets.
- Dump flags `--no-owner --no-privileges` are load-bearing: without them a restore aborts on the unknown `overlabels` role anywhere but prod.
- The bucket is `overlabels-backups`, **EU jurisdiction**. The jurisdiction is part of the endpoint host (`<account>.eu.r2.cloudflarestorage.com`); the non-jurisdictional host 403s, which looks exactly like a bad credential. Check `R2_JURISDICTION` before rotating keys.
- Dumps are NOT client-side encrypted. Deliberate: EU jurisdiction + Cloudflare DPA/SCCs + R2 at-rest AES-256 covers it, and a passphrase living only in GitHub secrets is a single point of failure that destroys every historical backup. Do not re-pitch encrypting them.
- Retention is a 30-day R2 lifecycle rule set on the bucket, deliberately not code.
- Dead-man's switch is Healthchecks.io via Laravel's built-in `pingOnSuccess`/`pingOnFailure`, wired in `routes/console.php` on the SCHEDULE not the command - so a manual `backup:database` cannot satisfy the switch and mask a dead scheduler. `HC_PING_URL` empty = no pings registered. Check is Period 1 day / Grace 30 min, so it alerts ~03:30 UTC.
- The `r2` disk is the only one with `throw => true` (a silent false would report a failed upload as a good backup) and pins `request_checksum_calculation`/`response_checksum_validation` to `when_required`, because aws-sdk-php >= 3.337 defaults to CRC32 trailers that R2 has been inconsistent about accepting.
- Adding any Kamal secret means THREE places: the `env:` block in `.github/workflows/deploy.yml`, the variable list in the loop that writes `.kamal/secrets`, and `env.secret:` in `config/deploy.yml`. GitHub secrets are the source of truth; `.kamal/secrets` is regenerated every deploy. The local `.kamal/secrets` is drifted and unused.

## Admin Panel (Implemented Feb 2026)

- `role` varchar + `is_system_user` bool + `softDeletes` on `users` table
- Ghost user: `twitch_id = 'GHOST_USER'`, `is_system_user = true`, seeded via `GhostUserSeeder`
- `admin_audit_logs` table: append-only (`UPDATED_AT = null`)
- Middleware: `EnsureAdminRole` (abort 404 for non-admins), `HandleImpersonation` (session swap)
- Route middleware: `admin.role` only (no `auth.redirect`) - unauthenticated users also get 404
- All admin controllers in `app/Http/Controllers/Admin/`
- Vue pages in `resources/js/pages/admin/` (lowercase)
- `isAdmin` + `impersonating` shared via `HandleInertiaRequests::share()`
- `OverlayTemplate::factory()` defaults `fork_of_id` to null (use `->forked()` to create a parent). Never give a self-referential FK a nested factory as its default - it recurses with no base case, commits one User per level via `owner_id`, and never lands a single template. See `FactoryRecursionTest`
- `tests/Pest.php` updated to `->in('Feature', 'Unit')` for Laravel TestCase in unit tests

## Alert Targeting (Implemented Mar 2026)

- `alert_template_static_overlays` pivot table: self-referential on overlay_templates, cascadeOnDelete, unique constraint
- `AlertTriggered` event has `?array $targetOverlaySlugs = null` property (backward-compatible)
- `broadcastWith()` includes `target_overlay_slugs` in the `alert` array
- All broadcast points: TwitchEventSubController::renderEventAlert, ExternalAlertService::dispatch, ExternalEventController::replay
- `OverlayRenderer.vue` early-exits in `handleAlertTriggered()`: if targetSlugs !== null and slug not in list, return
- Semantic: empty pivot = null slugs = fires on ALL overlays (backward-compatible default)

## Provider Icons (Implemented Jul 2026)

- 4x4 grid SVG icons identify the source service in the events feed (`EventsTable.vue`). Shape is the PRIMARY identity channel - legible in sunlight and under color vision deficiency even with no color at all. Replaced the color-only `eventDotClass` dot (which mixed event-type + source color).
- Color is layered back on as REINFORCEMENT, not sole identity (redundant encoding = the a11y-correct way to add color): `eventDotClass(event)` is applied to the icon in `EventsTable.vue`. It resolves by event type first - Twitch types get a `text-*` class (sets `currentColor`, which the SVG `fill` inherits) - then falls back to source, where external services get a `fill-*` class (colors the SVG directly). So Twitch reads as calm texture and a Ko-fi-orange icon pops pre-attentively. Do NOT reduce this back to shape-only or color-only; the pairing is deliberate.
- Encoding: each icon is a `uint16`, one bit per grid cell. Bit 15 (MSB) = top-left, filling left-to-right, top-to-bottom to bit 0 (bottom-right). A binary literal reads exactly like the grid.
- `resources/js/utils/providerIcons.ts`: pure module, `PROVIDER_ICONS` map (source -> {bits, label}), `iconCells()`, `providerIcon()`, `iconDistance()` (Hamming). `resources/js/components/ProviderIcon.vue` renders the SVG with `fill=currentColor` (so a `text-*` class tints it), `role="img"` + `aria-label`. Color class strings are full literals in `useEventColors.ts` so Tailwind's scanner keeps them.
- Adding a provider: pick an unused `uint16` with 4-8 filled cells, confirm `iconDistance() >= 6` against every existing icon. Current set's min pairwise distance is 8.
- Icon SHAPE is keyed by `source` (twitch/kofi/streamlabs/streamelements/bmac/fourthwall/throne), NOT event type - all Twitch events share the Twitch ring. Icon COLOR is keyed by event type for Twitch (and by source for externals - see the reinforcement bullet). Event type is additionally carried by the adjacent text label. `TemplateTable.vue` still uses `eventTypeDotClass` in a different context (which event triggers a template).

## Contextual Help (Implemented Jul 2026)

- Help pages declare where they are relevant, in a flat `context:` frontmatter line: `context: templates.index?type=block, templates.show?type=block, builder.create`. The association lives in the markdown, NOT in a central route map - writing the page wires it up.
- Key is the **route name**, not the URL. Optional `?k=v` constraints narrow one name down to a state (`/templates` is one route serving four filter states). Wildcards use `Str::is()`, so `settings.bot.*` works.
- Declared constraints must match; **undeclared query params are ignored** (so `search`/`sort`/`page` never break a match). There is no allowlist of meaningful params and none is needed.
- `HelpContext::add(['type' => $template->type])` injects context the URL cannot carry (one route, three template types). It merges into the same bag as the query string - one matcher.
- `HelpContext::for()` returns ALL matches, best first: exact-over-wildcard, then constraint count, then literal pattern length, then slug. No `priority:` key on purpose - specificity is the only currency.
- Shared as the `help` Inertia prop (`HelpLink[]`: slug, title, lead, url). Card copy uses the page's `heading` + `lead`, NOT `title` + `description` - the latter are written for browser tabs and search results and run too long for a 375px panel. A test caps both at panel length.
- `HelpBeacon.vue` renders it: floating bottom-right button on every app page (mounted once in `AppLayout`), dot when help matched, 375x650 panel. Links open in a new tab - the point is helping with the page you are on, so navigating away from it would undo the feature.
- `Alt+H` toggles the beacon, next to `Alt+R` for the tags reference (Alt = open a panel to read, Ctrl = do something). Registered via `useKeyboardShortcuts`, so it self-lists in the `Ctrl+K` dialog - there is no static shortcut list anywhere to update.
- Two tests are load-bearing: every declared context must name a real route (catches renames), and no single context may resolve to more than 3 pages (stops generic routes rotting into a link farm). Both verified to fail when violated - keep them.

## Collection List (Implemented Aug 2026)

- `CollectionList.vue` is the ONE component for rendering a collection of rows. Generic (`generic="TItem"`), typed, with `item` / `actions` / `empty` slots. Every page that lists things uses it. A sixth list design is the regression this exists to prevent.
- It replaced five designs for one idea. `TemplateTable.vue` (rendered no table - a relic name from when it was one) and `TemplateList.vue` (a copy of it that had drifted) are both deleted, merged into `TemplateCollection.vue`. `/triggers` and `/dashboard/lists` had each invented their own row from scratch.
- The row skin is `.collection-row` in `resources/css/collection.css` (renamed from `.overlabels-background`, which described nothing). It carries border + hover + active ONLY. Padding is deliberately not in it: baking padding into a skin is what let every caller override it with a different value and produce four densities from one class. `CollectionList` sets `p-3`; anything using the skin directly sets its own.
- **Rows navigate by stretched link**: the row is a plain container with an absolutely positioned `<Link>` covering it. This is why middle-click, ctrl-click and "copy link address" work. Interactive content inside the `item` slot needs its own `relative z-10`, since the stretched link paints over anything unpositioned. Do NOT go back to `role="button"` + `router.visit` (loses all anchor semantics) or to nesting buttons inside an `<a>` (invalid HTML; the nested button also navigates).
- Actions are hover-revealed only from `md` up. Below that they stay visible - a touch device has no hover, and hiding them there made the kebab menu unreachable.
- **`EventsTable.vue` and `ControlsManager.vue` are deliberately NOT converted.** Each row is wrapped in a popover / collapsible respectively, so they are a different shape, not the same row with different content. They stay on `.collection-row` so they still move with the skin. Do not "finish the job" unprompted.
- Empty states go through `EmptyState.vue` (via `emptyMessage`/`emptyDashed` props or the `empty` slot), not hand-rolled dashed divs.
- External event labels derive from `SERVICE_LABELS` in `resources/js/utils/services.ts`. The old hardcoded per-service map covered Ko-fi and Streamlabs only and had rotted into "bmac: donation" for the three donation services added after it was written.

## Development Workflow

### Setting Up Twitch Integration
1. Create Twitch app at dev.twitch.tv
2. Set `TWITCH_CLIENT_ID` and `TWITCH_CLIENT_SECRET` in `.env`
3. For local webhook testing, use ngrok and update webhook URL in Twitch settings

### Working with Templates
Templates use a custom tag system (e.g., `{{follower_count}}`) parsed by `TemplateParserService`. Tags are validated against available Twitch data. The template editor uses CodeMirror with custom syntax highlighting.

### Database Changes
Always create migrations for schema changes. Test rollback before committing. Use seeders for test data generation.

## Versioning

- Current version: `0.1.0`
- Version is set in TWO places - bump both when asked:
  - `package.json` -> `"version"` field
  - `composer.json` -> `"version"` field
- Uses semver: MAJOR.MINOR.PATCH

## Workflow Preferences

### STOP: check the working tree first

**At the start of EVERY conversation, run `git status` before doing anything the user asked for.**

If the working tree is dirty (any modified, staged, or untracked files), **do not start the task**.
Stop, show the user exactly which files are dirty, and tell them the tree should be cleaned first -
committed, stashed, or discarded, their choice. Then wait.

Be strict about this. Jasper consistently forgets he has uncommitted work in progress, and it is not
good: the risk is that unrelated changes get swept into a commit, or that his work in progress gets
clobbered by a checkout, a stash, or a branch switch that a later step needs to make.

- This applies to the whole tree, not just files related to the request. Dirty is dirty.
- Being careful to stage selectively is **not** a substitute for stopping. Do that only after he
  has been told and has decided to continue.
- The only way past this is Jasper explicitly saying to proceed anyway after being warned. Once he
  has said so, continue without raising it again for the rest of that conversation.

### Committing and pushing

- At the end of every logical unit of work, prepare a commit: update CHANGELOG (docs/changelog/changelog-YYYY-MM.md - per-month files) first, then commit everything together - one commit. Do NOT push automatically. Ask the user for confirmation before pushing.
- If unsure whether to commit first or apply changes first, commit first then apply
- NEVER use em dashes in user-facing copy or code. Use hyphens with spaces instead.
- NEVER call "Fork" in frontend-facing UI. Always use "Copy" instead.

