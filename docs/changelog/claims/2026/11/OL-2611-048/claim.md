## OL-2611-048 - docs(claims): disclose the sample-checkins caption and restate globe label opacity from OL-2611-047

Remedies OL-2611-047 audit F1, F2.

**Shipped:** 2026-11-28
**Commit:** `git log --grep=OL-2611-048`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-047/remedy.md` - new file, remedy of the OL-2611-047 audit

### Claims
- **C1** [code] `resources/js/pages/products/design.vue` renders the sentence "Sample checkins are invented viewers and only show here, never on stream." in the preview caption, inside `<template v-if="has('sample_checkins')">`; it shipped with OL-2611-047 and is not named in that claim's Surface or Claims (corrects OL-2611-047 Surface, audit F1).
- **C2** [code] `placeLabels()` in `resources/js/globe/checkinGlobe.ts` writes `obj.label.style.opacity = nameAlpha.toFixed(2)` on every label on every call and never clears it to `''` (corrects OL-2609-134 C5, audit F2).
- **C3** [code] `placeLabels()` toggles `is-hidden` on a label only when `nameAlpha === 0`, so the class now tracks the name fade (`smoothstep(turn, NAME_FADE_FROM, NAME_FADE_TO)` of OL-2611-047 C14), not the pin's `worldPos.z > 0.05` facing test (corrects OL-2609-134 C5, audit F2).
- **C4** [code] Because of C2, a template CSS `opacity` rule on `.ol-globe-label` cannot change any label's opacity, near side or far side, without `!important`; OL-2609-134 C5 limited this to far-side labels (corrects OL-2609-134 C5, audit F2).

### Unchanged
- OL-2609-134's Unchanged line left "whether template CSS should own far-side label visibility" open for a decision. That decision is still open; `checkinGlobe.ts` is not in this diff.
