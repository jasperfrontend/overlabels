## OL-2609-105 - ci(scrutinize): run Sally on every pushed claim, advisory and quiet

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-105`

Step 3 of the build order in `docs/changelog/claims-guide.md`, "Bringing her to life". Steps 1 and 2
are done: the `/sally` skill shipped 2026-09-17 and the prompt was calibrated across OL-2609-095
through 104, where three audits found false lines in claims their author was confident about.

### Surface
- `.github/workflows/scrutinize.yml` - new file, two jobs: `discover` and `audit`

### Claims
- **C1** [code] The workflow triggers only on `push` to `main`. It is not a `needs:` of `deploy.yml`, does not appear in `deploy.yml`, and nothing in `deploy.yml` waits on it, so a red audit cannot block a release.
- **C2** [code] `discover` walks `${{ github.event.before }}..${{ github.event.after }}` for commit messages matching `Changelog: OL-[0-9]{4}-[0-9]{3}`, dedupes, and emits only the IDs whose folder has a `claim.md` and no `audit.md`.
- **C3** [code] When `before` is all zeros or not a reachable commit, the range falls back to `$AFTER~1..$AFTER` rather than walking the whole history, so a force push or a new branch cannot re-audit every claim in the repo.
- **C4** [code] `audit` is a matrix over the discovered ids with `max-parallel: 1`, so each claim gets its own process and its own action invocation. A single run handed several ids would carry one claim's context into another's audit, which is the failure mode the cold rule exists to prevent.
- **C5** [code] The `audit` job runs `if: needs.discover.outputs.ids != '[]'`, so a push with no claim, or with only already-audited claims, starts no job.
- **C6** [code] The job provisions PHP 8.4, node 22, a throwaway Postgres 16 service, `composer install`, `npm ci`, `key:generate`, `ziggy:generate`, `migrate --force` and `npm run build`, mirroring `tests.yml`. Sally's definition requires her to RUN a `[test]` claim's test, and two September tests inspect the built assets and skip when `public/build` is absent.
- **C7** [code] The prompt does not restate Sally. It instructs the action to read `.claude/agents/sally.md` and act as that agent for one id, so the workflow and the local `/sally` skill run the same definition and tuning her stays one file.
- **C8** [code] The prompt tells the action not to commit or stage; the workflow's own step commits, matching the agent definition's rule that the spawning session commits.
- **C9** [code] The commit message ends with `[skip ci]`. `deploy.yml` fires on every push to `main`, so without it each audit would redeploy production, and this workflow would retrigger itself on its own commit.
- **C10** [code] The commit step pushes with `git pull --rebase origin main` before `git push`, never `--force`, so a commit that landed while the audit ran is not lost.
- **C11** [code] An issue is opened only when the audit contains a `CONTRADICTED` verdict or the `### Surface` section contains `Undisclosed:` or `Phantom:`. A `FINDINGS` verdict alone does not open one, which is what "quiet otherwise" means in the guide.
- **C12** [code] The issue body links the audit file, states that she is advisory and that prod has already deployed, and names `/remy <ID>` as the fix path. The `scrutinize` label is created idempotently and the issue is created without it if that fails, so a missing label cannot swallow the report.
- **C13** [code] `permissions` is `contents: write` and `issues: write` only. `lint.yml` remains `contents: read` and is not in this diff.
- **C14** [unverified] The action reference `anthropics/claude-code-action@v1` and the input names `anthropic_api_key`, `prompt`, `claude_args` were read from the action's own `docs/usage.md` on 2026-09-18, not from a run. The `--allowedTools` spelling inside `claude_args` is unconfirmed against that version.
- **C15** [unverified] `gh secret list` on 2026-09-18 returned no `ANTHROPIC_API_KEY`, so the audit step cannot authenticate until that secret is added.
- **C16** [unverified] The `discover` shell was run locally against four real ranges on 2026-09-18: `e679e8f8..b7975a0d` gave `[]` (OL-2609-104 already audited), `693d8538..1b641c7c` gave `["OL-2609-098"]`, an all-zeros `before` gave the same, and `a2b7e77f..6790c532` gave `["OL-2609-095","OL-2609-098"]`, skipping the three already audited. The verdict and issue-trigger shell was run against the four existing audits: 104 CLEAN stayed quiet, 103, 097 and 096 each triggered, and every `CONTRADICTED` match was a verdict-table row rather than prose.

### Unchanged
- `.github/workflows/deploy.yml` is not in this diff. The advisory decision is that nothing about deployment learns this workflow exists; adding a `needs:` later would be the gate this arrangement rejects.
- `.github/workflows/lint.yml` is not in this diff, and its note on why it has no commit-back still stands. Its objections are about a job that rewrites source and pushes it, runs on fork pull requests with no write token, and retriggers CI. This workflow writes one new `audit.md`, runs only on push to `main`, and carries `[skip ci]`; the closing comment in the new file states that distinction rather than leaving it to be rediscovered.
- `.claude/agents/sally.md` is not in this diff. C7 points the workflow at it precisely so that automating her is not also editing her.
- `docs/changelog/claims-guide.md` is not in this diff. Step 3 of its build order describes this workflow; nothing in the guide changed to accommodate it.

### Risk
The audit step fails until `ANTHROPIC_API_KEY` is added to the repository secrets. The failure is a
red `scrutinize` job and nothing else: `deploy.yml` is unaffected and prod still ships.

First real run is the verification of C14. If the action rejects `--allowedTools`, Sally will have no
tools and the job will produce no `audit.md`, which the commit step reports as a warning rather than
a failure.

A push carrying several unaudited claim trailers runs one action invocation per claim, sequentially,
each installing the full PHP and node toolchain. That is minutes and API spend per claim, which is
the cost of each one being genuinely cold.
