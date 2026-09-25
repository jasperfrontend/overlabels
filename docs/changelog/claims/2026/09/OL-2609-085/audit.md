## Audit of OL-2609-085 - chore(streamlabs): drop the closed-beta banner, the app is approved

**Audited:** 2026-09-25
**Commit:** 9ab911aebc603b1220d658cbf65fa804c8a233b7
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `git show 9ab911a:resources/js/pages/settings/integrations/streamlabs.vue \| grep -niE 'closed beta\|under review\|mailto\|early access\|whitelist'` returns nothing; the removed hunk (old lines 109-117) held all three. Same grep @HEAD returns nothing; no commit after 9ab911a touches the file |
| C2 | UNVERIFIABLE | tagged [unverified]; Streamlabs dashboard state is outside the repo |

### Surface
Complete.

### Findings
- **F1** contradiction with the record - this change removes the banner that `docs/changelog/claims/2026/09/OL-2609-064/claim.md:28` recorded under Unchanged ("`resources/js/pages/settings/integrations/streamlabs.vue` keeps its closed-beta banner: the app is not approved until it is submitted and approved, and the banner is the truth until then"), but the claim does not cite OL-2609-064 anywhere; a follow-up claim should state that this supersedes OL-2609-064's Unchanged line on the banner.

### Notes
- Unchanged line confirmed: `git show 9ab911a --format= -- app routes` is empty, so `StreamLabsIntegrationController` and the OAuth routes are not in the diff.
- `CLAUDE.md:477 @9ab911a` and `@HEAD` still reads "App approval: unapproved apps limited to 10 whitelisted users - closed beta banner shown on settings page"; the banner no longer exists @9ab911a. Not a "do not" rule, so not a finding, but the line is stale.
- No other "closed beta" / "whitelisted" copy remains under `resources/` @HEAD (grep, no matches).
- No tests named or run; the claim has no [test] lines.
