## OL-2609-112 - ci(scrutinize): push the audit with the job's own token

**Shipped:** 2026-09-19
**Commit:** `git log --grep=OL-2609-112`

Builds on OL-2609-111. Everything that claim fixed worked on the first run that exercised it - the
audit finished, the verdict gate passed, the autostash rebase ran - and the push was then rejected
for a credential this step never supplied on purpose.

### Surface
- `.github/workflows/scrutinize.yml` - the commit step takes `GITHUB_TOKEN` from `github.token` and rewrites `origin` to use it before pushing; two comments corrected with facts the OL-2609-111 run established

### Claims
- **C1** [code] The `Commit the audit` step declares `GITHUB_TOKEN: ${{ github.token }}` in its `env` and runs `git remote set-url origin "https://x-access-token:${GITHUB_TOKEN}@github.com/${GITHUB_REPOSITORY}.git"` before its first push.
- **C2** [code] The rewrite is before the push-then-rebase block introduced by OL-2609-111 C5, so both the first push and the retry use it.
- **C3** [unverified] Run 35467639180, job 105962816739 (OL-2609-111): the audit step succeeded, `[main afd11382] docs(sally): audit of OL-2609-111 - FINDINGS [skip ci]` was committed, and both pushes failed with `remote: Invalid username or token. Password authentication is not supported for Git operations.`
- **C4** [unverified] In the same step, between the two failed pushes, `git pull --rebase --autostash origin main` succeeded: it logged `From https://github.com/jasperfrontend/overlabels`, `Created autostash: 5be24fce`, `Current branch main is up to date.`, `Applied autostash.` A fetch of a public repo needs no credential, so that success says nothing about the one the push used.
- **C5** [unverified] In the same job, the `Open an issue` step ran with `GH_TOKEN: ${{ github.token }}` and created https://github.com/jasperfrontend/overlabels/issues/335. That is the same token this change adopts, alive and authorised seconds after the pushes were refused.
- **C6** [unverified] Whether the credential the pushes used was the checkout's or one the audit step installed is NOT established. The audit step logs `Using GITHUB_TOKEN from OIDC` and ends with a `Revoke app token` sub-step that DELETEs `/installation/token`, which would explain it, but nothing in the log shows the repo's `http.https://github.com/.extraheader` being rewritten. C1 does not depend on the answer: it supplies a third credential that neither path can revoke.
- **C7** [code] `github.token` can push here because the workflow already declares `permissions: contents: write`, which is not in this diff.
- **C8** [code] Every `run:` block in the file parses under `bash -n` after GitHub expressions are stubbed out.

Corrections to OL-2609-111, from its own audit (issue #335):
- **C9** [code] OL-2609-111 C6 is wrong and is corrected here: the `git status --porcelain` echo is AFTER `git commit`, not before it. The code is unchanged - it still runs before the push, which is where the diagnostic is needed - only the claim was misstated. (OL-2609-111 audit F1.)
- **C10** [code] OL-2609-111 C14 said which tracked file dirties the tree was "not established". It is now: `bootstrap/cache/packages.php` and `bootstrap/cache/services.php`, both tracked, both rewritten by `composer install`'s package discovery. The OL-2609-111 job printed exactly those two under `working tree:`. The comment above the rebase now names them. (OL-2609-111 audit F4, which also called that claim mistagged.)
- **C11** [code] OL-2609-111 C1 reversed OL-2609-107 C4 (queue capped at 3) and C5 reversed OL-2609-105 C10 (pull-rebase before push) without citing either, which the guide requires. Both reversals stand; this records the citations. (OL-2609-111 audit F2 and F3.)

### Unchanged
- The push-then-rebase order, `--autostash`, the verdict gate, `if: ${{ !cancelled() }}` and the queue cap of 1 are all as OL-2609-111 shipped them. The OL-2609-111 run exercised every one of them and each behaved as claimed; only the credential was wrong.
- `.claude/agents/sally.md` is not in the diff, for the third change running. Her output has been correct every time it has been produced; every failure so far has been in what the job does with it.
- The audit of OL-2609-111 is not in this diff and `audit.md` is still absent from that folder. The commit that held it existed only on the runner. Its findings survive in issue #335 because the issue step reads the file before the job ends, which is the only reason this change has that audit to cite at all.
- OL-2609-111's four findings are NOT remedied here. That is `/remy OL-2609-111` in a fresh session, and deliberately not this one: the session that wrote a claim is the one session that must not audit or remedy it. C9 to C11 restate the facts rather than edit the shipped claim, which is the RECORD outcome the remedy path defines.

### Risk
The token lands in `.git/config` on the runner for the remainder of the job. It is `github.token`,
which the runner masks in logs, the repository is destroyed with the runner, and `Post Checkout`
runs afterwards regardless.
