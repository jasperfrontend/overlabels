## OL-2611-005 - feat(alerts): alerts of any size fire, and an alert's head finally applies

**Shipped:** 2026-11-09
**Commit:** `git log --grep=OL-2611-005`

### Surface
- `app/Models/OverlayTemplate.php` - `sourceVersion()` (static content hash of head/html/css) and `alertSource()`
- `app/Events/AlertTriggered.php` - new `head` constructor arg, `INLINE_SOURCE_BUDGET`, `alert_template_version` in the payload, source inlined only under budget
- `app/Services/External/ExternalAlertService.php` - passes `head`
- `app/Http/Controllers/ExternalEventController.php` - passes `head` (replay)
- `app/Http/Controllers/TwitchEventSubController.php` - passes `head` (Twitch alerts, test cheers, replays)
- `app/Http/Controllers/OverlayTemplateController.php` - render payload gains `alert_sources`; emote detection moved from SQL to PHP since html is now loaded
- `app/Http/Controllers/Api/OverlayAlertSourceController.php` - new, `POST /api/overlay/alert-source`
- `routes/api.php` - route `api.overlay.alert-source`
- `resources/js/utils/alertSources.ts` - new, source resolution and head stylesheet extraction
- `resources/js/utils/alertSources.test.ts` - new
- `resources/js/components/OverlayRenderer.vue` - loads `alert_sources`, resolves each alert's source, fetches on a miss, injects the alert head, warms head stylesheets
- `tests/Feature/AlertSourceDeliveryTest.php` - new
- `CLAUDE.md` - "Alert sources" bullets under Alert Targeting

### Claims
- **C1** [code] `OverlayTemplate::sourceVersion($head, $html, $css)` is the first 16 hex chars of sha256 over the three fields joined by NUL, nulls read as empty.
- **C2** [test] `AlertTriggered::broadcastWith()` includes `html`, `css` and `head`, with `source_omitted` false, when the JSON-encoded-twice payload is at most `INLINE_SOURCE_BUDGET` (9000) bytes ("a small alert rides the broadcast whole, with its version").
- **C3** [test] Over that budget, `source_omitted` is true and `html`, `css` and `head` are empty strings (not null, which the pre-change renderer would pass to `processTemplate()`), while `alert_template_slug`, `alert_template_version` and `data` remain, and the wrapped request is under 10 000 bytes ("an alert too big for Reverb leaves its source behind...").
- **C4** [test] With a null `alertTemplateSlug` the source is always inlined, whatever its size.
- **C5** [test] Renaming a template leaves its version unchanged; changing its css changes it.
- **C6** [test] The `/api/overlay/render` payload's `alert_sources` holds exactly the owner's alert templates that are untargeted or target the rendered overlay, each equal to `alertSource()`.
- **C7** [test] `alert_sources` serialises as `{}` (an object), not `[]`, when no alert can fire.
- **C8** [test] `POST /api/overlay/alert-source` returns `slug`, `head`, `html`, `css`, `version` and `compiled_css` for the token owner's alert template.
- **C9** [test] The endpoint leaves the token's `access_count` and `last_used_at` unchanged.
- **C10** [test] The endpoint answers 401 for an unknown token and 404 for another user's alert, the owner's own non-alert template, and an unknown slug.
- **C11** [test] A Ko-fi webhook against a ~20 KB mapped alert dispatches an `AlertTriggered` whose payload has `source_omitted` true, the template's slug and version, and a wrapped size under 10 000 bytes.
- **C12** [code] All three `new AlertTriggered(` sites in `app/` pass the template's `head`.
- **C13** [test] `planAlertSource()` prefers a source on the broadcast (unless `source_omitted`), then a mount-time copy with a matching version (or no version to compare), and otherwise asks for a fetch by slug (`alertSources.test.ts`).
- **C14** [code] `OverlayRenderer.vue` drops a fetched source if a newer alert fired while the fetch was in flight (`alertSequence`).
- **C15** [code] `injectAlertHead()` replaces the previous alert's head elements (`[data-alert-head]`) and does nothing when the head is unchanged.
- **C16** [unverified] On local Reverb in Firefox, a 21.9 KB alert rendered from the mount-time copy, rendered edited text after a post-load edit (only reachable through the fetch), and a small alert still rendered from the inlined source.

### Unchanged
- `alert_sound_url` still rides every broadcast and remains what plays; `alert_sound_preload` still only warms the HTTP cache.
- `alert_css_preload` (compiled utility CSS) keeps its role; a source fetch also fills it for the fetched slug.
- `event.*` data still rides the broadcast. It is already limited to the template's tag allowlist, and an alert whose data alone exceeds 10 KB still fails as before.
- Reverb's `max_request_size` / `max_message_size` are not raised.

### Risk
The render payload now carries the full source of every alert that can fire on the overlay, so it grows
with the number and size of a streamer's alerts. Overlays already open during the deploy run the old
renderer: they keep rendering any alert that still fits inline. An alert too big to inline - which
could not fire at all before this change - reaches them as empty strings, so it plays its sound and
shows nothing until OBS reloads them.
