# Overlabels - Milestones

A forward-looking roadmap. Each milestone represents a coherent, shippable layer of the product.
Completed milestones are kept here as a record of intent vs. reality.

> **The north star.** The first user of Overlabels is `/overlabels` on Twitch itself, with a
> 24/7 showcase stream as the long-term end state. Every UX, copy, onboarding, and polish
> decision is judged against one question: "is this good enough to show up on /overlabels' own
> stream?" If something feels fine but not showcase-worthy, that is a reason to flag it.

---

## ✅ Completed Milestones

<details>
<summary><strong>Milestone 1 - "This shit actually works"</strong></summary>

- HTML/CSS/HEAD overlay editor with CodeMirror
- Template tag system (`[[[tag_name]]]`)
- Controls system
- EventSub pipeline
- Access token system
- Copy (duplicate) system for overlays
- Admin panel
</details>

<details>
<summary><strong>Milestone 1.5 - Separate In/Out Alert Transitions</strong></summary>

- `transition_in` / `transition_out`
- Migration & validation updates
- UI split for enter/exit animations
- Hand-rolled CSS transitions
</details>

<details>
<summary><strong>Milestone 2 - External Systems: Foundations</strong></summary>

- `ExternalServiceDriver` interface + `ExternalServiceRegistry` - plug-in architecture for any future service
- Generic webhook receiver (`POST /api/webhooks/{service}/{token}`) routes to the right driver by source
- Per-user credential vault (encrypted storage via `Crypt::encryptString`)
- `NormalizedExternalEvent` DTO - downstream features (alerts, controls) don't care where the event came from
- `ExternalEvent` append-only store with global dedup on `(service, message_id)`
- `ExternalControlService` + `ExternalAlertService` as the two action layers
- No actual integrations shipped in this milestone - only the rails they run on
</details>

<details>
<summary><strong>Milestone 3 - Ko-fi Integration</strong></summary>

- Ko-fi webhook receiver using the MS2 architecture (`KofiServiceDriver`)
- Normalised Ko-fi events: donation, subscription, shop order, commission
- Ko-fi alert mappings in Alerts Builder - per event type, with duration + enter/exit animations
- Ko-fi controls addable to static overlay templates via ControlFormModal presets (6 controls: received count, latest donor, amount, message, currency, total)
- `event.*` template tags available in Ko-fi alert templates (`event.from_name`, `event.amount`, `event.currency`, etc.) + `event.source` for multi-service template reuse
- Test mode toggle - bypasses dedup so the same payload can be fired repeatedly without a new transaction ID
- Ko-fi events appear in the dashboard activity feed alongside Twitch events, and are replayable on overlays
- Verified end-to-end: Ko-fi donation -> Overlabels event -> alert fires in OBS
</details>

<details>
<summary><strong>Milestone 3.5 - More Donation Integrations (StreamLabs, StreamElements, Fourthwall)</strong></summary>

- **StreamLabs**: OAuth 2.0, tokens never expire, Socket.IO pull model via `streamlabs-listener.mjs`, auto-provisions 6 donation controls
- **StreamElements**: JWT-based (no self-serve OAuth), Socket.IO via `streamelements-listener.mjs`, 6 donation-family controls, `tip` -> `donation` event normalisation
- **Fourthwall**: shipped 2026-04-24
- All four donation sources normalise to the same `event.*` shape so a single alert template targets `[[[if:event.type = donation]]]` across every service
- Namespaced controls per service: `[[[c:streamlabs:donations_received]]]`, `[[[c:streamelements:donations_received]]]`, etc.
</details>

<details>
<summary><strong>Milestone 4 - Full Responsive Dashboard</strong></summary>

- Every page in the dashboard is usable on mobile and tablet
- Sidebar navigation collapses correctly on small screens
- Overlay editor is usable on a laptop without a second monitor
- Tables degrade gracefully (priority columns, horizontal scroll where unavoidable)
- No new features - this milestone was purely polish and layout
</details>

<details>
<summary><strong>Milestone 4.5 - Security Audit & Dead Code Removal</strong></summary>

> *No new features ship until this is done. The goal is to be able to say with confidence:*
> *"this codebase is as safe as we can reasonably make it."*
>
> *The one known trade-off that is explicitly accepted:* hash-based public overlay URLs are
> *security-through-obscurity by design. A leaked hash gives read access to an overlay,*
> *but no write access - mutating state still requires a valid auth session and the correct*
> *Twitch ID. Streamers are warned about this. It stays.*

