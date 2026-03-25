# CHANGELOG MARCH 2026

## March 25th, 2026 - Docs: Update help page with StreamLabs integration

- Added StreamLabs integration section covering how to connect, auto-provisioned controls, alert event tags, and features (test mode, seed count, shared templates).
- Updated page title and meta descriptions to reflect all integrations.
- Fixed "Fork" to "Copy" in Starter Kit reference.

## March 25th, 2026 - Infra: Dockerfile for StreamLabs listener service

- Added `Dockerfile.streamlabs-listener` for deploying the Node.js listener as a separate Railway service.
- Uses `node:20-alpine` base image since Railpack detects the repo as PHP and doesn't install Node.js.

## March 24th, 2026 - Feature: StreamLabs donation integration

- Added StreamLabs as an external integration for donation alerts and controls.
- OAuth-based connection: users click "Authenticate with StreamLabs" and authorize their account.
- Server-side Node.js listener (`streamlabs-listener.mjs`) bridges StreamLabs Socket.IO events to the existing webhook pipeline.
- Internal API endpoint (`GET /api/internal/streamlabs/integrations`) provides active integrations to the listener.
- `StreamLabsServiceDriver` implements the `ExternalServiceDriver` contract for donation events.
- Auto-provisions 6 controls: donations_received, latest_donor_name, latest_donation_amount, latest_donation_message, latest_donation_currency, total_received.
- Settings page with OAuth flow, test mode, starting donation count seed, and closed beta banner.
- StreamLabs presets added to ControlFormModal for static overlay templates.
- Added `socket.io-client` npm dependency for the Node.js listener.
- Requires `STREAMLABS_CLIENT_ID`, `STREAMLABS_CLIENT_SECRET`, `STREAMLABS_LISTENER_SECRET` env vars.

## March 24th, 2026 - UX: Onboarding wizard shows correct state while job runs

- Success message now only appears when all 4 setup steps are complete (was showing prematurely after initial fetch).
- Added `has_webhook_secret` to the `setupComplete` check so all 4 steps must be green.
- "Next" button is now hidden while the job is running instead of showing as disabled.
- Added comfort message ("Hang tight...") for the in-progress state.

## March 24th, 2026 - Fix: Onboarding job failing on stale transition_type column

- `OnboardNewUser::autoAssignEventMappings()` referenced the dropped `transition_type` column instead of `transition_in`/`transition_out`, causing a DB error that prevented alert mappings from ever being created.
- Without mappings, the job guard (`hasAlertMappings()`) never triggered, so retries and re-dispatches kept forking the starter kit without finishing.
- Added `findExistingForkedKit()` to reuse an already-forked kit on retries instead of duplicating.
- Removed stale `transition_type` from `EventTemplateMapping::$fillable`.

## March 19th, 2026 - Fix: Computed control values update live in Control Panel

- `ComputedControlService::cascade()` now returns an array of updated computed controls.
- `setValue` and `setTimerValue` responses include `cascaded` array with fresh models.
- `ControlPanel.vue` applies cascaded updates immediately after any `postValue` call, so computed controls reflect changes without a page refresh.

## March 19th, 2026 - Feature: Computed controls

- Added `computed` control type whose value is automatically derived from another control using a WHEN/THEN/ELSE rule.
- New `ComputedControlService` handles evaluation, cascade propagation, cycle detection, and available-control queries.
- Formula stored in `config.formula` JSON - no migration needed. Fields: `watch_key`, `watch_source`, `operator`, `compare_value`, `then_value`, `else_value`.
- Cascade hooks added to all 4 control mutation points: `OverlayControlController::setValue()`, `OverlayControlController::setTimerValue()`, `ExternalControlService::applyUpdates()`, `StreamSessionService::handleEvent()` and `resetControls()`.
- Cascade propagates through multiple levels of computed controls, with max depth of 5 and visited-set tracking to prevent infinite loops.
- Cycle detection at save time via DFS - rejects circular dependencies with a 422.
- `setValue()` returns 403 for computed controls (read-only, like source_managed).
- Frontend: formula builder UI in `ControlFormModal` with watch-control dropdown, operator selector, compare/then/else inputs, and live formula preview.
- Frontend: read-only computed display in `ControlPanel` with current value and "Auto-computed" label.
- Frontend: `userScopedControls` prop threaded from controller through show/edit pages to `ControlsManager` and `ControlFormModal`.
- Fork/import: computed controls with missing dependencies are skipped; imported computed controls are evaluated for initial values.
- Unit tests for evaluate, cycle detection. Feature tests for creation, validation, setValue guard, cascade, and cross-service references.

