## Audit of OL-2609-032 - feat(help): search ranks sections with a real term engine, and a result lands on the heading that answers

**Audited:** 2026-09-25
**Commit:** 497425c9ac612eb87ac3c756354ee7b36562077c
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/HelpMarkdown.php:371 @497425c` - `sections()`: fence toggle at :379, h1 lines skipped at :392, h2/h3 matched at :396 and id from `self::headingId()` at :399; an empty null-id entry dropped at :413; `addHeadingAnchors()` (:301) calls the same `headingId()` at :313. File unchanged @HEAD |
| C2 | CONTRADICTED | `tests/Feature/HelpSearchIndexTest.php:21-35 @497425c` asserts non-null section ids equal the rendered `<h2/h3 id>` list in order, but skips `index` at :22-24, so it compares 185 documents, not 186 (`HelpCorpus::all()` returns 186 @497425c). `php artisan test --filter=HelpSearchIndexTest` @HEAD: 7 passed. @497425c Pest could not run from a scratch worktree (namespace error on the path), so the assertion was replayed by script: 185 compared, 0 mismatches |
| C3 | CONFIRMED | `app/Support/HelpCorpus.php:174 @497425c` - `HelpMarkdown::sections(Frontmatter::split($raw)[1])`, `[]` for `index`; the `help-reference-index.json` part of `BuildHelpReferenceIndex.php` is not in the diff, and `HelpUnificationTest` (14 passed @HEAD) still asserts keys `category, categoryLabel, slug, title, body` |
| C4 | CONFIRMED | `resources/js/utils/helpSearch.ts @497425c` - `KIND_BOOST` :80-85, `FIELD_BOOST` :98-106, `stem()` :122, `STOP_WORDS` :136, `processTerm()` :175-179, `tokenize()` :188-190 (`/[^\p{L}\p{N}]+/u`), `fuzzy()` :196-198, `bm25 b: 0.35` :213, `prefix: true` :214, `title` intro-only :239, `page` on every record :235. Unchanged @HEAD |
| C5 | CONFIRMED | `resources/js/utils/helpSearch.ts:274 @497425c` - `[{ combineWith: 'AND' }, { combineWith: 'OR' }]`, OR reached only if `hits.length === 0` (:292); `MAX_HITS_PER_DOC = 3` enforced at :282. Holds for `rank()` only; see F7 for the composed list |
| C6 | CONFIRMED | `sectionMatch()` :326-332 and `keywordMatch()` :362-387 @497425c have bodies identical to :151-157 and :202-227 @497425c^; `buildHelpSearch().search` :470-475 composes exact, `rank()`, partial, `sectionMatch()` in the same order as :285 @497425c^ |
| C7 | CONFIRMED | `helpSearch.ts:257 @497425c` - `section?.id ? \`${doc.url}#${section.id}\` : doc.url`; `useHelpReference.ts:65 @497425c` returns `HelpHit[]`; `ReferencePalette.vue:22,45 @497425c` types `HelpHit[]` and opens `entry.url`; `help/main.ts:102 @497425c` renders `href` from `e.url` |
| C8 | CONTRADICTED | `resources/js/utils/helpSearch.test.ts @497425c` pins inline-code body word (:92), `!` (:98), filler words (:119), plural/singular (:127), two-letter typo (:132), three-slot cap (:153), anchor (:142). The two-word half is narrower than stated: :102-110 asserts only that hit 0 is `/help/bot/commands#controls` and that `/help/chat` and `/help/controls` are absent; it does not assert that no other section is returned. `npm test` on this file and the corpus file, @HEAD and @497425c: 44 passed each |
| C9 | CONTRADICTED | `resources/js/utils/helpSearch.corpus.test.ts:28-30 @497425c` asserts the six queries within 15 hits (true). `:36` asserts `toMatch(/^\/help\/bot\/commands#controls/)` with no end anchor; the top hit for "enablecontrols" against the index built at 497425c is `/help/bot/commands#controls-access-switch` (same @HEAD), so the test passes while the claim's url is not the one returned. `describe.skipIf(!present)` at :19 is as stated |
| C10 | UNVERIFIABLE | tagged [unverified]; a fail-first run against the parent tree |
| C11 | CONFIRMED | `package.json @497425c` and @HEAD have no `fuse.js`; `git grep fuse` under `resources/js` @497425c and @HEAD matches only `defuseBrackets`/`refuse` |
| C12 | CONTRADICTED | tagged [code] but states a Chrome session on `overlabels.test`; no code location can decide it (F4) |

