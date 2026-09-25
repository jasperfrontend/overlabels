## Audit of OL-2609-134 - docs(claims): restate what OL-2609-007 C5, C6 and C7 actually hold

**Audited:** 2026-09-25
**Commit:** 08c0a5a75e9501aaf511e2ca92ceac57605a8b1b
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/globe/globeTag.test.ts:35-45 @08c0a5a` - "decodes a plausible planet: some land, mostly ocean, empty poles" loops `col` over `0..MASK_COLS-1` asserting `isLandCell(MASK_ROWS - 1, col)` is false (:42-44); its other assertions are dot-count bounds (:38-39), with no other row and no north polar row checked. Row 89 covers lat -88..-90 by the row formula at :50. `npm test -- resources/js/globe/globeTag.test.ts` at HEAD: 1 file, 14 tests passed. File unchanged 08c0a5a..HEAD |
| C2 | CONFIRMED | `resources/js/globe/landmask.ts:16-17 @08c0a5a` - `MASK_COLS = 180`, `MASK_ROWS = 90`; `LANDMASK_B64` embedded at :19; `maskBytes()` decodes it with `atob` at :52; `isLandCell()` reads bits at :60-65. Unchanged at HEAD |
| C3 | UNVERIFIABLE | tagged [unverified]; provenance needs the `geo_places` database, which the tree does not hold |
| C4 | CONFIRMED | `resources/js/globe/checkinGlobe.ts:73-83 @08c0a5a` - `mountCheckinGlobe(el)` calls `getComputedStyle(el)` (:76) and reads exactly the seven named properties (:77-83). Before `49565d0e` (trailer `Changelog: OL-2609-068`), `checkinGlobe.ts:134 @49565d0e~1` is `MeshBasicMaterial({ color: 0x000000, transparent: true, opacity: 0.85 })` and only five properties are read (:75-79); same hard-code at :133 @925d2e9. Unchanged at HEAD |
| C5 | CONFIRMED | `checkinGlobe.ts:182 @08c0a5a` - `label.className = 'ol-globe-label'`; :209 toggles `is-hidden` on `!facing`; :210 `obj.label.style.opacity = facing ? '' : '0'`. An inline style outranks a non-`!important` stylesheet rule, so the `.is-hidden` override statement holds. Unchanged at HEAD |

### Surface
Complete.

### Findings
None.

### Notes
- The heading subject (`docs(claims): restate what ...`) is not the commit subject (`docs(remy): remedy of OL-2609-007`); the guide asks for them to match, but it also calls a mismatch "untidy rather than broken" because the trailer is the anchor.
- C4 and C5 each make more than one assertion. Every part checked out true, so each gets a single verdict.
- I read the answered audit (`OL-2609-007/audit.md`). Remedy rows F1-F3 (RECORD) line up with C1, C2+C3 and C4+C5, and F4 is SKIPPED as the third Unchanged line says. `@08c0a5a`, `GLOBE_TAG` is declared at `resources/js/globe/globeTag.ts:15`, and `checkin_globe` does not appear under `app/`.
- This diff is docs only (`remedy.md` plus the claim), so the path rule does not require a claim; the guide does not forbid one.
