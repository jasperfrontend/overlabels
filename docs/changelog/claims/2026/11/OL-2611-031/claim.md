## OL-2611-031 - docs(claims): the Plinko sample drops in the CLAUDE.md preview bullet shipped in 9985ab00, not OL-2611-030

Remedies OL-2611-030 audit F1.

**Shipped:** 2026-11-20
**Commit:** `git log --grep=OL-2611-031`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-030/remedy.md` - new: remedy of the OL-2611-030 audit

### Claims
- **C1** [code] The `CLAUDE.md` bullet headed "The preview plays the game (OL-2611-030)" covers two commits: its Plinko sentence (the `sample_chat` extra and `createChatSampleFeed()`'s `SampleChatGame` third argument) describes commit 9985ab00, `feat(plinko): the designer preview plays itself with sample drops`, which carries no `Changelog:` trailer; the rest of the bullet (`designer.scenes`, `utils/previewScene.ts`, `handlePreviewScene()`) describes OL-2611-030 (corrects OL-2611-030 Surface, `CLAUDE.md` line, audit F1).
- **C2** [code] `git show 9985ab00 -- resources/recipes/twitch-chat-plinko/manifest.json` adds `"extras": ["sample_chat"]` to Plinko's `designer` block, and `git show 9985ab00 -- resources/js/utils/chatSample.ts` adds the `SampleChatGame` parameter of `createChatSampleFeed()`; neither path is in `git show --stat 1a271c42` (audit F1).

### Unchanged
- The `CLAUDE.md` bullet the finding names keeps its "(OL-2611-030)" heading; C1 above is the correction, and no line of `CLAUDE.md` is in this diff.
