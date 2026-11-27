## Audit of OL-2611-045 - feat(alerts): active-first Triggers and Targeting tabs

**Audited:** 2026-11-27
**Commit:** c1c31f176e25aff5707b6042a966ef6d6595475f

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `git grep FilterableGroupedList c1c31f17 -- resources/js` and the same at HEAD return nothing; the file is deleted in the diff |
| C2 | CONFIRMED | `resources/js/components/TriggerManager.vue:154 @c1c31f17` - `activeRows` filters `r.enabled`; `:256` is the only `TriggerRow` and iterates `activeRows`; unchanged @HEAD |
| C3 | CONFIRMED | `resources/js/components/ActiveFirstPicker.vue:44,192 @c1c31f17` - emits `toggle` with `item`; its only refs (`:48-52`) are `open`, `query`, `drill`, two element refs; picked state read via `isPicked` prop (`:29,81,189`); unchanged @HEAD |
| C4 | CONFIRMED | `resources/js/components/ActiveFirstPicker.vue:46 @c1c31f17` - `SEARCH_LIMIT = 12`; `:97` slices search matches to it; unchanged @HEAD |
| C5 | CONFIRMED | `resources/js/components/TriggerManager.vue:178-180 @c1c31f17` - `row.enabled = !row.enabled; save();`; unchanged @HEAD |
| C6 | CONFIRMED | `resources/js/components/TriggerManager.vue:220 @c1c31f17` - `if (announce) emit('saved')`; the only `save(true)` is the Save triggers button at `:248`; `toggle()` (`:180`) and `@save` (`:265`) call `save()`; unchanged @HEAD |
| C7 | CONFIRMED | `resources/js/components/TriggerManager.vue:218 @c1c31f17` and `resources/js/components/AlertTargetOverlaySelector.vue:93 @c1c31f17` - both `preserveState: true` in the `router.put` options; unchanged @HEAD |
| C8 | CONFIRMED | `resources/js/components/TriggerRow.vue:40-42 @c1c31f17` - `const next = ...; conditionType.value = next; if (next === null)`; unchanged @HEAD |
| C9 | UNVERIFIABLE | tagged [unverified]; compound - the "rejected on `required_with`" half is checkable in-repo (see F1) and is true: `app/Http/Controllers/OverlayTemplateController.php:1552 @355823c7` (unchanged through c1c31f17 and @HEAD) - `twitch.*.condition_value` carries `required_with:twitch.*.condition_type` |
| C10 | CONFIRMED | `resources/js/components/AlertTargetOverlaySelector.vue:37,85 @c1c31f17` - `nothingPicked` = `isOnly && selected.length === 0`; `save()` returns before `router.put`; unchanged @HEAD |
| C11 | CONFIRMED | `resources/js/components/AlertTargetOverlaySelector.vue:90 @c1c31f17` - `overlay_ids: isOnly.value ? selectedIds.value : []`; `setMode()` (`:71-75`) does not touch `selectedIds`; unchanged @HEAD |
| C12 | CONFIRMED | `resources/js/components/AlertTargetOverlaySelector.vue:41-47 @c1c31f17` - `live` gives "Default: your Alerts overlay"; both other branches give "Default: all static overlays"; unchanged @HEAD |
| C13 | CONFIRMED | `resources/js/components/AlertTargetOverlaySelector.vue:50-53 @c1c31f17` - `name.trim().charAt(0).toUpperCase()`, `A`-`Z` else `#`; `:55-61` groups by it; unchanged @HEAD |
| C14 | CONFIRMED | `resources/js/utils/triggerGroups.test.ts @c1c31f17` - 14 listed types, each a key of `EventTemplateMapping::EVENT_TYPES` (`app/Models/EventTemplateMapping.php:88-126 @HEAD`), including `burst.channel.follow` and `burst.channel.subscription.message`; `channel.something.new` asserted `other`. `npm test -- resources/js/utils/triggerGroups.test.ts`: 1 file, 17 tests passed. The test does not cover every `EVENT_TYPES` key, and the claim says only "that it lists" |
| C15 | CONFIRMED | `resources/js/pages/templates/edit.vue:750,761 @c1c31f17` and `resources/js/pages/templates/show.vue:364,389 @c1c31f17` - all four wrappers carry `max-w-6xl`; unchanged @HEAD |

### Surface
Complete.

### Findings
- **F1** mistagged, checkable as [code] - C9 is a compound claim whose second half ("`updateTriggers()` rejected it on `required_with`") names a method and a rule that are in-repo (`app/Http/Controllers/OverlayTemplateController.php:1552 @HEAD`); only the first half (what the old frontend sent) needs a tree that no longer exists. Split the rule half out as its own [code] claim in a later claim.

### Notes
- Unchanged lines checked: `OverlayTemplateController.php` and `EventTemplateMapping.php` are absent from `git show --stat c1c31f17`; flashes "Triggers saved." (`OverlayTemplateController.php:1670 @HEAD`) and "Targeting settings saved." (`:1698 @HEAD`), and `resources/js/layouts/AppLayout.vue:25-39 @HEAD` turns a flash into a toast.
- Because of C10, the targeting "Clear" button (`AlertTargetOverlaySelector.vue:174 @c1c31f17`) and removing the last picked overlay update the screen but send nothing; the server keeps the previous picks. The on-screen text at `:204` says so.
- No source path in the diff has changed since c1c31f17; the commits after it touch only OL-2611-043/044 claim files.
