## OL-2611-006 - feat(kits): play a kit's alerts before installing it, and copying a kit wires them up

**Shipped:** 2026-11-09
**Commit:** `git log --grep=OL-2611-006`

### Surface
- `app/Services/Alerts/AlertEventSamples.php` - new, sample events for every alert trigger, run through the real drivers and mapper
- `app/Services/Kits/KitPreview.php` - new, the kit page's `preview` prop
- `app/Models/Kit.php` - `alert_triggers` cast + fillable, `alertTriggers()`, `wireAlertTriggers()`, `fork()` remaps triggers onto the copies
- `app/Http/Controllers/KitController.php` - trigger validation on store/update, `preview` on show, template listing projected, trigger props on create/edit, fork wires triggers
- `database/migrations/2026_11_09_120000_add_alert_triggers_to_kits.php` - new, nullable json `kits.alert_triggers`
- `resources/js/utils/alertPreview.ts` - new, builds the preview frame document
- `resources/js/utils/alertPreview.test.ts` - new
- `resources/js/components/kits/AlertStage.vue` - new, the stage, buttons, sound and demo loop
- `resources/js/components/kits/KitTriggerPicker.vue` - new, trigger editor for the kit form
- `resources/js/pages/kits/show.vue` - stage beside the header, copy call to action, owner wiring panel
- `resources/js/pages/kits/create.vue` - Alert triggers section
- `resources/js/pages/kits/edit.vue` - Alert triggers section
- `tests/Feature/AlertEventSamplesTest.php` - new
- `tests/Feature/KitAlertTriggersTest.php` - new
- `CLAUDE.md` - "Kit preview and kit triggers" section

### Claims
- **C1** [test] `AlertEventSamples::for()` returns at least one variant with a non-empty tag bag for every entry of `EventTemplateMapping::EVENT_TYPES` and `ExternalEventTemplateMapping::SERVICE_EVENT_TYPES`.
- **C2** [test] Every `event.*` tag documented on a `resources/help/reference/eventsub-tags/*.md` page that names a Twitch event is produced by that event's samples, and the check covers more than 200 tags.
- **C3** [unverified] Removing `followed_at` from the `channel.follow` sample made C2's test fail naming `channel.follow: event.followed_at`.
- **C4** [code] External samples come from `ExternalServiceRegistry::driver($service)->normalizeEvent()->withFormattedAmount($locale)`; Twitch samples from `TemplateDataMapperService::mapEventDataForTemplates()`.
- **C5** [test] `POST /kits` stores declared `alert_triggers`, and refuses a trigger on a static template, on an alert outside the kit, on an unknown event, or on an event claimed twice.
- **C6** [test] `PUT /kits/{kit}` without `alert_triggers` leaves the stored triggers unchanged; with it, replaces them.
- **C7** [test] `POST /kits/{kit}/fork` creates enabled mappings on the copier's account pointing at the copied alerts with the declared durations, and the copy's `alert_triggers` point at the copied templates.
- **C8** [test] A copier's existing mapping for a declared event, enabled or not, is left unchanged and no second mapping is created; the flash message counts it as left alone.
- **C9** [test] `Kit::fork()` alone (the onboarding path) creates no mappings.
- **C10** [test] `kits.show` sends `preview` with private alerts' source, the first static template as backdrop, one entry per declared trigger with sample variants, and no `wiring` for a non-owner.
- **C11** [test] `kits.show`'s `kit.templates` carry no `html` or `css`, and `kit.alert_triggers` is not sent.
- **C12** [test] A kit with no declared triggers previews from its author's mappings for its alerts (`preview.declared` false), and an alert nothing points at gets a sample-tip entry with `service` null.
- **C13** [test] For the kit's owner each trigger carries `wiring` with `state`, `connected` and the service's `connect_url`.
- **C14** [test] `stageDocument()` renders backdrop and alert through `renderTemplateSource()`, static root before the alert container, alert sheets last, event values HTML-encoded (`alertPreview.test.ts`).
- **C15** [code] `AlertStage.vue` renders the frame with `sandbox=""` (no scripts) and plays sound only from a click.
- **C16** [unverified] On local, in Firefox: the stage played the Stamped alerts beside the header, the Throne buttons read Gift / Surprise gift / Crowdfunded, and saving the edit form stored 13 triggers with the edited duration.

### Unchanged
- `OnboardNewUser` still copies the starter kit through `Kit::fork()` and never calls `wireAlertTriggers()`.
- `RecipeInstaller`'s own trigger installation and its refuse-on-collision rule are not in the diff; kits take the softer keep-and-report rule.
- The kit markdown (`KitShareService`) still withholds private templates' source.

### Risk
Every existing kit has null `alert_triggers`; it previews from its author's Triggers and copying it
wires nothing until its author declares triggers on the edit page. A new column; the migration runs
on deploy.
