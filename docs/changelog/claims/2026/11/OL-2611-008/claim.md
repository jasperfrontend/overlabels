## OL-2611-008 - feat(alerts): alerts carry no controls and no Add to OBS; their pages show the Alerts overlay instead

**Shipped:** 2026-11-10
**Commit:** `git log --grep=OL-2611-008`

### Surface
- `app/Services/Alerts/AlertsOverlay.php` - `pageState()`
- `app/Http/Controllers/KitController.php` - `show()` builds `alertsOverlay` through `pageState()`
- `app/Http/Controllers/OverlayTemplateController.php` - `alertsOverlay` prop on `create()`, on `show()` for an owned alert, on `edit()` for an alert
- `app/Http/Controllers/OverlayControlController.php` - `ALERTS_CARRY_NO_CONTROLS`; `store()` and `importForkedControls()` 422 on an alert
- `resources/js/components/AlertsOverlayPanel.vue` - new, the Alerts overlay panel, exports `AlertsOverlayState`
- `resources/js/pages/kits/show.vue` - inline Alerts overlay block replaced by `AlertsOverlayPanel`
- `resources/js/pages/templates/edit.vue` - alerts: no Controls, Values or Add to OBS tabs; "On stream" tab with the panel
- `resources/js/pages/templates/show.vue` - same
- `resources/js/pages/templates/create.vue` - panel under the alert tips
- `resources/js/components/templates/AddToObsPanel.vue` - alert warning removed
- `tests/Feature/AlertsOverlayTest.php` - three tests added
- `CLAUDE.md` - "One Alerts overlay per account" section extended

### Claims
- **C1** [test] `POST /templates/{alert}/controls` returns 422 and creates no control; the same request on a static template returns 201.
- **C2** [test] `POST /templates/{alert}/controls/import` returns 422 and creates no control.
- **C3** [test] `templates.edit` and `templates.show` for an alert, and `templates.create`, send `alertsOverlay` with the user's Alerts overlay; `templates.edit` for a static template sends `alertsOverlay` null.
- **C4** [code] In `templates/edit.vue` and `templates/show.vue`, `mainTabs` contains no `controls`, `panel` or `obs` key when the template is an alert, and contains a `stream` key.
- **C5** [code] `AlertsOverlayPanel.vue` posts to `alerts-overlay.store` with `preserveState: true`.
- **C6** [code] `AddToObsPanel.vue` has no branch on `template.type === 'alert'`.
- **C7** [unverified] C1, C2 and C3 failed with `OverlayControlController.php` and `OverlayTemplateController.php` reverted to the parent commit.
- **C8** [unverified] On local, in Firefox: an alert's edit page listed Code, Meta, Tags, Screenshot, Triggers, Targeting, Effects, On stream; on the create page, "Create my Alerts overlay" created it and kept the typed name.

### Unchanged
- The render endpoint still renders an alert template requested directly; nothing in `OverlayTemplateController::renderAuthenticated` checks the type.
- Controls already stored on an alert are not deleted; `OverlayControlController::update()`, `destroy()` and `setValue()` are not in the diff.
- `ProductController`'s OBS step for alert products (`yourOverlays()`) is not in the diff.

### Risk
An alert's existing standalone browser source keeps working, but its page no longer offers the
URL. Any control already on an alert stays in the database with no tab to edit it.
