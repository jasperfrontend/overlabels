## Audit of OL-2609-135 - fix(tags): declare checkin_globe in the tag catalogue

**Audited:** 2026-09-25
**Commit:** ba8c648efbcf86ccf25988bff2962197c6163072
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/TemplateDataMapperService.php:250 @ba8c648` - `COMPUTED_TAGS['checkin_globe']` has `'category' => 'overlay'`, `'type' => 'string'`, `'sample' => ''`; the file has no later commit, same @HEAD |
| C2 | CONFIRMED | `TemplateDataMapperService.php:258-261 @ba8c648` - `tagCatalog()` is `array_merge(self::TAG_CATALOG, self::COMPUTED_TAGS)`; `getSampleTemplateData()` (:412), `getAvailableTemplateTags()` (:428), `getTagCategories()` (:448-450, files by `$spec['category']`, so under `overlay`) and `tagBrowser()` (:551) all iterate `self::tagCatalog()`; same @HEAD |
| C3 | CONFIRMED | `TemplateDataMapperService.php:325-331 @ba8c648` - `$templateData` is seeded with `'checkin_globe' => ''` before any mapping; the loop at :334 writes only `TAG_CATALOG` names (`checkin_globe` is not one; its only occurrences are :250 and :330), :348 writes only `user_email`, and `buildUserScopeIndexedKeys()` keys are `<alias>.*`, so nothing overwrites it; same @HEAD |
| C4 | CONFIRMED | `tests/Feature/TemplateTagCatalogTest.php:90-103` asserts every non-`event.` tag in `getTagCategories()` is a key of `mapTwitchDataForTemplates()`; `checkin_globe` is produced only by the literal at `TemplateDataMapperService.php:330 @ba8c648`. `php artisan test --filter=TemplateTagCatalogTest` @HEAD (test file and service unchanged since @ba8c648): 21 passed, 238 assertions |
| C5 | CONFIRMED | `TemplateTagCatalogTest.php:140-147` asserts the sorted `tagBrowser()` names equal the sorted `tagCatalog()` keys (it does not name `checkin_globe` itself; the claim does not say it does); passed in the same run |
| C6 | CONTRADICTED (compound) | Half 1, "resolves OL-2609-007 audit F4 ... now has a catalogue declaration": CONFIRMED - F4 (`OL-2609-007/audit.md:28`) offered "add a catalogue entry", and `tagCatalog()` includes it @ba8c648. Half 2, "as the CLAUDE.md Template Tags section requires": CONTRADICTED - that section names `TAG_CATALOG` as "the one and only declaration" and says "Adding a tag is one entry there and nothing else"; the entry is in `COMPUTED_TAGS` (:250 @ba8c648), not `TAG_CATALOG` (:150), and a second edit (:330) was needed. See F1 |

### Surface
Complete.

### Findings
- **F1** contradicts the record - `CLAUDE.md` Template Tags says "`TemplateDataMapperService::TAG_CATALOG` is the one and only declaration of a static template tag" and "Adding a tag is one entry there and nothing else", but this change declares `checkin_globe` in `COMPUTED_TAGS` (`app/Services/TemplateDataMapperService.php:250 @ba8c648`) and needs a second, hand-written emission in `mapTwitchDataForTemplates()` (:330) to satisfy the drift guard, and C6 cites the CLAUDE.md section as satisfied. It follows the existing `overlay_name`/`timestamp` computed-tag pattern (:248-249), which CLAUDE.md does not describe. Reader should either record in CLAUDE.md that computed tags live in `COMPUTED_TAGS` and also need an emission in `mapTwitchDataForTemplates()`, or restate C6 in a new claim without the "as CLAUDE.md requires" clause.

### Notes
- All three Unchanged lines hold: `resources/js/globe/globeTag.ts`, `tests/Feature/TemplateTagCatalogTest.php` and `CLAUDE.md` are not in the diff. `replaceGlobeTags()` is at `globeTag.ts:25 @ba8c648` and runs before `parseSource()` in `OverlayRenderer.vue:521 @HEAD`.
- Only one commit carries the trailer. No commit after ba8c648 touches `TemplateDataMapperService.php`, so every @ba8c648 line above is also @HEAD.
- The OL-2609-007 remedy marked F4 SKIPPED (`OL-2609-007/remedy.md:11`); C6 cites that inline. OL-2609-134's Unchanged line recorded F4 as still open; this change is the one that closes it.
