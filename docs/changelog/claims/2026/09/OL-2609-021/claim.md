## OL-2609-021 - feat(help): the help site gets its own design and a seven-section guide taxonomy

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-021`

### Surface
- `app/Support/HelpCorpus.php` - `SECTIONS` (label => description), `SECTION_EXTRAS`, `sections()`, `sectionOf()`, `sectionAnchor()`, `ordered()`, `sortByIndex()`, `indexOrder()` added; every corpus document carries a `section` key
- `resources/views/help/_section-icon.blade.php` - new file: one inline Lucide outline per section label, book fallback
- `app/Support/HelpNav.php` - rewritten: one tree shape (groups, optional sections, items, `open`) for both corpora; a Reference row on the prose side
- `app/Support/HelpPage.php` - `readingMinutes()` added and returned from `render()`; `section` is trimmed
- `app/Http/Controllers/HelpController.php` - the `index` slug renders `help.landing`; other slugs get `prev`, `next`, `related`, `group` from `neighbours()`
- `resources/views/layouts/help.blade.php` - new chrome: fixed gradient backdrop, nav with logo, HELP eyebrow, compact search, Reference / Updates / Kits links and a dashboard button, footer
- `resources/views/help/landing.blade.php` - new file: hero with search and counts, tutorials and deep dives cards, one card per guide section with icon tile and description (the first spanning two columns), reference card
- `resources/views/help/doc.blade.php` - rewritten: sidebar tree, breadcrumb, kind pill, reading time, "Copy page as Markdown", right-rail table of contents, previous/next, related docs
- `resources/views/help/reference.blade.php` - rewritten on the same grid and card classes; llms.txt block, category buttons, JSON-LD and tag snippet carried over
- `resources/views/help/_tree.blade.php` - new file: the sidebar tree partial, native `<details>`
- `resources/views/help/_search.blade.php` - new file: the search field with its results panel, included once per page
- `resources/css/help.css` - new file: the help chrome classes and three help-only CSS variables
- `resources/css/app.css` - imports `help.css`
- `resources/css/help-prose.css` - h2 gets a top rule, `scroll-mt-6`, pre gets `rounded-lg`, `.ov-tag` becomes a full pill
- `resources/js/help/main.ts` - search renders into the results panel instead of the sidebar; arrow-key navigation, Escape and outside-click close; table-of-contents scroll-spy; "Copy page as Markdown" fetches the `.md` twin
- `resources/help/pages/index.md` - heading and lead replaced; body restructured into `## Tutorials`, `## Guides` with one `###` per section, `## Deep dives`, `## Reference`; the three bot sub-pages linked directly
- `resources/help/pages/blocks.md` - gains `section: Building overlays`
- `resources/help/pages/bot/aliases.md` - gains `section: Bot & chat`
- `resources/help/pages/bot/commands.md` - gains `section: Bot & chat`
- `resources/help/pages/bot/index.md` - gains `section: Bot & chat`
- `resources/help/pages/bot/random-and-counters.md` - gains `section: Bot & chat`
- `resources/help/pages/builder.md` - gains `section: Building overlays`
- `resources/help/pages/chat.md` - gains `section: Bot & chat`
- `resources/help/pages/checkin.md` - gains `section: Bot & chat`
- `resources/help/pages/conditionals.md` - gains `section: Tags & syntax`
- `resources/help/pages/controls.md` - gains `section: Live data`
- `resources/help/pages/editor.md` - gains `section: Building overlays`
- `resources/help/pages/expressions.md` - gains `section: Live data`
- `resources/help/pages/for-creators.md` - gains `section: Getting started`
- `resources/help/pages/for-designers.md` - gains `section: Getting started`
- `resources/help/pages/formatting.md` - gains `section: Tags & syntax`
- `resources/help/pages/help-reference-index-json.md` - gains `section: For machines`
- `resources/help/pages/integration-test-mode.md` - gains `section: Integrations & testing`
- `resources/help/pages/lists-realtime.md` - gains `section: Live data`
- `resources/help/pages/lists.md` - gains `section: Live data`
- `resources/help/pages/llms-txt.md` - gains `section: For machines`
- `resources/help/pages/manifesto.md` - gains `section: Getting started`
- `resources/help/pages/markdown-endpoints.md` - gains `section: For machines`
- `resources/help/pages/math.md` - gains `section: Tags & syntax`
- `resources/help/pages/overlays-vs-alerts.md` - gains `section: Getting started`
- `resources/help/pages/rendering.md` - gains `section: Getting started`
- `resources/help/pages/resources.md` - gains `section: Building overlays`
- `resources/help/pages/tags-parse-once.md` - gains `section: Tags & syntax`
- `resources/help/pages/tailwind.md` - gains `section: Building overlays`
- `resources/help/pages/testing.md` - gains `section: Integrations & testing`
- `resources/help/pages/tokens.md` - gains `section: Integrations & testing`
- `resources/help/pages/why-kofi.md` - gains `section: Integrations & testing`
- `resources/help/pages/why-overlabels.md` - gains `section: Getting started`
- `tests/Feature/HelpTaxonomyTest.php` - new file
- `tests/Feature/HelpPageTest.php` - the crawlable-html loop skips the `index` slug
- `CLAUDE.md` - `section:` documented in the help-page frontmatter rules; the index.md link rule now names the matching heading

