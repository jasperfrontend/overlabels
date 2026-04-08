# CHANGELOG APRIL 2026

## April 8th, 2026 - Docs: Gift bomb detection explained on help page

- Added a detailed explanation of the gift bomb detection system to the Subscription Gifts section on the Conditionals help page.
- Explains why the system exists (Twitch sends individual events per gift), how it works (8-second collection window, live counter updates), and display durations per gift count tier.
- Includes a conditional template example for styling large gift bombs differently.

## April 8th, 2026 - Feature: Active events modal on integrations page

- The "Listening to 9 events" text on the integrations page now has a clickable link that opens a dialog showing all supported Twitch EventSub events with their human-readable labels.
- Each event shows a checkmark or cross indicating whether it's currently active for the user.
- Added `getSupportedEventLabels()` to `UserEventSubManager` to map event types to friendly names, kept in sync with `SUPPORTED_EVENTS`.

## April 8th, 2026 - Fix: Remove stale Twitch-side subscriptions before recreating

- `removeUserSubscriptions` only deleted subscriptions tracked in the local DB. If the DB was out of sync (e.g. from the broken queue job), Twitch-side subscriptions remained, causing 409 Conflict on recreation.
- Now also fetches all subscriptions from Twitch's API and deletes any belonging to the user by matching `broadcaster_user_id` / `to_broadcaster_user_id` in the condition.

## April 8th, 2026 - Fix: EventSub Connect button not working

- The Connect button dispatched a `SetupUserEventSubSubscriptions` job that never ran (likely serialization issue with the private `$user` property).
- Changed the connect endpoint to run setup synchronously via `UserEventSubManager` instead of dispatching a queue job. The user now gets immediate feedback with actual results (created/existing/failed counts).
- Removed the 3-second delay before page reload - the page now reloads immediately after setup completes.
- The queued job is still used for auto-connect on login (where synchronous execution would block the login flow).

## April 8th, 2026 - Fix: EventSub subscription status stuck at pending

- Race condition: Twitch sends the challenge verification request before the queue worker finishes storing the subscription record in the database. The challenge handler's `WHERE twitch_subscription_id = ...` update silently matches 0 rows, so the status stays `webhook_callback_verification_pending` forever.
- Added a `verifyUserSubscriptions` call at the end of `setupUserSubscriptions` that reconciles local status with Twitch's actual status. By the time all 9 subscriptions are created, earlier challenges have completed, so the verification picks up the correct `enabled` status.
- This fixes the Settings > Integrations page showing "No active subscriptions" and the yellow reconnect warning despite subscriptions working correctly.

## April 8th, 2026 - Fix: align webhook secrets across subscription creation paths

- `TwitchEventSubService` subscribe methods now accept an optional `$webhookSecret` parameter instead of always hardcoding the global secret.
- `TwitchEventSubController::connect()` now passes the user's per-user `webhook_secret`, matching what `UserEventSubManager` already does.
- Previously, subscriptions created via `connect()` used the global secret while `UserEventSubManager` used the user secret, causing duplicate subscriptions with mismatched secrets and Twitch retry storms on every event.
- After deploying, do a disconnect + reconnect from Settings > Integrations to recreate subscriptions with the correct secret.

## April 8th, 2026 - Debug: improved logging for invalid webhook signature

- Replaced the non-functional `Log::warning('Twitch webhook signature', $request)` with structured diagnostic data: subscription ID, message ID, event type, broadcaster ID, whether the user has a per-user webhook secret, and whether the global secret is configured.

## April 8th, 2026 - Fix: Dashboard stream status not updating in real-time

- The dashboard app did not initialize Echo/WebSocket, so `StreamStatusChanged` broadcasts from the stream state machine were never received by the frontend.
- Added Echo/Reverb initialization to `app.ts` (matching the existing overlay app setup).
- Updated `useStreamState` composable to listen for `.stream.status` events on the user's `alerts.{twitchId}` channel, so the live dot, transitioning indicator, and uptime counter update in real-time without requiring a page refresh.

