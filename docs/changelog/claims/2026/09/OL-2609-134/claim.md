## OL-2609-134 - docs(claims): restate what OL-2609-007 C5, C6 and C7 actually hold

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-134`

Remedies OL-2609-007 audit F1, F2, F3; F4 skipped.

### Surface
- `docs/changelog/claims/2026/09/OL-2609-007/remedy.md` - new: the remedy table for the OL-2609-007 audit

### Claims
- **C1** [test] In `resources/js/globe/globeTag.test.ts`, the test "decodes a plausible planet: some land, mostly ocean, empty poles" asserts emptiness for exactly one mask row, `MASK_ROWS - 1` (latitude -88 to -90), across all `MASK_COLS` columns. It asserts nothing about any other southern row and nothing about the north polar rows (corrects OL-2609-007 C6, audit F1).
- **C2** [code] `resources/js/globe/landmask.ts` embeds the land mask as the base64 string `LANDMASK_B64` and decodes it at runtime with `atob`, into a `MASK_COLS` (180) by `MASK_ROWS` (90) bitmask read by `isLandCell()` (corrects OL-2609-007 C5, audit F2).
- **C3** [unverified] The bits in `LANDMASK_B64` were produced by bucketing every `geo_places` row into 2-degree cells with the tinker script reproduced in OL-2609-007; checking this needs the gazetteer database, not the tree (corrects OL-2609-007 C5, audit F2).
- **C4** [code] `mountCheckinGlobe()` in `resources/js/globe/checkinGlobe.ts` reads seven custom properties off the placeholder via `getComputedStyle`: `--globe-dot-color`, `--globe-dot-size`, `--globe-pin-color`, `--globe-shell-color`, `--globe-shell-opacity`, `--globe-rotation-seconds`, `--globe-tilt-degrees`. The two shell properties were added by OL-2609-068; before it the shell was hard-coded `0x000000` at opacity `0.85` (corrects OL-2609-007 C7, audit F3).
- **C5** [code] Label nodes get class `ol-globe-label` and have `is-hidden` toggled when facing away, so template CSS can style them; but `checkinGlobe.ts` also sets inline `style.opacity = '0'` on a far-side label and clears it to `''` when it faces the viewer again, so a template rule on `.is-hidden` cannot make a far-side label visible without `!important`. OL-2609-007 C7's "template CSS owns all styling" is not true of far-side label opacity (corrects OL-2609-007 C7, audit F3).

### Unchanged
- `checkinGlobe.ts` line setting the inline label opacity is not in the diff. Whether template CSS should own far-side label visibility is a behaviour change for existing overlays and is left for a decision.
- `globeTag.test.ts` is not in the diff; C1 records its coverage rather than widening it.
- `[[[checkin_globe]]]` is still declared only as `GLOBE_TAG` in `resources/js/globe/globeTag.ts` and not in `TemplateDataMapperService::TAG_CATALOG`; neither that constant nor `CLAUDE.md` is in the diff (OL-2609-007 audit F4, skipped).
