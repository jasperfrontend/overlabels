## OL-2609-055 - feat(products): Chat Tower, the third product - the tower overlay, the manifest, the record list, the hero and the help page

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-055`

### Surface
- `resources/recipes/chat_tower/manifest.json` - new file, the listed product manifest
- `resources/recipes/chat_tower/tower.md` - new file, the tower overlay (eleven controls) exported from the `/engine` build
- `public/products/chat-tower-hero.svg` - new file, the product hero
- `resources/help/pages/chat-tower.md` - new file, the guide under `Bot & chat`
- `resources/help/pages/index.md` - the guide linked under `Bot & chat`
- `tests/Feature/ProductTowerTest.php` - new file
- `tests/Feature/ProductInstallTest.php` - the listed catalogue now has three slugs; the visitor listing has three products
- `tests/Feature/ProductBowlingTest.php` - Follower Bowling is the third listed product, not the second

### Claims
- **C1** [code] `resources/recipes/chat_tower/manifest.json` is `listed`, `requires_bot`, requires the `tower` integration, and installs one overlay (`tower.md`), the `tower` integration and a list with slug `tower_record`.
- **C2** [code] `tower.md` loops `[[[foreach:tower as block]]]` and `[[[foreach:c:list:tower_record as name]]]`, carries `data-falling="[[[tower.falling]]]"`, and declares ten expression controls plus one boolean `show_hint`.
- **C3** [code] The tower overlay's CSS contains no `[[[if`, no `[[[foreach` and no `??`, so its four moving tags stay on the renderer's fast path.
- **C4** [code] `public/products/chat-tower-hero.svg` contains no `c2pa` metadata.
- **C5** [code] `RecipeCatalog::listed()` returns `chat_checkin`, `chat_tower`, `follower_bowling` in that order.
- **C6** [test] `ProductTowerTest` asserts the listing position and hero, the product page's integration, list and overlay, an install creating the overlay with eleven controls, the enabled `tower` integration with eleven service controls and the `tower_record` list, the refusal over an existing `tower_record` list, and the integration and list wires.
- **C7** [test] `ProductInstallTest` and `ProductBowlingTest` assert the three-product catalogue.
- **C8** [code] `resources/help/pages/chat-tower.md` declares `section: Bot & chat`, no `context`, and is linked from `index.md` under the same heading.
- **C9** [unverified] The overlay was proven on the local account through the real bot endpoint: eight stacks rendered as blocks with the HUD, gauge and roster, a topple showed the banner and emptied the field, and the probe reported four bound `--ol-` properties with Echo connected. Frame rate was not sampled because the window was unfocused and `requestAnimationFrame` paused.

### Unchanged
- `RecipeInstaller`, `ProductController` and `WiringFacts::productSubject()` (OL-2609-038) install and report this product without change; nothing in `app/` is in the diff.
- `TowerService::RECORD_LIST_SLUG` (OL-2609-051) is the slug the manifest installs; the service is not in the diff.
- `docs/private/engine/chat-tower/` holds the build inputs and is gitignored, as every `/engine` folder is.

### Risk
Chat Tower appears on `/products` for every visitor on deploy. Installing it connects the `tower` integration and creates a list with slug `tower_record`; an account that already owns a list with that slug is refused with the validator's sentence.