</details>

<details>
<summary><strong>Milestone 5 - Twitch Bot (@overlabels)</strong></summary>

- Shared `@overlabels` Twitch account (not per-user, not piggybacking on Nightbot/SE) so streamers can `/ban overlabels` if it misbehaves; free brand surface as a side effect
- Twurple-based bot in a separate Node repo (`C:\Users\jmstu\PhpstormProjects\overlabels-bot`)
- Twitch Chat Bot Badge live end-to-end (app-token send path; `channel:bot` granted at Connect time, `/mod overlabels` fallback)
- Internal API authed by `BOT_LISTENER_SECRET`: channel list, per-channel command config, control read/write
- Commands shipped: read (`!control`, `!overlay`), write (`!set`, `!increment`/`!decrement`, `!reset`), plus `!followage`, `!accountage`, `!ping`
- Permissions via Twitch chat-event badges at command time (no ACL table)
- **Deliberately deferred**: "Chat Bots" segment in Users-in-Chat (requires EventSub Webhooks; current WS path works fine). Revisit only on rate-limit hits or a bot temp-ban.
- **Remaining follow-ups** are tracked in Milestone 9 (Bot Expressions hardening) below.
</details>

<details>
<summary><strong>Milestone 5d - Output Formatting (Pipe System)</strong></summary>

- Pipe syntax for template tags: `[[[tag|formatter]]]` or `[[[tag|formatter:args]]]`
- 8 built-in formatters: `round`, `number`, `currency`, `duration`, `date`, `uppercase`, `lowercase`
- Duration patterns with overflow: `hh:mm:ss`, `mm:ss`, `dd:hh:mm:ss` etc.
- Global locale setting (Settings > Appearance) drives default formatting for numbers, currencies, and dates
- Locale-to-currency mapping so `|currency` without args uses EUR for Dutch, GBP for British, etc.
- Pure client-side implementation using native `Intl` APIs - zero dependencies
- Full documentation at `/help/formatting` with example tables, locale comparisons, and quick reference
- PHP `extractTemplateTags()` updated to strip pipe expressions for the tag allowlist
</details>

<details>
<summary><strong>Milestone (foundational) - Stream State Machine</strong></summary>

- Deterministic `offline -> starting -> live -> ending -> offline` machine; Helix is source of truth, EventSub only nudges
- `stream_states` table, confidence scoring (0.75 threshold), 120s grace period for OBS crashes, session stitching within 5 min
- `StreamStateMachineService`, `VerifyStreamState` job (self-dispatching), safety-net scheduler every 5 min
- Frontend `useStreamState` composable, live dot + uptime counter on the avatar
</details>

<details>
<summary><strong>Milestone (foundational) - Controls, Alert Targeting & Lists (core)</strong></summary>

- **Controls**: `overlay_controls` (template-scoped and user-scoped), `[[[c:key]]]` / namespaced `[[[c:source:key]]]` syntax, service-managed controls, `ControlValueUpdated` broadcasts, Expression Controls with cycle guards, `_at` companions, `list_writer` control type
- **Alert targeting**: `alert_template_static_overlays` pivot so an alert can fire on specific overlays (empty pivot = all overlays)
- **Lists (core)**: user-managed `OptionSet`s as a top-level feature, full CRUD at `/dashboard/lists`, append/draw/pop/clear/clone, TTL + expiry sweeper, snapshots (list/manual/restore/pin/delete), per-list per-action chat permissions, `!list` meta-command, list appenders, disable/enable toggle, `[[[c:list:<slug>]]]` + `[[[foreach:...]]]`
</details>

<details>
<summary><strong>Milestone (foundational) - Recipes Engine (steps 1-5)</strong></summary>

- Primitives: `Picker`, `OptionSet`, `PickerLanded` event, `BridgePickerLandedToControl` listener
- Manifest validator + `RecipeInstaller` service (materialises a manifest into owned instance rows; cascade-cleanup on uninstall)
- Three-segment control namespace `[[[c:<recipe>:<instance>:<name>]]]`
- Chat-command + dashboard-button triggers (`RecipeChatTriggerService`, `BotRecipeTriggerController`)
- First-party recipes shipped: **Coin Flip** and **Dice** (validated the abstraction held across two shapes)
- `/dashboard/recipes` page lists installed instances and fires dashboard buttons
- **The shippable surface (browse / install / publish / gallery) is NOT done - see Milestone 8 below.**
</details>

---

## 🧭 Active Roadmap

