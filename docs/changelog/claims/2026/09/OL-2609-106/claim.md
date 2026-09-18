## OL-2609-106 - ci(scrutinize): a manual trigger, because a commit that names the skip token runs nothing

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-106`

OL-2609-105 shipped `scrutinize.yml` and then did not run it, or anything else. Its commit message
explains the skip-CI token in prose, GitHub scans the whole message including the body, and the push
produced zero workflow runs: no deploy, no tests, no linter, no audit, and no error to say so.

### Surface
- `.github/workflows/scrutinize.yml` - `workflow_dispatch` trigger with a required `id` input; the discover step branches on it instead of walking the push range
- `docs/changelog/claims-guide.md` - the skip-token warning, and step 3 of the build order marked built

### Claims
- **C1** [code] `scrutinize.yml` declares `workflow_dispatch` with a required string input `id` alongside the existing `push` trigger. The `push` trigger and its branch filter are unchanged.
- **C2** [code] When `inputs.id` is non-empty the discover step sets `IDS` to that one id and performs no range walk, so a hand-dispatched run does not depend on `github.event.before`, which does not exist on a `workflow_dispatch` event.
- **C3** [code] The `claim.md` exists and `audit.md` does not exist guards run for a manual dispatch too, so a manual run cannot overwrite a shipped audit. That is the guide's "shipped `claim.md` and `audit.md` files are never edited" rule holding at the automation layer.
- **C4** [code] Nothing else in the workflow changed: the matrix, `max-parallel: 1`, the service container, the toolchain steps, the prompt, the commit step and the issue condition are byte-identical to OL-2609-105.
- **C5** [code] `docs/changelog/claims-guide.md` states that the literal skip token must never appear in a commit message, gives the observed consequence (no workflows run, no error), names this incident, and points at the `workflow_dispatch` input as the way back.
- **C6** [unverified] `gh api repos/:owner/:repo/actions/runs?head_sha=490bf64c0c99...` returned `total_count: 0` on 2026-09-18, against a `git ls-remote origin main` showing that commit as the branch tip. The three workflows that fire on every push to main - Deploy, tests, linter - all have runs for the preceding commit `b7975a0d` and none for `490bf64c`.
- **C7** [unverified] The discover shell was run locally with `MANUAL_ID=OL-2609-105` on 2026-09-18 and emitted `ids=["OL-2609-105"]`, the claim having a `claim.md` and no `audit.md`.

### Unchanged
- `docs/changelog/claims/2026/09/OL-2609-105/claim.md` is not in this diff. It is shipped, so it is never edited; this claim records what its commit did instead. Its C9 ("the commit message ends with the skip token") remains true of the audit commits the workflow makes, which is what that claim was about.
- `.github/workflows/deploy.yml`, `tests.yml` and `lint.yml` are not in this diff. The skipped run was GitHub's documented behaviour, not a fault in any of them, and none needs a guard against it.
- `.claude/agents/sally.md` is not in this diff. A manual dispatch spawns the same agent through the same prompt.

### Risk
OL-2609-105 is still unaudited and this push does not audit it: its trailer is in an earlier commit,
outside this push's range. Audit it with a `workflow_dispatch` run naming `OL-2609-105`, which is the
first real exercise of both the new input and the workflow as a whole.

A hand-dispatched run bypasses the "only claims this push shipped" narrowing, so a mistyped id simply
finds no `claim.md` and the job ends without a matrix.
