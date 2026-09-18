## OL-2609-107 - ci(scrutinize): hang off the tests workflow, because the action refuses a push event

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-107`

OL-2609-105 and OL-2609-106 both shipped a workflow triggered `on: push`. The first run that actually
reached the action failed with `Action failed with error: Unsupported event type: push`.
`anthropics/claude-code-action@v1` accepts nine events and `push` is not one of them, so the trigger
was never going to work regardless of anything else in the file. This corrects OL-2609-105 C1 and
OL-2609-106 C1, both of which describe a `push` trigger.

### Surface
- `.github/workflows/scrutinize.yml` - trigger changed from `push` to `workflow_run` on `tests`; discovery changed from a push-range walk to a queue scan with a cap; `workflow_dispatch` input made optional; comments corrected where they described the push trigger

### Claims
- **C1** [code] The workflow triggers on `workflow_run` of the `tests` workflow, `types: [completed]`, `branches: [main]`, plus `workflow_dispatch`. It has no `push` trigger. `tests.yml` runs on push to main, so a claim still reaches Sally as a consequence of a push, one workflow removed.
- **C2** [unverified] `anthropics/claude-code-action@v1` accepts exactly nine events - `issues`, `issue_comment`, `pull_request`, `pull_request_review`, `pull_request_review_comment`, `workflow_dispatch`, `repository_dispatch`, `schedule`, `workflow_run` - and errors `Unsupported event type: <name>` for anything else. Read from the action's `src/github/context.ts` on 2026-09-18, and observed as a job failure on run 35390034009.
- **C3** [code] Discovery no longer reads `github.event.before` or `github.event.after`. It scans `docs/changelog/claims` for folders holding a `claim.md` and no `audit.md`, which is the queue definition the guide already states, sorted newest first.
- **C4** [code] The scan is capped at 3 ids per run. A backlog therefore costs three runner-hours rather than one per historical claim, and the remainder stays in the queue for the next run.
- **C5** [code] Queue discovery is self-healing where the range walk was not: a claim whose push produced no workflow run at all is still in the queue and is taken by the next run. OL-2609-105 is exactly that case.
- **C6** [code] The `workflow_dispatch` input `id` is now `required: false`. With an id, that one claim is audited; without one, the run takes the queue like any other.
- **C7** [code] The manual path still applies the `claim.md` exists and `audit.md` absent guards, so a dispatch naming an already-audited id audits nothing and cannot overwrite a shipped audit.
- **C8** [code] Nothing else changed: `permissions`, the concurrency group, the matrix with `max-parallel: 1`, the Postgres service, the toolchain steps, the prompt, the commit step with its skip token, and the issue condition are as OL-2609-105 shipped them.
- **C9** [unverified] The queue scan was run locally on 2026-09-18 and returned `["OL-2609-106","OL-2609-105","OL-2609-102"]`: newest first, the cap honoured, and 105 present, which is the self-healing C5 describes.
- **C10** [unverified] The `discover` job of run 35390034009 succeeded and produced the matrix; only the action step failed. The bash in this file is therefore unchanged in shape from a version observed working on a real runner.

### Unchanged
- `.github/workflows/tests.yml` is not in this diff. This workflow depends on it by name in a `workflow_run` trigger, which is a one-way reference: `tests.yml` does not know it exists and does not wait for it. Renaming that workflow would silently stop audits, which is the cost of this arrangement and is stated here rather than discovered later.
- `.github/workflows/deploy.yml` is not in this diff and still has no `needs:` on this workflow. Nothing about the trigger change affects the advisory decision.
- `.claude/agents/sally.md` is not in this diff.
- `docs/changelog/claims-guide.md` is not in this diff. Its step 3 says "on push to `main`", which is now one workflow removed from literally true; the intent - every claim reaching main gets audited - is what this implements, and the file records the mechanism.
- The claims for OL-2609-105 and OL-2609-106 are not edited. They are shipped; this claim cites what each got wrong.

### Risk
The trigger is now indirect. If `tests` is renamed, disabled, or stops running on main, audits stop
silently: there is no error, just no runs. The `workflow_dispatch` path is the manual fallback.

Audits now run after `tests` completes rather than alongside it, so an audit lands a few minutes
later than a deploy. Nothing waits on either.

This is the third trigger design for the same file in one evening, and the first two were shipped
without ever reaching the action. The first run that exercises the action end to end is still ahead;
until one produces an `audit.md`, the prompt, the `--allowedTools` spelling in `claude_args` and the
commit-back step remain unproven.
