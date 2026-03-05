# CHANGELOG MARCH 2026

## March 6th, 2026 — Responsive admin panel & unified EmptyState

- **Unified `EmptyState` component.** A single `EmptyState.vue` replaces all ad-hoc empty state patterns scattered across the app. Works in two modes: as a `<tr>` inside a `<tbody>` (when `colspan` prop is provided), or as a standalone `<div>` with optional icon, title, dashed border, and `#action` slot. All ~20 pages updated to use it.
- **Responsive headers.** The greeting + controls row on Dashboard and the Create/action buttons on Templates, Kits, Alerts, and Recents now stack vertically on mobile instead of overlapping.
- **Responsive alerts page.** Event rows on the `/alerts` page were completely broken on mobile (text-center misalignment, oversized mono tags, cramped quick-status). Fixed: removed `text-center`, hid verbose details behind `hidden sm:inline`, simplified quick-status display.
- **TemplateTable card layout.** The template table now renders a card list below the `xl` breakpoint (1280px) and the full table only at `≥ xl`, preventing the table from fighting the sidebar at 768–1280px widths.
- **Admin index pages — card layout below 1024px.** All 8 admin index pages now have a mobile card view hidden at `lg` and the full table visible only at `≥ lg`. Cards include all key fields, badges, and action buttons appropriate to each page.

## March 5th, 2026 — Admin: system lockdown mode

- **New: emergency lockdown kill switch at `/admin/lockdown`.** A triple-confirmed switch in the admin panel that immediately halts all overlay activity system-wide. Engaging lockdown deactivates every overlay access token, flushes all non-admin user sessions, returns 503 to all overlay render requests (OBS browser sources show an error banner), and silently absorbs Twitch and external webhook events without processing them. All user content is preserved — nothing is deleted.
- **Fully reversible.** The token IDs suspended during lockdown are stored in the cache. Lifting lockdown restores all those tokens and overlay health checks self-heal within ~5 minutes.
- **Triple-confirmation UI.** Engaging lockdown requires: (1) clicking "Engage lockdown", (2) reading consequences and optionally providing a reason, (3) typing `LOCKDOWN` verbatim into a confirmation input. Lifting requires a single confirmation step.
- **Lockdown banner shown to all logged-in users.** A red banner appears at the top of the dashboard for every user while the system is in lockdown. Admins see an additional "Manage lockdown" link.
- **Lockdown entry in admin sidebar** under the new `ShieldAlert` icon.
- **CLI fallback commands**: `php artisan lockdown:engage` and `php artisan lockdown:release` for emergencies when the admin panel itself is unreachable. Both run through the same `LockdownService` and write to the audit log.
- **Audit logged.** Both activation and deactivation write to `admin_audit_logs` with the acting admin, reason, token count, and timestamp.

## March 5th, 2026 — User-configurable dashboard icon

