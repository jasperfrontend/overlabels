## OL-2609-113 - ci(scrutinize): remove the workflow; Sally runs locally only

**Shipped:** 2026-09-19
**Commit:** `git log --grep=OL-2609-113`

Reverses OL-2609-105 (`scrutinize.yml` created) and with it OL-2609-106, 107, 108, 111 and 112,
which only ever fixed that file. The auditor itself is untouched and still runs through `/sally`.

### Surface
- `.github/workflows/scrutinize.yml` - deleted
- `CLAUDE.md` - the Scrutinize Sally entry gains a bullet: she runs locally only, and a CI version must not be rebuilt
- `docs/changelog/claims-guide.md` - "Bringing her to life" step 3 records the build and the removal instead of instructing the move to GitHub; step 4 points at the audits rather than at issues; the skip-token paragraph no longer points at a deleted file's `workflow_dispatch`

### Claims
- **C1** [code] `.github/workflows/scrutinize.yml` does not exist. `.github/workflows/` contains `deploy.yml`, `discord-notify.yml`, `lint.yml` and `tests.yml`.
- **C2** [code] `.claude/agents/sally.md` and `.claude/agents/remy.md` are present and not in this diff. `/sally` and `/remy` behave exactly as before; nothing about the agent, the audit format or the remedy format is in this diff.
- **C3** [code] Outside `docs/changelog/claims/` and the gitignored `docs/private/`, the only files naming `scrutinize.yml` are `CLAUDE.md` and `docs/changelog/claims-guide.md`, and both name it as a record of its removal.
- **C4** [code] `CLAUDE.md` states that she runs locally and only locally, that no CI for her is to be built, and gives the reason.
- **C5** [code] `docs/changelog/claims-guide.md` step 3 no longer instructs anyone to move her to GitHub. It is struck through and marked built-and-removed.
- **C6** [code] The skip-token warning in the guide is kept in full. Only its closing sentence changed, from pointing at `scrutinize.yml`'s `workflow_dispatch` to pointing at a manual re-run; the lesson it teaches applies to `deploy.yml` and `tests.yml` unchanged.
- **C7** [code] `/ship`'s claim requirement is not in this diff. A change touching `app/`, `routes/` and the rest still needs a claim file; what is gone is the thing that would have audited it without being asked.
- **C8** [unverified] One run (35465028611) cost about $5 as reported by the repository owner, and completed two audits with a third cancelled. That is the ~$2 per audit the CLAUDE.md note and the guide cite.
- **C9** [unverified] The workflow never committed an `audit.md`. Across six runs it failed in order on the action refusing a `push` event, a missing `id-token: write`, the Claude Code GitHub App not being installed, an Anthropic account with no credit, a git credential that was invalid by the time the step pushed, and finally branch protection.
- **C10** [code] The last of those is not fixable inside a workflow file, which is why this is a removal rather than another fix: `main` requires the `ci` and `quality` checks, the audit commit carries the skip token so neither check can ever run on it, and classic branch protection has no per-actor bypass. `git ls-files .github/workflows` and the protection settings are both checkable; the second is not in the repo.

### Unchanged
- The claims system itself. `docs/changelog/claims-guide.md`'s format, tags, Surface rule, ID allocation and the path rule for when a claim is required are all as they were; only the paragraph describing where the auditor runs moved. Removing the workflow removes where she runs, not what she is or what a claim must contain.
- `docs/changelog/claims/2026/09/OL-2609-105/` through `OL-2609-112/` are not in this diff. They record what was true when they were written, which is the standing rule for shipped claims; the reversal lives here and in the two documents above rather than in edits to them.
- The `ANTHROPIC_API_KEY` repository secret, the Claude Code GitHub App installation, the `scrutinize` issue label and issue #335 are all outside the repository and none is in this diff. #335 carries four live findings against OL-2609-111 that no `remedy.md` has resolved.
- `.github/workflows/lint.yml`, `tests.yml` and `deploy.yml` are not in this diff. The required checks on `main` are the `ci` and `quality` jobs in the first two, and they are unaffected by removing a workflow that was never one of them.

### Risk
No claim is audited automatically any more. The queue - about 100 folders with a `claim.md` and no
`audit.md`, plus every claim shipped from now on - moves only when someone runs `/sally <ID>` in a
fresh session.
