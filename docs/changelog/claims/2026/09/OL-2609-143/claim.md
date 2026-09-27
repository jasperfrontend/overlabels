## OL-2609-143 - docs(claims): restate OL-2609-142 C5's evidence and correct OL-2609-141 remedy F3's label

**Shipped:** 2026-09-27
**Commit:** `git log --grep=OL-2609-143`

Remedies OL-2609-142 audit F1, F2.

### Surface
- `docs/changelog/claims/2026/09/OL-2609-142/remedy.md` - new

### Claims
- **C1** [code] `tests/Feature/ProductChatEmoteBubblesTest.php:274` asserts `where('product.designer.groups', ['Look', 'Motion', 'Spawn', 'Crowd'])`, the raw ordered `groups` array from the server-side `product.designer` prop, directly - not "the joined card copy", which is `resources/js/pages/products/show.vue:172`'s frontend `designerGroups` computed (`joinNames` over lowercased titles), a string this test file never touches. The four titles ARE pinned directly by that line. Which keys sit in which of the four groups (the Look/Motion/Spawn/Crowd membership) remains pinned by no test in the file (corrects OL-2609-142 C5, audit F1).
- **C2** [code] `docs/changelog/claims/2026/09/OL-2609-141/remedy.md`'s F3 row is labelled FIXED. `CLAUDE.md`'s Workflow Preferences ("Committing and pushing") states Remy resolves a finding to "FIXED (test red then green)". The row's own "What" column states the three restored tests "pass against the unchanged tree, and were not seen red", because the guards they pin - `SavedChatPresetController::resolve()`'s two `abort_if(..., 404)`, the `auth.redirect` middleware on the `products.saved-presets.*` route group, and `cascadeOnDelete()` on `user_chat_presets.user_id` - never changed. Nothing in the guarded behaviour regressed; only test coverage was missing. The row's outcome should read RECORD, not FIXED (corrects OL-2609-141 remedy.md F3 outcome label, audit F2).

### Unchanged
- `SavedChatPresetController::resolve()`, the `auth.redirect` middleware group and `cascadeOnDelete()` on `user_chat_presets.user_id` are untouched by this claim; C2 restates that none of them was ever in question.
- `ProductChatEmoteBubblesTest.php:274` and `show.vue:172` are untouched by this claim; C1 restates what each already does.
