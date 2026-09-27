## Remedy of OL-2609-141 - feat(products): every product declares its own designer, and Chat Emote Bubbles gets one

**Remedied:** 2026-09-27
**Claim:** OL-2609-142

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2609-142 C5 restates what `ProductChatEmoteBubblesTest` "declares a designer with three looks..." asserts: keys as one sorted set, no group titles, no per-group membership |
| F2 | RECORD | OL-2609-142 C6 places the scoped listing at `ProductDesigner::savedPresets()`, read by `ProductController::design()`; `SavedChatPresetController` has no list door |
| F3 | FIXED | `tests/Feature/UserChatPresetTest.php:261-284` restores the three deleted tests (no-designer and no-install 404s, login required, cascade on account delete), 9 -> 12; they pass against the unchanged tree, and were not seen red: the guards they pin (`resolve()`, `auth`, `cascadeOnDelete`) never moved |
| F4 | RECORD | OL-2609-142 C7 discloses the 18 -> 16 count and names the two `ProductChatDesignerTest` tests merged into one |
| F5 | RECORD | OL-2609-142 C8 discloses the `design.vue` title and save-look blurb changes |
| F6 | SKIPPED | the fix is an edit to `CLAUDE.md:772-773`, which needs Jasper's own go-ahead rather than an agent's; OL-2609-142 Unchanged names the stale line |
