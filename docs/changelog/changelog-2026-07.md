# CHANGELOG JULY 2026

## July 3rd, 2026 - feat(events): collapse gift-sub bombs into one expandable row

A single gift-sub bomb landed in the feed as N+1 loose rows: one "gifted subs" line plus a separate "subscribed" line for every recipient, drowning out everything around it. StreamElements folds these into one tidy row you can expand; now so do we.

- `EventsTable.vue` groups on the client: each `channel.subscription.gift` event claims the next `total` `channel.subscribe` events with `is_gift: true` that share its broadcaster and tier, walking oldest to newest. The gifter keeps its own row; the recipients fold underneath it.
- A small gift pill next to the gifter's name shows the recipient count and toggles the list open. It uses `@click.stop` so it never trips the row's replay-confirm popover, and the recipients render as indented name + tier rows.
- Display-only: no backend, migration, or query changes. `is_gift`, `total`, `tier` and `user_name` were already in `event_data`, so replay routes, the token-auth path and pagination are all untouched. Applies everywhere `EventsTable` renders (token feed and the dashboard event pages).
- Graceful edges: a bomb only splits if a page boundary falls mid-burst (rare, since recipients arrive within ~a second), in which case it degrades to the old individual rows. Two simultaneous same-tier bombs can't be perfectly disambiguated - Twitch never links recipients back to the gifter - so recipients attach to the nearest preceding gift.

## July 3rd, 2026 - feat(events): replay alerts from the token-authed events feed

The feed shipped view-only (mute was the single token write), but replaying an alert from your phone mid-stream is exactly what the feed is for. Replay is now the second write an overlay token can perform.

**Backend**

- New token-authed endpoints: `POST /api/events/{id}/replay` (Twitch) and `POST /api/external-events/{id}/replay` (Ko-fi/StreamLabs/...). Same posture as the mute toggle: `throttle:overlay` + `lockdown`, Sanctum-stateful shed, token in the JSON body, `write` ability required, successful replays land in the token's access log (`events-feed:replay`).
- Foreign event ids return 404 (not 403) so a token cannot probe which ids exist for other users.
- The replay cores were extracted out of the session-bound actions into `replayForUser(User, event)` on `TwitchEventSubController` / `ExternalEventController` - dashboard replay and feed replay run the exact same logic (ownership, mute guard, mapping resolution, broadcast + TTS + bot message). Dashboard behavior unchanged, including flash messages.
- Muted replay still returns the "Alerts are muted" warning - the feed gets the same no-bypass rule as the dashboard.

**Frontend**

- `EventsTable` lost the `readonly` prop and gained an optional `token` prop: without it replay posts via Inertia as before; with it replay posts to the token API via fetch and emits a `replay-result` event (the feed has no Inertia flash messages).
- `EventsFeed` shows the replay outcome in an inline notice (violet success / amber warning / red error, auto-clears) and passes the token through.
- Feed info dialog and the feed-link warning copy updated: the link can now also replay alerts on stream, and the warning says so explicitly.

Tests: 6 new Pest tests covering write-ability replay for both event kinds, read-only 403, foreign-event 404, muted warning, and missing-mapping 422. Full suite 1010 green.

## July 3rd, 2026 - fix(events): readonly guard on the replay confirm popover

Clicking a row on the token-authed events feed crashed with `page$1.get() is undefined`. The `readonly` guard covered the row's own `@click` and `openConfirm()`, but Reka's `PopoverTrigger` toggles open state on its own click, so `@update:open` set `confirmingId` unconditionally and the "Replay?" confirm still opened - and its Yes button calls Inertia's `router.post`, which has no page state on the feed (plain `createApp`, no Inertia).

- `EventsTable.vue`: `@update:open` now checks `canReplay(event)` before opening the confirm, closing the trigger path that bypassed the readonly guard.

## July 3rd, 2026 - feat(events): one-click feed link + QR from the recents page

Closes the "how do I even get the feed URL onto my phone" gap from the feed feature: plaintext tokens are shown once, so no page could reconstruct the link after the fact. Now the recents page mints it for you.

