## Audit of OL-2609-108 - ci(scrutinize): grant the OIDC permission the action needs to authenticate

**Audited:** 2026-09-25
**Commit:** 32cb2009d96c2e2a04422336612c188ff256f553
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `.github/workflows/scrutinize.yml:45-49 @32cb200` - `permissions:` is `contents: write`, `issues: write`, `id-token: write`, `actions: read`; it is the only `permissions` key in the file (`grep -n permissions` at that revision). `scrutinize.yml:22-24 @490bf64c` (OL-2609-105) has `contents: write`, `issues: write`, so the first two are unchanged. File deleted @HEAD by e8592dca (OL-2609-113) |
| C2 | UNVERIFIABLE | tagged [unverified] - a CI run log outside the repo |
| C3 | UNVERIFIABLE | tagged [code] but not checkable in-repo: the tree shows only that `id-token: write` is present (`scrutinize.yml:48 @32cb200`) and a comment restating the claim (`:41-43 @32cb200`); what the permission grants, what the action uses it for, and whether it widens what the workflow can change are GitHub and action behaviour. Compound: three assertions, none decidable from the tree. See F1 |
| C4 | UNVERIFIABLE | tagged [unverified] - a CI run outcome outside the repo |
| C5 | UNVERIFIABLE | tagged [unverified] - a CI run outcome outside the repo |

### Surface
Complete.

### Findings
- **F1** mistagged, uncheckable as [code] - C3 asserts GitHub platform semantics and `anthropics/claude-code-action` behaviour ("grants an OIDC token exchange, not repository write", "what the action uses to authenticate", "does not widen what this workflow can change"), none of which any file in the tree decides; `.github/workflows/scrutinize.yml:48 @32cb200` only shows the key exists. It should have been tagged [unverified] and split into separate claims; a later record bears on the third half, since OL-2609-112 C6 [unverified] quotes the audit step logging `Using GITHUB_TOKEN from OIDC` and revoking an app installation token, i.e. the OIDC exchange yielded a git credential. A maintainer should treat C3 as unestablished; no fix is possible now that the workflow is deleted (OL-2609-113), so a RECORD is the only remedy.

### Notes
- Diff is one hunk, `scrutinize.yml @32cb200` lines 40-49: a five-line comment and the two permission lines. The Unchanged line ("nothing else in the workflow moved") holds - no other hunk exists.
- OL-2609-105 C13 recorded `permissions` as `contents: write` and `issues: write` only; this change widens it and cites OL-2609-105 inline in C1.
- Later drift, all disclosed: the permissions block is identical @5cb89e27 (OL-2609-111) and @285ee56d (OL-2609-112); the whole file was removed by e8592dca (OL-2609-113), which also added the CLAUDE.md rule that the workflow must not be rebuilt.
- The added comment's assertion that `actions: read` "lets it read the workflow_run context" is not carried by any claim; it is commentary inside a Surface-disclosed line, not scope creep.
- No [test] claims; no tests were run.
