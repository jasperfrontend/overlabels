## Audit of OL-2609-002 - docs(changelog): claims must read cold, with tethered Unchanged lines

**Audited:** 2026-09-25
**Commit:** ba6feb34dffef575d199d0f7218d6a77e4535fd9
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `docs/changelog/claims-guide.md:137-142 @ba6feb34` - `## Writing claims` is followed first by "**Write for a reader who is cold on purpose.** The audit agent is deliberately kept uninformed ... Every line in every section must stand on the tree and the diff alone"; same text at `:159-164 @HEAD` |
| C2 | CONFIRMED | `docs/changelog/claims-guide.md:127-132 @ba6feb34` - "Each line names the symbol, says what ties it to THIS change, then states it is not in the diff" and "no smuggled judgments ... assertions live in Claims with a tag or nowhere"; same text at `:149-154 @HEAD` |
| C3 | CONFIRMED | `docs/changelog/claims-guide.md:98-100 @ba6feb34` - two bullet lines naming `EventTemplateMapping::resolveForEvent()`, the events-feed formatter, `latest_cheer*` controls and per-session bits aggregation, each tied to `channel.cheer` payloads and stated not in the diff; same at `:120-121 @HEAD` |
| C4 | CONFIRMED | `git diff 7c44972a ba6feb34 -- docs/changelog/claims/2026/09/OL-2609-001/claim.md` is one hunk (`@@ -20,7 +20,10 @@`, 4 insertions, 1 deletion) inside `### Unchanged`; `7c44972a` is the only commit carrying `Changelog: OL-2609-001`, and no other commit lies between it and `ba6feb34`; `git log ba6feb34..HEAD` on that file is empty |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged line 1 checked: `CLAUDE.md:620-626 @ba6feb34` summarizes the claim format and defers to `claims-guide.md` without Unchanged rules; `CLAUDE.md` is not in the diff.
- Unchanged line 2 said "three mechanical /ship checks" and was true at `claims-guide.md:180 @ba6feb34`; at `:202 @HEAD` there are four (path-rule check added by `b6ad212b`, a docs-only commit with no claim trailer, exempt under the rule it introduced).
- This commit edited the already-shipped OL-2609-001 `claim.md` in place, disclosed in C4 and Risk. No rule forbade that at `ba6feb34`; the later rule "Shipped `claim.md` and `audit.md` files are never edited" (`claims-guide.md @HEAD`, added with Remy in `88c01b69`) would now forbid it.
