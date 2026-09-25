## Audit of OL-2609-120 - feat(products): veil the chat designer's preview until the frame has applied every write

**Audited:** 2026-09-25
**Commit:** 5f95bfab10598bc48c02ede551d897e24e833454
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:790 @5f95bfab` - `if (!props.sample \|\| window.parent === window) return;` before any `postMessage`; `sample` is only true for `?chat=sample` (`resources/js/overlay/app.js:77 @5f95bfab`), and a top-level OBS source has `window.parent === window`. Unchanged @HEAD |
| C2 | CONFIRMED | `OverlayRenderer.vue:1258-1259 @5f95bfab` - slug filter and `data` guard return first; `:1297 @5f95bfab` - `reportApplied(event.key, fontLink)` is the single call, after the if/else-if/else chain that writes `data`. Unchanged @HEAD |
| C3 | CONFIRMED | `OverlayRenderer.vue:792 @5f95bfab` - `window.parent.postMessage({ ol: 'chat-sample', applied: key }, '*')`, no other fields. Unchanged @HEAD |
| C4 | CONFIRMED | `OverlayRenderer.vue:794-800 @5f95bfab` - null link posts at once; otherwise `addEventListener('load', post, { once: true })` and the same for `'error'`; `:1292-1293` assigns `fontLink = syncWebfontLink(...)` only for `webfontControls` keys; `:754-756` and `:765` are the two null returns. Unchanged @HEAD |
| C5 | CONTRADICTED (half) | Compound. (a) CONFIRMED: `OverlayRenderer.vue:765-767 @5f95bfab` - `if (existing.href === href) return null; existing.href = href; return existing;`. (b) CONTRADICTED: the citation - OL-2609-119 C11 (`docs/changelog/claims/2026/09/OL-2609-119/claim.md:37`) states only that the existing element's `href` is mutated rather than removed and re-appended; it says nothing about an href-equality check, and the pre-change code (`5f95bfab~1`) had a conditional assignment `if (existing.href !== href) existing.href = href; return;`, not an equality early return |
| C6 | CONFIRMED | `resources/js/pages/products/design.vue:147-148 @5f95bfab` - `expectInFrame([key])` follows `remember(...)` inside the `try`, after the awaited `axios.post`; `:191 @5f95bfab` - inside `applyPreset()`'s `try`, after the loop that sets `controls`/`saved`; no call in either `catch` (`:149-153`, `:201-202`) or before the request (`:136-137`). @HEAD the preset call moved into `adoptValues()`, still called from inside the `try` (OL-2609-121 C15) |
| C7 | CONFIRMED | `design.vue:191 @5f95bfab` - `Object.keys(values).filter((controlKey) => !!controls[controlKey])`; server side, `app/Support/ChatPresets.php:205-212 @5f95bfab` skips a key with no control row and `ProductController::applyPreset()` broadcasts each written control. @HEAD same filter in `adoptValues()` (OL-2609-121) |
| C8 | CONFIRMED | `design.vue:390-397 @5f95bfab` - `event.source !== frame.value?.contentWindow` return, then `payload?.ol !== 'chat-sample'` return, both ahead of the `ready` and `applied` branches; the same two checks existed @`5f95bfab~1`. @HEAD an `expired` branch is added after them (OL-2609-122) |
| C9 | CONFIRMED | `design.vue:366-384 @5f95bfab` - `VEIL_SILENCE_MS = 8000`; `armVeilBackstop()` clears and sets `setTimeout(() => pendingKeys.clear(), VEIL_SILENCE_MS)`; `expectInFrame()` calls it; `appliedInFrame()` deletes, clears when size is 0, else re-arms. @HEAD `recoverPreview()` also clears `pendingKeys` and the backstop (OL-2609-122 C7) |
| C10 | CONFIRMED | `design.vue:365 @5f95bfab` - `applyingLook = computed(() => pendingKeys.size > 0)`; `:688` stage class gains `relative`; `:703-711` veil is a direct child of the `ref="stage"` div with `v-if="applyingLook"`, `absolute inset-0`, `role="status"`, `aria-live="polite"`. Unchanged @HEAD |
| C11 | CONFIRMED | `design.vue:418 @5f95bfab` - `clearTimeout(veilBackstop)` in `onBeforeUnmount`. Unchanged @HEAD |
| C12 | UNVERIFIABLE | tagged [unverified]; a local browser timing observation |
| C13 | UNVERIFIABLE | tagged [unverified]; a local browser timing observation. `ChatPresets::KEYS` has 13 entries (`app/Support/ChatPresets.php:34-37 @5f95bfab`) |

### Surface
Complete.

### Findings
- **F1** false citation in a claim - C5 says the href-equality early return is "in OL-2609-119 C11", but that claim (`docs/changelog/claims/2026/09/OL-2609-119/claim.md:37`) records only the mutate-in-place behaviour, and the equality check before this commit was a conditional assignment (`OverlayRenderer.vue @5f95bfab~1`), not an early return; a later claim should restate the citation without attributing the equality check to OL-2609-119 C11.

### Notes
- C4's parenthetical "same href already loaded" goes further than the code: `OverlayRenderer.vue:765 @5f95bfab` compares hrefs only, so an equal href whose stylesheet is still loading also reports at once. Not scored as a finding, because the claim's mechanics are exact.
- C4 covers only the plain-value branch. A `webfontControls` key arriving through the expression, timer or `random_state` branch keeps `fontLink` null and reports at once (`OverlayRenderer.vue:1266-1277 @5f95bfab`).
- Drift at HEAD is disclosed: OL-2609-121 moves the preset `expectInFrame()` into `adoptValues()`, and OL-2609-122 adds the `expired` message and `recoverPreview()`, which clears the veil.
- No tests named or run; every checkable claim is `[code]`.
