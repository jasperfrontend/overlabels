## Audit of OL-2609-142 - fix(products): restore the three saved-look guard tests, and restate what OL-2609-141 pinned

**Audited:** 2026-09-27
**Commit:** 8e312060849aaa63d2145d8df7bb123e04bbb32b
**Verdict:** FINDINGS

This is a remedy claim (remedies OL-2609-141 audit F1-F5, F6 skipped). Its `remedy.md` and
OL-2609-141's `audit.md` were both read; RECORD lines below are judged against the audit findings
they answer, not against OL-2609-141's original claim text.

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `tests/Feature/UserChatPresetTest.php:261-270 @8e312060` - posts to `SAVED_LOOKS` with no install (404), installs, posts to `/products/chat-tower/saved-presets` (404), asserts `UserChatPreset::where('user_id', ...)->count() === 0`; ran `php artisan test --filter=UserChatPresetTest`, this test passed among 12 |
| C2 | CONFIRMED | `tests/Feature/UserChatPresetTest.php:272-274 @8e312060` - unauthenticated `postJson(SAVED_LOOKS, ...)` asserts `assertUnauthorized()` (401); passed |
| C3 | CONFIRMED | `tests/Feature/UserChatPresetTest.php:276-284 @8e312060` - creates a preset, `$user->forceDelete()`, asserts `UserChatPreset::find($preset->id)` is null; passed |
| C4 | CONFIRMED | Three deleted tests located at `959d24b7:202-225` (`it('answers 404 for another product and for an account with no install'...)`, `it('requires a login'...)`, `it('goes with the account when the account is deleted'...)`) match the three restored ones one-for-one; `SavedChatPresetController.php:127,167,177 @8e312060` - `abort_if($preset->user_id !== $user->id \|\| $preset->product !== $product, 404)` and the two designer/template `abort_if(..., 404)`; `routes/web.php:600,764-768 @8e312060` - the `products.saved-presets.` group sits inside `Route::middleware('auth.redirect')->group(...)`; `database/migrations/2026_09_22_120000_create_user_chat_presets_table.php:30 @8e312060` - `foreignId('user_id')->constrained()->cascadeOnDelete()`; none of these four is in this commit's diff |
| C5 | CONTRADICTED (compound, one clause) | `tests/Feature/ProductChatEmoteBubblesTest.php:207-225 @8e312060` ("declares a designer with three looks...") confirms the sorted-keys-only assertion, and the membership stated in OL-2609-141 C10 (which keys sit in which of the four groups) is pinned by no test in the file - both true. But the clause "the four titles are asserted only as `has('groups', 4)` and the joined card copy in the two page tests" mischaracterizes the third test: `ProductChatEmoteBubblesTest.php:274 @8e312060` asserts `where('product.designer.groups', ['Look', 'Motion', 'Spawn', 'Crowd'])` - the raw, un-joined, server-side prop's exact ordered title array, not "the joined card copy" (that phrase describes `show.vue`'s `designerGroups` computed at `resources/js/pages/products/show.vue:172`, which is a frontend string nothing in this test file touches). The four titles ARE pinned, directly, not via a "joined card copy" proxy |
| C6 | CONFIRMED | `app/Http/Controllers/SavedChatPresetController.php @8e312060` - `grep -n "function"` lists only `store`, `apply`, `overwrite`, `rename`, `destroy`, `own`, `validateName`, `resolve`, no list method; `app/Support/ProductDesigner.php:338-341 @8e312060` - `savedPresets(User $user, string $product)` filters `where('product', $product)`; `app/Http/Controllers/ProductController.php:487 @8e312060` - `'saved_presets' => ProductDesigner::savedPresets($user, $slug)` |
| C7 | CONFIRMED | `git show 959d24b7:tests/Feature/ProductChatDesignerTest.php` has 18 `it(` blocks, `41598c47:...` has 16; the removed names are `offers only layouts and backgrounds the overlay styles`, `declares the same vocabularies on the chat document itself, so a fresh install carries them on the rows` and `gives every look control exactly one home on the page` (the groups test); the new name is `declares layouts and backgrounds the overlay styles, on the document itself` at `41598c47:85` |
| C8 | CONFIRMED | `git diff 959d24b7 41598c47 -- resources/js/pages/products/design.vue` - line 238 `title="Design your chat"` -> line 239 `` :title="`Design ${product.name}`" ``; line 247 `Saves the skin, font, colors, layout and lifetime as they are right now, under any name you like.` -> line 248 `Saves every knob below as it is right now, under any name you like.` |

### Surface
Complete. `git show --stat --format=` for `8e312060` lists exactly `docs/changelog/claims/2026/09/OL-2609-141/remedy.md` and `tests/Feature/UserChatPresetTest.php`, both listed on the Surface line (plus `claim.md`, exempt). No undisclosed or phantom paths.

### Findings
- **F1** claim mischaracterizes its own evidence - C5's phrase "the joined card copy in the two page tests" describes `ProductChatEmoteBubblesTest.php:274 @8e312060`'s `where('product.designer.groups', ['Look', 'Motion', 'Spawn', 'Crowd'])` as if it tested a frontend-joined string; it directly asserts the raw ordered title array instead, which is stronger evidence than the claim credits it for. The substantive conclusion (per-group key membership untested) still stands - fix the description, not the verdict.
- **F2** contradiction with the record - `remedy.md:10 @8e312060` marks F3 "FIXED" while its own "What" column states the restored tests "pass against the unchanged tree, and were not seen red." `CLAUDE.md`'s Workflow Preferences section (Committing and pushing) states Remy "resolves each finding to FIXED (test red then green), RECORD..., or SKIPPED..." - a FIXED row with no red-then-green cycle is not what that line describes, and neither `remedy.md` nor `claim.md` cites an exception for it.

### Notes
- `CLAUDE.md:772-773` at the shipped commit (`8e312060`) still read "no designer (the chat designer is hard-wired to `ChatPresets`...)" as the Unchanged line states; a later commit, `c956aaca` (no `Changelog:` trailer, a docs-only correction exempt from the claim requirement), rewrote that line the same day. The Unchanged line is accurate for the revision it names and is now superseded at HEAD by `c956aaca`, not by a claim.
- Tests run: `php artisan test --filter=UserChatPresetTest` - 12 passed (117 assertions).
