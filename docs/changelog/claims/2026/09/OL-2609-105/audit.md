## Audit of OL-2609-105 - ci(scrutinize): run Sally on every pushed claim, advisory and quiet

**Audited:** 2026-09-25
**Commit:** 490bf64c0c992b9dd058e652e3c01d007dcd0720
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `.github/workflows/scrutinize.yml:15-17 @490bf64c` - `on: push: branches: [main]` only; `grep -n -i 'scrutin\|needs\|workflow_run' .github/workflows/deploy.yml @490bf64c` matches no job dependency (one comment line, 75, unrelated). @HEAD the file is deleted (OL-2609-113); trigger changed earlier by OL-2609-106/107 |
| C2 | CONFIRMED | `scrutinize.yml:50-52,68-71 @490bf64c` - `git log --format=%B "$RANGE"` over `$BEFORE..$AFTER`, `grep -oE 'Changelog: OL-[0-9]{4}-[0-9]{3}'`, `sort -u`; `:80-88` skips an id with no `claim.md` or with an `audit.md` |
| C3 | CONFIRMED | `scrutinize.yml:59-60 @490bf64c` - `if [ -z "${BEFORE//0/}" ] \|\| ! git cat-file -e "$BEFORE^{commit}"` sets `RANGE="$AFTER~1..$AFTER"` |
| C4 | CONFIRMED | `scrutinize.yml:114-118 @490bf64c` - `max-parallel: 1`, `matrix: id: ${{ fromJSON(needs.discover.outputs.ids) }}`; one `claude-code-action` step per leg at `:188-189` |
| C5 | CONFIRMED | `scrutinize.yml:111 @490bf64c` - `if: needs.discover.outputs.ids != '[]'`; discover writes `ids=[]` when nothing is pending (`:91-94`) |
| C6 | CONTRADICTED (second half) | First half CONFIRMED: `scrutinize.yml:123-186 @490bf64c` provisions `postgres:16`, PHP `'8.4'`, node `'22'`, `composer install`, `npm ci`, `key:generate`, `ziggy:generate`, `migrate --force`, `npm run build`. Second half false: the two tests that inspect the built assets and skip without it, `tests/Feature/DevToolsExcludedFromBuildTest.php:42 @490bf64c` and `tests/Feature/EmoteLibraryMinificationTest.php:53 @490bf64c`, were added 2026-08-16 (`85b3a17b`, `0b64ff76`) and not touched again before 490bf64c - they are not September tests |
| C7 | CONFIRMED | `scrutinize.yml:195-206 @490bf64c` - prompt says "Read `.claude/agents/sally.md` ... and act as that agent" for `${{ matrix.id }}`; no restatement of her rules |
| C8 | CONFIRMED | `scrutinize.yml:205-206 @490bf64c` - "Do not commit it and do not stage it; this workflow commits."; commit step `:211-249` |
| C9 | CONFIRMED | `scrutinize.yml:244 @490bf64c` - `git commit -m "docs(sally): audit of $ID - $verdict [skip ci]"`; `deploy.yml:3-5 @490bf64c` fires on `push` to `main` |
| C10 | CONFIRMED | `scrutinize.yml:248-249 @490bf64c` - `git pull --rebase origin main` then `git push origin HEAD:main`; no `--force` in the file. Push mechanism later changed by OL-2609-112 |
| C11 | CONTRADICTED (first half) | `scrutinize.yml:265 @490bf64c` - `contradicted=$(grep -c 'CONTRADICTED' "$AUDIT")` counts the word on ANY line of the audit (Findings, Notes, prose), not a verdict; the claim's "only when the audit contains a `CONTRADICTED` verdict" is not what the code tests. Surface half CONFIRMED (`:267`, `sed -n '/^### Surface/,/^### /p' \| grep -cE 'Undisclosed:\|Phantom:'`), and "FINDINGS alone does not open one" CONFIRMED (`:269-272`, verdict is not read) |
| C12 | CONFIRMED | `scrutinize.yml:280 @490bf64c` links the audit, `:282` "She is advisory ... prod has already deployed", `:283` names `/remy $ID`; `:292-294` `gh label create ... \|\| true`; `:296-300` retries `gh issue create` without `--label` |
| C13 | CONFIRMED | `scrutinize.yml:22-24 @490bf64c` - `contents: write`, `issues: write` only; `.github/workflows/lint.yml:15-16 @490bf64c` is `contents: read` and not in the diff. Permissions later widened by OL-2609-108 |
| C14 | UNVERIFIABLE | tagged [unverified] |
| C15 | UNVERIFIABLE | tagged [unverified] |
| C16 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** Contradicted claim - C6 calls the two built-asset tests "September tests", but `DevToolsExcludedFromBuildTest.php` and `EmoteLibraryMinificationTest.php` were both added 2026-08-16 (`85b3a17b`, `0b64ff76`); the same false dating is in the comment at `scrutinize.yml:181 @490bf64c` ("Two [test] claims in September"). A new claim should restate the reason with the correct provenance.
- **F2** Contradicted claim - C11 says an issue opens only on a `CONTRADICTED` verdict, but `scrutinize.yml:265 @490bf64c` greps the whole audit for the bare word, so any audit mentioning it in Findings or Notes with no CONTRADICTED row would open an issue. No audit existing at 490bf64c triggers this gap (checked: none has the word outside a table row). A new claim should state the condition the code actually tests.
- **F3** Scope - `scrutinize.yml:26-30 @490bf64c` adds a workflow-level `concurrency: group: scrutinize, cancel-in-progress: false`, which serializes runs across pushes; no claim, Surface line or Unchanged line mentions it. Its behaviour for queued runs should be stated in a claim.
- **F4** Scope - `scrutinize.yml:208 @490bf64c` caps the action at `--max-turns 40`, a limit that ends an audit early with no `audit.md`; C14 names `claude_args` but not this cap, and the Risk section gives only `--allowedTools` as a cause of a missing audit.
- **F5** Scope - `scrutinize.yml:113 @490bf64c` sets `timeout-minutes: 30` on the audit job, and `:116` sets `fail-fast: false` on the matrix; neither is claimed, and both decide whether a claim in a multi-claim push gets audited.

### Notes
- No `[test]` claims; no tests were run.
- The commit message of 490bf64c contains the literal skip token (its fifth paragraph), so no workflow, this one included, ran on that commit. The claim does not mention it; OL-2609-106 (`44f6a8f5`) and the current `claims-guide.md` record it.
- `scrutinize.yml` changed under OL-2609-106, 107, 108, 111, 112 and was deleted by OL-2609-113 (`e8592dca`); every [code] claim above holds at 490bf64c only. All of that drift is disclosed by those later claims.
- The workflow's premise is reversed at HEAD: `CLAUDE.md @HEAD` says "There is no CI for her, and a new one must not be built (removed 2026-09-19, OL-2609-113)". That is a later decision, not a contradiction by this change; at 490bf64c `claims-guide.md` step 3 described this workflow.
