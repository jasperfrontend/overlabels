## OL-2609-111 - ci(scrutinize): stop throwing away a finished audit, and buy one per push instead of three

**Shipped:** 2026-09-19
**Commit:** `git log --grep=OL-2609-111`

The first run that reached the model completed two audits and landed neither. Both were lost after
Sally had finished writing, by the workflow around her rather than by anything she did.

### Surface
- `.github/workflows/scrutinize.yml` - `discover` caps the queue at 1; `--max-turns` 40 to 80; the commit step runs on a failed audit step, gates on a verdict line, and pushes before it rebases; the issue step matches the commit step's condition

### Claims
- **C1** [code] `discover` breaks at `[ ${#PENDING[@]} -ge 1 ]`. It was 3, so a push now queues one audit rather than three.
- **C2** [code] `claude_args` sets `--max-turns 80`. It was 40.
- **C3** [code] The `Commit the audit` step carries `if: ${{ !cancelled() }}`. It previously carried no `if`, which defaults to `success()`, so it was skipped whenever the audit step failed.
- **C4** [code] That step now derives `verdict` first and exits early when it is empty, rather than testing for the file. A run cut off mid-write leaves a file on disk, and an audit with no `**Verdict:**` line is no longer committed as though it were a finding.
- **C5** [code] The step runs `git push origin HEAD:main` first and only rebases if that push fails. The rebase carries `--autostash`.
- **C6** [code] The step prints `git status --porcelain` before committing, so the next failure of this kind names the file that dirtied the tree instead of leaving it to be guessed.
- **C7** [code] The issue step's condition is `${{ !cancelled() && steps.commit.outputs.wrote == 'true' }}`. It was `steps.commit.outputs.wrote == 'true'`, which is implicitly `success() && ...` and so was skipped for the same reason as C3.
- **C8** [code] Every `run:` block in the file parses under `bash -n` after GitHub expressions are stubbed out.
- **C9** [code] Run the C4 snippet against `docs/changelog/claims/2026/09/OL-2609-104/audit.md`, `.../OL-2609-099/audit.md`, a path that does not exist, and `.../OL-2609-109/claim.md`: the first two yield `CLEAN` and `FINDINGS`, the last two yield an empty string.
- **C10** [unverified] Run 35465028611, job 105959220947 (OL-2609-108): the audit step SUCCEEDED, the commit was created (`[main 94103622] docs(sally): audit of OL-2609-108 - FINDINGS [skip ci]`, 1 file changed, 28 insertions), and the step then exited 128 on `git pull --rebase origin main` with `error: cannot pull with rebase: You have unstaged changes.` The audit was never pushed.
- **C11** [unverified] Same run, job 105959220997 (OL-2609-109): `Claude reported a successful result after 41 turns, exceeding the configured maximum of 40`. The job summary records `Commit the audit` as skipped.
- **C12** [unverified] Same run, job 105959220994 (OL-2609-107): `The operation was canceled.` That job was cancelled by hand and is not a failure of this workflow.
- **C13** [unverified] That run cost about $5 and committed no audit. It is the first run of this workflow to reach the model at all; the four before it failed on the push event, on OIDC permissions, on the Claude Code GitHub App not being installed, and on the API account having no credit.
- **C14** [unverified] Which tracked file dirties the working tree is not established. The job runs `composer install`, `npm ci`, `php artisan key:generate`, `php artisan ziggy:generate`, `php artisan migrate --force` and `npm run build` before the audit, and `resources/js/ziggy.js`, `public/build/` and `.env` are all untracked, so none of those is the one. C6 exists to answer this on the next run; C5 makes the answer unnecessary.

### Unchanged
- `.claude/agents/sally.md` is not in the diff. What she reads, what she is allowed to write and how she reaches a verdict are exactly as they were; both losses happened after her output was complete, so nothing about the agent was implicated in either.
- `[skip ci]` on the audit commit is unchanged. It is what stops `deploy.yml` redeploying prod on every audit and what stops this workflow retriggering on its own commit, and the commit step's new `if` does not reach it.
- The advisory stance is unchanged. This workflow is still not a `needs:` of `deploy.yml`, a red audit still blocks no release, and a job whose audit step failed still reports failure - the new `if` changes what is kept, not what is reported.
- The trigger block, `permissions`, the `scrutinize` concurrency group, the postgres service and the PHP/Node/build setup steps are not in the diff. The failures were in what the job did with a finished audit, not in reaching one.

### Risk
- An audit that finished inside a job that then failed now lands on `main` anyway. That is the intent of C3, and C4 is what keeps a half-written one out.
- The queue drains at one per push instead of three, so a 100-deep backlog takes proportionally longer. The cap is one character and the queue carries no state, so raising it once a run completes end to end costs nothing.
