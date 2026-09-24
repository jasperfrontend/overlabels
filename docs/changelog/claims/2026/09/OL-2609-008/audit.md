## Audit of OL-2609-008 - feat(globe): Overlabels checks in at Avarua on every globe

**Audited:** 2026-09-24
**Commit:** 5eb6a3a728f844ad97a7572958115669328b5c5b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/globe/brandPin.ts:19-29 @5eb6a3a` - `BRAND_PIN` has login `overlabels`, place `Avarua, CK`, lat `'-21.2078'`, lng `'-159.775'`; `:37 @5eb6a3a` returns `[...pins.filter((pin) => pin.login !== BRAND_PIN.login), BRAND_PIN]` (new array, input not written). Same function @HEAD; `BRAND_PIN.distance_km` renamed to `distance` @HEAD (OL-2609-009), a field C1 does not name |
| C2 | CONTRADICTED (3 of 4 parts confirmed) | `resources/js/globe/globeTag.test.ts:68-108 @5eb6a3a` describe "the maker's mark": present-and-last is asserted (`toBe(BRAND_PIN)` on the last element), empty globe (`toEqual([BRAND_PIN])`), dedup (exactly one `overlabels` login, last pin place `Avarua, CK`). "Input array is untouched" is asserted only as `expect(input).toHaveLength(1)`; element identity and pin-object contents are not asserted. `npm test -- resources/js/globe/globeTag.test.ts` @HEAD: 14 passed |
| C3 | CONFIRMED | `git grep withBrandPin 5eb6a3a -- resources/` - only non-test call is `resources/js/globe/checkinGlobe.ts:242 @5eb6a3a` inside `update()`; `destroy()` calls `rebuildPins([])` at `:248 @5eb6a3a`, and `rebuildPins` removes every prior label at `:149 @5eb6a3a` (`for (const obj of pinObjects) obj.label.remove()`). Same call-site set @HEAD (`checkinGlobe.ts:249 @HEAD`) |
| C4 | CONFIRMED | `resources/js/globe/checkinGlobe.ts:175-176 @5eb6a3a` - every pin passed to `rebuildPins` gets `className = 'ol-globe-label'` and `dataset.login = pin.login`; `resources/help/pages/checkin.md:64 @5eb6a3a` documents `.ol-globe-label[data-login="overlabels"]`; still present at `checkin.md:76 @HEAD` |
| C5 | UNVERIFIABLE | tagged [unverified]; a visual check in a browser, correctly tagged |

### Surface
Complete.

### Findings
- **F1** [test] claim narrower than stated - C2 says the test asserts "the input array is untouched", but `resources/js/globe/globeTag.test.ts:102-107 @5eb6a3a` ("never mutates the input window") asserts only `input` length 1, so a length-preserving mutation (replacing `input[0]`, editing a pin object) would pass; a new claim should restate C2 as a length check, or the test should also assert `input[0]` is the original pin with `toEqual` against a fresh copy.

### Notes
- Unchanged lines checked: `checkinSlots.ts`, `BotCheckinController`, `Checkin::windowFor()`, the render payload and any `database/` path are absent from `git show --stat 5eb6a3a` (four paths plus the claim).
- HEAD drift disclosed by later claims: `BRAND_PIN` and the test fixture field `distance_km` -> `distance` (OL-2609-009); `checkinGlobe.ts` shell colour/opacity from CSS (OL-2609-068, which declares `brandPin.ts` unchanged).
- The Vitest run was at HEAD, where the test file differs from 5eb6a3a only by the fixture field rename above.
