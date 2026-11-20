## OL-2611-030 - feat(designer): the preview plays the game while you pick its colors

**Shipped:** 2026-11-20
**Commit:** `git log --grep=OL-2611-030`

### Surface
- `resources/js/utils/previewScene.ts` - new: `expandScene()` turns manifest scene events into overlay handler calls
- `resources/js/utils/previewScene.test.ts` - new file
- `resources/js/components/OverlayRenderer.vue` - `handlePreviewScene()`, registered in sample mode only, removed on unmount
- `resources/js/pages/products/design.vue` - `scenes` prop, "Show a moment" bar, `playScene()`, `backToLive()`
- `app/Support/ProductDesigner.php` - `scenes()`
- `app/Http/Controllers/ProductController.php` - design page passes `scenes`
- `app/Services/Recipes/RecipeManifestValidator.php` - duplicate scene key error
- `resources/recipes/recipe-manifest.schema.json` - `designer.scenes`
- `resources/recipes/chat-tower/manifest.json` - five scenes
- `resources/recipes/follower-bowling/manifest.json` - one scene
- `tests/Feature/RecipeDesignerBlockTest.php` - the three full-screen products in the shipped-blocks test; scene key and event test
- `tests/Feature/ProductTowerTest.php` - designer page test
- `CLAUDE.md` - preview scenes and the proxy gotcha

### Claims
- **C1** [code] `expandScene()` turns `tower.stack` into one cleared `tower.updated` payload followed by one payload per block, with `position` counting from 1, `x` the running sum of `offset`, and `login` the lowercased name.
- **C2** [code] `expandScene()` turns `tower.topple` into a cleared payload whose `toppled.height` is one above the last stacked block.
- **C3** [code] `expandScene()` replaces a `last_removed_at` of `"now"` with the time it is called, in Unix seconds.
- **C4** [code] `expandScene()` skips malformed events and never throws on non-array input.
- **C5** [code] `OverlayRenderer.vue` adds the `message` listener for `handlePreviewScene` only when `props.sample` is true, and `handlePreviewScene` ignores any message whose `source` is not `window.parent` or whose `ol` is not `preview-scene`.
- **C6** [code] `handlePreviewScene` passes each step to `applyControlUpdate`, `handleTowerUpdated` or `handleListUpdated`; it makes no network request.
- **C7** [code] `playScene()` in `design.vue` posts a JSON copy of the scene's events, not the reactive prop.
- **C8** [code] `backToLive()` reloads the frame with a `reload=` nonce in the query string and the same token in the fragment.
- **C9** [test] `previewScene.test.ts` asserts every event in the Chat Tower and Follower Bowling manifests' scenes expands to at least one step.
- **C10** [test] `previewScene.test.ts` asserts the Tower scenes `standing`, `close`, `about` and `record` set a `tower:tower_room` whose danger level (under 0.8 is 2, under 1.8 is 1) is 0, 1, 2 and 0, with `about` above 0.
- **C11** [test] `RecipeDesignerBlockTest` asserts a duplicate scene key and an unknown scene event are both rejected.
- **C12** [test] `ProductTowerTest` asserts the Tower design page receives five scenes.
- **C13** [unverified] In Firefox on the local app, the Toppled scene produced 17 blocks with `data-falling="1"` and `data-banner="1"`, and Bowl filled the line and set a bowler; a first build posting the reactive prop delivered nothing to the frame.

### Unchanged
- The Plinko sample drops (`SampleChatGame`, Plinko's `sample_chat` extra) shipped in the earlier commit of this push, `feat(plinko): the designer preview plays itself with sample drops`, and are not in this diff.
- `handleTowerUpdated`, `applyControlUpdate` and `handleListUpdated` are what scenes call; none of them is changed.

### Risk
None for streams: an overlay outside the designer never registers the listener. A scene clicked
before the preview frame has loaded is lost; a second click plays it.
