## OL-2611-044 - docs(claims): restate the snow colour fallback in OL-2611-043 C6

Remedies OL-2611-043 audit F1.

**Shipped:** 2026-11-27
**Commit:** `git log --grep=OL-2611-044`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-043/remedy.md` - new: remedy of the OL-2611-043 audit

### Claims
- **C1** [code] `readSnowSettings()` in `resources/js/snow/snowPhysics.ts` falls back to `DEFAULT_SETTINGS` (or a literal equal to it after `/100`) for a missing or non-numeric `--snow-count`, `--snow-speed`, `--snow-size`, `--snow-shape`, `--snow-color` and `--snow-angle` (corrects OL-2611-043 C6, audit F1).
- **C2** [code] A missing `--snow-color-2` takes `--snow-color` when that is set, and `DEFAULT_SETTINGS.color2` only when both are missing (corrects OL-2611-043 C6, audit F1).
- **C3** [test] `snow.test.ts` "uses the first colour for the second when only one is set" asserts that a lone `--snow-color` of `#bfe3ff` yields `color2` `#bfe3ff` (corrects OL-2611-043 C6, audit F1).