- **New: pick your own dashboard icon.** The smile icon next to your name on the dashboard is now personalised. Clicking it opens an inline text input where you can type any [lucide.dev](https://lucide.dev/icons/) icon code in kebab-case (e.g. `arrow-big-right`). The same setting is also available under **Settings → Appearance**. Unknown icon names fall back to `heart-crack`. Stored per-user in the database (`users.icon`).

## March 5th, 2026 — Admin: data pruning controls + access logs in sidebar

- **Access Logs now appear in the admin sidebar.** The `/admin/logs` page existed but was missing from `adminNavItems` — added with a ScrollText icon between Sessions and Audit Log.
- **Prune controls on Access Logs, Twitch Events, and External Events pages.** Each page now has a prune bar with a period dropdown (30 days / 60 days / 90 days / All records), a two-stage confirm button, and a success flash message after pruning. The events page is source-aware — pruning on the Twitch tab only deletes `twitch_events`, pruning on the External tab only deletes `external_events`.
- **All prune actions are audit-logged.** Each prune records the period and row count deleted to `admin_audit_logs` via `AdminAuditService`.
- **Weekly auto-prune scheduled for all three tables.** `overlay_access_logs`, `twitch_events`, and `external_events` are automatically pruned to 90-day retention every Sunday, guarding against unbounded growth.

## March 5th, 2026 — Database hygiene: Telescope, cache, sessions autovacuum

- **Telescope now defaults to disabled.** `TELESCOPE_ENABLED` defaults to `false` instead of `true`. Telescope was running in production with all watchers enabled, logging every request, query, cache hit, and job check to `telescope_entries` — the primary cause of the 121 MB database. Set `TELESCOPE_ENABLED=true` explicitly to enable it in local dev.
- **Telescope entries are now pruned daily.** Added `telescope:prune --hours=48` to the scheduler so entries older than 48 hours are automatically removed. Keeps the table from growing unbounded even if Telescope is enabled.
- **Sessions autovacuum tuned.** Added a PostgreSQL-specific `ALTER TABLE sessions SET (autovacuum_vacuum_scale_factor = 0.01, ...)` so autovacuum cleans up dead rows as soon as ~1% are dead rather than waiting for the default 20% threshold. Fixes the "187.5% dead rows" vacuum health warning.
- **`foxes` table dropped.** Removed an old experimental table that had no purpose in the application.
- **`CACHE_STORE` default changed to `file`.** The database cache driver caused the queue worker to poll the `cache` table ~43 times per minute (restart signal checks) with no benefit. File-based cache eliminates that. Update your Railway env var accordingly.

## March 3rd, 2026 — Fix: Ko-fi controls can now be added to multiple templates

- **Fixed unique constraint on `overlay_controls` blocking Ko-fi preset reuse.** The `overlay_controls_user_source_key_unique` index on `(user_id, source, key)` was a full-table constraint, which prevented adding the same Ko-fi control (e.g. `kofis_received`) to more than one static overlay template. Replaced it with a PostgreSQL partial unique index scoped to `WHERE overlay_template_id IS NULL` so user-scoped controls remain unique while template-scoped Ko-fi presets can be freely added to any number of templates.

## March 2nd, 2026 — External events visible in admin panel (Issue #77)

- **`/admin/events` now shows both Twitch and External events.** A source toggle (Twitch | External) appears above the filter row. Clicking "External" switches to a table showing Ko-fi (and future) external events with Service, Type, User, Controls Updated, and Alert Dispatched columns.
- **New read-only detail page `/admin/external-events/{id}`.** Shows metadata, the normalized payload, and (collapsed by default) the raw payload. External events are append-only so no edit/delete actions are present.
- **Filters persist across source switches.** `applyFilters()` now includes the active `source` in every router.get call; the "processed" filter dropdown is hidden when viewing External events (it only applies to Twitch events).

## March 2nd, 2026 — Alert template targeting (Issue #74)

- **New: Per-alert-template overlay targeting.** Alert templates can now be restricted to fire on specific static overlays instead of all of them. On any alert template's edit or show page a new "Targeting" tab lets you select which static overlays receive the alert. Leaving all unchecked keeps the original behaviour (fires on every connected overlay).
- **New pivot table `alert_template_static_overlays`.** Self-referential pivot on `overlay_templates` recording which alert template fires on which static overlays.
- **`AlertTriggered` broadcast now carries `target_overlay_slugs`.** All three dispatch points (Twitch EventSub, Ko-fi/external webhook, and event replay for both) populate a `target_overlay_slugs: string[]|null` field. `null` means "all overlays".
- **`OverlayRenderer.vue` enforces the whitelist.** On receiving an `alert.triggered` event, each overlay instance compares its own slug against the list. If the list is non-null and the slug is absent the alert is silently skipped.
- **10 new feature tests** covering: `updateTargetOverlays` route (saves pivot, ownership check, type validation, cross-user rejection), `ExternalAlertService` broadcast with/without targets, and `ExternalEventController::replay` with/without targets.

## March 1st, 2026 — Ko-fi starting donation count seed

- **New: Starting donation count on Ko-fi settings page.** Users who had Ko-fi donations before joining can set a starting number so the `kofis_received` control doesn't begin from zero. Setting it immediately updates all existing `kofis_received` controls across all their overlay templates.
- **One-time lock.** Once set, the field is replaced with a locked read-only display. Corrections go through admin@overlabels.com — the value is stored in the integration settings and survives re-saves.
- **Fixed: `save()` was silently wiping seed settings.** The settings JSON was being overwritten instead of merged on each form save. Now uses `array_merge` so `kofis_seed_set` and `kofis_seed_value` survive a token update or event-type change.

## March 1st, 2026 — Add Control modal UX improvements

- **Ko-fi preset selector replaced with a dropdown.** The button-chip grid for Ko-fi control presets is gone. A single `<select>` now lists all 6 presets as "Label (type)". Selecting one still pre-fills key/type (locked) and shows the template snippet hint below.
- **Sort order replaced with a Position dropdown.** The raw number input is replaced with three options: "After existing (last)" (default when adding — places the control after the highest current sort order), "Before existing (first)" (places it before the lowest), and "Enter sort order manually" (reveals the number input). When editing an existing control the dropdown defaults to manual with the current value pre-filled. Sort order math is computed from the live controls list passed down from `ControlsManager`.

## March 1st, 2026 — External events in Activity Feed + Replay

- **Ko-fi events now appear in the dashboard activity feed.** The Recent Stream Activity section on the dashboard and `/dashboard/recents` now merges Twitch events and Ko-fi (external) events into a single unified list, sorted newest-first.
- **Ko-fi events are replayable.** Every Ko-fi event row in the activity table has the same "Replay alert" dropdown menu option as Twitch events. Triggering it re-broadcasts the stored `normalized_payload` through the user's active `ExternalEventTemplateMapping`, re-firing the alert on connected overlays. New `POST /external-events/{id}/replay` endpoint handles this.
- **Unified event shape.** The backend merges `TwitchEvent` and `ExternalEvent` records into a common `UnifiedEvent` array with a `source` discriminator (`'twitch'` or service name). `EventsTable.vue` renders both types with correct labels, "From" names, and detail summaries (amount + currency for Ko-fi donations).

## March 1st, 2026 — Ko-fi Integration Finalization (Alerts + Controls)

### Part 1 — Ko-fi alert mappings in Alerts Builder
- **New: Ko-fi events section in Alerts Builder.** When Ko-fi is connected, a new "External Integrations" section appears below the Twitch events list on `/alerts`. Each Ko-fi event type (Donation, Subscription, Shop Order, Commission) can be mapped to an alert template with independent duration and enter/exit animation settings.
- **Alert mappings persist per user+service+event_type.** Saved via a new `PUT /alerts/external/bulk` endpoint. New `ExternalEventTemplateMappingController` mirrors the Twitch `EventTemplateMappingController` with the same store/updateMultiple/destroy pattern.
- **Alert duration and transitions now come from the mapping.** `ExternalAlertService` previously hardcoded `duration: 5000, fade/fade`. It now reads `duration_ms`, `transition_in`, and `transition_out` from the stored mapping, so each event type can have its own animation.
- **Migration:** `duration_ms` (int, default 5000), `transition_in`, `transition_out`, `settings` (JSON) added to `external_event_template_mappings`.

### Part 2 — Ko-fi controls via ControlFormModal (template-scoped)
- **Ko-fi controls are now explicitly added to templates.** Instead of auto-provisioning 6 user-scoped controls on Ko-fi connect (invisible in all template Controls tabs), users now add them explicitly from the Controls tab on any static overlay template. The "Add control" modal shows a Ko-fi presets panel listing all 6 controls — selecting one pre-fills the key/type (locked) and sets `source=kofi, source_managed=true`.
- **Removed auto-provisioning.** `KofiIntegrationController::save()` no longer calls `ExternalControlService::provision()` on first connect.
- **Disconnect now cleans up template-scoped Ko-fi controls.** The existing `deprovision()` query (`source=kofi, source_managed=true`) now correctly targets template-scoped controls since that's what gets created.
- **`applyUpdates()` now handles multiple controls per key.** When a Ko-fi donation arrives, all controls with that key (potentially across multiple templates) receive the update and each broadcasts to their specific overlay slug.
- **Fixed: `renderAuthenticated()` now uses correct namespaced data key for service controls.** Service-managed controls (e.g. `kofis_received` with `source=kofi`) previously stored as `c:kofis_received` (wrong) — now correctly stored as `c:kofi:kofis_received`, matching the `[[[c:kofi:kofis_received]]]` template tag syntax.
- **Snippet column in Controls table shows namespaced key.** `[[[c:kofi:kofis_received]]]` is displayed and copied, not `[[[c:kofis_received]]]`.
- **Ko-fi settings page updated.** The "doesn't do anything" warning banner is replaced with clear guidance: configure alert templates on `/alerts`, add controls from any static template's Controls tab.

### Part 3 — Ko-fi event tags in Help docs
- **New: Ko-fi Integration Events section in `/help`.** Documents all `event.*` tags available in Ko-fi alert templates, organized by: all Ko-fi events, donation/subscription, subscription-only, with a working example template snippet.

### Bug fixes
- **Fixed: `message_id` deduplication hack removed.** The webhook controller was appending `substr(md5(microtime()),rand(0,26),5)` to every stored `message_id`, making all Ko-fi events unique and breaking the 409 dedup check. Removed — transaction IDs are stored exactly as received.

## March 1st, 2026 — MS2 + MS3 hotfix

- **Fixed: Ko-fi webhook URL showing "false" after connecting.** The Ko-fi settings page used `:value` to pass the webhook URL to the Input component, which does not correspond to the component's declared `modelValue` prop. Changed to `:model-value` so the value routes through the component correctly.
- **Fixed: Ko-fi integration saving as disabled on first connect.** The form initialised `enabled` from `props.integration.enabled`, which is `false` in the disconnected state. This meant every first-time connect immediately stored `enabled: false`, causing the webhook pipeline to silently drop all incoming Ko-fi events. The form now defaults `enabled: true` when not yet connected, and the controller forces `enabled: true` on first save.

## March 1st, 2026 — MS2 + MS3: External Integrations & Ko-fi

### External integration rails (MS2)
- **New: external integration infrastructure.** A new `POST /api/webhooks/{service}/{webhook_token}` route handles incoming payloads from any supported third-party service. The pipeline: verify → deduplicate → normalize → update Controls → fire alert — exactly mirrors the Twitch EventSub pipeline. Adding a new service in the future means writing one driver class.
- **New: service-managed Controls.** Controls now support a `source` / `source_managed` flag. When a service (e.g. Ko-fi) writes to a control, the overlay receives the update over the existing `control.updated` broadcast — no renderer changes needed. Service-managed controls return 403 to any manual edit attempt from the dashboard.
- **New: user-scoped Controls.** `overlay_template_id` is now nullable. Controls with `NULL` template ID are user-global — they appear in every overlay belonging to that user. Ko-fi donation counters, latest donor name, etc. are all user-scoped.
- **New: namespaced control keys.** Service controls use a namespaced tag syntax: `[[[c:kofi:kofis_received]]]`. The broadcast key is `kofi:kofis_received`; the data map key is `c:kofi:kofis_received`. The colon was already in the tag extraction regex — no parser changes needed. CSS `[[[c:kofi:...]]]` works the same as any other tag replacement.
- **New: Settings → Integrations page.** `/settings/integrations` lists all supported services with connected/disconnected status. Additional services show "Coming soon".
- **New: `ExternalServiceDriver` interface + `ExternalServiceRegistry`.** Clean extensibility pattern for adding Throne, Patreon, Fourthwall, etc. in future milestones.

### Ko-fi integration (MS3)
- **New: Ko-fi connected.** Connect Ko-fi from Settings → Integrations → Ko-fi. Paste your verification token, copy your webhook URL into Ko-fi's API settings, and donation/subscription/shop order events flow directly into your overlays.
- **Automatic Ko-fi controls provisioned on connect.** Six controls are created automatically: `kofis_received` (counter), `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`. Use them in any template as `[[[c:kofi:kofis_received]]]` etc.
- **Ko-fi alerts.** Map Ko-fi donation/subscription/shop order events to any alert template from the Integrations settings page. The alert fires on the same broadcast channel as Twitch events — existing alert rendering requires no changes.
- **Disconnect Ko-fi** removes the integration and all auto-provisioned controls.
- **28 new tests** cover: KofiServiceDriver (unit), webhook pipeline (404/403/200/409/dedup/control updates), and source_managed guard (feature).
