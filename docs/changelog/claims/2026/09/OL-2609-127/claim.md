## OL-2609-127 - feat(shortcuts): the key tip of a completed chord stays up and bounces while its page loads

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-127`

Builds on OL-2609-126. Completing a chord clears the armed prefix, which removed every key tip at
the moment the user wanted to see theirs land. The sidebar now remembers the letter that fired and
`NavMain` keeps that one tip visible, with a single bounce, until the Inertia visit it started has
finished (or a fallback timer runs out).

### Surface
- `resources/js/components/AppSidebar.vue` - `confirmedShortcut` ref, `holdKeyTip()` helper with a bounded timer, `CONFIRM_FALLBACK_MS`, `CONFIRM_NEW_TAB_MS`; the chord callback calls `holdKeyTip()` before navigating and passes `onFinish`/`onCancel` to `router.visit`; `:confirmed` passed to the three signed-in `NavMain` groups; `ref` imported
- `resources/js/components/NavMain.vue` - `confirmed` prop (default null); `showTip(item)`; both `<kbd>` tips render on `showTip` and take `key-tip-confirm` when `confirmed === item.shortcut`
- `resources/css/app.css` - `.key-tip-confirm` class, `key-tip-confirm` keyframes, reduced-motion override

### Claims
- **C1** [code] `NavMain.showTip(item)` is true when the item has a `shortcut` and either `keyTips` is true or `confirmed` equals that shortcut; false otherwise.
- **C2** [code] Both `<kbd>` elements in `NavMain.vue` carry `key-tip-confirm` exactly when `confirmed === item.shortcut`.
- **C3** [code] `AppSidebar.holdKeyTip(letter, ms)` sets `confirmedShortcut` to `letter`, replaces any earlier timer with one that clears it after `ms`, and returns a `done` function that clears both the timer and the value.
- **C4** [code] For an internal item the chord callback calls `holdKeyTip(item.shortcut, CONFIRM_FALLBACK_MS)` (4000) and passes the returned `done` as both `onFinish` and `onCancel` of `router.visit`.
- **C5** [code] For a `_blank` item (Help, Reference) the chord callback calls `holdKeyTip(item.shortcut, CONFIRM_NEW_TAB_MS)` (900) and then `window.open`; nothing else clears it, so the tip drops on the timer.
- **C6** [code] `.key-tip-confirm` runs the `key-tip-confirm` keyframes once over 0.6 s (translateY and scale only), and `@media (prefers-reduced-motion: reduce)` sets its animation to none.
- **C7** [code] `confirmedShortcut` is independent of `armedPrefix`: `gArmed` still reads only the composable's prefix, so the other eleven tips disappear when the chord completes while the fired one stays.
- **C8** [unverified] On overlabels.test in Chrome, keydown events for `g` then `s` dispatched on `window`: 50 ms after `g`, 12 `kbd[aria-hidden]` elements; 50 ms after `s`, exactly one, reading `S`, with class `key-tip-confirm`; 2.5 s later, zero, with `location.pathname` at `/dashboard/stream-sessions`. A real G then O press afterwards showed the lone O badge in a zoom taken before the Overlays row turned active.

### Unchanged
- `useKeyboardShortcuts.ts` is not in the diff: the composable still clears the prefix when a chord fires (OL-2609-125 C7, C8); the lingering tip is sidebar state layered on top, not a change to arming.
- The chord letters and registrations (OL-2609-125 C10, C11) are not in the diff.
- `keyTipClass` (OL-2609-126 C4) is not in the diff; the bounce is an additional class on the same element.

### Risk
None for existing data. A visit that neither finishes nor cancels within four seconds leaves the tip
up until the fallback timer clears it.
