## OL-2609-142 - fix(products): restore the three saved-look guard tests, and restate what OL-2609-141 pinned

**Shipped:** 2026-09-27
**Commit:** `git log --grep=OL-2609-142`

Remedies OL-2609-141 audit F1, F2, F3, F4, F5; F6 skipped.

### Surface
- `tests/Feature/UserChatPresetTest.php` - three tests restored after the scoping test: "answers 404 for a product with no designer and for an account with no install", "requires a login", "goes with the account when the account is deleted"; 9 -> 12
- `docs/changelog/claims/2026/09/OL-2609-141/remedy.md` - new

### Claims
- **C1** [test] `UserChatPresetTest` "answers 404 for a product with no designer and for an account with no install" asserts a store on `/products/twitch-chat-overlay/saved-presets` with no install is 404, a store on `/products/chat-tower/saved-presets` after a chat install is 404, and no `user_chat_presets` row exists for the account afterwards (corrects OL-2609-141 Surface, audit F3).
- **C2** [test] `UserChatPresetTest` "requires a login" asserts an unauthenticated store on `/products/twitch-chat-overlay/saved-presets` is 401 (audit F3).
- **C3** [test] `UserChatPresetTest` "goes with the account when the account is deleted" asserts `forceDelete()` on the owner leaves `UserChatPreset::find($id)` null (audit F3).
- **C4** [code] The three tests in C1-C3 are the ones deleted at `41598c47` (`@959d24b7:202-225`), with `product => twitch-chat-overlay` on the created row and `chat-tower` named as the product with no designer; the behaviour they pin is `SavedChatPresetController::resolve()`'s two `abort_if(..., 404)`, the `auth` middleware on the `products.saved-presets.*` route group, and `cascadeOnDelete()` on `user_chat_presets.user_id` in `2026_09_22_120000_create_user_chat_presets_table.php`; none of those is in the diff.
- **C5** [code] `ProductChatEmoteBubblesTest` "declares a designer with three looks, every control in a group and no skin strip" asserts the designer's keys as one sorted set equal to `bubbles.md`'s control keys, and asserts neither the group titles nor which keys each group holds; the four titles are asserted only as `has('groups', 4)` and the joined card copy in the two page tests; the Look/Motion/Spawn/Crowd membership stated in OL-2609-141 C10 is pinned by no test (corrects OL-2609-141 C12, audit F1).
- **C6** [code] The scoped listing of saved looks is `ProductDesigner::savedPresets()` (`where('product', $product)`), read by `ProductController::design()`; `SavedChatPresetController` has no list door, its methods being `store`, `apply`, `overwrite`, `rename`, `destroy`, `own`, `validateName` and `resolve` (corrects OL-2609-141 C21, audit F2).
- **C7** [code] At `41598c47`, `tests/Feature/ProductChatDesignerTest.php` went from 18 to 16 tests: the groups test moved to `RecipeDesignerBlockTest`, and "offers only layouts and backgrounds the overlay styles" and "declares the same vocabularies on the chat document itself, so a fresh install carries them on the rows" were merged into "declares layouts and backgrounds the overlay styles, on the document itself" (corrects OL-2609-141 Surface, audit F4).
- **C8** [code] At `41598c47`, `resources/js/pages/products/design.vue` changed the page title from `Design your chat` to `` `Design ${product.name}` `` and the save-look blurb from "Saves the skin, font, colors, layout and lifetime as they are right now, under any name you like." to "Saves every knob below as it is right now, under any name you like." (corrects OL-2609-141 Surface, audit F5).

### Unchanged
- `SavedChatPresetController`, `ProductDesigner`, `ProductController`, `ProductChatEmoteBubblesTest`, `ProductChatDesignerTest` and `design.vue`: C5-C8 restate what those already are; none is in the diff.
- `CLAUDE.md:772-773` still reads that Chat Emote Bubbles has "no designer (the chat designer is hard-wired to `ChatPresets` ...)"; OL-2609-141 audit F6 is skipped in `remedy.md` and the line is not in the diff.
