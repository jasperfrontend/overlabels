## Audit of OL-2609-107 - ci(scrutinize): hang off the tests workflow, because the action refuses a push event

**Audited:** 2026-09-25
**Commit:** 580b80a34c112f5124b9be21070d8147145dfebc
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `.github/workflows/scrutinize.yml:21-35 @580b80a` - `workflow_run` with `workflows: [tests]`, `types: [completed]`, `branches: [main]`, plus `workflow_dispatch`; no `push:` key under `on:`. `.github/workflows/tests.yml:1-7 @580b80a` - `name: tests`, `on: push: branches: [develop, main]`. File absent @HEAD (OL-2609-113) |
| C2 | UNVERIFIABLE | tagged [unverified] |
| C3 | CONFIRMED | `scrutinize.yml @580b80a` - grep for `event.before`/`event.after` returns nothing; step env is `MANUAL_ID` only (line 69); line 82 `find docs/changelog/claims -mindepth 3 -maxdepth 3 -type d \| sed 's#.*/##' \| sort -r`; lines 90-91 skip folders without `claim.md` or with `audit.md` |
| C4 | CONFIRMED | `scrutinize.yml:98 @580b80a` - `[ ${#PENDING[@]} -ge 3 ] && break`; ran the loop shape under `set -euo pipefail` in bash 5.2 with one pending id and it completes (exit 0), so a sub-3 queue does not abort the step. Cap changed to 1 by OL-2609-111 |
| C5 | CONTRADICTED (compound) | First half CONFIRMED: `scrutinize.yml:82,90-91 @580b80a` - a claim's inclusion depends on folder state only, not on any run having existed. Second half ("is taken by the next run") CONTRADICTED: line 82 `sort -r` plus line 98 cap mean only the 3 newest unaudited ids are taken; the tree @580b80a holds 97 unaudited claim folders, so e.g. OL-2609-101 is not taken by the next run. For OL-2609-105 specifically it holds: the scan @580b80a yields `OL-2609-107, OL-2609-106, OL-2609-105` |
| C6 | CONFIRMED | `scrutinize.yml:34 @580b80a` - `required: false`; lines 78-83 use the id when set, else the queue scan |
| C7 | CONFIRMED | `scrutinize.yml:78-79,86-91 @580b80a` - `MANUAL_ID` becomes `CANDIDATES` and goes through the same `claim.md`/`audit.md` guards |
| C8 | CONFIRMED | `git diff 490bf64c 580b80a -- .github/workflows/scrutinize.yml` - every non-comment changed line is in the `on:` block or the `discover` step; `permissions`, `concurrency`, matrix/`max-parallel: 1`, Postgres service, toolchain, prompt, commit step and issue `if:` are identical to OL-2609-105's commit. `permissions` changed later by OL-2609-108 |
| C9 | UNVERIFIABLE | tagged [unverified]; see F3 - reproduced from `git ls-tree -r 580b80a^ docs/changelog/claims`: newest three unaudited are `OL-2609-106, OL-2609-105, OL-2609-102` |
| C10 | CONTRADICTED (compound) | First half (run 35390034009's `discover` job succeeded) UNVERIFIABLE. Second half ("the bash in this file is therefore unchanged in shape") is checkable in-repo and false: the diff @580b80a removes `BEFORE`/`AFTER`, the `RANGE` computation and the `git log --format=%B "$RANGE" \| grep -oE 'Changelog: ...'` extraction, replaces them with the `find` scan at line 82, and adds the cap at line 98 |

### Surface
Complete.

### Findings
- **F1** contradicted claim - C5's "is taken by the next run" is false in general: `scrutinize.yml:82,98 @580b80a` take only the three newest unaudited ids and the tree held 97, so a skipped older claim waits until everything newer is audited; the reader should treat C5 as true only of OL-2609-105, where the scan does reach it.
- **F2** compound claim, mistagged half - C10's second sentence ("the bash in this file is therefore unchanged in shape from a version observed working") is an in-repo statement tagged [unverified], and the diff of 580b80a contradicts it by replacing the range walk with the `find` scan (line 82) and adding the cap (line 98); the discovery bash in this commit had not been observed on a runner.
- **F3** mistagged, checkable as [code] - C9's returned list is reproducible from the tree at `580b80a^` (`git ls-tree` gives `OL-2609-106, OL-2609-105, OL-2609-102` as the newest three unaudited), so the result half should have been a [code] claim; it does match.
- **F4** contradiction with the record - `docs/changelog/claims-guide.md:237-241 @580b80a` says the workflow "runs ... on every commit in the push range that carries a `Changelog:` trailer", which this change makes false (queue scan, capped at 3), while the claim's Unchanged line about the guide mentions only the "on push to `main`" wording; the guide's discovery description went stale without disclosure (since superseded: step 3 is struck through by OL-2609-113).

### Notes
- `.github/workflows/scrutinize.yml` does not exist @HEAD; deleted by OL-2609-113 (commit e8592dca), which names this claim as reversed. All [code] verdicts above are at 580b80a.
- Later drift, all disclosed: OL-2609-108 (permissions, 32cb2009), OL-2609-111 (cap 3 -> 1, `--max-turns` 40 -> 80, commit step reworked, 5cb89e27), OL-2609-112 (285ee56d).
- No [test] claims; no tests were run. The only command executed beyond git reads was the bash `set -e` check of the cap line cited under C4.
- `workflow_run` `types: [completed]` has no `conclusion` guard, so audits also ran after a failed `tests` run; consistent with C1 as written, not a finding.
