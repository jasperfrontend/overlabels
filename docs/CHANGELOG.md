# CHANGELOG

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

## February 28th, 2026
- **Fixed: control values in CSS now update live.** Using `[[[c:key]]]` tags inside overlay CSS (e.g. to drive `font-size` or `background-color` from a control) now reacts instantly when values change, just like HTML does. Previously the style tag was only injected once on load and never updated.
- **Fixed: channel points reward tags now use dot notation.** `[[[event.reward.title]]]`, `[[[event.reward.prompt]]]`, and `[[[event.reward.cost]]]` now correctly reflect the nested payload shape. The old underscore variants (`event.reward_title` etc.) were wrong.
- **Controls table now shows sort order and current value.** The controls list in the template editor and the controls manager both show the `sort_order` column and the current stored value at a glance.

## February 27th, 2026
- **Onboarding now captures users at exactly the right moment.** When you generate your token during setup, the wizard immediately constructs your complete OBS Browser Source URL — token already embedded — with a one-click copy button. No more explaining what a URL hash is or asking users to manually paste a token into the right spot.
- **New interstitial warning step before token generation.** A dedicated screen now appears between the setup summary and the token reveal, with four amber-coloured callouts: your token is shown exactly once, you'll get a ready-to-use OBS URL, alerts won't fire without the overlay in OBS, and never share it on stream. A big "I read all of the above" button gets you through.
- **OBS setup dialog on the overlay detail page.** The ⓘ button next to the OBS URL is now always visible (not just for private overlays) and opens a modal with step-by-step OBS Browser Source setup instructions, a reminder that the `#YOUR_TOKEN_HERE` placeholder must be replaced, where to find a lost token, and a security warning.
- **Collapsible source viewer on the overlay detail page.** HTML, CSS, and HEAD source is now hidden behind a "View source / Hide source" toggle so the page isn't overwhelming by default.
- **Kebab menus on the overlay detail and edit pages.** Secondary actions (Preview, Fork, Delete) are now tucked into a `⋮` dropdown. The primary action (Edit / Save) stays as a standalone button to the left.
- **Admin: onboarding preview tool.** Admins can now trigger a fake onboarding run from the admin dashboard without needing a fresh account. The session flag is consumed on first render so it cleans itself up automatically.
- **Fixed: global Pusher socket was running on every page.** The main app was unconditionally creating a Pusher WebSocket connection on boot, causing a persistent reconnection loop and console spam on every page. Removed — the overlay's own connection is unaffected.
- **Fixed: shared TypeScript types.** Fifteen files each had their own local `interface Template`, causing TS2719 type incompatibility errors. Consolidated into shared `OverlayTemplate` and `AdminTemplate` types in `types/index.d.ts`.
- **Alert transitions are now actually implemented.** Fade, scale, and four directional slide variants (↑ ↓ ← →) all work. Previously the transition type was stored and sent with the broadcast but no CSS existed to back it up, so every alert hard-cut regardless of what you'd configured. The `slide` legacy value falls back to fade gracefully.
- **Enter and exit animations are now independent.** You can configure a separate animation for when an alert appears and when it disappears — slide up from the bottom on entry, fade out on exit, for example. The Alerts Builder now shows two dropdowns per event with labels that reflect direction correctly for each context.

## v0.1.0 — Milestone 1: "This shit actually works" (February 2026)
Early access launch. See MILESTONES.md for the full scope of what this covers.

## February 22nd, 2026
- Added a new admin panel.
- Completely redid the homepage.
- Improved the og:image.
- Flipped the order of contents in this CHANGELOG.
## February 21st, 2026
- Implemented Controls.
  Users can now create single-purpose data stores as string, number, countup, countdown, datetime, or boolean. The implementation is portable, forkable, unique per overlay, straightforward to use, and on top of it all, the new Controls also work well with the comparison engine baked into Overlabels. This means you can now compare values you set and manipulate yourself without having to dive back into your code to make changes. [See how it works](https://overlabels.com/help/controls).
- Any HTML, CSS, or HEAD change to an overlay triggers a hard refresh of the overlay now. This removes any issues with cached data being left behind when embedding this overlay in OBS.
- The Dashboard and Template Details screen had a visual update which makes them more coherent and easier to understand.
- I ditched all Card layouts in favour of tables wherever it made sense. A list of stored overlays is tabular data after all, no matter how you look at it.
- I re-organised the main sidebar navigation, so it's easier to understand where you need to go. I also removed the menu items you don't really need and added those in a "Debug" menu in your profile link.
- I removed the whole community part of Overlabels because its implementation was awful, to say the least. I will work on a better implementation of the Community part of Overlabels, but this is honestly not a top priority right now. You can still view all public overlays on the "All overlays" page.
- The Overlabels Dashboard is still in no way, shape, or form responsive – but at least the Events view is now responsive. The new "Events view" offers a simple way for streamers to see an overview of the latest alerts and the option to replay them on your overlay. You can find this overview under "My activity" in the Overlabels Dashboard.
## February 17th, 2026
- Implemented a massive onboarding system for new users.
- Trying to fix the bug where alerts don't show more than once.
- Added a Twitch CLI test endpoints explanation page.
