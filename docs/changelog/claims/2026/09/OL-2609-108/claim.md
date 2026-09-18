## OL-2609-108 - ci(scrutinize): grant the OIDC permission the action needs to authenticate

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-108`

First run to reach the action's auth step failed with `Could not fetch an OIDC token. Did you
remember to add 'id-token: write' to your workflow permissions?`. The trigger fixed in OL-2609-107
is correct; this is the next thing in the way.

### Surface
- `.github/workflows/scrutinize.yml` - `id-token: write` and `actions: read` added to `permissions`

### Claims
- **C1** [code] `permissions` now lists `contents: write`, `issues: write`, `id-token: write` and `actions: read`. The first two are unchanged from OL-2609-105.
- **C2** [unverified] Run 35391245070 failed three audit jobs with `Unable to get ACTIONS_ID_TOKEN_REQUEST_URL env variable` and `Could not fetch an OIDC token`, observed 2026-09-18. That variable is only present when a job requests `id-token: write`.
- **C3** [code] `id-token: write` grants an OIDC token exchange, not repository write. It is what the action uses to authenticate and does not widen what this workflow can change.
- **C4** [unverified] Run 35391245070's `discover` job succeeded and queued `OL-2609-105`, `OL-2609-106` and `OL-2609-107`: newest first, the cap of 3 honoured, and 105 present, which confirms OL-2609-107 C5's self-healing on a real runner.
- **C5** [unverified] The same run reached the action's auth step rather than being rejected on its event, confirming OL-2609-107 C1 and C2 on a real runner.

### Unchanged
- Nothing else in the workflow moved: the triggers, discovery, matrix, services, toolchain, prompt, commit step and issue condition are as OL-2609-107 shipped them.

### Risk
Still unproven end to end. No run has yet written an `audit.md`, so the prompt, the `--allowedTools`
spelling and the commit-back step remain untested.
