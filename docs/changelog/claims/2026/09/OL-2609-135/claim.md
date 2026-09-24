## OL-2609-135 - fix(tags): declare checkin_globe in the tag catalogue

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-135`

### Surface
- `app/Services/TemplateDataMapperService.php` - `checkin_globe` entry added to `COMPUTED_TAGS`, and `mapTwitchDataForTemplates()` emits it as `''`

### Claims
- **C1** [code] `TemplateDataMapperService::COMPUTED_TAGS` has a `checkin_globe` entry with category `overlay`, type `string` and sample `''`.
- **C2** [code] `TemplateDataMapperService::tagCatalog()` returns `checkin_globe`, so `getTagCategories()['overlay']['tags']`, `getAvailableTemplateTags()`, `getSampleTemplateData()` and `tagBrowser()` all include it.
- **C3** [code] `mapTwitchDataForTemplates()` returns the key `checkin_globe` with the value `''` for every payload.
- **C4** [test] `TemplateTagCatalogTest` "produces every tag the categories advertise" passes with `checkin_globe` advertised, because of C3.
- **C5** [test] `TemplateTagCatalogTest` "covers the whole catalogue and nothing else" passes with `checkin_globe` in the browser.
- **C6** [code] Resolves OL-2609-007 audit F4 (skipped by the OL-2609-007 remedy): `[[[checkin_globe]]]` now has a catalogue declaration, as the CLAUDE.md Template Tags section requires of every static tag.

### Unchanged
- The globe is still drawn by the literal pre-pass in `resources/js/globe/globeTag.ts` (`replaceGlobeTags()`), which removes `[[[checkin_globe]]]` from the source before the tag pass reads tag data. The `''` from C3 is never rendered. `globeTag.ts` is not in the diff.
- `tests/Feature/TemplateTagCatalogTest.php` is not in the diff. The drift guard was not loosened to admit a tag the mapping does not produce; C3 is what satisfies it.
- `CLAUDE.md` is not in the diff. No exception to the "one and only declaration" rule was written.

### Risk
An overlay whose template contains `[[[checkin_globe]]]` now receives `checkin_globe: ""` in its render payload. That key is unused, because the pre-pass has already replaced the tag.