- **`TokenUrlDialog`** - the token-URL machinery extracted from `AddToObsButton` (link warning, `tokens.store` POST, fragment URL assembly, copy-to-clipboard box, QR code) into one shared dialog with `instructions`/`footer` slots. `AddToObsButton` is now a thin wrapper around it with identical behavior and copy.
- **`EventsFeedLinkButton`** - replaces the "Embed view" link on `/dashboard/recents` (which pointed at the session-locked `/dashboard/events`). One click mints a fresh token **scoped to `read,write`** (tighter than the unrestricted default), named "Events feed" so it's recognizable on the Tokens page, and shows `/events/feed#<token>` with the QR code open by default - the phone is the whole point. Copy warns that the link reads your history and can mute your alerts, and points at token revocation if it leaks.
- ESLint + vite build clean; no backend changes.

## July 3rd, 2026 - feat(events): token-authed events feed + one-click global alert mute

Two user-requested pieces that make the events page usable mid-stream from a phone. First: `/dashboard/events` required a full Twitch login, painful on mobile where you're usually not logged in to Twitch. Second: there was no way to silence every alert at once (StreamElements has this; now so do we).

**Token-authed events feed (`/events/feed#<token>`)**

- New standalone page (own Vite entry, `resources/js/events-feed/`) authenticated by the same `OverlayAccessToken` overlays use: token in the URL fragment, read client-side, never sent to the server in a URL. Mirrors the overlay shell + the `/api/lists/{slug}?token=` precedent.
- New stateless endpoints: `GET /api/events` (filters, facets, pagination - same query as the dashboard page, extracted into `UnifiedEventFeedService`) and `POST /api/events/mute`. Both `throttle:overlay` + `lockdown`, Sanctum-stateful shed.
- **Token abilities are now enforced for the first time**: the feed requires `read`, the mute toggle requires `write`. Tokens with no abilities set remain unrestricted (matches `hasAbility()`), so every existing overlay token keeps working. The mute toggle is deliberately the only write an overlay token can perform, and each mute write lands in the token's access log.
- Live updates via the existing overlay broadcasting auth endpoint (it already signs `twitch-events`/`alerts` channels for a token): new events refresh page 1 debounced; the mute state flips live no matter where it was toggled from.
- `EventsTable` gained a `readonly` prop - replay stays a logged-in dashboard action.

**Global alert mute (muted is muted)**

- State lives in ONE place: a service-managed boolean control `alerts:muted` (user-scoped, source_managed, provisioned lazily on first toggle). The same control templates read - `[[[if:c:alerts:muted]]]ALERTS ARE MUTED[[[endif]]]` shows/hides live in overlays with zero engine changes, and `[[[c:alerts:muted_at]]]` ("muted since") comes free from the client-side `_at` companion. Conceptual sibling of the `tts` gate control.
- `AlertMuteService`: `isMuted()` (one indexed exists() query, absent control = not muted) + `setMuted()` (provision, flip `'0'`/`'1'`, broadcast `ControlValueUpdated` with empty overlay_slug = all overlays).
- Guarded at all three alert build-sites BEFORE any output: `TwitchEventSubController::renderEventAlert` (covers live webhooks, replay, test cheer), `ExternalAlertService::dispatch`, `ExternalEventController::replay`. Muting stops the visual broadcast, the alert sound, the ElevenLabs TTS synthesis (no credits burned), and the bot chat message. Events keep recording and controls keep updating - only alert output stops.
- No replay bypass: replaying while muted returns a "Alerts are muted" warning instead; test cheer reports `alerts_muted` in its response.
- Mute/unmute button + amber muted banner on both `/dashboard/events` (session, `POST /dashboard/events/mute`) and the token feed.
- `alerts:muted` is in the Add-Control preset picker (new "Overlabels - Alerts" group, no integration required) so the overlay banner pattern is one click to add. `alerts` reserved as a control key; drive-by: `fourthwall`, `bmac`, `throne` added to `RESERVED_KEYS` too (they were missing, same collision class).

## July 2nd, 2026 - docs(controls): document preset controls on the Controls help page

The Controls help page (`/help/controls`) thoroughly explained user-created controls but never mentioned preset controls - the service-managed values Overlabels feeds in from Twitch, the donation integrations, and the GPS app. New readers had no bridge from that page to the concept or to the exhaustive `/help/integration-presets` reference.

