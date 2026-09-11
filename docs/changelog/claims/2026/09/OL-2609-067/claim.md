## OL-2609-067 - docs(help): document the color control

**Shipped:** 2026-09-11
**Commit:** `git log --grep=OL-2609-067`

### Surface
- `resources/help/pages/controls.md` - `keywords:` added to the frontmatter; a `color` row in the type table; a new `### color` section; a `Color` row in the Control Panel's "How each type works" table; one sentence added under `### Controls in CSS`

### Claims
- **C1** [code] `resources/help/pages/controls.md` has a `### color` section, placed between `### datetime` and `### expression`, bringing the page's "Control Types in Detail" sections to nine.
- **C2** [code] The type table under "What are Controls?" has a `` `color` `` row, and the "How each type works" table under "The Control Panel" has a `Color` row.
- **C3** [code] The `### color` section states that nothing is validated and lists five values the picker cannot read - `rebeccapurple`, `rgb(170 187 204 / 50%)`, `oklch(0.7 0.19 145)`, `color-mix(in oklab, red 40%, blue)`, `var(--brand)` - which are the same shapes asserted in `tests/Feature/ColorControlTest.php`'s dataset (OL-2609-066 C12).
- **C4** [code] The sentence added under `### Controls in CSS` links to `#color`, and `HelpMarkdown::headingId()` renders the `### color` heading with the id `color`, so the in-page anchor resolves.
- **C5** [code] The frontmatter gains `keywords: colour, palette, dynamic styling`. None of those three terms appears in the page body, which is the stated purpose of that field.
- **C6** [code] `title`, `description`, `heading`, `lead`, `section`, `canonical` and `context` in the frontmatter are unchanged, so the page keeps its `Live data` section, its two declared contexts and its beacon copy.
- **C7** [test] `php artisan test --filter=Help` passes, 73 tests. `HelpSearchIndexTest` asserts every section id in the index equals the id the renderer gives that heading, which is what C4 depends on; `HelpContextTest` asserts the heading and lead caps that C6 leaves untouched.

### Unchanged
- `resources/help/pages/index.md` is not in the diff. It links pages, and `controls` was already linked under its `Live data` heading; no page was added.
- No other help page enumerates the control types - `list writer` appears in `resources/help/` only in `controls.md` - so there is no second list to keep in step.
- The `### Values are sanitized` tip is not in the diff. It says HTML is stripped before storage, which stays true for `color`: `OverlayControl::sanitizeValue()` routes it through the same `strip_tags()` arm as `text` (OL-2609-066 C2).
- `public/help-index.json` and `public/help-reference-index.json` are gitignored (`.gitignore` lines 10 and 11) and are not in the diff. They are rebuilt by `help:build-index` on the deploy entrypoint.
- No application code is in the diff. This change is documentation for OL-2609-066 and alters no behaviour.