## April 7th, 2026 - Fix: EventSub webhook challenge response

- Replaced the `exit()` hack in the webhook challenge handler with a proper Laravel `response()`.
- The `exit()` call was killing the PHP process before the challenge response could be properly flushed through Railway's reverse proxy, causing all EventSub subscriptions to get stuck at `webhook_callback_verification_pending`.
- Challenge handler now marks the local subscription record as `enabled` immediately after responding, so the UI reflects the correct status without waiting for a health check or manual refresh.
- Connect/Reconnect button now always force-recreates subscriptions, cleaning up stale/pending ones first.
- This fixes EventSub subscription creation for all users.

## April 7th, 2026 - UI: EventSub connection management on Integrations settings page

- Added a Twitch EventSub card to the Settings > Integrations page showing subscription count, active count, and connected-since date.
- Connect/Reconnect button dispatches the `SetupUserEventSubSubscriptions` job to create or recreate all EventSub subscriptions.
- Refresh button verifies existing subscriptions with Twitch and renews any that have failed.
- Shows a yellow warning when `eventsub_connected_at` is set but no active subscriptions exist (e.g. after user deletion and re-registration).

## April 7th, 2026 - Feature: Stream state machine with confidence-based verification

- Implemented a deterministic state machine (offline -> starting -> live -> ending) that confidently determines whether a user is currently streaming.
- EventSub `stream.online` and `stream.offline` events now only trigger state transitions, not define truth. The Twitch Helix API (`GET helix/streams`) is the authoritative source.
- Confidence score (0.0-1.0) builds in 0.25 increments through Helix verification. Transitions to "live" or "offline" require confidence >= 0.75.
- `VerifyStreamState` queue job polls Helix every 10-60 seconds depending on state: 10s during starting/ending, 60s heartbeat during live.
- 120-second grace period in "ending" state handles OBS crashes and connection drops - if the stream comes back, the session is stitched (reused) instead of creating a new one.
- Session stitching: if a stream goes offline and comes back within 5 minutes, the existing session is reopened rather than creating a new session.
- Retroactive repair: session `started_at` is corrected to match Twitch Helix's `started_at` for accuracy.
- New `stream_states` table as the single source of truth for stream status per user.
- Added `stream_session_id` FK to `twitch_events` and `external_events` tables for future event grouping (query all events that happened during a stream session).
- Added `helix_stream_id` to `stream_sessions` table to store the Twitch stream ID.
- `StreamSessionService::isLive()` and `handleEvent()` now check confidence-based state instead of raw session existence.
- Per-stream controls (follows, subs, raids, etc.) only increment when confidence >= 0.75 and state is "live".
- `StreamStatusChanged` broadcast now includes `state`, `confidence`, and `startedAt` fields (backward-compatible - existing overlay listeners unaffected).
- Cached app access token in `TwitchEventSubService` (50-minute cache) to avoid redundant token requests during verification loops.
- Safety-net scheduler runs every 5 minutes to catch stuck states (if verification job chain breaks).
- Minimal frontend: green dot on user avatar when live, pulsing orange dot when transitioning, uptime tooltip.
- New `useStreamState` composable for reactive stream state in the frontend.
- Edge cases handled: missed offline events (heartbeat catches them), offline without prior online, OBS crash, quick restarts, overlapping sessions, Twitch API downtime.

## April 7th, 2026 - Fix: Test mode toggle now resets all service controls

