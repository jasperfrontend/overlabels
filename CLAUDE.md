# Claude Code Memory Export

Last updated: 2026-03-15

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

**Overlay System**: Templates stored in `overlay_templates` table with a custom tag system that parses Twitch data dynamically. Access controlled through tokens (`OverlayAccessToken`) or hash-based public links (`OverlayHash`). Render pipeline: `authenticate.blade.php` -> `overlay/app.js` (creates Echo/Pusher) -> `OverlayRenderer.vue`.

**Frontend Stack**: Vue 3 components in `/resources/js/`. Inertia.js eliminates separate API endpoints for most operations. Pages in `/Pages/`, reusable components in `/components/`, UI primitives in `/components/ui/`. Components follow Shadcn/Reka-UI/Vue patterns. Composables in `/composables/`, TypeScript types in `/types/`. Tailwind v4 with CSS layers.

**Route Organization**: Routes split across `/routes/`: `web.php` (main), `api.php` (public API), `auth.php` (authentication), `settings.php` (user settings), `admin.php` (admin panel - must load BEFORE the catch-all route).

**API Endpoints**:
- Public overlay rendering: `/api/overlay/render` (rate-limited)
- Twitch webhook: `/api/twitch/webhook`
- External webhooks: `POST /api/webhooks/{service}/{webhook_token}` (no auth/CSRF)
- Template operations require authentication through Inertia

**Testing**: Feature tests in `/tests/Feature/`, unit tests in `/tests/Unit/`. Pest framework with Laravel-specific helpers. Tests use `RefreshDatabase`.

### Key Architecture Notes

- `useEventSub.ts` reuses `window.Echo` instead of creating a duplicate Pusher connection
- `useOverlayHealth.ts` composable handles: retry with backoff, Pusher monitoring, periodic health checks, auto-reload
- Banner styles live in the blade template (not in Vue) so they're available before Vue mounts
- Overlay auth uses 64-char hex tokens in URL fragments (never sent to server)
- Two Pusher channels: `twitch-events` (global) and `alerts.{user_twitch_id}` (per-user)
- OBS browser sources can't show console errors - visual banners are the only way to communicate errors to streamers

### Important Services

- `TwitchApiService`: All Twitch API interactions
- `TemplateParserService`: Template tag parsing and validation
- `OverlayAccessService`: Access control for overlays
- `AdminAuditService`: Append-only audit logging
- `ExternalControlService`: External service control updates
- `ExternalAlertService`: External service alert dispatch
- Queue workers handle background tasks (EventSub processing)

## Controls System (Implemented Feb 2026)

- `overlay_controls` table: id, overlay_template_id (nullable!), user_id, key, label, type, value, config (json), sort_order, source, source_managed
- `OverlayControl` model: `sanitizeValue()`, `resolveDisplayValue()`, `createForTemplate()`, `provisionServiceControl()`, `broadcastKey()`
- Carbon `diffInSeconds` bug: use `$start->diffInSeconds($now)` not `$now->diffInSeconds($start)` (latter returns negative)
- Template syntax: `[[[c:key]]]` or namespaced `[[[c:kofi:kofis_received]]]` - colon already in regex char class
- Broadcast: `ControlValueUpdated` -> `alerts.{twitch_id}` channel, broadcastAs `control.updated`
- Service-managed controls: `source_managed=true` -> `setValue()` and `update()` return 403
- User-scoped controls: `overlay_template_id=null`, available in all user's overlays
- Namespaced broadcast key: "kofi:kofis_received" -> stored in data as "c:kofi:kofis_received"
- Empty `overlay_slug` in broadcast = user-scoped; OverlayRenderer applies to all overlays

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
- `renderAuthenticated()` uses `c:` + `broadcastKey()` for source_managed controls -> `c:kofi:kofis_received`
- `connectedServices` prop threaded: OverlayTemplateController::show() -> show.vue -> ControlsManager.vue -> ControlFormModal.vue

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

