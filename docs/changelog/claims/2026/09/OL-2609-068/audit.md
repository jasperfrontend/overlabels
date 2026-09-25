## Audit of OL-2609-068 - feat(checkin): the globe sphere takes its color and opacity from CSS

**Audited:** 2026-09-25
**Commit:** 49565d0e05dfde7edb0c66a90eda4d5579633a60
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/globe/checkinGlobe.ts:80-81 @49565d0` - `cssColor(styles, '--globe-shell-color', '#000000')`, `cssNumber(styles, '--globe-shell-opacity', 0.85)`; helpers at `:44` and `:50` are the ones `:77-78` already use; the diff adds no function. File unchanged at @HEAD |
| C2 | CONFIRMED | `checkinGlobe.ts:141 @49565d0` - `{ color: shellColor, transparent: true, opacity: shellOpacity }`; removed line in the diff is `{ color: 0x000000, transparent: true, opacity: 0.85 }`, and both defaults resolve to black at 0.85 |
| C3 | CONFIRMED | `checkinGlobe.ts:50-53 @49565d0` returns the trimmed raw string; `node_modules/three/src/materials/Material.js:580-582` (three 0.185.1, same pin @49565d0 and @HEAD) calls `Color.set()`, which routes strings to `setStyle()` (`three/src/math/Color.js:165-167`) |
| C4 | CONFIRMED | `checkinGlobe.ts:44-48 @49565d0` - `raw !== '' && Number.isFinite(n) ? n : fallback` |
| C5 | CONFIRMED | Diff touches only `checkinGlobe.ts:137-141 @49565d0`; the geometry (`:142`), `spin.add(shell)` (`:143`) and the `disposables` entry (`:149`) are not in any hunk |
| C6 | CONTRADICTED (compound) | Half 1 CONFIRMED: `:141 @49565d0` sets no `depthWrite`, and `three/src/materials/Material.js:230` defaults it to `true`. Half 2 CONTRADICTED: pin heads use an opaque material (`checkinGlobe.ts:160 @49565d0`, `MeshBasicMaterial({ color: pinColor })`, no `transparent`), and three renders the opaque list before the transparent one (`three/src/renderers/WebGLRenderer.js:1959-1961`), so the shell's depth write lands after the far-side heads are already drawn and only its opacity covers them; far-side stalks (`:161`, transparent) are sorted back-to-front by bounding-sphere z (`three/src/renderers/webgl/WebGLRenderLists.js:41-43`) and so are also drawn before the shell. At opacity 0 neither is hidden. Half 3 CONFIRMED as stated: the comment (`checkinGlobe.ts:138-140 @49565d0`) and `resources/help/pages/checkin.md:67-69 @49565d0` both say so. Unchanged at @HEAD |
| C7 | CONFIRMED | `checkinGlobe.ts:15-16 @49565d0` - both properties with defaults, same column alignment as `:12-14,17-18` |
| C8 | CONFIRMED | `resources/help/pages/checkin.md:52-53 @49565d0` (example) and `:67-70` (prose on `0`); the only hunks start at `:49` and `:64`, so frontmatter is untouched. Unchanged at @HEAD |
| C9 | CONFIRMED | Run in a worktree at 49565d0 (composer deps installed for that lock): `pint --test`, `format:check`, `lint:check`, `typecheck`, `build` exit 0; `npm test` 286 passed, 4 skipped (290); `php artisan test` 2017 passed, 2 skipped (see Notes) |
| C10 | CONFIRMED | `php artisan test --filter=Recipe` @49565d0: 51 passed (141 assertions); the filter includes `ProductInstallTest`, which parses every recipe `.md` and installs `chat_checkin` from `globe.md` ("creates the globe overlay with its two controls on install") |

### Surface
Complete.

### Findings
- **F1** claim contradicted (C6) - `checkinGlobe.ts:138-140 @49565d0` and `resources/help/pages/checkin.md:67-69 @49565d0` tell streamers the shell hides far-side dots and pins at any opacity including `0`, but opaque pin heads (`checkinGlobe.ts:160`) are drawn before the transparent shell and are only covered by its opacity (the same file's `:136-137` and OL-2609-007 C9 record them showing through at 0.55); the reader should either correct the comment and help paragraph, or make occlusion opacity-independent (e.g. a depth pre-pass or `renderOrder` on the shell) and pin it with a visual check.

### Notes
- C9: the first full Pest run at 49565d0 reported 12 failed / 2 skipped / 2005 passed; an immediate rerun passed 2017 with 2 skipped. The claim's 2032 passed / 6 skipped was not reproduced on this Linux/sqlite host; the green result was.
- `resources/recipes/chat_checkin/globe.md` was moved to `resources/recipes/chat-checkin/globe.md` with no content change by 0212f7b3 (OL-2609-114, which lists the move).
- CLAUDE.md's "Re-export from there, never hand-edit the recipe copy" names `chat_tower/tower.md` only; this change hand-edits the analogous `chat_checkin/globe.md` export and discloses it under Risk.
- The recipe's shipped `--globe-shell-opacity: 0.5` (`globe.md:70 @49565d0`) is below the 0.55 at which the source comment records far-side pin heads punching through; disclosed under Risk.
