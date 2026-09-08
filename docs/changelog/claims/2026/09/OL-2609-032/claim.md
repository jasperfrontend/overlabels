## OL-2609-032 - feat(help): search ranks sections with a real term engine, and a result lands on the heading that answers

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-032`

### Surface
- `app/Support/HelpMarkdown.php` - new `sections()` splitter and `plainHeading()`; heading id logic extracted into `headingId()` shared with `addHeadingAnchors()`
- `app/Support/HelpCorpus.php` - every document carries `sections`; the root `index` page gets none
- `app/Console/Commands/BuildHelpReferenceIndex.php` - unified index ships `sections` instead of `body`; description no longer says "fuzzy"
- `resources/js/utils/helpSearch.ts` - rewritten on MiniSearch: section records, tokenizer, stop words, stemming, field boosts, kind boosts, AND-then-OR, per-page cap, `HelpHit`, `snippet()`
- `resources/js/utils/helpSearch.test.ts` - rewritten against the new engine (40 tests)
- `resources/js/utils/helpSearch.corpus.test.ts` - new, runs against `public/help-index.json` when present
- `resources/js/composables/useHelpReference.ts` - `search()` returns `HelpHit[]`
- `resources/js/components/ReferencePalette.vue` - renders hits (page title, section heading, snippet from `helpSearch.ts`); the local snippet function is gone
- `resources/js/help/main.ts` - result rows render page title plus section heading
- `resources/css/help.css` - `.help-search-result-title` and `.help-search-result-section`
- `tests/Feature/HelpSearchIndexTest.php` - new (7 tests)
- `tests/Feature/HelpUnificationTest.php` - one comment updated; no assertion changed
- `package.json`, `package-lock.json` - `fuse.js` removed, `minisearch` 7.2.0 added
- `CLAUDE.md` - the three Help Documentation bullets describing the Fuse engine replaced

### Claims
- **C1** [code] `HelpMarkdown::sections()` returns one entry per h2/h3 plus a leading null-id entry for text above the first heading, skips h1 lines, ignores headings inside fenced code, and derives each id through `headingId()`, the same function `addHeadingAnchors()` uses on the rendered HTML.
- **C2** [test] `HelpSearchIndexTest` walks every document in `HelpCorpus::all()` and asserts the non-null section ids equal, in order, the `id` attributes of the rendered h2/h3 elements. It passes for all 186 documents.
- **C3** [code] `HelpCorpus::all()` splits frontmatter off before sectioning a page, and gives `index` an empty `sections` array. The frozen `help-reference-index.json` shape is untouched.
- **C4** [code] `buildHelpSearch()` indexes one MiniSearch record per section with fields `title` (intro record only, boost 3), `page` (every record, boost 1), `heading` (2.5), `slug` (2), `lead` (1.5), `text` (1), `keywords` (3); tokenizer splits on anything that is not a letter or digit; `processTerm` lowercases, drops `STOP_WORDS`, and applies `stem()`; search options are `prefix: true`, fuzzy 0.2 for terms over four characters, `bm25.b` 0.35, and a kind boost of tutorial 1.5, guide 1.25, deep dive 1.1, reference 1.
- **C5** [code] `rank()` searches with `combineWith: 'AND'` first and falls back to `'OR'` only when that is empty, and admits at most three sections of one document.
- **C6** [code] `keywordMatch()` and `sectionMatch()` are unchanged in behaviour and still compose as exact keywords, ranked hits, partial keywords, folder members.
- **C7** [code] A hit's `url` is `doc.url + '#' + section.id` when the section has an id, else `doc.url`. `useHelpReference.search()`, `ReferencePalette.vue` and `help/main.ts` all consume `HelpHit` and open that url.
- **C8** [test] `helpSearch.test.ts` pins, on a fixture shaped like the bot commands page: a word present only inside inline code in a section body is found; `!` is not part of a word; a two-word query returns only sections saying both; filler words are dropped; plural and singular match; a two-letter typo in a long word matches; one page occupies at most three slots; a section hit carries its anchor.
- **C9** [test] `helpSearch.corpus.test.ts` asserts, against the real index, that "controls", "chat", "controls chat", "enablecontrols", "twitch controls" and "bot controls" each return `bot/commands` within the first 15 hits and that "enablecontrols" lands on `/help/bot/commands#controls`. It is `describe.skipIf` on the index file being absent.
- **C10** [unverified] Against the index built from the pre-change tree, the same six queries returned nothing for four of them and a list without `bot/commands` for the other two. Measured on 2026-09-08 with the old `helpSearch.ts` bundled by esbuild; not reproducible from the committed tree without checking out the parent.
- **C11** [code] `package.json` no longer depends on `fuse.js`, and no file under `resources/js` imports it.
- **C12** [code] Verified in Chrome on `overlabels.test`: typing "controls chat" in the help page search box lists "Bot Commands › Controls", and clicking it opens `/help/bot/commands#controls` scrolled to the Controls heading.

### Unchanged
- `public/help-reference-index.json` keeps its exact key set; `HelpUnificationTest` still asserts it.
- `keywords:` frontmatter, `HelpPage::splitKeywords()` and the keyword tiering are as before. No help page content was edited.
- `help-index.json` is still fetched without `force-cache`.
- The Alt+R palette inside the app was not exercised in a browser; it shares `HelpHit`, `snippet()` and `docLabel()` with the on-page box and passes `vue-tsc`.

### Risk
The pre-change tuning note that "raid" surfaced the Random Rolls and Counters guide is not preserved: that guide never contains the word and the old hit was a one-edit fuzzy match on "rand". Natural-language questions ("how do mods change controls") are better than before but still lexical; a query whose words the answering section does not use still misses.
