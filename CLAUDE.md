# Claude Code Memory Export

Last updated: 2026-04-12

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
npm run lint             # ESLint with auto-fix
npm run format           # Prettier formatting
php artisan pint         # PHP code style fixes
```

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
- Event grouping: `stream_session_id` FK on `twitch_events` and `external_events`. Stamped via `stampEventsWithSession()` on live transition.
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

## Pipe Formatting System (Implemented Apr 2026)

- Syntax: `[[[tag_name|formatter]]]` or `[[[tag_name|formatter:args]]]`
- Built-in formatters: `round`, `duration`, `currency`, `date`, `number`, `uppercase`, `lowercase`
- Duration patterns: `hh:mm:ss`, `mm:ss`, `dd:hh:mm:ss` etc. - units overflow into the largest present unit
- Formatter utility: `resources/js/utils/formatters.ts` - pure functions, zero dependencies, uses native `Intl` APIs
- `OverlayRenderer.vue` uses `TAG_REGEX` for single-pass replacement: matches tag + optional pipe, resolves value, applies formatter
- `TAG_REGEX`: `/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?]]]/g` - group 1 = tag key (includes hyphens for service names like `overlabels-mobile`), group 2 = pipe expression
- PHP `extractTemplateTags()` regex: `/\[\[\[([a-zA-Z0-9_.][a-zA-Z0-9_.:\-]*?)(?:\|[a-zA-Z0-9_.:% -]+)?]]]/` - tag key includes hyphens; pipe char class includes space for patterns like `date:dd-MM-yyyy HH:mm`
- PHP `extractTemplateTags()` strips pipe expressions to extract clean tag names for the allowlist
- Global locale stored on `users.locale` (default `en-US`), passed via API response as `json.locale`
- Settings UI: Appearance page has locale picker with live number/currency/date preview

## External Integrations (Implemented Mar 2026)

- Pipeline: ExternalWebhookController -> verifyRequest -> parsePayload -> normalizeEvent -> ExternalEvent (dedup on service+message_id) -> ExternalControlService.applyUpdates -> ExternalAlertService.dispatch
- `ExternalServiceDriver` interface in `app/Contracts/` - getServiceKey, verifyRequest, parseEventType, normalizeEvent, getSupportedEventTypes, getAutoProvisionedControls, getControlUpdates
- `ExternalServiceRegistry` maps service key -> driver class
- `ExternalIntegration`: UUID webhook_token (routing key), encrypted credentials (Crypt::encryptString), settings (json), enabled, last_received_at
  - Use `setCredentialsEncrypted(array)` / `getCredentialsDecrypted()` - NOT raw $fillable assignment
  - In tests: pass pre-encrypted credentials directly to factory->create(['credentials' => Crypt::encryptString(...)])
- `ExternalEvent` append-only model (UPDATED_AT = null), global dedup on (service, message_id)
- `ControlValueUpdated::dispatch()` uses variadic `...$arguments` - use POSITIONAL args, not named args

### Ko-fi Integration

- Ko-fi driver: payload is form-encoded body with `data` JSON field - use `$request->input('data')` to get string
  - In tests: use `$this->post(url, ['data' => json_encode($payload)])` NOT `postJson`
- Ko-fi controls: NO auto-provision on connect - user explicitly adds from ControlFormModal on static templates
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

### StreamElements Integration

- JWT-based (NOT OAuth): StreamElements does not have self-serve OAuth app registration. Users generate a JWT from their dashboard (Account > Channels > Show secrets > JWT Token) and paste it into the Overlabels settings page
- JWTs have no refresh flow - if revoked, user must paste a new one. This trade-off is why the integration is JWT rather than OAuth
- WebSocket: Socket.IO at `https://realtime.streamelements.com`. After connect, `socket.emit('authenticate', { method: 'jwt', token: jwtToken })`. Listen for `'authenticated'` (channelId), `'unauthorized'`, `'event'`
- Only `tip` event type supported - payload shape: `{ _id, channel, type: 'tip', data: { username, displayName, amount, message, currency, tipId } }`. Driver's `parseEventType()` maps `tip` -> `donation` so alert templates can target `[[[if:event.type = donation]]]` across Ko-fi, StreamLabs, and SE uniformly
- Uses Socket.IO (pull model) via server-side Node.js listener, NOT webhooks
- `streamelements-listener.mjs` bridges SE Socket.IO -> POST to `/api/webhooks/streamelements/{webhook_token}`
- Internal API `GET /api/internal/streamelements/integrations` is polled every 60s and returns `jwt_token` + `listener_secret` per integration. Authenticated by `STREAMELEMENTS_LISTENER_SECRET`
- Listener reconnects when JWT changes (checks cached token vs new one from poll). On `unauthorized`, drops connection; user must save a new JWT to recover
- Verification: `X-Listener-Secret` header checked against per-integration `listener_secret` credential
- Auto-provisions 6 controls (donation-family naming, aligned with Ko-fi and StreamLabs): `donations_received`, `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`
- Credentials stored (encrypted): `jwt_token`, `listener_secret`
- Env vars: `STREAMELEMENTS_LISTENER_SECRET` only (no client id/secret)
- Settings routes: under `settings/integrations/streamelements` prefix. `POST /settings/integrations/streamelements` saves/replaces the JWT. Seed method named `seedDonationCount()`, settings keys `donations_seed_set`/`donations_seed_value`
- Template syntax: `[[[c:streamelements:donations_received]]]`
- In tests: use `postJson` with `X-Listener-Secret` header (same as StreamLabs). Test credentials shape: `['jwt_token' => ..., 'listener_secret' => ...]`

