## Audit of OL-2609-020 - feat(help): every guide, tutorial and deep dive gets its own OG card

**Audited:** 2026-09-24
**Commit:** 9a34f6bf0e7371999d9f5551337b9cece211b041
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/OgImageService.php:137-151 @9a34f6b` - `renderOrCached(..., 'og.update')`, eyebrow `mb_strtoupper(HelpCorpus::KIND_LABELS[$page['kind']] ?? ...)`, title `$page['heading']`, body `$this->bodyExcerpt($page['lead'])`; `headlineContext()` (`:159-193 @9a34f6b`) wraps the title with `UPDATE_TITLE_LINES_MAX` = 3 (`:40 @9a34f6b`). No later commit touches the file; same @HEAD (`:137`) |
| C2 | CONFIRMED | `contextForUpdate()` `:108-144 @9a34f6b^` vs `:109-125` + `headlineContext()` `:159-193 @9a34f6b` - same eyebrow, same `wrapBody(title, UPDATE_TITLE_LINE_MAX, UPDATE_TITLE_LINES_MAX)`, fallback `'Update'` passed as `$titleFallback`, same `wrapBody(plainExcerpt(UPDATE_EXCERPT_MAX), BODY_LINE_MAX, UPDATE_BODY_LINES_MAX)`, identical `bodyTop` and `url` expressions |
| C3 | CONFIRMED | `app/Http/Controllers/HelpController.php:59 @9a34f6b` - `'ogImage' => $this->og->urlForPage($page, $page['canonical'])`; `help/doc.blade.php:1 @9a34f6b` extends `layouts.help`, whose `:42-44 @9a34f6b` uses `$ogImage` when non-empty else `asset('ogimage.jpg')`. @HEAD `HelpController.php:60` still passes it, now via a `$shared` array restructured by 2ee9d622 (OL-2609-021) |
| C4 | CONFIRMED | `app/Console/Commands/OgGenerate.php:42-43 @9a34f6b` - `generatePages($og)` then `generateUpdates($og)`; `:55,62 @9a34f6b` iterate `HelpPage::all()` calling `urlForPage()` per slug; `docker/docker-entrypoint.sh:55 @9a34f6b` runs `php artisan og:generate`, not in the diff. Unchanged @HEAD |
| C5 | CONFIRMED | `tests/Feature/HelpPageOgImageTest.php:30,37-38` requests `/help/tokens`, asserts `#/og/[0-9a-f]{64}\.png$\|/ogimage\.png$#` and `not->toEndWith('/ogimage.jpg')`; `php artisan test --filter=HelpPageOgImageTest` @HEAD: passed |
| C6 | UNVERIFIABLE | `HelpPageOgImageTest.php:41-59` asserts what the claim says (`tokens` vs `conditionals`, neither `/ogimage.png`, distinct, both files exist), but `:43` skipped on this machine: "resvg is not installed on this machine." (`which resvg` finds nothing). Not run, not passed |
| C7 | CONFIRMED | `tests/Feature/UpdateSeoTest.php` not in the diff; `php artisan test --filter=UpdateSeoTest` @HEAD: 15 passed, 1 skipped ("renders a distinct card per post", resvg absent) |
| C8 | UNVERIFIABLE | tagged [unverified]; a visual render needing resvg, which is outside the repo |

### Surface
Complete.

### Findings
- **F1** test not run - C6's test `tests/Feature/HelpPageOgImageTest.php:41` ("gives two help pages two different cards") skips without resvg, so this audit could not observe it pass; run `php artisan test --filter=HelpPageOgImageTest` on a machine with resvg on PATH or `RESVG_BIN` set (the app image) to confirm C6.

### Notes
- Unchanged line 3 names `/help/gamejam`; that page was removed by OL-2609-034, so the line describes the tree as shipped only.
- The third test, "labels the card with the page kind" (`HelpPageOgImageTest.php:61-69`), asserts only `HelpPage::render(...)['kind'] === 'tutorial'` and never builds a card; no claim relies on it.
- Tests were run at HEAD, not at 9a34f6b; no commit after 9a34f6b touches `OgImageService.php`, `OgGenerate.php` or either test file. `HelpController.php` changed in 2ee9d622 (OL-2609-021).
- `OgGenerate::$description` (`:15 @9a34f6b`) still reads "help reference and every published update post" and does not mention help pages.
