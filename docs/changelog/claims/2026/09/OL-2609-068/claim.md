## OL-2609-068 - feat(checkin): the globe sphere takes its color and opacity from CSS

**Shipped:** 2026-09-11
**Commit:** `git log --grep=OL-2609-068`

### Surface
- `resources/js/globe/checkinGlobe.ts` - two custom properties added to the header comment's recognised list; two `const` reads added in `mountCheckinGlobe`; the `shellMaterial` literal now uses them; the comment above it extended
- `resources/help/pages/checkin.md` - two lines added to the `.ol-checkin-globe` CSS example; one paragraph added after the labels paragraph
- `resources/recipes/chat_checkin/globe.md` - two lines added to the `.ol-checkin-globe` rule in the `css` block (authored by Jasper, not by the exporter)

### Claims
- **C1** [code] `mountCheckinGlobe` reads `--globe-shell-color` through the existing `cssColor` helper with fallback `'#000000'`, and `--globe-shell-opacity` through the existing `cssNumber` helper with fallback `0.85`. No new helper was added; both are the same functions `--globe-dot-color` and `--globe-dot-size` already used.
- **C2** [code] `shellMaterial` is constructed with `color: shellColor, transparent: true, opacity: shellOpacity`. The previous literal was `color: 0x000000, transparent: true, opacity: 0.85`, so the two defaults in C1 reproduce the prior value exactly and every existing globe renders identically with no CSS change.
- **C3** [code] `cssColor` returns the raw trimmed property string, and `THREE.MeshBasicMaterial` accepts a CSS color string for `color`, so any value three.js's `Color` parser understands works - not only hex.
- **C4** [code] `cssNumber` falls back to the default when the property is absent or non-finite, so a missing or malformed `--globe-shell-opacity` yields `0.85` rather than `NaN`.
- **C5** [code] The shell mesh, its geometry (`SPHERE_RADIUS * 0.98`, 48x32), its position in the `spin` group and its entry in `disposables` are unchanged. Only the material's two constructor arguments changed, so nothing about occlusion order, disposal or teardown moved.
- **C6** [code] `shellMaterial` keeps three.js's default `depthWrite: true`, so the shell occludes far-side dots and pins at every opacity including `0`. Both the source comment and the new help paragraph state this.
- **C7** [code] The header comment block in `checkinGlobe.ts` lists both new properties with their defaults, in the same aligned format as the four already there, so the file's own documentation of "recognised properties, all optional" is complete.
- **C8** [code] `resources/help/pages/checkin.md` shows both properties in the `.ol-checkin-globe` example and explains the `0` case in prose. Its frontmatter is not in the diff, so the page keeps its section, context and beacon copy.
- **C9** [test] Full local gate green: `pint --test`, `format:check`, `lint:check`, `npm test` (290), `typecheck`, `npm run build`, `php artisan test` (2032 passed, 6 skipped).
- **C10** [test] `php artisan test --filter=Recipe` passes (51 tests), so the edited `chat_checkin/globe.md` still satisfies `RecipeManifestValidator` and installs.

### Unchanged
- No test covers `checkinGlobe.ts`. There was none before this change and none was added; the module is WebGL rendering with no pure surface the Vitest suite reaches.
- `resources/js/globe/landmask.ts` and `brandPin.ts` are not in the diff. The dot cloud and the maker's-mark pin are unaffected.
- `resources/help/pages/controls.md` is not in the diff. Its `--globe-dot-color` / `--globe-pin-color` snippet is an illustrative example of a color control feeding a CSS variable, not a reference list of the globe's properties.
- No prose changelog entry. Two optional CSS custom properties with backward-compatible defaults is below the bar in `CLAUDE.md` ("only a real feature, a big refactor or a substantial functionality change").

### Risk
- Verified visually by Jasper before push: a globe rendered with the recipe's non-default shell color and opacity looks as expected. No automated test covers the rendering, so that remains the only evidence.
- The recipe's `--globe-shell-opacity: 0.5` sits just under the `0.55` the source comment records as the point where far-side pin heads begin to punch through. That is a deliberate authored value, not a defect, but new Chat Checkin installs will show slightly more of the far side than the default did.
- `resources/recipes/chat_checkin/globe.md` is the exported copy; its inputs live in gitignored `docs/private/engine/chat-checkin/`. This edit was made in the export directly, so a future re-export from those inputs will drop it unless they are updated too.
