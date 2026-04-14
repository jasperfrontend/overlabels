# CHANGELOG APRIL 2026

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
