## OL-2611-007 - feat(alerts): one Alerts overlay per account, and untargeted alerts render only there once OBS has it

**Shipped:** 2026-11-10
**Commit:** `git log --grep=OL-2611-007`

### Surface
- `database/migrations/2026_11_10_120000_add_alerts_overlay_to_users.php` - new, nullable `users.alerts_overlay_id` (FK to `overlay_templates`, null on delete) and `users.alerts_overlay_live_at`
- `app/Models/User.php` - `alertsOverlay()` relation, `alerts_overlay_live_at` datetime cast
- `app/Services/Alerts/AlertsOverlay.php` - new, `ensure()`, `mergeControls()`, `markLive()`, `targetSlugs()`, `firesOn()`
- `app/Http/Controllers/AlertsOverlayController.php` - new, invokable, creates the Alerts overlay
- `routes/web.php` - `POST /alerts-overlay` named `alerts-overlay.store`
- `app/Http/Controllers/TwitchEventSubController.php` - alert target slugs from `AlertsOverlay::targetSlugs()`
- `app/Services/External/ExternalAlertService.php` - same
- `app/Http/Controllers/ExternalEventController.php` - same, for replay
- `app/Http/Controllers/OverlayTemplateController.php` - render endpoint calls `markLive()`; `alert_sources` filtered by `firesOn()`
- `app/Models/Kit.php` - `fork()` merges the author's Alerts overlay controls into the copier's instead of copying it
- `app/Http/Controllers/KitController.php` - `show()` sends `alertsOverlay` to the owner of a kit with alerts
- `resources/js/pages/kits/show.vue` - "Alerts overlay" block: create button, Add to OBS, live state
- `tests/Feature/AlertsOverlayTest.php` - new
- `CLAUDE.md` - Alert Targeting semantic line corrected, "One Alerts overlay per account" section

### Claims
- **C1** [test] `AlertsOverlay::ensure()` creates one private static template owned by the user, stores its id in `users.alerts_overlay_id`, and returns the same template on a second call.
- **C2** [test] With no Alerts overlay, a Ko-fi webhook dispatches `AlertTriggered` with `targetOverlaySlugs` null.
- **C3** [test] With an Alerts overlay whose `alerts_overlay_live_at` is null, a Ko-fi webhook dispatches `AlertTriggered` with `targetOverlaySlugs` null.
- **C4** [test] `POST /api/overlay/render` for the Alerts overlay sets `users.alerts_overlay_live_at`; rendering another static overlay of the same user does not.
- **C5** [test] With a live Alerts overlay, an untargeted alert dispatches with `targetOverlaySlugs` equal to `[<alerts overlay slug>]`.
- **C6** [test] With a live Alerts overlay, an alert attached to a static overlay through `targetStaticOverlays` dispatches with that overlay's slug only.
- **C7** [test] With a live Alerts overlay, the render payload's `alert_sources` for another static overlay does not contain an untargeted alert, and for the Alerts overlay it does.
- **C8** [test] After the Alerts overlay is deleted, `ensure()` creates a new one and `alerts_overlay_live_at` is null.
- **C9** [test] `Kit::fork()` on a kit containing its author's Alerts overlay creates no extra static template for the copier, attaches the copier's Alerts overlay to the copied kit, and copies the author overlay's controls (key and `config`) onto it.
- **C10** [test] A second `Kit::fork()` of the same kit by the same user does not duplicate those controls.
- **C11** [test] A static template in a kit that is not its author's Alerts overlay is still copied as its own template, and the copier gets no Alerts overlay from it.
- **C12** [test] `POST /alerts-overlay` twice leaves the user with exactly one template named `Alerts`.
- **C13** [code] `TwitchEventSubController`, `ExternalAlertService` and `ExternalEventController` contain no inline `targetStaticOverlays->isNotEmpty()` slug resolution.
- **C14** [unverified] C4, C5, C7 and C9 failed with the five wiring files (`TwitchEventSubController`, `ExternalAlertService`, `ExternalEventController`, `OverlayTemplateController`, `Kit`) reverted to the parent commit.
- **C15** [unverified] On local, in Firefox: the kit page block read "Not in OBS yet", loading the Alerts overlay URL stamped `alerts_overlay_live_at`, a Ko-fi tip rendered in it, and the block then read "In OBS. Your alerts show here."

### Unchanged
- `OverlayRenderer.vue` filters an alert broadcast by `target_overlay_slugs` exactly as before; it is not in the diff. The new routing only changes which slugs the server puts in that list.
- The Targeting tab (`OverlayTemplateController::targetOverlays`, the `alert_template_static_overlays` pivot) still sets explicit targets and is not in the diff.
- `RecipeInstaller` still installs alerts without targets and the product copy still says they show in every overlay; neither is in the diff.
- The alert editor's Controls, Values and Add to OBS tabs are not in the diff.

### Risk
Two new nullable columns on `users`; the migration runs on deploy. No account has an Alerts overlay
until one is created from a kit page, so alert routing is unchanged for everyone on deploy. An
account whose Alerts overlay goes live stops seeing untargeted alerts in its other overlays.
