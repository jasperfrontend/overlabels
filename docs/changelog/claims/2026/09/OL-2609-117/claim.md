## OL-2609-117 - fix(products): every slider and color picker in the chat designer saves

**Shipped:** 2026-09-20
**Commit:** `git log --grep=OL-2609-117`

### Surface
- `resources/js/pages/products/design.vue` - a `saved` record of the last server-confirmed value per
  control; `writeControl()` compares against it instead of the local one and maintains it on write,
  on the server's answer and on failure; `applyPreset()` maintains it too

### Claims
- **C1** [code] `design.vue` declares `saved`, a reactive `Record<string, string>` initialized from `props.controls` by key.
- **C2** [code] `writeControl()`'s early return is `if (!control || saved[key] === value) return;` - it no longer reads `controls[key].value` for that decision.
- **C3** [code] `writeControl()` assigns `saved[key]` in three places: the value being sent, before the POST; `data.value` when the server answers with a string; and `previous` in the catch, beside the existing `control.value` revert.
- **C4** [code] `previous` is read from `saved[key]`, falling back to `control.value`, so a failed write reverts to what the server last confirmed rather than to the optimistic value.
- **C5** [code] `applyPreset()` assigns `saved[controlKey]` for every key the preset endpoint returns, beside the existing `controls[controlKey].value` assignment.
- **C6** [code] The knobs bound to `writeControlSoon` in the template are exactly the `type === 'color'` input and the `type="range"` number input; the skin buttons, the `<select>`s and the boolean checkboxes call `writeControl` directly.
- **C7** [unverified] Against the pre-fix tree, dragging the Font size slider on `/products/twitch-chat-overlay/design` produced no request to `/templates/{id}/controls/{controlId}/value` in Chrome's network log, while clicking a skin produced one that returned 200.
- **C8** [unverified] Against the fixed tree, the same drag produced a POST to that endpoint returning 200; `overlay_controls.value` for `font_size` on the affected template read `13` afterwards, and the preview overlay's `--ol-c-font_size-px` custom property changed without the frame reloading.

### Unchanged
- `writeControlSoon()` still assigns `controls[key].value` immediately and still debounces `writeControl` by 250ms. That optimistic write is what makes a slider feel like a slider, and it is the input side of the bug, not the bug: only its doc comment is in the diff (spelling).
- The write path beyond the POST is not in the diff and was never implicated: `OverlayControlController`'s value endpoint returned 200 for the knobs that did reach it, `ControlValueUpdated` broadcasts on `alerts.{twitch_id}`, and `OverlayRenderer.vue`'s CSS fast path (`compileCssBindings` plus `useCssCustomProperties.applyAll`) was already writing `--ol-c-*` for these keys - the initial values it wrote at mount were correct. What never happened was the request.
- `writeWindowSoon()` and the chat-filter writers debounce through the same pattern but have no equality guard to be defeated by their own optimistic write, so they were never affected and are not in the diff.
- No test is added. The logic lives inside a Vue SFC, and this repo's Vitest suite is `environment: 'node'` with no component testing by decision (CLAUDE.md); C7 and C8 are browser observations rather than a fail-first run.

### Risk
A value a streamer set with a slider or a color picker since the designer shipped was never saved,
so their overlay is on whatever those controls last held. Nothing is migrated: the knob takes effect
the next time it is moved.
