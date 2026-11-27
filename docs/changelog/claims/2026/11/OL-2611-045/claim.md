## OL-2611-045 - feat(alerts): active-first Triggers and Targeting tabs

**Shipped:** 2026-11-27
**Commit:** `git log --grep=OL-2611-045`

### Surface
- `resources/js/components/ActiveFirstPicker.vue` - new: generic card listing what is on, plus an Add button that opens a search / group tiles / tick-list picker
- `resources/js/components/TriggerManager.vue` - `<script setup>` and template rewritten on `ActiveFirstPicker`; the plain `<script>` block (`TriggerData`, `firstAssignedEvent()`) is unchanged
- `resources/js/components/TriggerRow.vue` - toggle switch replaced by a remove button; `onConditionTypeChange()` branches on the new value
- `resources/js/components/AlertTargetOverlaySelector.vue` - rewritten on `ActiveFirstPicker` with a Default / Only some overlays choice, and now saves itself
- `resources/js/components/FilterableGroupedList.vue` - deleted
- `resources/js/pages/templates/edit.vue` - targeting tab passes the new props; `saveTargeting()` and `localTargetOverlayIds` removed; both tabs `max-w-6xl`
- `resources/js/pages/templates/show.vue` - same as `edit.vue`
- `resources/js/utils/triggerGroups.ts` - new: Twitch event type to picker group, and `stripServicePrefix()`
- `resources/js/utils/triggerGroups.test.ts` - new

### Claims
- **C1** [code] No file under `resources/js` imports `FilterableGroupedList`.
- **C2** [code] `TriggerManager.vue` renders a `TriggerRow` only for rows with `enabled` true.
- **C3** [code] `ActiveFirstPicker.vue` emits `toggle` with the clicked item and keeps no picked state of its own; it reads picked state through its `isPicked` prop.
- **C4** [code] `ActiveFirstPicker.vue` shows at most `SEARCH_LIMIT` (12) search results.
- **C5** [code] `TriggerManager.vue` `toggle()` flips `enabled` on the row and calls `save()`.
- **C6** [code] `TriggerManager.vue` `save()` emits `saved` only when called with `announce` true, which only the Save triggers button does.
- **C7** [code] Both `TriggerManager.vue` `save()` and `AlertTargetOverlaySelector.vue` `save()` call `router.put` with `preserveState: true`.
- **C8** [code] `TriggerRow.vue` `onConditionTypeChange()` decides whether to clear or default `conditionValue` from the local `next`, not from `conditionType.value` read back after the write.
- **C9** [unverified] Before C8, choosing "At least" on a row whose value was null sent `condition_type` `at_least` with `condition_value` null, and `updateTriggers()` rejected it on `required_with`. Observed by capturing the request body in a dev browser.
- **C10** [code] `AlertTargetOverlaySelector.vue` `save()` returns without a request when mode is `only` and no overlay is picked.
- **C11** [code] `AlertTargetOverlaySelector.vue` sends `overlay_ids: []` when mode is `default`, and keeps the picks in `selectedIds`.
- **C12** [code] `AlertTargetOverlaySelector.vue` titles the default card "Default: your Alerts overlay" when `alertsOverlay.live` is true, and "Default: all static overlays" otherwise.
- **C13** [code] `AlertTargetOverlaySelector.vue` groups overlays by the uppercased first character of the name, with any non A-Z character under `#`.
- **C14** [test] `triggerGroups.test.ts` asserts a group for each Twitch event type in `EventTemplateMapping::EVENT_TYPES` that it lists, burst variants included, and that an unknown type falls into `other`.
- **C15** [code] The triggers and targeting tab wrappers carry `max-w-6xl` in both `edit.vue` and `show.vue`.

### Unchanged
- The two save endpoints, `OverlayTemplateController::updateTriggers()` and `updateTargetOverlays()`, still validate the same payloads and are not in the diff, so each auto-save still returns their "...saved." flash, which `AppLayout.vue` shows as a toast.
- The Twitch picker groups are a frontend display grouping; `EventTemplateMapping::EVENT_TYPES` and `buildTriggerData()` are not in the diff.

### Risk
Targeting now saves on every pick and on every switch between Default and Only some overlays, where
before it saved only on the button. Each save shows the server's flash toast.
