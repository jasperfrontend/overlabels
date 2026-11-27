## OL-2611-049 - fix(checkin): a real checkin reaches the globe without a reload, with the first component test

**Shipped:** 2026-11-28
**Commit:** `git log --grep=OL-2611-049`

### Surface
- `resources/js/components/OverlayRenderer.vue` - `handleCheckinsUpdated()` calls `syncGlobes()`
- `resources/js/components/OverlayRenderer.dom.test.ts` - new file, mounts the renderer in happy-dom
- `vitest.config.mts` - split into a `unit` (node) project and a `dom` (happy-dom) project
- `package.json` - `happy-dom` added to devDependencies
- `package-lock.json` - lockfile for `happy-dom`

### Claims
- **C1** [code] `handleCheckinsUpdated()` calls `syncGlobes()` after `bump()` when `templateUsesGlobe` is true.
- **C2** [test] `OverlayRenderer.dom.test.ts` mounts `OverlayRenderer.vue` with a template whose only content is `[[[checkin_globe]]]` and asserts that a `.checkins.updated` event reaches the globe's `update()` with the new pin's login.
- **C3** [unverified] That test failed against the tree without C1, with `expected 1 to be greater than 1`.
- **C4** [code] The test fakes only `fetch`, `window.Echo` and the `@/globe/checkinGlobe` module; the renderer, its composables and Pinia are real.
- **C5** [code] `vitest.config.mts` declares two projects: `unit` (`environment: 'node'`, all `*.test.ts` except `*.dom.test.ts`) and `dom` (`environment: 'happy-dom'`, `@vitejs/plugin-vue`, `*.dom.test.ts` only).
- **C6** [code] `happy-dom` is the only package added to `package.json`.
- **C7** [unverified] In Firefox on a local overlay with the HUD and pin list off, a `checkins.updated` broadcast left the globe at one label until a `toggle_pinlist` broadcast changed the HTML, after which the pin appeared; a second checkin after that appeared at once.

### Unchanged
- `handleCheckinSample()` already calls `syncGlobes()` the same way (OL-2611-047 C29); it is not in this diff.
- `syncPlinkos()` and `syncSnows()` mount their scenes through the same lazy-load-then-sync pattern, but neither reads `data`: Plinko is fed by `twitchChat.onMessage()` directly and both read their look from CSS custom properties. They are not in the diff.
- The watchEffect that morphs the compiled HTML and calls `syncGlobes()` is not in the diff.

### Risk
None on the server. Corrects the Risk line of OL-2611-047, which recorded this bug as open.