Milestone numbers are stable identifiers, not a strict sequence. Work the **Priority** order:

| Priority | Milestone                                               | Status                                      |
|----------|---------------------------------------------------------|---------------------------------------------|
| 1 (NOW)  | M8 - Lists as a Realtime Data Bus                       | Designed, ~1 day                            |
| 2 (NEXT) | M9 - Recipes: Producer Layer + Bot Expression Hardening | Engine shipped, surface + follow-ups remain |
| 3 (THEN) | M10 - Flows: Reactive Stream-Processing Engine          | Fully designed, no code                     |
| later    | M11 - Patreon Integration                               | Parked, path-of-least-resistance            |
| later    | M6 - Community (Rebuilt Properly)                       | Parked                                      |
| later    | M7 - IRL / GPS Session Extensions                       | Parked during code freeze                   |
| ongoing  | Backlog - loose bugs & polish                           | See bottom                                  |

---

### ★ Milestone 8 - Lists as a Realtime Data Bus (diff broadcasts)
> *TOP PRIORITY (vision locked 2026-05-14). Replace the after-only `ListUpdated` payload with*
> *Supabase-style `{before, after, op}` diffs so external consumers (Lottie via dotlottie-wc,*
> *Web Components, external Bun scripts, custom Vue) can do their own choreography. Once shipped,*
> *Overlabels becomes the bus and the wheel-of-fortune-style visual problems stop being ours.*

**Core (~1 day with tests)**
- Wire format `{slug, before, after, op: {type, ...details}, timestamp}` where `op` is a tagged union covering:
  - `append` (item, position) - `ListAppendService::fire`
  - `replace` (items) - dashboard textarea save (`ListController::update`); needs a structural sequence diff helper
  - `remove` (items, indices, reason: draw|pop_first|pop_last|sweep_ttl|sweep_expiry) - `ListActionService` + `ListExpirySweeper`
  - `clear` - `ListActionService::actionClear`
  - `restore` (from_snapshot_id) - `ListActionWebController::restoreSnapshot`
  - `clone_create` (source_slug) - `ListActionService::actionClone`
  - `state` (disabled: bool) - enable/disable
  - `delete` - `ListController::destroy`
  - `meta_change` (changed: [expires_at, entry_ttl_seconds]) - focused PATCH paths
- Add a per-list channel `lists.{twitch_id}.{slug}` alongside `alerts.{twitch_id}`. The overlay renderer keeps its dumb full-replace behaviour on the existing channel; new consumers subscribe to the new one.
- All before-state is already in scope in every mutator; the only non-trivial piece is the structural diff for the textarea-save path.
- Watch the **Reverb 10 KB payload cap** - escaped data inflates ~20%, and this is exactly the surface that crashed the gamejam. Bound the diff payload.

**Follow-ups (natural sequence after the diff API exists)**
- **List items as objects, not just strings** (`{label, weight, color}`) - this is when typed ops let consumers really cook.
- Snapshot retention sweep cron for unpinned snapshots > 30 days (schema + pin behaviour already exist).
- "Extend by N" UI for nudging `expires_at` without resetting from scratch.

**Stretch / vision**
- "Overlabels as your API provider, Supabase-style" - `import overlabels from ...; const ol = overlabels`. The endgame the user wants: every loop over any data should be able to become a List (even an immutable one). JSON data export from Lists.

---

### Milestone 9 - Recipes: Producer Layer + Bot Expression Hardening
> *The Recipes engine shipped (Coin Flip, Dice, installer, triggers, instance dashboard). What's*
> *missing is the surface that lets a non-power-user discover, install, and a power-user publish.*
> *Plus the small follow-ups owed to Bot Expressions (the consumer half, already live).*

**Recipe install / publish surface (the real gap)**
- Browse + install UI / gallery. Today `RecipeInstanceController` only has `index` + `fireButton`; installs happen via seeder/tinker. Build the install flow on the existing copy/slug rail (the same machinery kits and overlays use).
- Authoring + publish flow for third-party recipes (the "Pamela" path): copy/share slug, copy count, author attribution, version field, public/unlisted/private states.
- **Claude-auditable validation gate before publish** - manifests are 100% declarative precisely so they can be machine-verified for safety. No broken/unsafe recipe reaches the share link.
- Commit the manifest JSON-schema: `resources/recipes/overlabels_recipe_manifest.schema` is authored but currently untracked.
- Permissive dependency handling: a recipe declaring `requires_integrations` warns but does not block on install (mirrors overlay-copy behaviour); it "just works" once the user connects the service.
- Multi-instance with explicit instance slugs; per-recipe install cap (`max_instances_per_user`, admin-configurable). Buyer pays per recipe, not per instance.