### Surface
Complete.

### Findings
- **F1** test asserts less than the claim - `resources/js/utils/helpSearch.corpus.test.ts:36 @497425c` matches `#controls` as a prefix and passes on `#controls-access-switch`, which is the actual top hit for "enablecontrols" @497425c and @HEAD (`resources/help/pages/bot/commands.md:65,72 @497425c`: the `!enablecontrols` table row is under "Controls-access switch"); anchor the regex and decide which section C9 means.
- **F2** test asserts less than the claim - `tests/Feature/HelpSearchIndexTest.php:22-24 @497425c` skips `index`, so C2's "passes for all 186 documents" is 185; restate the count or say the root index is excluded.
- **F3** test asserts less than the claim - `resources/js/utils/helpSearch.test.ts:105-109 @497425c` checks the first hit and two excluded urls, not "only sections saying both" (the fixture does return exactly one url when probed); either assert `toEqual(['/help/bot/commands#controls'])` or narrow C8.
- **F4** mistagged, should be [unverified] - C12 is a browser observation tagged [code]; retag it in a new claim.
- **F5** false Unchanged line - Unchanged line 4 says the palette "shares `HelpHit`, `snippet()` and `docLabel()` with the on-page box", but `resources/js/help/main.ts:2 @497425c` imports only `buildHelpSearch, docLabel, HelpDoc, HelpSearch` and the on-page rows render no snippet; correct the record.
- **F6** scope, unclaimed behaviour - page-level fields ride only on a record whose section id is null (`helpSearch.ts:228,239-242 @497425c`), and `HelpMarkdown::sections()` drops an empty intro (`HelpMarkdown.php:413 @497425c`), so for 15 of 185 documents @497425c (`manifesto`, `why-kofi`, `blocks`, `integration-test-mode`, `tutorials/latest-donator`, and 10 more) `title` at boost 3, `slug`, `lead` and `keywords` are never indexed; probing the shipped index, "why kofi" returns no `why-kofi` hit in the top 5 and "seed practice" does not return `integration-test-mode`, whose keywords include both words. This contradicts the docblock at `helpSearch.ts:352 @497425c` ("Keywords are also an indexed field"); decide whether it is intended and claim or fix it.
- **F7** scope, unclaimed behaviour - `dedupe()` (`helpSearch.ts:435-444 @497425c`) now keys on the hit url, and a keyword/folder page hit (`doc.url`) never equals a section hit (`doc.url#id`), so one page can appear twice or more: against the shipped index "autocomplete" returns `/help/editor` and `/help/editor#tag-autocomplete`, and "test mode" returns `integration-test-mode` 4 times. Before the change `dedupe()` keyed on `doc.url` (`:249-258 @497425c^`). No claim covers it, and the fixture test "answers a keyword query the engine also finds, once" (`helpSearch.test.ts:295`) passes only because the fixture editor page has no sections.

### Notes
- Stale Fuse-era comments not in the diff: `app/Support/HelpPage.php:155 @497425c` (splitKeywords docblock) and `resources/js/help/main.ts:20 @497425c` ("The score cutoff, not this, is what bounds an ordinary query"); both still present @HEAD.
- The index shape change (`body` -> `sections`) broke search for browsers holding a cached old index; OL-2609-035 records this and added `Cache-Control: no-cache`. The Risk section here does not mention it.
- The removed dotted-root fallback (`rankedSearch()` @497425c^) is still met by the tokenizer and pinned at `helpSearch.test.ts:136`.
- Drift since 497425c in the Surface: `HelpCorpus.php` (3f1ff439, Chat Castle removal) and `help/main.ts` (55ae69c7, theme menu); neither touches a claimed symbol.