- Disabling test mode for Ko-fi and StreamLabs now resets all source-managed controls to their defaults, not just the donation counter.
- Resets `total_received`, `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, and `latest_donation_currency` alongside the count key.
- Counter/number controls reset to '0' (or the seed value for the count key), text controls reset to empty.

## April 7th, 2026 - Fix: Timer controls not working in expressions

- Timer controls (and their `:running` companion) caused expression evaluation to silently fail, producing no output on the overlay.
- Root cause: `buildContext()` in the expression engine treated `c:timer:running` as a namespaced key (namespace `timer`, subkey `running`), overwriting the scalar `c:timer` value with a namespace object. This made `c.timer + 2` throw a TypeError that was silently caught.
- Fixed `buildContext()` to skip namespaced keys when a scalar value already occupies that namespace, and to let scalar keys overwrite any pre-existing namespace object.
- Fixed ExpressionBuilder preview showing wrong values for timer controls (always used empty string instead of computing elapsed seconds from config).

## April 7th, 2026 - UI: Timer running indicators and light mode contrast

- Timer cards in ControlPanel now show a green/red gradient background and ring border indicating running/stopped state, with a colored dot next to the timer display. Countto timers are excluded (always running).
- ControlPanel cards get `bg-accent/70` in light mode for visibility against the white page background; dark mode keeps `bg-background`.
- ControlsManager table rows now use zebra striping (`odd:bg-accent/50`) for better readability in light mode.
- Added `backup-*.sql` to `.gitignore`.

## April 7th, 2026 - Fix: Pipe arguments with spaces break tag rendering

- Template tags with spaces in pipe arguments (e.g. `[[[c:datetime_thing|date:dd-MM-yyyy HH:mm]]]`) were not rendering at all - the raw tag text was output in the overlay.
- Fixed both the frontend TAG_REGEX in `OverlayRenderer.vue` and the PHP `extractTemplateTags()` regex in `OverlayTemplate.php` to allow spaces in the pipe argument character class.

## April 7th, 2026 - Feature: Date formatter now includes time and named presets

- Default `|date` output now includes time (e.g. "Apr 5, 2026, 7:00 PM") instead of date only.
- Added named presets: `|date:short` (compact), `|date:long` (full weekday), `|date:date` (date only), `|date:time` (time only).
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

- Replaced the hardcoded 70-line sample data object in `create.vue` with the canonical `TemplateDataMapperService::getSampleTemplateData()` passed as an Inertia prop.
- Updated sample data to use `wilko_dj` for user/channel name fields.
- Replaced the broken custom Modal component with Shadcn Dialog for the preview modal - now properly closes on ESC and click-outside.
- Added a Close button and ESC hint to the preview footer.

## April 6th, 2026 - UI: Show control values and config summary in ControlsManager table

- The Settings column now shows the current value for text, boolean, and other controls that have no type-specific config.
- Long values are truncated with `max-w-48` and hoverable via a `title` attribute to see the full value.
- Source-managed controls show "Managed by source" alongside any other config info.

## April 6th, 2026 - Fix: Undo and cursor-aware inserts in ExpressionBuilder

- Fixed Ctrl+Z not working in the expression formula textarea. The computed get/set round-tripped every keystroke through the parent prop, causing Vue to programmatically reset the textarea value and wipe the browser's undo stack. Replaced with a local ref and sync watchers.
- Clicking a control button now inserts at the cursor position instead of appending to the end.
- Programmatic inserts use `execCommand('insertText')` so they are also undoable with Ctrl+Z.

## April 6th, 2026 - UI: Redesign ExpressionBuilder panel

- Moved syntax reference and usage help into a dedicated Help dialog (accessed via a Help button), decluttering the main panel.
- Added a filter input for the Available Controls list so you can quickly find controls.
- Controls are now grouped by source (Your controls, Twitch, Ko-fi, StreamLabs, etc.) with sticky headers.
- Each control button shows its label as a subtitle for easier identification.
- Better contrast: buttons use solid backgrounds instead of dashed muted borders, with violet highlight on hover.
- Help dialog includes organized sections for syntax, operators, 4 examples (with "insert this" for the cross-service example), and string tips.

## April 6th, 2026 - Docs: Expanded Controls help page with per-type sections

- Restructured the Control Types overview into clickable links to detailed per-type sections.
- Added detailed sections for all 7 control types: Text, Number, Counter, Timer, Boolean, Datetime, and Expression.
- Each section includes usage examples, code snippets, and type-specific features (random mode, timer modes, expression syntax).
- Documented the new Timer `:running` companion value with conditional examples.
- Added Boolean and Expression to the Control Panel "How each type works" section.
- Updated TOC with nested type links.

## April 6th, 2026 - Feature: Timer :running state in overlay conditionals

- Timer controls now expose a `:running` virtual value in the overlay data, accessible as `[[[c:my_timer:running]]]` (outputs `1` or `0`) and in conditionals: `[[[if:c:my_timer:running]]]`.
- `countto` timers are always considered running since they tick continuously.
- No backend changes needed - the running state is injected into the data object alongside the timer seconds in `OverlayRenderer.vue`.

## April 6th, 2026 - Fix: Control preset duplicates and key display

- Fixed being able to add a service preset control that already exists on the template. The preset dropdown now filters out already-added presets per source.
- Fixed validation errors being invisible when adding duplicate presets - the key error was hidden behind `v-if="!selectedServicePreset"`. Now surfaced as a general error.
- Fixed the Key column in ControlsManager showing bare keys (`total_received`) instead of namespaced keys (`kofi:total_received`) for service-managed controls.

## April 6th, 2026 - Tribute: wilko_dj - first live follower notification

- Changed the Welcome page alert example from a cheer event to a follow event, with an HTML comment tribute: `<!-- First ever: wilko_dj -->`.
- Added a dedication line in the Welcome page footer linking to wilko_dj's Twitch profile.
- Replaced the `SampleStreamer` placeholder in the template create preview with `wilko_dj`.

## April 5th, 2026 - Feature: Random mode for number controls

- Number and counter controls can now be set to "random mode" via a checkbox in the control config.
- When enabled, the control generates a random integer between min and max on a configurable interval (default 1000ms, minimum 100ms).
- Works with template tags (`[[[c:my_random]]]`), expressions (`c.my_random + 10`), and the comparison engine.
- Random state is broadcast via WebSocket so the overlay picks up config changes (min/max/interval) in real-time.
- Follows the existing timer pattern: backend resolves an initial random value, frontend runs a periodic interval.
- Enables creative use cases like slot machines, whack-a-mole, and randomized choices.

## April 5th, 2026 - Revert: Remove Railway webhook debounce

- Removed the `BroadcastVersionUpdate` debounce job and cache nonce logic.
- Railway webhook now broadcasts `VersionUpdated` immediately on receipt.
- The debounce delayed broadcasting by 30 seconds, which coincided with Reverb restarting during deploys - the broadcast fired into a Reverb instance with no connected clients.

## April 5th, 2026 - Reorganize help pages under /help/* and add logged-out sidebar menu

- Moved all help/learn pages under `/help/*`: conditionals, controls, formatting, resources, why-kofi, manifesto.
- Created `/help` landing page linking to all sub-pages.
- Added "Learn" nav section in sidebar for logged-out users.
- Replaced user menu "Learn" submenu with a single link to `/help`.
- Updated all internal links and breadcrumbs across the app.

## April 5th, 2026 - Fix: Inertia link clicks while unauthenticated now redirect to login

- Fixed unauthenticated Inertia navigation (clicking links) returning a raw JSON 401 instead of redirecting to the login page.
- `RedirectIfUnauthenticated` middleware now uses `Inertia::location()` for Inertia requests, triggering a full-page visit to `/login?redirect_to=...` instead of breaking with "All Inertia requests must receive a valid Inertia response".

## April 5th, 2026 - Docs: Formatting Pipes help page and currency locale fix

- Created `/help/formatting` page with full documentation for all 8 pipe formatters, example tables with locale comparisons, quick reference, and tips.
- Added "Formatting Pipes" to the Learn submenu in the user menu and the command palette.
- Cross-linked from the Help page intro and the Controls page timer description.
- Fixed currency preview in Appearance settings showing USD for all locales - now maps each locale to its typical currency (EUR for Dutch, GBP for British, etc.).
- Fixed `|currency` pipe without explicit code defaulting to USD regardless of user locale - now uses locale-aware default via `LOCALE_CURRENCY_MAP`.

## April 5th, 2026 - Feature: Pipe formatting system for template tags (Milestone 5d)

- Added pipe syntax for template tags: `[[[c:timer|duration:hh:mm:ss]]]`, `[[[c:amount|currency:EUR]]]`, `[[[c:score|round]]]`.
- Built-in formatters: `|round`, `|duration`, `|currency`, `|date`, `|number`, `|uppercase`, `|lowercase`. All accept optional format-string arguments.
- Duration formatter supports auto-format (smart unit selection) and explicit patterns (`hh:mm:ss`, `mm:ss`, `dd:hh:mm:ss`, etc.).
- Currency, date, and number formatters use native `Intl` APIs - zero external dependencies.
- Added global locale setting (user settings > Appearance) with live preview of number, currency, and date formatting.
- Overlay renderer uses a single-pass regex replacement that resolves tags and applies formatters in one step.
- PHP `extractTemplateTags()` updated to recognize pipe syntax and correctly strip formatters when extracting tag names.
- Migration adds `locale` column to users table (default: `en-US`).
- Locale is shared via Inertia and passed to the overlay renderer API response.

## April 5th, 2026 - Docs: Major README update

- Rewrote intro to drop "DSL" jargon, matching the new GitHub repo description.
- Added Expression controls section with formula syntax and reactive evaluation.
- Added External Integrations section covering Ko-fi and StreamLabs, including control namespaces, auto-provisioned controls, and the shared webhook pipeline.
- Added Alert Targeting section explaining per-overlay alert routing.
- Added Control Timestamps (`_at` companion values) section.
- Updated Timer section with the new "Count to" datetime mode.
- Replaced all Pusher references with Reverb (self-hosted WebSocket).
- Added Overlay Health subsection (reconnection, backoff, error banners).
- Renamed "Forking" to "Copying" throughout to match UI terminology.
- Updated Tech Stack table and Self-Hosting env vars.
- Updated self-hosting clone URL to `jasperfrontend/overlabels`.

## April 4th, 2026 - UX: Contextual breadcrumbs for template show/edit pages

- Breadcrumbs on show and edit pages now reflect the filtered list you navigated from (e.g. "My static overlays" or "My event alerts") instead of always showing "My overlays".
- Clicking the breadcrumb navigates back to the exact filtered route, preserving filter, type, sort, and direction.
- Filter context is persisted to `sessionStorage` from `index.vue` and read by `show.vue` / `edit.vue`. Falls back to "My overlays" on direct navigation.

## April 4th, 2026 - UI: Redesign TemplateTagsList component

- Replaced the grid-of-boxes layout with collapsible category sections and horizontal flow-wrapped tag badges - all tags visible at once without excessive scrolling.
- Added a search/filter input that matches against tag names, descriptions, and category names.
- Removed the "Other" category entirely (contained only array-type data and generic count tags that don't render in templates).
- Replaced the all-caps orange "IMPORTANT INFO" button with a subtle full-width amber callout banner explaining `user_*` tag behavior.
- Tag descriptions now appear on hover via tooltips instead of a global checkbox toggle.
- Category expand/collapse state is persisted to `localStorage` - survives tab switches and page navigation.
- Added "Collapse all" / "Expand all" toggle with chevron icons, reactively derived from per-category state.
- Cleaned up the info dialog copy (hyphens instead of em dashes, consistent amber theming).

## April 4th, 2026 - Remove: User dashboard icon, greeting, and per-section limit

- Removed the per-user icon feature: deleted `UserIconPicker.vue`, the `PATCH /settings/icon` route, the icon picker from Appearance settings, and the `icon` column from the `users` table (migration included).
- Removed the random greeting bar from the dashboard (greeting text, icon, and "Show X per section" dropdown).
- Dashboard now shows 5 items per section with no user override.
- Deleted unused `Icon.vue` (was already unreferenced).

## April 4th, 2026 - Perf: Tree-shake Lucide icons (976 KB -> 82 KB)

- Deleted unused `Icon.vue` which imported `* as icons from 'lucide-vue-next'`, pulling in all ~1,500 icons.
- Rewrote `UserIconPicker.vue` to lazy-load individual icon files via `defineAsyncComponent` + dynamic `import()` instead of importing the entire library.
- Removed the `lucide-icons` manual chunk from `vite.config.mts` since tree-shaking now works correctly.
- Net bundle reduction: ~894 KB raw / ~161 KB gzipped.

## April 1st, 2026 - UI: Redesign TemplateTable + shared TemplateMeta component

- Replaced the heavy dual table+card layout in `TemplateTable.vue` with a clean card-based list matching the `EventsTable` pattern.
- Removed Owner, Views, Forks, Updated columns from the main view - dates and owner moved to kebab dropdown.
- Only shows a "Private" pill when the template is not public; removed type and public badges.
- Event dots now use the `useEventColors` composable with support for both Twitch and external (Ko-fi, StreamLabs) event mappings.
- Added `externalEventMappings` relationship on `OverlayTemplate` model and eager-loaded it in the index controller.
- Extended `useEventColors` composable with `eventTypeDotClass(eventType, source?)`, `eventTypeHoverBorderClass(eventType, source?)`, and exported `EVENT_TYPE_LABELS`.
- Created shared `TemplateMeta.vue` component (meta grid + template tags card) used by both show and edit pages.
- On `/templates/show`: source code expanded by default, "Forked from" moved into meta grid as "Copied from".
- Renamed "Forks" to "Copies" across meta displays.

## April 1st, 2026 - Fix: Event color classes missing in production

- Replaced dynamic Tailwind class construction (`bg-${color}`) with full literal class strings so Tailwind's scanner includes them in the production build.
- Simplified external event color lookup to match on source instead of repeating per event type.

## April 1st, 2026 - Refactor: Extract event color composable

- Moved event color logic and `UnifiedEvent` interface from `EventsTable.vue` into `useEventColors` composable for reuse.

## April 1st, 2026 - Fix: StreamLabs listener Dockerfile Node version

- Bumped `Dockerfile.streamlabs-listener` from `node:20-alpine` to `node:22-alpine` to match the `engines` constraint in `package.json`.

## April 1st, 2026 - Refactor: Split ControlFormModal into smaller components

- Extracted `ExpressionBuilder.vue` - expression formula panel (textarea, variable buttons, live preview).
- Extracted `ComputedFormulaBuilder.vue` - computed formula builder (watch control, operator, compare/then/else).
- Extracted `controlPresets.ts` - service preset constants and `getPresetsForSource()` helper.
- `ControlFormModal.vue` reduced from 783 to 530 lines with no behavior changes.

## April 1st, 2026 - Fix: Reverb broadcasting CA verification for local TLS

- Added configurable CA bundle path for Reverb's Guzzle client via `REVERB_CA_BUNDLE` env var.
- Fixes curl error 56 (connection reset) when Herd auto-starts Reverb in secure/TLS mode but the broadcasting client can't verify the self-signed certificate.

## April 1st, 2026 - UI: Two-column layout for expression controls in ControlFormModal

- Expression controls now use a wider two-column layout on desktop (max-w-4xl) so the formula editor has its own dedicated column alongside the standard form fields.
- Non-expression types keep the existing single-column narrow layout (max-w-lg).
- On mobile, the layout collapses to a single stacked column.
- Fixes Cancel/Save buttons being pushed off-screen when editing expression controls.

## April 1st, 2026 - Fix: Expression validation recognizes _at companion values

- Expression dependency extraction now strips `_at` suffixes to resolve to the base control, since `_at` values are virtual companions that don't exist as database rows.
- Fixes 422 error when saving expressions referencing `c.streamlabs.latest_donor_name_at` or similar `_at` values.

## April 1st, 2026 - Feature: Control _at timestamps

- Every control now has a companion `_at` value containing the Unix timestamp of its last update.
- Available as template tags (`[[[c:kofi:latest_donor_name_at]]]`) and in expressions (`c.kofi.latest_donor_name_at`).
- Enables cross-service comparisons like: `c.streamlabs.latest_donor_at > c.kofi.latest_donor_at ? c.streamlabs.latest_donor_name : c.kofi.latest_donor_name`.
- Injected at initial overlay load from the control's `updated_at` and on every real-time broadcast.
- No database schema changes - timestamps are virtual companion values derived from existing data.

## April 2nd, 2026 - Removed: Computed control type

- Removed the `computed` control type entirely. Everything it did (simple if/else logic) is better handled by Expression controls, which evaluate client-side with zero latency.
- Deleted `ComputedControlService`, `ComputedFormulaBuilder.vue`, and all computed-related tests.
- Removed cascade logic from `OverlayControlController`, `StreamSessionService`, and `ExternalControlService` - computed controls were the only consumer.
- Moved `getAvailableControls()` and `detectExpressionCycle()` to static methods on `OverlayControl` model (still needed for expression validation).
- Also improved `ControlsManager` config summary to show "Count to" mode and target datetime for timer controls.

## April 2nd, 2026 - Feature: Timer "Count to date/time" mode

- Added `countto` as a third Timer mode alongside `countup` and `countdown`.
- User picks a target date/time via a `datetime-local` picker; the timer counts down the remaining seconds until that moment.
- Stored entirely in the Timer's own config (`target_datetime`) - no dependency on other controls.
- `countto` timers always tick (no start/stop needed); the ControlPanel shows the target datetime instead of play/pause/reset buttons.
- Output is raw seconds, same as other timer modes - will benefit from the pipe/formatter system (Milestone 5d) when that ships.

## April 2nd, 2026 - Docs: Added Milestone 5d (Output Formatting)

- Added Milestone 5d to the roadmap: a pipe/formatter system for template tags (`[[[c:key|format]]]`).
- Covers duration, date, and number formatters that work for all control types.

## April 4th, 2026 - Chore: Install barryvdh/laravel-ide-helper for PhpStorm support

- Installed `barryvdh/laravel-ide-helper` as a dev dependency.
- Ran `ide-helper:models --write --reset` to generate `@property` and `@method` PHPDoc blocks for all 19 Eloquent models.
- Generated `_ide_helper.php` for facade method resolution (gitignored).
- Removed unused `$request` parameter from `OverlayControlController::index()`.
- Used `::query()->where()` instead of `::where()` in controller for explicit builder typing.

## April 4th, 2026 - Fix: Expressions can now reference timer/datetime controls

- Removed the `timer`/`datetime` type filter from `OverlayControl::getAvailableControls()` and the frontend `availableWatchControls` computed. Expressions like `c.count_to / 3600` now validate and evaluate correctly.
- Fixed 422 errors from `abort()` being silently swallowed in the ControlFormModal - these now display as a visible error message in the modal instead of only appearing in the browser console.

## April 4th, 2026 - Feature: Distraction-free code editor and HEAD CodeMirror upgrade

- Added distraction-free fullscreen mode (Ctrl+Shift+F or "Focus" button in sidebar). Editor takes over the full viewport with just tabs and code. Exit with Escape or the same shortcut.
- HEAD tab now uses CodeMirror with HTML syntax highlighting instead of a plain textarea.
- Dark/light mode switching now updates CodeMirror instantly without page refresh, using a MutationObserver on the HTML class. Removed the broken `isDark` prop - the editor handles it internally.

## April 3rd, 2026 - UX: Auto-generate control key from label

- Control key field now auto-derives from the label as the user types (e.g. "Death Counter" becomes `death_counter`).
- Users can manually override the key; auto-derive stops once the key field is edited directly.
- Live validation warnings (amber) for invalid key patterns: spaces, uppercase, leading/trailing underscores, starting with a number.
- Shows a live template tag preview (`[[[c:death_counter]]]`) as the key forms.
- Service preset controls skip auto-derive (unchanged behavior).

## April 3rd, 2026 - Feature: Command palette and keyboard shortcuts overhaul

- Added a command palette (Ctrl+Space) with fuzzy search over all navigable routes, grouped by section (Navigation, Settings, Learn, Tools, Admin). Admin routes only shown to admins.
- Consolidated keyboard shortcuts into a single system: rewrote `useKeyboardShortcuts` composable with per-component scoped ownership (shortcuts auto-cleanup on unmount), a shared global listener, and reactive `getAllShortcuts()` for the shortcuts dialog.
- Deleted duplicate `lib/keyboardShortcuts.ts` singleton (was imported by nothing).
- Moved sidebar toggle (Ctrl+B) from a standalone `useEventListener` into the composable.
- Made Ctrl+K shortcuts dialog global (available on every page, not just template editor). Page-specific shortcuts (Ctrl+S, Ctrl+P) still register on their pages and appear context-dependently.
- Ctrl+modifier shortcuts now fire even when focused in inputs/textareas (so Ctrl+S works inside the code editor).
- Added keyboard shortcut hints in the sidebar below navigation.

## April 3rd, 2026 - Feature: Profile dropdown in header

- Replaced the static avatar/name link in the header with a dropdown menu triggered by an Avatar component.
- Moved Learn items, Settings, Sensitive Data, and Log out from the sidebar into the profile dropdown.
- Removed `NavUser` from the sidebar footer; sidebar is now cleaner with just core navigation.

## April 3rd, 2026 - UI: Theming and visual fixes

- Changed dark mode background from neutral gray to a purple-tinted dark (`hsl(270 16% 6%)`).
- Added CSS custom properties for page gradient (`--gradient-spot-1/2/3`, `--gradient-base`) so gradient colors are theme-swappable.
- Added `bg-popover text-popover-foreground shadow-md` to tooltip content (was invisible with no background).
- Fixed collapsed sidebar menu items (My overlays, Alerts builder, Overlay kits) being unclickable by adding `pointer-events-none` to hidden group labels.

## April 3rd, 2026 - Fix: Welcome page mobile and light mode issues

- Moved the Login with Twitch button to its own full-width row below the header on mobile (was cramped and overlapping).
- Favicon now swaps between `favicon-light.svg` (light mode) and `favicon.png` (dark mode).
- Closed Beta banner now has proper light mode colors (solid purple-100 background, purple-800 text) instead of translucent dark-only colors.

## April 3rd, 2026 - Fix: twitchdata.refresh.all returning 404

- Changed `router.visit()` (GET) to `router.post()` to match the `Route::post()` definition.

## April 3rd, 2026 - Fix: Expression formula edits not updating overlay in real-time

- Editing an Expression control's formula saved to the database but never broadcast a change to the overlay, requiring a hard refresh to pick up the new expression.
- `ControlValueUpdated` event now accepts an optional `expression` parameter, included in the broadcast payload.
- `OverlayControlController` broadcasts the updated expression after saving.
- `OverlayRenderer` re-registers the expression via `expressionEngine.registerExpression()` when it receives a `control.updated` event with a new expression, triggering immediate re-evaluation.