### Claims
- **C1** [code] `HelpCorpus::SECTIONS` is an ordered map whose keys are `Getting started`, `Tags & syntax`, `Building overlays`, `Live data`, `Bot & chat`, `Integrations & testing`, `For machines`, each with a non-empty description.
- **C2** [code] Every file under `resources/help/pages/` that is neither the root `index.md` nor under `tutorials/` or `deep-dives/` carries a `section:` line whose value is a key of `SECTIONS`.
- **C3** [code] `HelpCorpus::sections()` returns one entry per `SECTIONS` key in that order, each with its guides plus that label's `SECTION_EXTRAS`, sorted by `sortByIndex()`: the position of the first `](/help...)` link to the same url in `index.md`, unlinked urls last by title.
- **C17** [code] `HelpCorpus::ordered($kind)` applies the same `sortByIndex()` to one kind, and is what the landing, `HelpNav::docGroups()` and `HelpController::neighbours()` use for tutorials and deep dives.
- **C18** [test] `HelpTaxonomyTest` asserts Getting started opens with `why-overlabels`, Bot & chat with `bot/index`, and `ordered('tutorial')` with `tutorials/show-chat-on-screen`, all of which differ from alphabetical order.
- **C4** [code] `SECTION_EXTRAS` lists `/help/integration-presets` under `Live data` and `/help/gamejam` under `Bot & chat`, and those are the only two entries.
- **C5** [code] `HelpController::show('index')` renders `help.landing` and never echoes `index.md`'s rendered body; `HelpController::markdown('index')` still serves the file verbatim.
- **C6** [code] `HelpController::neighbours()` walks a guide's section, or a tutorial's / deep dive's kind, for previous, next and up to `RELATED_LIMIT` (3) related pages.
- **C7** [code] `HelpNav::docGroups($slug)` marks open only the group of the page's kind and, for a guide, only the section it belongs to; `HelpNav::referenceGroups()` marks open only the active category.
- **C8** [code] `HelpPage::readingMinutes()` is `max(1, ceil(words / 200))` over the markdown body with tags stripped.
- **C9** [code] `layouts/help.blade.php` includes `help._search` on every page except the landing, which includes it in its hero, so `id="help-search"` appears exactly once per page.
- **C10** [code] `_tree.blade.php` renders the active item with `data-help-active` and `aria-current="page"`, which `main.ts`'s `scrollActiveIntoView()` reads unchanged.
- **C11** [code] `main.ts` no longer toggles `#help-nav-tree`; results render into `#help-search-results` inside the `.help-search` container.
- **C12** [code] `help.css` defines no gradient other than the pre-existing page backdrop; kind identity is `.help-pill--*` flat tints and text.
- **C19** [code] `.help-card` sets a border and background only - no border radius - and every landing-page link inside a card uses `.help-doc-link--row`, so tutorials, deep dives, guides and the reference link share one hover treatment.
- **C13** [test] `HelpTaxonomyTest` asserts every guide declares a section from `SECTIONS`, tutorials and deep dives declare none, every section is non-empty and in constant order, the landing links every non-reference document and carries every section anchor, and index.md files each guide under a heading equal to its section.
- **C14** [test] `HelpTaxonomyTest` asserts previous/next/related on `/help/integration-test-mode`, the collapsed/open state of the tree on `/help/bot/aliases`, and `readingMinutes()` at 0, 199 and 201 words.
- **C15** [test] `HelpPageTest`, `HelpUnificationTest`, `HelpContextTest`, `HelpPageOgImageTest`, `LlmsTxtDiscoverabilityTest` and the sitemap tests pass unchanged apart from the `index` skip in `HelpPageTest`.
- **C16** [unverified] Rendered locally in Chrome at 1400px: landing, a guide, and a reference entry match the Claude Design canvas; the search panel opens under the field with results; the table of contents highlights the section under the reader. Phone widths were not rendered - the Chrome window would not resize.

### Unchanged
- `HelpMarkdown` is not in the diff: the render pipeline, heading anchors, callouts and tag widgets are as they were, so `HelpPage::render()['html']` is byte-identical for every page.
- `HelpContext`, `HelpReferenceService`, `HelpReferenceController`, `SitemapController` and `help:build-index` are not in the diff; the search index, sitemap and beacon read the same corpus fields plus the new `section` key.
- `/help/integration-presets` and `/help/gamejam` stay Inertia; they are only linked, via `SECTION_EXTRAS` and index.md, as they already were from index.md.
- `resources/js/utils/helpSearch.ts` is not in the diff: ranking, weights and cutoffs are untouched; only where results are drawn changed.
- Route registration in `routes/web.php` is not in the diff; `/help`, every page and every `.md` twin resolve as before.

### Risk
`/help.md` no longer mirrors `/help` one-to-one: the landing is derived from the corpus, the markdown twin is the hand-written index.md. `HelpTaxonomyTest` holds them to the same filing but not the same words. The reference sidebar now opens only the active category, so the other 140-odd entries are one click further than before.