- New **"Preset Controls (from integrations)"** section (plus a Table of Contents entry) covering how they differ from hand-made controls: auto-managed read-only value, the namespaced `[[[c:source:key]]]` tag, user-scoped so one add is shared across every overlay, and only visible once the integration is connected (Twitch excepted).
- Documents the shared six-key donation family (`donations_received`, `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`) that lets cross-service expressions total donations, plus the per-service extras (Throne item/thumbnail/surprise flag, BMAC support type).
- A compact icon grid of all eight integrations with **live preset counts imported from `controlPresets.ts`** (`TWITCH_PRESETS.length`, etc.), so the doc can't drift as presets change, and two links out to the full Integration presets reference. ESLint clean.

## July 1st, 2026 - fix(throne): register Throne in the alert trigger catalogue

Throne shipped (#142) with a working webhook but no alert trigger - the Triggers tab showed no Throne row to attach an alert template to. Root cause: the TriggerManager UI lists external triggers from `ExternalEventTemplateMapping::SERVICE_EVENT_TYPES`, a hand-maintained catalogue separate from the driver, and the Throne entry was never added. The webhook, controls, recents, and replay all worked because those flow off the normalized event; only the trigger picker reads this constant.

- Added `throne => ['donation' => 'Throne Gift or Contribution']` to `SERVICE_EVENT_TYPES` so the trigger appears (no frontend change - TriggerManager renders connected services dynamically).
- Added `throne => ['donation']` to `AMOUNT_EVENT_TYPES` so Throne gifts get the same at-least / exactly variant conditions as every other donation service (a bigger gift can fire a louder alert).
- **New guard test** (`ExternalTriggerCatalogueTest`) asserts every registered driver's `getSupportedEventTypes()` is present in `SERVICE_EVENT_TYPES`, so this drift is a red build for the next integration instead of a UI hunt. Would have caught this on the original PR.

## July 1st, 2026 - polish(throne): clearer "paste into Throne" manual step

Tightened the connected-state copy on the Throne settings page so the one manual step (pasting the webhook URL into Throne) is unmissable. Replaced scattered inline "go there" links with a single prominent "Open Throne webhook settings ->" button directly below the webhook URL input, plus a helper line that reacts to the Copy button (after copying it turns violet and reads "Copied. Now open Throne and paste it into the Webhook URL field.").

## July 1st, 2026 - docs(throne): "All Throne Events" reference page

Added `resources/help/reference/eventsub-tags/all-throne-events.md`, the Throne counterpart to "All Ko-fi Events". The filesystem-driven help reference picks it up automatically (the gitignored search index rebuilds on deploy via the composer hook). Documents every normalized tag across the three Throne event types, including the Throne-unique `event.item_name`, `event.item_thumbnail_url`, and `event.is_surprise_gift`, and notes that `from_name`/`message` are empty on crowdfunded gifts.

## July 1st, 2026 - docs(throne): homepage + integration-presets help page

Surfacing Throne everywhere the other integrations already appear, now that the integration is functionally complete.

- **Homepage (`SectionIntegrations`)** - Throne is the sixth tab in the donations section with its own card and namespace example. The `NEW:` badge moved off Fourthwall and Buy Me a Coffee (no longer new) and onto Throne. The cross-service `latest()` showcase - the feature no donation-platform-owned overlay tool can match - now threads all six donation services plus Twitch bits: "Five donation services" became "Six", and both `latest()` examples gained a `c.throne.*` pipe.
- **Help (`IntegrationPresets`)** - a Throne section documenting all nine controls, including the three Throne-unique ones (item name, product thumbnail, surprise-gift flag), with a note that the thumbnail drops straight into an `<img>`. `serviceLabel('throne')` added to the frontend label source of truth.
- ESLint + vite build clean.

## July 1st, 2026 - feat(throne): control presets in the Add-Control picker

The last functional gap: surface Throne's controls in the "Add control" modal so a streamer can build a Throne overlay without hand-typing tags. Mirrors the existing Ko-fi / Fourthwall / BMAC preset pattern.

- **`THRONE_PRESETS`** in `controlPresets.ts` - the six shared donation-family controls plus the three Throne-unique ones (`latest_item_name`, `latest_item_thumbnail_url`, `latest_is_surprise_gift`), exactly matching the driver's auto-provision definitions. Registered in `getPresetsForSource('throne')`.
- **`ControlFormModal`** - a `Throne` preset group that appears on static templates once the user has connected Throne, with the same already-added filtering and fuzzy search as every other service. No backend threading needed: `connectedServices` is plucked from the user's enabled integrations, so connecting Throne lights up the group automatically.
- Adding a preset still creates one user-scoped, service-managed control shared across all overlays. ESLint + vite build clean.

## July 1st, 2026 - feat(throne): connect / settings flow

The settings page that turns the Throne driver into something a streamer can actually use. Throne is the simplest connect flow of any integration: it signs every webhook with its own global key, so there's no token to paste and no OAuth dance - connecting is one click, then you copy the webhook URL into Throne.

- **`ThroneIntegrationController`** + routes under `settings/integrations/throne` (`show`, `connect`, `test-mode`, `seed-count`, `disconnect`), mirroring the Ko-fi donation-service shape. `connect` is credential-less: it `firstOrCreate`s the integration (the model generates the routing `webhook_token`) and surfaces the URL. Idempotent - reconnecting never duplicates the row or rotates the token.
- **Settings page** (`settings/integrations/throne.vue`) - one-click Connect, copyable webhook URL, a "what to do next" checklist (paste into Throne, map an alert, add controls), test mode (disables dedup so Throne's "Test webhook" button can be fired repeatedly), a one-time starting gift count seed, and a disconnect danger zone. No verification-token field and no event-type picker, since all three Throne types normalize to `donation`. The integrations index already listed Throne via the registry, so it now links straight through.
- **Tests:** 8 new (`ThroneIntegrationSettingsTest`) covering the disconnected render, credential-less connect, connect idempotency, the webhook URL surfacing, test-mode persistence + the not-connected 404, seed-count, and disconnect. Pint + ESLint + vite build clean.

## July 1st, 2026 - feat(throne): webhook driver + Ed25519 verification (backend slice)

Throne was previously written off as un-integrable (no public API, only an unofficial Docker image). It now ships a real signed webhook, which makes it a Ko-fi-class integration with no listener process. This first slice is the backend core: the driver, signature verification, and tests. The connect/settings flow, control presets UI, and help page are follow-ups.

- **`ThroneServiceDriver`** - maps all three Throne event types (`gift_purchased`, `contribution_purchased`, `gift_crowdfunded`) to the normalized `donation` type so `[[[if:event.type = donation]]]` alert templates stay uniform across every donation service. No controller change needed: Throne posts `application/json`, which already flows through the existing JSON path in `ExternalWebhookController::parsePayload()`.
- **Ed25519 verification** - Throne signs each delivery with a detached signature in the `X-Signature-Ed25519` header over the message `{X-Signature-Timestamp}.{rawBody}`, verified against Throne's single global public key. `verifyRequest()` guards the timestamp (numeric) and signature (128 hex / 64 bytes) per Throne's spec, then verifies via libsodium against the **raw** request body (`$request->getContent()`) - never a re-encoded parse, which would reorder keys and fail. The global PEM is pinned in `config('services.throne.public_key')` with a `THRONE_PUBLIC_KEY` env override so a key rotation is a config change, not a deploy.
- **Amount handling** - Throne sends integer minor units (`price: 10000` = 100.00); `contribution_purchased` uses an `amount` field where gifts use `price`. The driver divides by 100 and stays currency-naive (no FX), consistent with the rest of the donation stack.
- **Throne-unique controls** - beyond the six shared donation-family controls, Throne gifts are real products, so the driver provisions three extras: `latest_item_name`, `latest_item_thumbnail_url`, and `latest_is_surprise_gift`. `gift_crowdfunded` carries no gifter or message, so it bumps the counters without blanking the latest donor/message.
- **Tests:** 12 new (in `ExternalWebhookTest`) including a regression test that verifies a **real captured Throne delivery against the pinned production key** through the full controller pipeline, plus wrong-key / tampered-body / bad-timestamp / malformed-signature rejections, dedup on `event_id`, the crowdfunded donor-preservation edge, and the contribution `amount`-vs-`price` field. Full external-webhook suite **25 passed**; Pint clean.
