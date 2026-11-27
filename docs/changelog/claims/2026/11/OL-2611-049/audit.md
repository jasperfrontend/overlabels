## Audit of OL-2611-049 - fix(checkin): a real checkin reaches the globe without a reload, with the first component test

**Audited:** 2026-11-28
**Commit:** db561e73f0341a45d25243402623cd6a1801de6e
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:1698-1699 @db561e73` - `bump();` then `if (templateUsesGlobe.value) syncGlobes();` inside `handleCheckinsUpdated()` (starts :1679); HEAD is db561e73 |
| C2 | CONFIRMED | `resources/js/components/OverlayRenderer.dom.test.ts:51 @db561e73` - template html is `<div class="stage">[[[checkin_globe]]]</div>` (tag is the only tag/text, inside one wrapper div); :99 fires `handlers['.checkins.updated']` with `pin('viewer_one')`; :101-102 assert a new globe `update()` arrived and its last pins contain `viewer_one`. `npx vitest run resources/js/components/OverlayRenderer.dom.test.ts`: 1 file, 1 test passed |
| C3 | UNVERIFIABLE | tagged [unverified] (fail-first run against a prior tree) |
| C4 | CONFIRMED | `OverlayRenderer.dom.test.ts @db561e73` - only fakes are `vi.mock('@/globe/checkinGlobe')` :15, `window.Echo = fakeEcho()` :68, `vi.stubGlobal('fetch', ...)` :69-72; renderer imported real at :82, `createPinia()` real at :86; no other `vi.mock`/`stubGlobal` in the file |
| C5 | CONFIRMED | `vitest.config.mts:34-40 @db561e73` - `unit`, `environment: 'node'`, include `resources/js/**/*.test.ts` + `cloudflare/**/*.test.ts`, exclude `resources/js/**/*.dom.test.ts`; :44-49 - `dom`, `plugins: [vue()]`, `environment: 'happy-dom'`, include `resources/js/**/*.dom.test.ts` |
| C6 | CONFIRMED | `git show db561e73 -- package.json` - one added line, `package.json:30 @db561e73` `"happy-dom": "^21.0.4"`; lockfile adds happy-dom plus its transitive deps (`@types/whatwg-mimetype`, `@types/ws`, `buffer-image-size`, ...) |
| C7 | UNVERIFIABLE | tagged [unverified] (browser observation) |

### Surface
Complete.

### Findings
- **F1** contradiction with the record - the change adds a DOM environment and the repo's first component test, which `CLAUDE.md:121-122 @HEAD` still records as not done ("`environment: 'node'`, no jsdom: component testing would mean adding both and is an open decision, not an oversight") and which `docs/changelog/claims/2026/09/OL-2609-117/claim.md:25` cites as a standing decision ("no component testing by decision (CLAUDE.md)"); the claim cites neither, so update the CLAUDE.md Testing paragraph to describe the `unit`/`dom` projects and happy-dom.

### Notes
- Full suite `npm test` @db561e73: 42 files, 562 tests passed, the dom project included.
- HEAD is db561e73; no later commit touches the audited paths, so there is no drift.
- The Unchanged lines hold: the only `OverlayRenderer.vue` hunk is at :1692 (`handleCheckinsUpdated`); `handleCheckinSample()` :1794, `syncGlobes()` :757 and the watchEffect calls at :718-719/:745 are outside the diff. `handleCheckinSample()` matches OL-2611-047 C29.
