## Audit of OL-2609-021 - feat(help): the help site gets its own design and a seven-section guide taxonomy

**Audited:** 2026-09-24
**Commit:** 2ee9d622872e5a1518da7479e1fc4eab2495692b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/HelpCorpus.php:81-89 @2ee9d62` - `SECTIONS` keys in the stated order, each with a non-empty description string; same at `:81 @HEAD` |
| C2 | CONFIRMED | `git show 2ee9d62:<file>` for every file under `resources/help/pages/`: all 33 files outside `index.md`, `tutorials/` and `deep-dives/` carry a `section:` line naming a `SECTIONS` key; the 7 tutorial / deep-dive files and `index.md` carry none |
| C3 | CONFIRMED | `app/Support/HelpCorpus.php:231-265 @2ee9d62` - one entry per `SECTIONS` key, extras appended at `:245`, sorted at `:261`; `sortByIndex()` `:292-302` sorts on `[$order[url] ?? PHP_INT_MAX, title]`; `indexOrder()` `:310-327` records the first match of `#\]\((/help[^)\s]*)\)#`. Unchanged in logic @HEAD (`:237`) |
| C4 | CONFIRMED | `app/Support/HelpCorpus.php:101-108 @2ee9d62` - exactly `/help/integration-presets` under `Live data` and `/help/gamejam` under `Bot & chat`. @HEAD only the `Live data` entry remains (OL-2609-034) |
| C5 | CONFIRMED | `app/Http/Controllers/HelpController.php:67-78 @2ee9d62` returns `help.landing`; `resources/views/help/landing.blade.php @2ee9d62` never references `$html`; `markdown()` `:99-116 @2ee9d62` returns `file_get_contents($path)` and is not in the diff. Same @HEAD |
| C6 | CONFIRMED | `app/Http/Controllers/HelpController.php:129-170 @2ee9d62` - guide uses `sectionOf()` (`:134`), others `ordered($kind)` (`:141`); related sliced to `RELATED_LIMIT` (`:31`, value 3) at `:169`, excluding prev and next. Same @HEAD |
| C7 | CONFIRMED | `app/Support/HelpNav.php:48,62,66 @2ee9d62` open only where `$activeKind` matches; Reference row `:69` is `false`; section `open` at `:58` compares the active section label; `referenceGroups()` open at `:88` is `category === $activeCategory`. Same @HEAD |
| C8 | CONFIRMED | `app/Support/HelpPage.php:232-236 @2ee9d62` - `str_word_count(strip_tags($body))`, `max(1, (int) ceil($words / 200))`; `$body` is the post-frontmatter markdown (`render()` splits frontmatter first). Same @HEAD |
| C9 | CONFIRMED | `resources/views/layouts/help.blade.php:86-87 @2ee9d62` includes `help._search` inside `@unless ($isLanding)`; `landing.blade.php:15 @2ee9d62` includes it in the hero; `id="help-search"` occurs only in `_search.blade.php:15 @2ee9d62` |
| C10 | CONFIRMED | `resources/views/help/_tree.blade.php:34,44 @2ee9d62` emit `data-help-active aria-current="page"` on the active item; `resources/js/help/main.ts:425-431 @2ee9d62` `scrollActiveIntoView()` queries `[data-help-active]` and has no hunk in the diff |
| C11 | CONFIRMED | `resources/js/help/main.ts @2ee9d62` contains no `help-nav-tree` (the diff removes it); `wireSearch()` `:53-108` renders into `#help-search-results` and bails unless `input.closest('.help-search')` exists |
| C12 | CONFIRMED | `resources/css/help.css:35-44 @2ee9d62` is the only gradient, reproducing the backdrop already in `resources/css/app.css:224 @2ee9d62~1`; `.help-pill--*` `:170-184` are `bg-*/10` tints plus text colour |
| C19 | CONFIRMED | `resources/css/help.css:152-155 @2ee9d62` - `.help-card` is `border border-sidebar-border` and `background` only; every `<a>` in `landing.blade.php @2ee9d62` (`:33,46,77,95`) carries `help-doc-link--row` |
| C13 | CONFIRMED | `tests/Feature/HelpTaxonomyTest.php @2ee9d62` - section declared `:14-25`, none on tutorials/deep dives `:27-35`, non-empty and constant order `:37-46`, landing links every `docs()` url (non-reference) and every anchor `:80-101`, index.md heading equals section `:103-142`. Ran at @2ee9d62 and @HEAD: pass |
| C14 | CONFIRMED | `HelpTaxonomyTest.php:144-166 @2ee9d62` asserts prev and next hrefs and the literal `Related docs` heading on `/help/integration-test-mode` (it does not assert which pages are related); `:168-176` the open/closed `<details>` on `/help/bot/aliases`; `:178-183` `readingMinutes` for `''`, 199 and 201 words. Pass |
| C18 | CONFIRMED | `HelpTaxonomyTest.php:55-58 @2ee9d62` asserts all three firsts. The test does not assert the "differs from alphabetical" half; checked by hand against `heading:` @2ee9d62: alphabetical firsts would be `How an overlay renders`, `Bot Aliases`, `Latest donator from any source`. Pass |
| C15 | CONFIRMED | `git show --stat 2ee9d62` touches only `HelpPageTest.php` (the `index` skip, `:71`) among the named tests. `php artisan test --filter='HelpTaxonomyTest\|HelpPageTest\|HelpUnificationTest\|HelpContextTest\|HelpPageOgImageTest\|LlmsTxtDiscoverabilityTest'`: 68 passed, 1 skipped (resvg absent) on a snapshot of @2ee9d62 and on @HEAD |
| C16 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** contradiction with the record - `CLAUDE.md:505 @2ee9d62` (still `:702 @HEAD`) says "A test asserts, for every page of every kind, that the whole rendered body appears byte-for-byte in the response", but this commit makes that loop skip `index` (`tests/Feature/HelpPageTest.php:71 @2ee9d62`) and did not amend the line, although it edited CLAUDE.md around it; amend that bullet to name the `index` exception and `HelpTaxonomyTest` as its cover.
- **F2** false Unchanged line - Unchanged says `HelpPage::render()['html']` "is byte-identical for every page", but the diff rewrites the body of `resources/help/pages/index.md`, so `render('index')['html']` changes; the statement holds only for pages whose change is frontmatter-only. Restate it with that exception in a later claim.
- **F3** Surface misdescribes - `resources/help/pages/bot/{aliases,commands,index,random-and-counters}.md` are listed as "gains `section: Bot & chat`", but each diff hunk replaces an existing `section: Bot` line (`-section: Bot` / `+section: Bot & chat`); the 2 +/- lines in the stat show it. Record that these four already had a `section:` key.
- **F4** scope - `resources/css/help-prose.css:21,29 @2ee9d62` also changes the h2 top margin `mt-14` -> `mt-11` and the h3 `scroll-mt-24` -> `scroll-mt-6`; the Surface line names only the h2 rule and h2 `scroll-mt-6`. Disclose in a later claim.
- **F5** scope - `resources/js/help/main.ts:401 @2ee9d62` changes the `showToast()` background from `bg-card` to `bg-popover`, which no Surface line or claim mentions (it affects every help-page toast, including the pre-existing code-copy ones).
- **F6** scope - `resources/js/help/main.ts:163 @2ee9d62` adds `input.scrollIntoView({ block: 'center', behavior: 'smooth' })` when a `[data-help-search]` category button is clicked; not in the Surface description of `main.ts` or any claim.

### Notes
- C4 and the `/help/gamejam` half of the third Unchanged line were superseded by OL-2609-034 (Chat Castle removed; `SECTION_EXTRAS` and `HelpTaxonomyTest` updated there).
- The Surface's "Kits link" in `layouts/help.blade.php` was removed by 55ae69c7, disclosed by OL-2609-091.
- `HelpCorpus::all()` gained a `sections` key in OL-2609-032; no claim here asserts that shape.
- Tests at the shipped commit were run on a `git archive` copy in the scratchpad; stale committed `bootstrap/cache/*.php` and `config/location.php` (packages removed from vendor since) had to be deleted from that copy to boot. The repo was not touched.
- `HelpPageOgImageTest`'s PNG case skipped both times because resvg is not installed here.
