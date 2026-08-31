## OL-2609-002 - docs(changelog): claims must read cold, with tethered Unchanged lines

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-002`

### Surface
- `docs/changelog/claims-guide.md` - cold-reader rule added to Writing claims, Unchanged field rules extended, worked example's Unchanged section rewritten
- `docs/changelog/claims/2026/09/OL-2609-001/claim.md` - Unchanged section rewritten to the new form

### Claims
- **C1** [code] The Writing claims section of `claims-guide.md` opens with a rule that every line must stand on the tree and the diff alone, because the audit agent is deliberately kept uninformed.
- **C2** [code] The Unchanged field rules in `claims-guide.md` require each line to name a symbol, state what ties it to the change, and assert it is not in the diff, and ban untagged assertions in that section.
- **C3** [code] The worked example's Unchanged section in `claims-guide.md` consists of per-line entries naming symbols and their tie to that example's change.
- **C4** [code] In `claims/2026/09/OL-2609-001/claim.md`, only the Unchanged section differs from the version committed under the `Changelog: OL-2609-001` trailer.

### Unchanged
- `CLAUDE.md` summarizes the claim format in its Committing and pushing section and defers to `claims-guide.md` for the rules; the summary names no Unchanged rules, so it is not in the diff.
- The three mechanical /ship checks described in `claims-guide.md` (folder exists, trailer matches, Surface complete) gate structure, not wording; their section is not in the diff.

### Risk
The OL-2609-001 claim file was edited after its own ship: `git log --grep=OL-2609-001` shows the original Unchanged wording, and the tree holds the corrected one.
