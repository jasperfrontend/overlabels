## Audit of OL-2609-113 - ci(scrutinize): remove the workflow; Sally runs locally only

**Audited:** 2026-09-25
**Commit:** e8592dca2e5de8d0b9de5cff8580714a914482b7
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `git ls-tree --name-only e8592dc .github/workflows/` lists exactly `deploy.yml`, `discord-notify.yml`, `lint.yml`, `tests.yml`; same @HEAD |
| C2 | CONFIRMED | `.claude/agents/sally.md` and `.claude/agents/remy.md` present in `git ls-tree e8592dc`; neither is in `git show --stat e8592dc`. No later commit touches either @HEAD. The `/sally` and `/remy` skill files are untracked (`.gitignore:20` `.claude/*`, `:21` `!.claude/agents/`), so no commit can carry them |
| C3 | CONFIRMED | `git grep -l scrutinize.yml e8592dc -- . ':!docs/changelog/claims/' ':!docs/private/'` returns only `CLAUDE.md` and `docs/changelog/claims-guide.md`; same @HEAD. Mentions: `CLAUDE.md:873 @e8592dc` ("existed for one day and is gone"), `claims-guide.md:237 @e8592dc` (built and removed), `claims-guide.md:252 @e8592dc` (past-tense history of its first commit) |
| C4 | CONFIRMED | `CLAUDE.md:872-886 @e8592dc` - "She runs LOCALLY and only locally. There is no CI for her, and a new one must not be built", reason given as per-run API cost; unchanged @HEAD (line 872) |
| C5 | CONFIRMED | `docs/changelog/claims-guide.md:237 @e8592dc` - `~~**Move her to GitHub as her own workflow.**~~ **Built 2026-09-18 ... removed on 2026-09-19 (OL-2609-113). Do not build it again.**`; same @HEAD |
| C6 | CONTRADICTED | The diff to `claims-guide.md` changes two sentences of the skip-token paragraph, not only the closing one: "keep the literal string in the workflow file" became "in a workflow file" (`claims-guide.md:253 @e8592dc`), and the closing `workflow_dispatch` sentence was replaced. The rest of the paragraph (lines 249-252) is unchanged. The part about `deploy.yml` and `tests.yml` holds: both trigger `on: push` (`deploy.yml:4`, `tests.yml:4 @e8592dc`) |
| C7 | CONFIRMED | The only hunks are `CLAUDE.md @@ -869,6 +869,21` (one added bullet) and `claims-guide.md @@ -234,21 +234,25` (steps 3-4 and the skip-token paragraph). The claim-requirement text ("A claim file is required..." in `CLAUDE.md`, "When a claim is required" in the guide) is outside both. The `/ship` skill file is untracked (`.gitignore:20`) |
| C8 | UNVERIFIABLE | tagged [unverified] |
| C9 | UNVERIFIABLE (first half mistagged) | tagged [unverified]. The first sentence could be checked in-repo, and holds: `git log e8592dc -- 'docs/changelog/claims/*/*/*/audit.md'` shows nothing between `490bf64c` (workflow added) and `e8592dc`; the most recent earlier one is `b7975a0d`, authored by Jasper and dated before `490bf64c`. The failure sequence is external |
| C10 | CONTRADICTED (compound, mistagged) | In-repo half confirmed: job `ci` at `tests.yml:14 @e8592dc`, job `quality` at `lint.yml:19 @e8592dc`, and the audit commit message carries the skip token at `.github/workflows/scrutinize.yml:285 @285ee56d`. The claim's actual reason - `main` requires those checks, and classic protection has no per-actor bypass - is a GitHub setting that nothing in the tree records, as the claim itself says. It is tagged [code], so nothing checkable backs it |

### Surface
Complete.

### Findings
- **F1** incorrect record - `CLAUDE.md:881 @e8592dc` (same @HEAD) says "The eight claims OL-2609-105..112 are the whole attempt", but OL-2609-109 and OL-2609-110 are `feat(products)` chat-designer changes (their `claim.md` headings). Only six claims belong to the attempt, and this change's own claim lists them as 105, 106, 107, 108, 111 and 112. `claims-guide.md:238 @e8592dc` repeats the range as "(OL-2609-105..112)". A new claim should restate the six IDs.
- **F2** claim contradicted - C6 says only the closing sentence of the skip-token paragraph changed, but `claims-guide.md:253 @e8592dc` also changes "the workflow file" to "a workflow file". A new claim should record both edits.
- **F3** mistagged, checkable as [code] - the first sentence of C9 ("The workflow never committed an `audit.md`") can be checked with `git log` over `490bf64c..e8592dc` (it holds), so it should not have been tagged [unverified].
- **F4** mistagged, should be [unverified] - C10 is tagged [code], but its deciding premise (branch protection on `main` requires `ci`/`quality` and has no per-actor bypass) is a GitHub setting that is not in the repo. The claim says so itself. It should be split, with the protection half tagged [unverified].

### Notes
- No [test] claims, so no tests were run.
- The new skip-token text (`claims-guide.md:254 @e8592dc`) gives "a manual re-run from the Actions tab" as the way back. `tests.yml` and `lint.yml` have no `workflow_dispatch` trigger @e8592dc; only `deploy.yml:6` does. The claim does not assert that this works, so it is not recorded as a finding.
- The claim's Unchanged line says "only the paragraph describing where the auditor runs moved". The diff edits three paragraphs: step 3, step 4 and the skip-token paragraph. Surface discloses all three.
