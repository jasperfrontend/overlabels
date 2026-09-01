## OL-2609-008 - feat(globe): Overlabels checks in at Avarua on every globe

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-008`

### Surface
- `resources/js/globe/brandPin.ts` - new: `BRAND_PIN` constant + `withBrandPin()`, pure
- `resources/js/globe/checkinGlobe.ts` - `update()` draws `withBrandPin(pins)` instead of `pins`
- `resources/js/globe/globeTag.test.ts` - four maker's-mark tests
- `resources/help/pages/checkin.md` - TIP callout documenting the mark and its CSS hook

### Claims
- **C1** [code] `withBrandPin()` appends `BRAND_PIN` (login `overlabels`, place `Avarua, CK`, lat -21.2078, lng -159.775) after filtering any incoming pin with the same login, and does not mutate its input.
- **C2** [test] `globeTag.test.ts` "the maker's mark" describe asserts: the mark is present and last with real pins, present on an empty globe, deduplicated against a real `overlabels` login, and the input array is untouched.
- **C3** [code] `GlobeInstance.update()` is the only call site, so the mark exists purely in the globe drawing: `destroy()` still calls `rebuildPins([])` directly and clears everything including the mark's label.
- **C4** [code] The mark's label renders through the same path as every pin (`ol-globe-label`, `data-login="overlabels"`), which is the selector the help page documents for styling it.
- **C5** [unverified] Verified visually in Chrome against the local build: the Overlabels label renders in the South Pacific at Avarua's position on the seeded proof overlay.

### Unchanged
- `checkinSlots.ts`, `BotCheckinController`, `Checkin::windowFor()` and the render payload are not in the diff: the mark never enters the data, so `checkins.count`, the `foreach:checkins` feed, the `c:checkin:*` controls and alert triggers all read exactly as they did - only the drawn globe differs.
- The `checkins` table gains no row; a future remove-the-mark option is a condition around the one `withBrandPin()` call site.

### Risk
The mark cannot be removed through settings (a paid removal is a stated future idea, not built).
The pin head and stalk are canvas and unreachable by CSS; the HTML label can be hidden with CSS
like any label.