## Admin Panel (Implemented Feb 2026)

- `role` varchar + `is_system_user` bool + `softDeletes` on `users` table
- Ghost user: `twitch_id = 'GHOST_USER'`, `is_system_user = true`, seeded via `GhostUserSeeder`
- `admin_audit_logs` table: append-only (`UPDATED_AT = null`)
- Middleware: `EnsureAdminRole` (abort 404 for non-admins), `HandleImpersonation` (session swap)
- Route middleware: `admin.role` only (no `auth.redirect`) - unauthenticated users also get 404
- All admin controllers in `app/Http/Controllers/Admin/`
- Vue pages in `resources/js/pages/admin/` (lowercase)
- `isAdmin` + `impersonating` shared via `HandleInertiaRequests::share()`
- In tests: use `OverlayTemplate::factory()->create(['fork_of_id' => null])` to avoid recursion
- `tests/Pest.php` updated to `->in('Feature', 'Unit')` for Laravel TestCase in unit tests

## Alert Targeting (Implemented Mar 2026)

- `alert_template_static_overlays` pivot table: self-referential on overlay_templates, cascadeOnDelete, unique constraint
- `AlertTriggered` event has `?array $targetOverlaySlugs = null` property (backward-compatible)
- `broadcastWith()` includes `target_overlay_slugs` in the `alert` array
- All broadcast points: TwitchEventSubController::renderEventAlert, ExternalAlertService::dispatch, ExternalEventController::replay
- `OverlayRenderer.vue` early-exits in `handleAlertTriggered()`: if targetSlugs !== null and slug not in list, return
- Semantic: empty pivot = null slugs = fires on ALL overlays (backward-compatible default)

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

- At the end of every logical unit of work, prepare a commit: update CHANGELOG (docs/changelog/changelog-YYYY-MM.md - per-month files) first, then commit everything together - one commit. Do NOT push automatically. Ask the user for confirmation before pushing.
- If unsure whether to commit first or apply changes first, commit first then apply
- NEVER use em dashes in user-facing copy or code. Use hyphens with spaces instead.
- NEVER call "Fork" in frontend-facing UI. Always use "Copy" instead.

