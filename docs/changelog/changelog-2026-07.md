# CHANGELOG JULY 2026

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
