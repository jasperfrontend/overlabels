## Audit of OL-2609-106 - ci(scrutinize): a manual trigger, because a commit that names the skip token runs nothing

**Audited:** 2026-09-25
**Commit:** 44f6a8f514426bdcb1ee5cbbc6b9445d1a4181ba
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `.github/workflows/scrutinize.yml:24-29 @44f6a8f5` - `workflow_dispatch: inputs: id: required: true, type: string`; `:16-17 @44f6a8f5` `push: branches: [main]` is a context line in `git show 44f6a8f5`, not a changed one. @HEAD the file is deleted (OL-2609-113) |
| C2 | CONFIRMED | `scrutinize.yml:65 @44f6a8f5` - `MANUAL_ID: ${{ inputs.id }}`; `:69-73` `if [ -n "${MANUAL_ID:-}" ]` sets `IDS="$MANUAL_ID"`; `BEFORE`, `RANGE` and the `git log` walk are only in the `else` branch `:74-91` |
| C3 | CONFIRMED | `scrutinize.yml:93-109 @44f6a8f5` - the `claim.md`-missing (`:100`) and `audit.md`-present (`:104`) skips sit after the `fi` at `:91`, so both branches pass through them |
| C4 | CONFIRMED | `git diff 490bf64c 44f6a8f5 -- .github/workflows/scrutinize.yml` has exactly two hunks: the `on:` block (`@@ -15,6 +15,18`) and the discover step's env/run (`@@ -50,25 +62,33`). Matrix, `max-parallel: 1`, services, toolchain, prompt, commit step and issue step are outside both hunks |
| C5 | CONFIRMED | `docs/changelog/claims-guide.md:245-251 @44f6a8f5` - "Never write the literal skip-CI token in a commit message", "runs no workflows at all ... no error anywhere", "The commit that introduced `scrutinize.yml` did exactly that", "`scrutinize.yml` has a `workflow_dispatch` input ... the way back". @HEAD the closing sentence points at a manual re-run instead (OL-2609-113 C6) |
| C6 | UNVERIFIABLE | tagged [unverified] |
| C7 | UNVERIFIABLE | tagged [unverified]; see F2 |

### Surface
Complete.

### Findings
- **F1** Contradiction with the record - OL-2609-105 C1 records "The workflow triggers only on `push` to `main`", and this change adds a second trigger (`scrutinize.yml:24-29 @44f6a8f5`) without citing OL-2609-105 C1 inline (C1 here says only "alongside the existing `push` trigger"); the same diff also left the closing comment at `scrutinize.yml:330 @44f6a8f5` saying "it runs only on push to main". A new claim should state "reverses OL-2609-105 C1". The comment needs no fix because the file is deleted @HEAD (OL-2609-113).
- **F2** Mistagged, checkable as [code] - C7 describes the output of the discover shell, which is in the repo (`scrutinize.yml:66-119 @44f6a8f5`), run with `MANUAL_ID=OL-2609-105` against a tree that still exists (`44f6a8f5`, where `OL-2609-105/` has `claim.md` and no `audit.md`). That can be re-run from the repo alone, so it is not the "tree that no longer exists" case the guide allows for `[unverified]`. A new claim should either re-tag it `[code]` or state the result as a [code] claim about `:69-73` and `:100-108`.

### Notes
- No `[test]` claims; no tests were run.
- @44f6a8f5 the guide contradicts itself: the new paragraph says the workflow file is "the only place" the literal token belongs, while step 3 of the same file (`claims-guide.md:239 @44f6a8f5`, pre-existing) prints it literally. Step 3 was rewritten by OL-2609-113 and @HEAD the guide no longer contains the literal.
- The comments that describe discover as walking "the push range" (`scrutinize.yml:46,60 @44f6a8f5`) were not updated for the manual branch. The file is deleted @HEAD (OL-2609-113).
- `scrutinize.yml` changed again under OL-2609-107, 108, 111 and 112 and was deleted by OL-2609-113 (`e8592dca`); every [code] claim above holds at 44f6a8f5 only. All of that drift is disclosed by those claims.
- The Unchanged line's side assertion that OL-2609-105 C9 still holds is true at `scrutinize.yml:264 @44f6a8f5` (`... - $verdict [skip ci]`). The commit message of 44f6a8f5 does not contain a literal skip token.
