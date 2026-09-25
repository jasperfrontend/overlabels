## Audit of OL-2609-111 - ci(scrutinize): stop throwing away a finished audit, and buy one per push instead of three

**Audited:** 2026-09-25
**Commit:** 5cb89e27721ca7be3aa15ca02d8b326b8137b8c8
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `.github/workflows/scrutinize.yml:111 @5cb89e2` - `[ ${#PENDING[@]} -ge 1 ] && break`; the diff removes `-ge 3`. File deleted @HEAD (OL-2609-113) |
| C2 | CONFIRMED | `.github/workflows/scrutinize.yml:231 @5cb89e2` - `--max-turns 80`; the diff removes `--max-turns 40`. File deleted @HEAD (OL-2609-113) |
| C3 | CONFIRMED | `.github/workflows/scrutinize.yml:245 @5cb89e2` - `if: ${{ !cancelled() }}` on `Commit the audit`; the diff adds it where there was no `if`. File deleted @HEAD (OL-2609-113) |
| C4 | CONFIRMED | `.github/workflows/scrutinize.yml:258-264 @5cb89e2` - `verdict` derived by `grep -m1 '^\*\*Verdict:\*\*' ... 2>/dev/null` and the step exits 0 with `wrote=false` when it is empty; the `[ ! -f "$path" ]` test and the `|| echo UNKNOWN` fallback are removed. File deleted @HEAD (OL-2609-113) |
| C5 | CONFIRMED | `.github/workflows/scrutinize.yml:297-300 @5cb89e2` - `git push origin HEAD:main \|\| { git pull --rebase --autostash origin main; git push origin HEAD:main; }`. Commit step later changed by OL-2609-112 (`285ee56d`, adds a `git remote set-url` before this block); file deleted @HEAD (OL-2609-113) |
| C6 | CONTRADICTED | `.github/workflows/scrutinize.yml:283 @5cb89e2` runs `git commit`; `:289 @5cb89e2` runs `git status --porcelain` AFTER it, not before committing. It does run before the push (`:297`) |
| C7 | CONFIRMED | `.github/workflows/scrutinize.yml:310 @5cb89e2` - `if: ${{ !cancelled() && steps.commit.outputs.wrote == 'true' }}`; the diff removes the bare `steps.commit.outputs.wrote == 'true'`. File deleted @HEAD (OL-2609-113) |
| C8 | CONFIRMED | Parsed the file @5cb89e2 with PyYAML, replaced every `${{ ... }}` with `STUB`, piped each `run:` to `bash -n`: all six blocks (`Find unaudited claims`, `Install Dependencies`, `Prepare Application`, `Build Assets`, `Commit the audit`, `Open an issue ...`) rc=0 |
| C9 | CONFIRMED | Ran the `:258 @5cb89e2` line verbatim against the files extracted at @5cb89e2: `OL-2609-104/audit.md` -> `CLEAN`, `OL-2609-099/audit.md` -> `FINDINGS`, a nonexistent path -> empty, `OL-2609-109/claim.md` -> empty |
| C10 | UNVERIFIABLE | tagged [unverified] (CI run log) |
| C11 | UNVERIFIABLE | tagged [unverified] (CI run log) |
| C12 | UNVERIFIABLE | tagged [unverified] (CI run log) |
| C13 | UNVERIFIABLE | tagged [unverified] (billing, CI history) |
| C14 | UNVERIFIABLE | tagged [unverified]; mistagged in part, see F4 - `git ls-tree -r 5cb89e2` lists `bootstrap/cache/packages.php` and `bootstrap/cache/services.php` as tracked |

### Surface
Complete.

### Findings
- **F1** claim contradicted - C6 says `git status --porcelain` is printed "before committing", but `.github/workflows/scrutinize.yml:289 @5cb89e2` follows `git commit` at `:283 @5cb89e2`; the reader should take OL-2609-112 C9 (which restates this) as the record, and a remedy should cite it.
- **F2** reverses an earlier claim without citing it - C1 lowers the queue cap to 1, reversing OL-2609-107 C4 ("The scan is capped at 3 ids per run", `docs/changelog/claims/2026/09/OL-2609-107/claim.md:19`), and neither the claim nor its Surface line cites OL-2609-107; OL-2609-112 C11 later records the citation.
- **F3** reverses an earlier claim without citing it - C5 moves the push ahead of the rebase, reversing OL-2609-105 C10 ("The commit step pushes with `git pull --rebase origin main` before `git push`", `docs/changelog/claims/2026/09/OL-2609-105/claim.md:23`), without citing OL-2609-105; OL-2609-112 C11 later records the citation.
- **F4** mistagged, checkable as [code] - C14 asserts in-repo facts under [unverified]: that `resources/js/ziggy.js`, `public/build/` and `.env` are untracked (true @5cb89e2 by `git ls-tree`), and that which tracked file dirties the tree "is not established", while the tree @5cb89e2 tracks `bootstrap/cache/packages.php` and `bootstrap/cache/services.php`, which `composer.json:55 @5cb89e2` regenerates via `@php artisan package:discover` on the workflow's `composer install` (`scrutinize.yml:194 @5cb89e2`); OL-2609-112 C10 restates this, and a remedy should cite it.

### Notes
- `.github/workflows/scrutinize.yml` does not exist @HEAD: deleted by OL-2609-113 (`e8592dca`), which names OL-2609-111 among the claims it reverses. All [code] drift above is disclosed by that claim or by OL-2609-112 (`285ee56d`).
- No `remedy.md` exists in this folder; F1-F4 are recorded by OL-2609-112 C9-C11 but not remedied (OL-2609-112 Unchanged says so), and OL-2609-113 Unchanged says issue #335 still carries them.
- `CLAUDE.md @HEAD` now says a CI version of Sally "must not be built"; that rule was added by OL-2609-113 after this commit and is not a contradiction of it.
- No [test] claims; no tests run. C8 and C9 were checked with a scratch script, not a repo test.
