## OL-2609-070 - style(settings): the Twitch event count only spells out a fraction when one is missing

**Shipped:** 2026-09-12
**Commit:** `git log --grep=OL-2609-070`

### Surface
- `resources/js/utils/integrationRows.ts` - `twitchRow()` gains a `complete` branch and widens `statusAlert`
- `resources/js/utils/integrationRows.test.ts` - one test replaced by two
- `resources/js/pages/settings/integrations/index.vue` - alert text and the `ZapOff` icon moved from pink to fuchsia
- `resources/js/pages/settings/integrations/twitch.vue` - `complete` computed added, status paragraph split in two, both alert paragraphs moved to fuchsia

### Claims
- **C1** [code] `twitchRow()` returns status `Listening to N events` when `active_count >= supported_count`, and `Listening to N of M events` when `active_count` is above zero and below `supported_count`.
- **C2** [code] `twitchRow()` sets `statusAlert` true in both the stalled case and the partial case, and false when every event is subscribed.
- **C3** [code] `twitchRow()` sets `stalled` true only when `connected` is true and `active_count` is 0; a partial count leaves it false.
- **C4** [code] `index.vue` renders `text-fuchsia-400` for a row whose `statusAlert` is true, and `text-muted-foreground` otherwise. No `text-pink-400` remains in `index.vue`.
- **C5** [code] `twitch.vue` defines `complete` as `listening && inactiveEvents.length === 0` and renders three mutually exclusive status paragraphs: complete in `text-muted-foreground`, partial in `text-fuchsia-400`, stalled in `text-fuchsia-400`. No `text-pink-400` remains in `twitch.vue`.
- **C6** [test] `integrationRows.test.ts` asserts the complete case says `Listening to 29 events` with `statusAlert` false.
- **C7** [test] `integrationRows.test.ts` asserts the partial case says `Listening to 28 of 29 events` with `statusAlert` true and `stalled` false.

### Unchanged
- `IntegrationController::index()` still sends `active_count` and `supported_count` as it did in OL-2609-069; the decision about which to print is entirely client-side and no prop changed shape.
- The band order and every other row's status line in `buildIntegrationRows()` are not in the diff. Only `twitchRow()` moved.
- `product-target` also paints fuchsia on this page, on a border rather than text, and was not touched or renamed.