## March 20th, 2026 - UX: GPSLogger settings page polish

- Linked the "Your Webhook URL" label to its input via `for`/`id`.
- QR code is now hidden until the user has saved a Shared Secret Token, with a hint to save one first.
- Added violet "(required)" badge to the Shared Secret Token label.
- Updated QR code copy to direct users to scan and continue setup on their phone.

## March 20th, 2026 - UX: Full setup instructions on GPSLogger landing page

- The GPSLogger webhook landing page (GET `/api/webhooks/gpslogger/{uuid}`) now shows the complete step-by-step setup instructions instead of just the webhook URL.
- Both the webhook URL and HTTP Body fields have copy buttons so users can easily paste them into GPSLogger's settings on their phone.
- Lists all available GPS control tags at the end of the instructions.

## March 18th, 2026 - UX: GPSLogger QR code landing page

- Added a GET route for the webhook URL so that scanning the QR code from a phone shows a landing page instead of a 404/error.
- Landing page displays the webhook URL with a copy button, instructions to paste it into GPSLogger's "Log to custom URL" settings, and a warning not to share the unique URL.
- Standalone blade template (no Inertia/Vue) so it works for unauthenticated mobile users.

## March 18th, 2026 - Fix: GPSLogger empty payload and webhook retries

- Fixed root cause of empty GPS payloads: GPSLogger's HTTP Body field is a free-text template, not a format selector. Updated setup instructions with the correct body template using GPSLogger's native `lat=%LAT&lon=%LON&...` syntax.
- Made `parsePayload` more robust: tries JSON body, then form/query params, then falls back to parsing raw body as query string (needed when GPSLogger doesn't set a Content-Type header).
- Added `spd` as a fallback field name for speed in `normalizeEvent` and `getControlUpdates` (GPSLogger uses `%SPD` which maps to key `spd`, not `speed`).
- Changed duplicate event response from 409 to 200 to prevent GPSLogger (and other clients) from treating dedup as an error and retrying.

## March 17th, 2026 - UI: QR code for GPSLogger webhook URL

- Added QR code on GPSLogger settings page so users can scan the webhook URL with their phone instead of typing it manually.
- Uses the `qrcode` npm package for client-side generation.

## March 17th, 2026 - Feature: GPSLogger integration

- Added GPSLogger for Android as an external data source, following the established driver pattern (Ko-fi reference).
- New `StatefulExternalServiceDriver` interface with `beforeControlUpdates()` for stateful enrichment (distance accumulation) without polluting the base interface.
- `GpsLoggerServiceDriver` verifies requests via `X-GPSLogger-Token` header, normalizes GPS payloads, converts speed from m/s to km/h or mph, and calculates distance using haversine formula.
- Auto-provisions 4 controls on connect: `gps_speed`, `gps_lat`, `gps_lng`, `gps_distance`.
- Distance state (`last_lat`/`last_lng`) stored in `ExternalIntegration.settings` JSON - no new table needed for core feature.
- GPS jitter filtering: only accumulates distance for movements > 1 meter.
- Settings page at `/settings/integrations/gpslogger` with token input, speed unit selector, webhook URL display, and reset distance button.
- GPSLogger presets added to ControlFormModal alongside Twitch and Ko-fi presets.
- Created `gps_pings` migration (stretch goal) for future route plotting.
- 11 feature tests covering verification, control updates, distance accumulation, speed unit conversion, distance reset, and connect/disconnect flows.
- Improved settings page copy with step-by-step setup instructions for both pre-connect and post-connect states, clearer token explanation, and link to GPSLogger on Play Store.

## March 16th, 2026 - UI: Link commit hash to GitHub

- Sidebar commit hash now links to the corresponding GitHub commit page, opening in a new tab.

## March 16th, 2026 - UI: Ko-fi settings "Why Ko-fi" placement + Vite chunk splitting

- Replaced inline "Why Ko-fi?" link on Ko-fi settings page with a proper highlighted card using HeadingSmall + Button. Only shown when Ko-fi is not yet connected.
- Split Vite build chunks: isolated `lucide-vue-next` and `pusher-js`/`laravel-echo` into dedicated chunks. Raised `chunkSizeWarningLimit` to 1000kB to suppress unavoidable Lucide icon library warning.

## March 16th, 2026 - Feature: Free Resources page

- Created `/resources` page with 38 curated free tools for building overlays.
- Eight categories: Colors (6), Gradients & Contrast (5), Icons (7), Fonts (4), Textures & Patterns (3), Animations (6), Sounds (3), CSS Tools (4).
- All resources are genuinely free with no attribution required unless noted.
- Includes LottieFiles (works natively in overlays), CC0 sound effects, CSS animation libraries, SVG generators, and more.
- Added to sidebar under "Learn" with BookOpen icon.
- Public page, accessible to logged-out users. Full OG and Twitter meta tags.

## March 15th, 2026 - Feature: "Why Ko-fi" public page

- Created `/why-kofi` page explaining why Ko-fi is the best donation platform for streamers.
- Content includes fee comparison table (Ko-fi vs PayPal vs StreamElements), privacy and guest checkout advantages, why direct PayPal is problematic, and a transparency section clarifying Overlabels never takes a cut.
- Added route in `web.php` (public, no auth required - accessible to logged-out users).
- Added "Why Ko-fi" entry to sidebar under "Learn" section with Heart icon.
- Added "Why Ko-fi?" link on the Ko-fi integration settings page.
- Full OG and Twitter meta tags for social sharing.

## March 15th, 2026 - Fix: resolve 11 TypeScript errors across the frontend

- **`create.vue`**: Fixed `v-model:html` to `v-model:body` after the TemplateCodeEditor prop rename.
- **`edit.vue`**: Narrowed `type: string` to `type: 'static' | 'alert'` in Props interface.
- **`types/index.d.ts`**: Added missing `FlashMessage` interface definition (was used but never declared).
- **`admin/events/index.vue` + `admin/logs/index.vue`**: Removed invalid `usePage` generic that didn't satisfy the `PageProps` constraint. Untyped `usePage()` inherits the merged PageProps which already includes `flash`.
- **`useGiftBombDetector.ts`**: Replaced `object` return type with a properly typed interface, fixing `processEvent` and `processedEvent` errors in OverlayRenderer.
- **`TwitchData.vue`**: Replaced `NodeJS.Timeout` with `ReturnType<typeof setInterval>`. Extracted `window.location.href` from template into a `reauthenticate()` function (Vue templates resolve `window` against the component instance). Removed invalid `preserveScroll` from `router.reload()` and dead `errors.status === 401` comparison (string vs number).
- **`TooltipBase.vue`**: Typed `side` and `align` props as literal unions instead of generic `String`.
- **`globals.d.ts`**: Changed `window.Echo` from `typeof Echo` (class) to `InstanceType<typeof Echo>` (instance), fixing `Echo.channel` errors.

## March 15th, 2026 - Fix: last ESLint error in Pagination.vue

- **Moved `v-html` from `<Link>` component onto a `<span>` inside its slot.** The `vue/no-v-text-v-html-on-component` rule flags `v-html` on components because it overwrites slot content. Wrapping in a `<span>` preserves the HTML rendering and satisfies the linter. Zero lint errors remaining.

## March 15th, 2026 - OG meta tags + sidebar auth awareness + admin UX polish

- **Per-page OG & Twitter meta tags on public pages.** `/help`, `/manifesto`, and `/help/controls` now have full `og:title`, `og:description`, `og:url`, `og:image`, `twitter:card`, `twitter:title`, `twitter:description`, and `twitter:image` tags for proper social sharing previews. Uses the same Cloudinary OG image as the site default.
- **Sidebar auth awareness.** The sidebar now hides authenticated-only nav groups (Dashboard, My overlays, Alerts, Kits) for guest visitors. Only the "Learn" section remains visible. The footer shows a "Log in" button for guests and the usual user menu for logged-in users. The logo links to `/` for guests and `/dashboard` for authenticated users.
- **Admin UX polish.** Added `cursor-pointer` to buttons across admin pages (logs, tags, users/show). Template names on the admin templates index are now clickable links to their detail pages.

## March 10th, 2026 - UI: Welcome page light mode polish + Twitch login button

- **Light mode color contrast fixed across Welcome page.** All `-400` syntax highlighting colors in code blocks (sky, emerald, amber, zinc) were too pale on white backgrounds. Each now uses a darker variant for light mode with the original preserved for dark mode (e.g. `text-sky-600 dark:text-sky-400`). Inline `<code>` elements with forced dark backgrounds (`bg-zinc-900`) switched to mode-aware `bg-zinc-100 dark:bg-zinc-900`.
- **Em dashes removed from all user-facing copy.** Title tag, code examples, and event data cleaned up per project style rules.
- **Login with Twitch button redesigned.** Replaced the bare `<a>` tag with a proper Twitch-branded button using the official Twitch purple (`#9146FF`), the Glitch icon as inline SVG, and hover/active states.
- **TemplateList last-item rounding.** Added `rounded-b-sm` to the last item in template lists, matching the existing `rounded-t-sm` on the first item.

## March 9th, 2026 - Fix: onboarding polling ignores auth errors

- **Root cause:** `OnboardingWizard.vue` polling continued indefinitely when the server returned 401/403/419 (session expired or account deleted). The `fetchStatus()` function treated auth errors the same as other non-OK responses - returning early but leaving the `setInterval` running. Combined with Chrome's intensive background-tab throttling (3 s interval throttled to 60 s), this produced a persistent once-per-minute request to `/onboarding/status` that survived session invalidation and account deletion.
- **Fix:** `fetchStatus()` now explicitly calls `stopPolling()` on 401, 403, or 419 responses, killing the interval immediately.

## March 8th, 2026 - Version check / deployment refresh prompt

- **Pusher-based version notification.** Instead of polling, the frontend subscribes to a `app-updates` Pusher channel. When a `version.updated` event arrives, a blue banner appears prompting the user to refresh.
- **GitHub Action trigger.** The `notify-deploy` workflow listens for Railway's `deployment_status: success` event, then sends a signed Pusher event via the HTTP API. The banner only appears after the new code is live - no app server involvement.
- **`VersionBanner` component** renders a blue top-bar banner with a Refresh button. Mounted in the sidebar layout above all other banners.
- Requires `PUSHER_SECRET` as a GitHub repository secret.
- **Commit hash in sidebar footer.** The current git commit short hash is baked into the build via Vite `define` and displayed at the bottom of the sidebar. Hidden when sidebar is collapsed.

## March 8th, 2026 - Show public kits on the kits page

- **Public kits section restored.** The kits index page now shows public kits from other users below the user's own kits, with owner avatars and a "Copy" button. The backend was already sending `recentPublicKits` but the frontend never rendered it.
- **Renamed "Fork" to "Copy"** in the KitCard confirm dialog.

## March 8th, 2026 - Fix: soft-deleted users cannot log back in via Twitch

- **Root cause:** The Twitch OAuth callback used `User::where('twitch_id', ...)->first()` which excludes soft-deleted users by default. A deleted user trying to log back in would find no matching row, attempt to `User::create()` with the same `twitch_id`, hit the unique constraint, and silently fail - leaving the user in a login loop.
- **Fix:** Changed to `User::withTrashed()->where(...)` so soft-deleted users are found. If the matched user is trashed, they are automatically restored before the token update and login proceed.

## March 8th, 2026 - Admin: "Delete all content" strategy for user deletion

- **New third option** when deleting a user from the admin panel: "Delete all content" permanently removes all templates, kits, controls, tags, categories, and external integrations belonging to the user. Kits are detached from their templates first, templates are detached from alert targeting pivots, and all child records (controls, event mappings) are cleaned up before deletion.
- **UI:** Red-highlighted radio option with a clear warning that this cannot be undone.

## March 8th, 2026 - Fix: admin user deletion crashes on unique constraint violation

- **Root cause:** The "Assign to Ghost User" deletion strategy blindly reassigned all `template_tag_categories` and `template_tags` to the ghost user. When the ghost user already had rows with the same `(user_id, name)` or `(user_id, category_id, tag_name)`, Postgres threw a unique constraint violation (23505).
- **Fix:** Before reassigning, conflicting rows (duplicates of what the ghost user already owns) are deleted. Non-conflicting rows are then safely reassigned.

## March 8th, 2026 - Templates in kits cannot be deleted (Issue #49)

- **Backend guard:** `OverlayTemplateController::destroy()` now checks `kits()->exists()` before deleting and returns a friendly error instead of a 500. The model `boot()` guard with `restrict` FK constraint remains as a safety net.
- **Frontend:** Delete buttons on template show, edit, and index pages are hidden when the template belongs to a kit. A disabled "Part of a kit - cannot delete" message shows in the dropdown instead, matching the kit deletion guard pattern.
- **`kits_exists` flag:** Added via `withExists('kits')` / `loadExists('kits')` on index, show, and edit queries. Exposed in the `OverlayTemplate` TypeScript type.
- **Renamed "Fork" to "Copy"** across all user-facing UI: template show/edit dropdowns, TemplateTable card/table views, and the public overlay preview bar.

## March 8th, 2026 - UI: redesigned public overlay preview bar + screenshot button

- **Redesigned preview bar.** Moved from top to bottom, frosted glass background (`backdrop-filter: blur`), subtle border, rounded pill-style buttons with smooth hover transitions. Removed the harsh 2001-era inline styles.
- **Screenshot button.** If the overlay has an uploaded screenshot, a "Screenshot" button appears in the bar (separated by a visual divider) that opens the screenshot page in a new tab.
- **Copy feedback.** Clicking HEAD/HTML/CSS now briefly changes the button text to "Copied!" instead of showing an `alert()` dialog.
- **Fork button accent.** Fork/Login-to-fork uses a violet accent style to stand out from the copy buttons.
- **New route: `GET /overlay/{slug}/public/screenshot`** renders a minimal page with the Cloudinary screenshot. Returns 404 if the overlay is private or has no screenshot.

## March 8th, 2026 — Fix: public overlay routes firing spurious GET requests for unresolved tags

- **Root cause:** On public overlay routes (`/overlay/{slug}/public`), the raw template HTML is injected via `{!! $html !!}` without any tag replacement. When a user writes `<img src="[[[c:avatar]]]">`, the browser interprets the raw tag string as a relative URL and fires a GET request for `/overlay/{slug}/[[[c:avatar]]]`.
- **Fix:** `servePublic()` now rewrites `src` and `srcset` attributes containing `[[[` tags to `data-src`/`data-srcset` before sending to the browser. This prevents the browser fetch while preserving the tags in the markup for the preview copy buttons. Normal URLs are left untouched.

## March 8th, 2026 — Fix: reset kofis_received controls when disabling test mode

- **When test mode is turned off**, all `kofis_received` overlay controls for the user are reset to the `kofis_seed_value` (if set) or `0`. This prevents inflated donation counts from test webhooks from carrying over to live usage.
- **Live overlay update.** Each reset control broadcasts a `ControlValueUpdated` event so connected OBS overlays reflect the reset immediately.
- **Frontend hint.** The test mode toggle warning now tells the user the exact value their count will reset to.
- **3 new tests** covering: reset to seed value, reset to 0 when no seed, and no reset when enabling test mode.

## March 8th, 2026 — Fix: onboarding wizard infinite polling + returning user support

- **Root cause:** The `OnboardNewUser` job only dispatched on `UserRegistered` (initial signup). Users who pre-date the onboarding wizard never got the job dispatched, so the starter kit was never forked and the wizard polled forever.
- **Backend fix:** `OnboardingController::status()` now auto-dispatches `OnboardNewUser` when the kit hasn't been forked yet (cache-guarded to avoid re-dispatching on every poll, 5-minute TTL).
- **Frontend fix:** `OnboardingWizard.vue` polling now has a max of 20 attempts (~60 seconds). When exceeded, shows an amber warning banner with a retry button instead of spinning forever. Status checklist remains visible so users can see partial progress.
- **Also fixed:** `ControlFormModal.vue` — "Before existing (first)" sort order could produce `-1` when the lowest control was at `sort_order=0`, causing a silent 422. Clamped to `Math.max(0, ...)` and added `errors.sort_order` display in the template.

## March 8th, 2026 — Chore: fix final 3 ESLint errors (0 remaining)

- **TemplateCard.vue** — `confirm(...) && router.post(...)` flagged as unused expression; rewrote as `if` statement.
- **TemplateTagGenerator.vue** — destructured-but-unused `removed` / `removedLoading` variables (used only to omit keys from rest spreads); added `void` expressions to satisfy the linter.

## March 8th, 2026 — Fix + Enhancement: screenshot preview modal & TS fix

- **Fixed TS2769 error in `TemplateScreenshot.vue`.** The ternary `emit(url ? 'saved' : 'removed')` produced a union type that TypeScript couldn't resolve across overloads. Replaced with an explicit `if`/`else`.
- **Clickable screenshot preview.** Clicking the uploaded screenshot image now opens a responsive full-size modal (up to 90vw/90vh). Uses the existing Shadcn Dialog component. The `ImageDropZone` component gained a new `clickImage` emit and the image thumbnail shows a hover opacity effect as a visual affordance.

## March 8th, 2026 — Fix: preserve CodeMirror state when switching editor tabs

- **Problem:** Switching from the Code tab to another tab (Meta, Tags, etc.) and back reset the CodeMirror editor to its default state — cursor back to line 1, sub-tab reset to BODY, scroll position lost. Alt-tabbing away from the browser and back was fine.
- **Root cause:** `TemplateCodeEditor` was rendered with `v-if="mainTab === 'code'"`, which destroys and recreates the entire component (and all CodeMirror instances) on every tab switch.
- **Fix:** Changed `v-if` to `v-show` so the editor stays mounted in the DOM (just hidden via `display: none`). Converted the remaining `v-else-if` chain to independent `v-if` conditions. Cursor position, scroll offset, and active sub-tab (HEAD/BODY/CSS) are now fully preserved.

## March 7th, 2026 — Chore: renamed `HTML` to `BODY` in template editor

- **Renamed** the "HTML" sub-tab label in `TemplateCodeEditor` to "BODY" to accurately reflect that the editor content is injected into the `<body>` tag, not the full HTML document. Updated tab label, footer indicator, and removed unused `Keyboard` icon import.

## March 7th, 2026 — Fix: CodeMirror syntax highlighting restored

- **Root cause** — deprecated `@codemirror/basic-setup@0.20.0` (pre-release) occupied the top-level `@codemirror/language` slot with v0.20.2, forcing all stable v6 packages to install separate nested copies. Since CodeMirror relies on shared module identity for facets, `defaultHighlightStyle` from one copy couldn't style tokens parsed by a language from another — breaking syntax highlighting.
- **Removed** `@codemirror/basic-setup` — unused, deprecated; replaced by the `codemirror` package that `vue-codemirror` already depends on.
- **Deduplication** — `npm dedupe` consolidated all `@codemirror/language@6.x` copies into a single shared instance.
- **Vite config** — updated `manualChunks` in `vite.config.mts` to reference `codemirror` instead of the removed package.

## March 7th, 2026 — Feature: Per-stream Twitch counters via Controls system

- **Stream sessions** — new `stream_sessions` table tracks live/offline state. `stream.online` opens a session, `stream.offline` closes it.
- **`StreamSessionService`** — core service handling session lifecycle, control resets on go-live, and per-event incrementing of matching twitch controls.
- **6 Twitch control presets** — `follows_this_stream`, `subs_this_stream`, `gift_subs_this_stream`, `resubs_this_stream`, `raids_this_stream`, `redemptions_this_stream`. All counters, all `source_managed`, all auto-reset when the stream starts.
- **Unified preset UI** — ControlFormModal now shows Twitch per-stream counters (always on static templates) and Ko-fi controls (when connected) in grouped `<optgroup>` dropdowns.
- **`StreamStatusChanged` broadcast** — new event on `alerts.{twitch_id}` channel broadcasts `{live: true/false}` so overlays and the dashboard know stream state in real-time.
- **Overlay integration** — `OverlayRenderer` initializes `stream_live` from the API and listens for `.stream.status` events. User-scoped source_managed controls now included in initial overlay render.
- **Offline state in ControlPanel** — Twitch controls show an "Offline" badge and 50% opacity when the stream is not live.
- **Hook in TwitchEventSubController** — `stream.online`/`stream.offline` trigger session open/close; all countable events (follow, sub, raid, etc.) increment matching controls during active sessions.

## March 7th, 2026 — Feature: Controls & Values on edit page + config visibility

- **ControlsManager & ControlPanel on edit page** — the overlay edit page now has full Add/Edit/Delete controls and value management, matching the show page. No more jumping between pages.
- **connectedServices passed to edit** — backend now sends connected external integrations (e.g. Ko-fi) to the edit page so service-specific control presets work there too.
- **Settings column in Controls table** — new "Settings" column in ControlsManager shows config metadata inline: min/max/step/reset for number/counter, mode/duration for timer, initial value for datetime.
- **Config summary in Values panel** — each ControlPanel card now displays the control's config settings below the header, so all settings are visible while editing values.
- **Button type fix** — all ControlsManager buttons now have `type="button"` to prevent accidental form submission when used inside the edit page's `<form>` wrapper.

## March 7th, 2026 — Feature: IP geolocation lookup on admin sessions page

- **Custom `ExtendedIpApi` driver and `ExtendedPosition`** extending `stevebauman/location`'s IpApi driver to include `isp`, `org`, `asName`, and `query` fields that the upstream driver omits.
- **New `GET /admin/sessions/ip-lookup/{ip}` endpoint** returning JSON geolocation data via the IpApi free tier.
- **Clickable IP addresses on `/admin/sessions`** — clicking any IP opens a Shadcn Dialog showing city, region, country, coordinates, timezone, currency, ISP, organization, and AS number.

## March 7th, 2026 — Feature: Banhammer integration for user/IP ban management

- **Installed `mchev/banhammer` v2.4.3** for user and IP ban management with temporary/permanent ban support.
- **New `CheckBanned` middleware** applied to both web and API middleware stacks. Blocks banned users (auto-logout + redirect to `/banned`) and banned IPs (redirect or JSON 403). Admins always bypass. Webhook endpoints (Twitch, Ko-fi, EventSub health) are excluded.
- **New `/admin/bans` page** with full ban management: create bans (user or IP), set duration (1h/6h/24h/7d/30d/permanent), add comments, view/filter/search active and expired bans, unban with one click.
- **Enhanced `/admin/sessions` page** with per-session ban status badges ("User Banned" / "IP Banned") and inline "Ban User" / "Ban IP" buttons that ban + invalidate sessions in one action.
- **Ban status on `/admin/users/{id}`** show page — "BANNED" badge in profile header, ban details card in Admin Actions tab with ban/unban controls.
- **Session invalidation on ban.** When a user or IP is banned, all their active sessions are immediately deleted from the database.
- **Login protection.** Banned users attempting to authenticate via Twitch OAuth are immediately logged out and redirected to `/banned`.
- **Simple `/banned` page** with generic "Access Denied" message — no ban details leaked to bad actors.
- **Dashboard stat card** for "Active Bans" linking to the bans management page.
- **Sidebar navigation** entry for "Bans" with `ShieldBan` icon in the admin section.
- **CLI commands** for emergency ban management: `ban:ip`, `ban:unip`, `ban:user` (with admin guards).
- **Audit trail** — all ban/unban actions logged via `AdminAuditService` with `ban.created` and `ban.removed` actions. Real admin resolved during impersonation.
- **19 feature tests** covering middleware blocking, admin bypass, CRUD operations, session invalidation, webhook exclusion, audit logging, and UI data.

## March 7th, 2026 — Feature: overlay screenshot tab

- **New: Screenshot tab on template edit page.** Overlay templates now have a "Screenshot" tab where users can upload a screenshot of their active overlay. This replaces the broken public preview (which shows raw `[[[c:key]]]` tags) with an actual visual representation of the styled overlay.
- **Three upload methods:** Click the drop zone and paste from clipboard (Ctrl+V after Print Screen), drag-and-drop an image, or browse files via a file picker.
- **Images uploaded to Cloudinary** via direct unsigned upload to the `overlabels-overlay-screenshots` preset, stored in the `overlays/screenshots` folder.
- **New `screenshot_url` column on `overlay_templates`.** Stores the Cloudinary URL. New `PUT /templates/{id}/screenshot` endpoint for saving/removing screenshots independently from the main template form.
- **Cloudinary cleanup on replace/remove.** When a screenshot is replaced or removed, the old image is deleted from Cloudinary via the Admin API so orphaned assets don't accumulate.
- **Focus state UX.** Clicking the drop zone shows a pulsing "Ready — press Ctrl+V" prompt with a violet highlight, making it clear the zone is ready for paste input.
- **Reusable `ImageDropZone` component.** Extracted the paste/drop/browse upload UX into a shared `ImageDropZone.vue` component. Used by both overlay screenshots (`TemplateScreenshot.vue`) and kit thumbnails (`kits/create.vue`, `kits/edit.vue`). Replaces the old Cloudinary Upload Widget popup with the same inline experience.
- **Kit thumbnail upload upgraded.** Both `/kits/create` and `/kits/edit` now use `ImageDropZone` instead of the Cloudinary Upload Widget popup. Paste, drag-and-drop, and browse all work. The Cloudinary widget JS is no longer needed for uploads.
- **5 new feature tests** covering ownership, removal, validation, and auth guards.

## March 7th, 2026 — Perf: slim down Inertia shared props

- **Reduced `auth.user` payload from ~10.8KB to ~268 bytes per page load (97.5% reduction).** `HandleInertiaRequests::share()` was sending the entire User model on every Inertia request, including the massive `twitch_data` JSON blob (followers, subscribers, channels, goals — ~10KB alone). Replaced `$request->user()` with an explicit `->only()` whitelist of the 8 fields the frontend actually uses: `id`, `name`, `email`, `twitch_id`, `avatar`, `icon`, `onboarded_at`, `role`.
- **Reduced Ziggy routes payload from ~12.2KB (130 routes) to ~6.3KB (70 routes), a 48% reduction.** Created `config/ziggy.php` with an `except` list filtering out 60 routes that the frontend never calls via the `route()` helper (backend-only endpoints, hardcoded URL routes, webhook receivers, etc.).
- **Combined: ~23KB → ~6.6KB saved on every page request.**
- **Also removed unused `Inspiring::quotes` shared prop** that was still being computed on every request.
- **Bugfix:** Fixed `route('dashboard')` → `route('dashboard.index')` in AppHeader (route name didn't exist).

## March 6th, 2026 — Fix: controls not copied when forking a kit

- **Root cause:** `OverlayTemplate::fork()` only stashes controls in a transient `_sourceControls` property for the interactive fork wizard UI — it never inserts rows. Kit forks (e.g. onboarding) bypass the wizard entirely, so all controls were silently dropped.
- **Fix:** `Kit::fork()` now copies non-service-managed controls (`source IS NULL`) to each forked template after calling `$template->fork()`. Source-managed controls (Ko-fi etc.) are deliberately excluded — they're provisioned when the user connects the relevant service.

## March 6th, 2026 — Fix: onboarding wizard stuck on "Starter Kit forked"

- **Root cause:** `Kit::fork()` uses `$this->replicate()` which copies all model attributes including `is_starter_kit = true` onto the forked kit. After the first onboarding, two kits had `is_starter_kit = true`. Postgres returned either when `->first()` was called (no ORDER BY), so the status check could pick up the user's own fork as the "starter kit", then look for a kit forked from itself — finding nothing — leaving `kit_forked` permanently `false`.
- **Fix:** `Kit::fork()` now explicitly sets `is_starter_kit = false` on the fork before saving. Data corrected directly on existing rows.

## March 6th, 2026 — Fix: new user login lands on raw JSON instead of dashboard

- **Root cause:** `OnboardingWizard.vue` polls `/onboarding/status` via `fetch()` every 3 s. When the session expired mid-poll, `RedirectIfUnauthenticated` returned `302 → /login?redirect_to=/onboarding/status`. `fetch()` followed the redirect, which triggered `AuthenticatedSessionController::create()` and stored `/onboarding/status` as `session('url.intended')`. The next Twitch OAuth callback used `redirect()->intended()` and sent the browser straight to the JSON endpoint.
- **Fix 1 — middleware:** `RedirectIfUnauthenticated` now returns `401 JSON` for requests that send `Accept: application/json` or `X-Inertia`, instead of redirecting them into the login flow.
- **Fix 2 — OAuth callback:** `redirect()->intended()` replaced with explicit intended-URL validation. Paths under `/onboarding/` and `/api/` are rejected; falls back to `/dashboard`.
- **Fix 3 — wizard fetch:** `fetchStatus()` now sends `Accept: application/json` and skips processing non-OK responses, so a 401 on session expiry is handled silently rather than triggering a redirect chain.

## March 6th, 2026 — Admin: starter kit management

- **New: Admin → Kits page (`/admin/kits`).** Admins can now designate which kit is forked for every new user during onboarding directly from the admin panel — no more hardcoded env var. The page lists all original (non-forked) kits with a "Set as Starter" button; the active starter is highlighted with a star badge.
- **`is_starter_kit` column on `kits` table.** Migration adds a boolean flag; only one kit can be the starter at a time. `setStarter()` clears all flags before setting the new one, and writes an audit log entry.
- **`OnboardNewUser` and `OnboardingController` updated** to look up `Kit::where('is_starter_kit', true)->first()` instead of reading `config('app.starter_kit_id')`.
- **Removed `starter_kit_id` from `config/app.php` and `.env`.** The config key no longer exists.
- **Fix: onboarding wizard hung on "Starter Kit forked"** because the local DB kit ID didn't match the hardcoded `STARTER_KIT_ID=1`. Now impossible to misconfigure.

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
- **One-time lock.** Once set, the field is replaced with a locked read-only display. Corrections go through jasper@emailjasper.com — the value is stored in the integration settings and survives re-saves.
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
