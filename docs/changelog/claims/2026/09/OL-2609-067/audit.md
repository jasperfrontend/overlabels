## Audit of OL-2609-067 - docs(help): document the color control

**Audited:** 2026-09-25
**Commit:** f7e137cdaff1707f0122890e06120e90a212b89f
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/help/pages/controls.md:186 @f7e137c` - `### color` sits between `### datetime` (:172) and `### expression` (:228); the h3s under `## Control Types in Detail` (:37) are text, number, counter, timer, boolean, datetime, color, expression, list writer = nine. Same line numbers @HEAD |
| C2 | CONFIRMED | `resources/help/pages/controls.md:33 @f7e137c` - `` | `color` | `` row in the type table; `:529 @f7e137c` - `| Color |` row under `### How each type works` (:520) |
| C3 | CONFIRMED | `resources/help/pages/controls.md:206 @f7e137c` - "nothing is validated"; :211-215 list the five values. `tests/Feature/ColorControlTest.php:22-29 @f7e137c` dataset holds the same five shapes plus `transparent`; the oklch literal differs (`oklch(0.7 0.15 30)` in the test, `oklch(0.7 0.19 145)` in the doc), which "same shapes" allows |
| C4 | CONFIRMED | `resources/help/pages/controls.md:485 @f7e137c` - `[color control](#color)`; `app/Support/HelpMarkdown.php:338-351 @f7e137c` - `headingId()` is `Str::slug(...)` with a `-N` suffix only on collision, and no earlier heading in the page slugs to `color`. Unchanged @HEAD |
| C5 | CONTRADICTED (half) | First half CONFIRMED: `resources/help/pages/controls.md:9 @f7e137c` is `keywords: colour, palette, dynamic styling`. Second half false: "dynamic styling" appears in the body at `:12 @f7e137c` ("possibilities for dynamic styling") and across the line break at `:484-485` ("opens up dynamic / styling"). `colour` and `palette` do not appear in the body |
| C6 | CONFIRMED | `git show f7e137c -- resources/help/pages/controls.md` - the only frontmatter hunk adds line 9 `keywords:`; `title`, `description`, `heading`, `lead`, `section: Live data`, `canonical`, `context: settings.controls, controls.index` are untouched |
| C7 | CONFIRMED | `php artisan test --filter=Help` @HEAD: 73 passed, 1 skipped. `HelpSearchIndexTest.php:14 @f7e137c` ("gives every search section the id its heading renders with") and `HelpContextTest.php:28,42,44 @f7e137c` (40/320 caps) exist as described. Not run @f7e137c (see Notes) |

### Surface
Complete.

### Findings
- **F1** Contradicted claim - C5 says none of the three keywords appears in the page body, but "dynamic styling" is in the body at `resources/help/pages/controls.md:12 @f7e137c` (and :484-485, still true @HEAD); a new claim should restate C5, and the term should be dropped from `keywords:` if F2 is accepted.
- **F2** Contradicts CLAUDE.md - adding `dynamic styling` to `keywords:` at `resources/help/pages/controls.md:9 @f7e137c` goes against CLAUDE.md "Help Documentation": "`keywords:` is for words a page is ABOUT but never SAYS ... a word the page uses is found without one"; the page already says it at :12, so the keyword is padding; remove it or record why it stays.

### Notes
- C7 was run at HEAD only. A worktree at f7e137c failed to boot against the current `vendor/` (`Stevebauman\Location\LocationServiceProvider` not found, a package removed later by OL-2609-097), so the 73-test count at the shipped commit was not reproduced. `HelpContextTest.php` has changed by one line since then.
- `controls.md` has drifted since the shipped commit only via 64a7e3c (OL-2609-074, Streamlabs spelling, lists `controls.md` in its Surface); no claim here is affected.
- Shipped prose at `resources/help/pages/controls.md:207-208 @f7e137c` names four formats (hex, `rgb()`, `hsl()`, `hsb()`) and then says "those are the three it can show you". No claim covers it; still present @HEAD.
- Unchanged lines all verified: `.gitignore:10-11 @f7e137c` are the two index JSONs; `git grep 'list writer' f7e137c -- resources/help/` matches only `controls.md`; `OverlayControl.php:182 @f7e137c` puts `color` in the `strip_tags` arm with `text`; `index.md` is not in the diff.
