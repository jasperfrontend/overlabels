## Audit of OL-2611-030 - feat(designer): the preview plays the game while you pick its colors

**Audited:** 2026-11-20
**Commit:** 1a271c424a874d4e396b9f1e752958483a1a5c11 (single commit carrying the trailer; it is also HEAD, so @HEAD = @1a271c4 throughout)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/utils/previewScene.ts:60-67 @1a271c4` - `tower.stack` pushes `{cleared: true, height: 0, block: null, toppled: null}` then one `cleared: false` step per block; `:109` `position: String(top.position + 1)`, `:111` `x: round(top.x + offset)` (running sum, rounded to 2 dp at each step), `:107` `login: b.name.toLowerCase()`; same @HEAD |
| C2 | CONFIRMED | `previewScene.ts:70-78 @1a271c4` - `tower.topple` builds its block on `top` (position `top.position + 1`), pushes `cleared: true` with `toppled.height: Number(block.position)`; same @HEAD |
| C3 | CONFIRMED | `previewScene.ts:89 @1a271c4` - `last_removed_at === 'now'` becomes the `nowSeconds` argument; the one caller passes `Math.floor(Date.now() / 1000)` at `resources/js/components/OverlayRenderer.vue:1668 @1a271c4`; same @HEAD |
| C4 | CONFIRMED | `previewScene.ts:47 @1a271c4` returns `[]` for non-array input; `:53` skips non-object events, `:65`/`:71` skip blocks `sceneBlock()` rejects (`:100-103`), unknown or incomplete events match no branch; same @HEAD |
| C5 | CONFIRMED | `OverlayRenderer.vue:1208 @1a271c4` - `if (props.sample) window.addEventListener('message', handlePreviewScene)`; `:1664` returns unless `event.source === window.parent`, `:1666` unless `payload.ol === 'preview-scene'`; removed at `:1444`; same @HEAD |
| C6 | CONFIRMED | `OverlayRenderer.vue:1668-1672 @1a271c4` dispatches `control`/`tower`/else to `applyControlUpdate`/`handleTowerUpdated`/`handleListUpdated`; none of `:1543-1587`, `:1613-1655`, `:1675-` calls `fetch` or `axios` (the file's only `fetch` calls are `:369` and `:1153`); same @HEAD. See Notes on the webfont path |
| C7 | CONFIRMED | `resources/js/pages/products/design.vue:598 @1a271c4` - `JSON.parse(JSON.stringify(scene.events))` is what `postMessage` sends at `:599`; same @HEAD |
| C8 | CONFIRMED | `design.vue:607-611 @1a271c4` - splits `frameSrc` on `#`, strips an old `reload=`, sets `?`/`&reload=${Date.now()}#${token}`; `frameSrc` is the iframe `:src` at `:1108`, and `ProductDesigner::previewUrl()` (`app/Support/ProductDesigner.php:604`) always carries `#{$token}`; same @HEAD |
| C9 | CONFIRMED | `resources/js/utils/previewScene.test.ts:117-123 @1a271c4` - for `chat-tower` and `follower-bowling`, every scene event expanded alone is `not.toEqual([])`. `npm test -- resources/js/utils/previewScene.test.ts`: 9 passed |
| C10 | CONFIRMED | `previewScene.test.ts:125-141 @1a271c4` - `danger = room < 0.8 ? 2 : room < 1.8 ? 1 : 0` on each scene's first `tower:tower_room`; asserts standing 0, close 1, about 2, `about > 0`, record 0 (manifest values 2.81, 1.29, 0.42, 2.06). Matches `resources/recipes/chat-tower/tower.md:540 @HEAD`. Passed in the run above |
| C11 | CONFIRMED | `tests/Feature/RecipeDesignerBlockTest.php:156-164 @1a271c4` - asserts message `Duplicate scene key "standing".` and an error at pointer `/designer/scenes/0/events/0/event` for `tower.explode` (the schema enum). `php artisan test tests/Feature/RecipeDesignerBlockTest.php tests/Feature/ProductTowerTest.php`: 29 passed |
| C12 | CONFIRMED | `tests/Feature/ProductTowerTest.php:97-113 @1a271c4` - `->has('scenes', 5)` on `/products/chat-tower/design`; passed in the run above |
| C13 | UNVERIFIABLE | tagged [unverified]; a browser observation and a fail-first run against a tree that no longer exists |

### Surface
Complete.

### Findings
- **F1** scope - the `CLAUDE.md` hunk (`CLAUDE.md:915-917 @1a271c4`) documents the Plinko sample drops (`sample_chat` extra, `createChatSampleFeed()`'s `SampleChatGame` argument) under the heading "(OL-2611-030)", but that code shipped in 9985ab00, which carries no trailer, the Surface line for `CLAUDE.md` says only "preview scenes and the proxy gotcha", and the claim's own Unchanged line says the Plinko sample drops are not this change. The reader should either cite 9985ab00 next to the Plinko sentence in `CLAUDE.md` or say in a later claim that the bullet covers both commits.

### Notes
- C6: `applyControlUpdate` calls `syncWebfontLink()` (`OverlayRenderer.vue:1581-1583 @1a271c4`) for a webfont control. That adds a font stylesheet `<link>`, which is a network load. No shipped scene can reach it: neither `chat-tower/tower.md` nor the follower-bowling recipe declares a `webfont` control, and the scene schema does not check `control.updated` keys.
- OL-2609-109 C9 describes "the `message` listener" as added only inside `startSampleChat()`. That listener (`handleSampleMessage`) is still the only one added there. This commit adds a second, separate one in `onMounted` with the same `window.parent` guard.
- `expandScene` is a static import in `OverlayRenderer.vue:49 @1a271c4`, so it ships in every overlay bundle. That differs from `chatSample`, which is imported dynamically (OL-2609-109 C6). It contains no fixtures: the scene data comes from the manifest over `postMessage`.
- Tests run: Vitest `previewScene.test.ts` (9 passed); Pest `RecipeDesignerBlockTest` + `ProductTowerTest` (29 passed, 139 assertions). No build was needed.