**Kits + first-party content (steps 6+)**
- **Wheel of Fortune Kit**: bundles a Wheel-flavoured recipe + the platform's Wheel Vue component + suggested overlay template + suggested alert + suggested Bot Expression. The visual personality lives at the Kit layer, never in the recipe.
- Extend Kits to bundle recipes + Bot Expressions + alerts as install-time defaults (small change, blocks the Wheel kit).
- Random Viewer recipe (a third shape to keep the manifest honest).
- Recipe-level declarative TTL in the manifest (`entry_ttl_seconds`) so a recipe can pre-create a List *and* declare its expiry.

**Manifest design choices still to resolve**
- Align the trigger `permissions` enum with the Bot Expression permission vocabulary (one consistent set for users).
- Decide whether scheduled triggers ("fire this picker every 30 min") ship in a v2 manifest or stay out of recipes entirely.

**Bot Expression follow-ups (consumer half - small wins)**
- `c:` reference validation at save time (today a typo saves clean and resolves empty at fire time).
- `!commands` meta-command listing enabled, non-hidden expressions per channel - cheap onboarding win.
- Per-user cooldowns (v1 is per-channel only): `cooldown_scope` enum + invocations table.
- Channel-level anti-spam cap on `bot_chat_outbox`, separate from per-expression cooldown.
- Tag autocomplete inside the expression textarea (CodeMirror-shaped surface; non-trivial).
- Document the outbox cadence (1-2s delay) in user-facing docs.

**Far future (named, not scheduled)**
- Visual node-editor builder for authoring recipes.
- Marketplace: discoverability, ratings, paid recipes, price-tag-on-slug.

---

### Milestone 10 - Flows: Reactive Stream-Processing Engine
> *The big strategic differentiator. Turns Overlabels from "dynamic templates with alerts" into a*
> *programmable stream layer. Fully designed (Gist + a long 2026-04-12 design conversation); zero*
> *code. Mental model: Kafka Streams / RxJS / Node-RED adapted to overlays, single-user scale.*
> *Pipeline: Trigger -> Filter -> Window -> Aggregate -> Combine -> Transform -> Emit.*

**Architectural invariants (already decided - do not relitigate)**
- Only **Emit** mutates state; every other step is pure. Evaluation is stateless (recompute from the windowed event log, never from persisted counters).
- Time is explicit via **Window**. **Combine** is snapshot-at-now, not a live subscription. Flows never talk to each other directly - only through persisted Controls.
- **Filters** are pure predicates, WordPress-hook-style placement. Payload is append-only (each step writes a named slot; nothing overwrites). before/after pairs live only at boundaries (Trigger-from-change, Emit-that-mutates).
- Observability runs on a separate fire-and-forget channel (trace failures can never fail a Flow). **Replayability is a day-1 requirement** - given event log + timestamp, any Flow reproduces; unlocks "simulate against the last hour" in the builder.
- Fire Control = debouncing/cooldown only (once/cooldown/every X); conditional logic belongs in Filter.

**Build order (each addition independently shippable)**
1. **2-step Flow** (Trigger + Emit only). The Twitch bot writing a Control is essentially this in all but name and already exercises the substrate.
2. Add **Window**, then **Aggregate**, then **Combine**.

**Dependencies & UI**
- `symfony/expression-language` for user-written Filter predicates (sandboxed; do not hand-roll a parser). RxPHP rejected (maintenance mode, too heavy). `@vue-flow/core` is for the later visual builder only.
- **MVP UI = form-based configurator** (name, Trigger, predicate, Emit target), not a node editor. v2/v3 = visual builder + the re-run-to-animate debugger built on the same primitives.

**Must design before features pile on**
- The typed **context schema** (the registry of what fields are available to Filter/Combine at each step). Today's template-tag registry is the seed. Open question.
- Abuse surface: chat-triggered Emit-to-endpoint is SSRF/spam bait. Require pre-registered endpoints, per-Flow rate limits, per-user Flow caps.

---

### Milestone 11 - Patreon Integration
> *The strongest next donation/support integration once the existing four are validated through real use.*

