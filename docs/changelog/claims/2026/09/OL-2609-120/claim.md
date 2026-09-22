## OL-2609-120 - feat(products): veil the chat designer's preview until the frame has applied every write

**Shipped:** 2026-09-22
**Commit:** `git log --grep=OL-2609-120`

A knob's write reaches the preview frame over the control broadcast a beat after the server
answers, and a preset is thirteen of those arriving staggered. The stage is now covered from the
moment a write is confirmed until the frame has reported every key written. Builds on OL-2609-109
(the designer) and OL-2609-119 (the webfont link the frame owns).

### Surface
- `resources/js/components/OverlayRenderer.vue` - `syncWebfontLink()` returns the `<link>` when it started a fetch and null otherwise; `reportApplied()` added; `applyControlUpdate()` calls it once per update, passing that link
- `resources/js/pages/products/design.vue` - `pendingKeys`, `applyingLook`, `VEIL_SILENCE_MS`, `armVeilBackstop()`, `expectInFrame()`, `appliedInFrame()`; `writeControl()` and `applyPreset()` call `expectInFrame()` after the server confirms; `onFrameMessage()` handles `applied`; the veil element over the stage; `relative` on the stage; `Loader2` imported; the backstop timer cleared on unmount

### Claims
- **C1** [code] `reportApplied()` returns without posting when `props.sample` is false or `window.parent === window`. An overlay loaded by OBS therefore never posts a message.
- **C2** [code] `applyControlUpdate()` calls `reportApplied(event.key, fontLink)` exactly once, after the branch that updates `data`, for every update that passes the slug filter and the `data` guard. Updates rejected by either guard report nothing.
- **C3** [code] The message posted is `{ ol: 'chat-sample', applied: <control key> }` and carries nothing else.
- **C4** [code] For a key in `webfontControls` the report is deferred to the link's `load` or `error` event, `{ once: true }` on both, and posts immediately when `syncWebfontLink()` returned null (same href already loaded, or an invalid family).
- **C5** [code] `syncWebfontLink()` returns the existing link only when it rewrote its `href`, and returns null when the href was already equal. The href-equality early return in OL-2609-119 C11 is preserved.
- **C6** [code] In `design.vue`, `expectInFrame()` is called only after the write's promise resolved: inside the `try` of `writeControl()` after `remember()`, and inside the `try` of `applyPreset()` after the local values are set. Neither call sits on the optimistic assignment path or in a `catch`.
- **C7** [code] `applyPreset()` expects only the keys of the returned bundle for which `controls[key]` exists, so an install missing a control never waits for a key the server did not write.
- **C8** [code] `onFrameMessage()` accepts a message only when `event.source` is the frame's `contentWindow` and `event.data.ol === 'chat-sample'`, the same two checks it already applied to `ready`.
- **C9** [code] `appliedInFrame()` deletes the key; when keys remain it re-arms the backstop, when none remain it clears it. `expectInFrame()` arms it. The backstop clears `pendingKeys` after `VEIL_SILENCE_MS` (8000) of no reported key.
- **C10** [code] The veil renders under `v-if="applyingLook"`, which is `pendingKeys.size > 0`, as an `absolute inset-0` child of the stage (`.ol-design-stage`, now `relative`), with `role="status"` and `aria-live="polite"`.
- **C11** [code] `onBeforeUnmount` clears `veilBackstop`.
- **C12** [unverified] On overlabels.test with `composer run dev`, a layout change showed the veil at 1963 ms after the select changed (the server's answer) and lifted it at 3557 ms, the same millisecond the frame's `applied: layout` message arrived.
- **C13** [unverified] On overlabels.test, applying the Terminal preset produced thirteen `applied` messages, one per key in `ChatPresets::KEYS`, arriving 2966 ms to 13281 ms after the click at roughly 800 ms intervals (local `queue:listen`). Under the earlier fixed 8 s backstop the veil lifted at 9176 ms with keys still arriving, which is why the backstop measures silence instead.

### Unchanged
- The control broadcast itself: `ControlValueUpdated`, `OverlayControlController::setValue()` and `ProductController::applyPreset()` are not in the diff. The frame reports what it already received; nothing new is sent from the server.
- `handleSampleMessage()` in the frame and the `ready` message are not in the diff. The new message travels the other way, frame to page, on the same `ol: 'chat-sample'` envelope.
- The `remember()` history-entry rewrite from the preceding commit (`fix(products): keep the chat designer's history entry as current as the database`, no claim, `.vue` only) is not touched; `expectInFrame()` is called beside it.
- `webfontControls` and the per-key `syncWebfontLink()` loop after `injectHead()` are not in the diff; that loop ignores the new return value.

### Risk
None for OBS: the report is gated on sample mode. On the designer, a broadcast that never arrives
leaves the veil up for 8 s of silence and no longer; the frame's own "Live connection lost" banner
is under it during that time.