- First-party Composer package + documented OAuth; would follow the Fourthwall shape almost 1:1 (driver, API client, add to `SERVICE_EVENT_TYPES`, help-page entry).
- OAuth registration UX matches Fourthwall + StreamLabs.
- **Throne is explicitly skipped** until it ships a real public API (only an unofficial Docker image exists today).

---

### Milestone 6 - Community (Rebuilt Properly)
> *The original community feature was removed because it was bad. This time, do it right.*

- Public overlay gallery with search, filtering by type and tags
- User profiles showing public overlays
- Copy counts, view counts, featured overlays
- No gamification, no badges, no points. Just useful discovery.

---

### Milestone 7 - IRL / GPS Session Extensions (overlabels-mobile + bot)
> *Make the mobile app a real telemetry source for IRL streams, not just a GPS pin. Long-term:*
> *Overlabels as an ethical next-gen IRL streaming platform competing with RTIRL.*
> *Status: parked during the current code freeze. Do not implement without an explicit go.*
>
> *All GPS/map/location UI copy must be drafted adversarially: assume a chatter is visiting a bare*
> *URL and ask what each line confirms to them.*

**Telemetry -> chat**
- Battery low warning: mobile pushes battery %, new `/settings/integrations/overlabels-mobile` setting "Warn in chat when battery dips below {int}%" (default off), one-shot per crossing, re-arms on climb. `!battery` read-only command.
- Heat / thermal data (Android `BatteryManager` temp, iOS `ProcessInfo.thermalState`) stored alongside battery. Future `!heat` command, overlay tags, alerts when the phone is cooking.

**RTIRL-style global map**
- Worldwide live map of everyone currently logging (current position only, no history). Click a user -> their public route page.
- Public per-user route list at `/map/{sqid}/logs` (opt-in, like the existing public current-session page); each route can opt out of appearing in the list. Reuse dashboard map design on a public route.

**Mobile app fixes (carry into this milestone)**
- QR scanner screen has no copy telling the user what to scan.
- `/settings/integrations/overlabels-mobile` doesn't show the QR until a manual refresh - re-fetch after token generation.
- First-launch on a fresh install showed the previous owner's house on the map (privacy bug, friend reported). Investigate cached coordinates / clean state on first run / after sign-out.
- Tooltip on the safezone setter: "Long-press to set your location".

**Other GPS items**
- `!plotscreen` should render an image into a Control (issue #101). Keep overlabels-mobile lean (GPSLogger's irregular updates, huge payloads, and out-of-order delivery are exactly what drove the custom app).
- Long-term game-show mechanics: `!plot City,Country` boundaries, `!discover Location` routing, donation-gated movement, boundary-violation alerts.

---

## 🪲 Backlog - loose bugs & polish

Not milestone-shaped; tracked here so they don't get lost. Roughly ordered by sharpness of pain.

- **Resub double-payload in the event feed**: Twitch sends both a "new sub" *and* a "resub" payload when a user resubs, so resubs show twice in the feed. Looks odd and confuses users (2 complaints). Collapse/dedup at the feed-output layer. (Display-only - the stream-sessions aggregation that looked related was a separate, now-fixed query bug.)
- **Event feed pagination on tablet**: works, but past ~20 pages the pager breaks on tablet view. Responsive fix needed.
- **SE replays logged as new events**: StreamElements replays (e.g. for a sound clip) appear in the event log as new events instead of replayed / suppressed.
- **SE previous-donation seed** saves the *count* of past donations, not the *amount*. Offer both options.
- **Richer last-donation seed**: set a full fake payload (name + amount + source dropdown), and surface the real donations so the user can choose which to keep/delete.
- **Stream sessions - donations data**: recent streams (`/dashboard/stream-sessions`) don't include donations data yet. Feature gap, not a correctness bug.
- **`/public` route** should also show the used controls and their current values.
- **Lee's request**: a minimal chat layout.
- **Recheck `[[[tag]]]` autocomplete** in the editor.
- **Answered, kept for the record**: "set a control value through another control" -> no (use Expression Controls). "A bot control that speaks in chat" -> no, superseded by Bot Expressions.

**Recently cleared (2026-05-24):** GDPR admin delete now hard-deletes all content/history/references behind an all-caps confirmation string; `requestAnimationFrame` overlays hit 60 FPS in every browser (the Edge "failure" was request overload, now fixed); idle Expression Controls are no longer calculated when unreferenced; stream-sessions aggregation discrepancies fixed (a query bug was multiplying output numbers on every event - e.g. 3 raids x 30 viewers read as 90).

---

*Last updated: 2026-05-24*
