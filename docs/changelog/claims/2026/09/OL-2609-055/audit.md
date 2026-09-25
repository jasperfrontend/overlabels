## Audit of OL-2609-055 - feat(products): Chat Tower, the third product - the tower overlay, the manifest, the record list, the hero and the help page

**Audited:** 2026-09-25
**Commit:** fc4347396f7fef177b66b63e208a3c370ec2234b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/chat_tower/manifest.json @fc43473` - `"listed": true` (l.15), `"requires_bot": true`, `"requires_integrations": ["tower"]`, `installs.overlays` = `[{ref: tower, file: tower.md}]`, `installs.integrations` = `["tower"]`, `installs.lists` = `[{ref: record, slug: tower_record}]`. @HEAD the file is `resources/recipes/chat-tower/manifest.json`, slug `chat-tower` with `url_aliases: ["chat_tower"]` (OL-2609-114) and `"category": "product"` (OL-2609-088); the six asserted fields are unchanged |
| C2 | CONFIRMED | `resources/recipes/chat_tower/tower.md @fc43473` - l.38 `data-falling="[[[tower.falling]]]"`, l.49 `[[[foreach:c:list:tower_record as name]]]`, l.67 `[[[foreach:tower as block]]]`; Controls table l.553-565 lists ten `expression` rows and `c:show_hint` `boolean`. Same content @HEAD in `chat-tower/tower.md` |
| C3 | CONFIRMED | `tower.md @fc43473` css fence l.101-547 contains exactly four tags (`c:sway`, `c:cam`, `c:lean_pct`, `c:band_pct`, css l.3-6) and no `[[[if`, `[[[foreach` or `??`; the bail conditions are `resources/js/utils/tagParser.ts:83,106 @fc43473` (`compileCssBindings`) |
| C4 | CONFIRMED | `git show fc43473:public/products/chat-tower-hero.svg \| grep -ci c2pa` = 0; same @HEAD |
| C5 | CONFIRMED | `app/Services/Recipes/RecipeCatalog.php:25-47 @fc43473` - `all()` `ksort`s by slug, `listed()` filters `listed`; the three `"listed": true` manifests @fc43473 are `chat_checkin`, `chat_tower`, `follower_bowling`. @HEAD slugs are hyphenated (OL-2609-114) and more products are listed (OL-2609-077, -084, -089) |
| C6 | CONFIRMED | `tests/Feature/ProductTowerTest.php @fc43473` - five tests cover `products.1.slug`/`.hero` + no `c2pa`, product page `integrations.0`/`lists.0.slug`/`overlays.0.name`, install with 11 template controls (10 expression), enabled `tower` integration with 11 `source_managed` `tower` controls and the `tower_record` list, the `slug 'tower_record'` refusal, and the integration/list/command wires. Ran `php artisan test --filter='ProductTowerTest\|ProductInstallTest\|ProductBowlingTest'` in a worktree @fc43473: 33 passed. Same filter @HEAD: 33 passed |
| C7 | CONTRADICTED (half) | `ProductInstallTest` half CONFIRMED: `tests/Feature/ProductInstallTest.php @fc43473` asserts `array_keys($listed)` is `['chat_checkin', 'chat_tower', 'follower_bowling']` and `->has('products', 3)`; passed. `ProductBowlingTest` half CONTRADICTED: `tests/Feature/ProductBowlingTest.php:47-49 @fc43473` asserts only `products.2.slug` = `follower_bowling`, `products.2.hero`, and `products.0.hero`; it asserts no product count and does not name `chat_tower`. Passed |
| C8 | CONFIRMED | `resources/help/pages/chat-tower.md:3 @fc43473` `section: Bot & chat`, no `context:` line in the frontmatter (l.1-8); `resources/help/pages/index.md @fc43473` links `/help/chat-tower` under `### Bot & chat` (l.92). @HEAD two links changed (OL-2609-114, OL-2609-129) |
| C9 | UNVERIFIABLE | tagged [unverified]; runtime observations on a local account |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - C7 says `ProductBowlingTest` asserts the three-product catalogue, but `tests/Feature/ProductBowlingTest.php:47-49 @fc43473` only pins follower_bowling at index 2 and checkin's hero at index 0, with no count and no `chat_tower`; a later claim should restate C7 as covering `ProductInstallTest` only, or the bowling test should assert the count.
- **F2** contradicts CLAUDE.md - `resources/help/pages/chat-tower.md:7 @fc43473` declares `record` and `tower` as keywords, while `record` is in the h2 `## The record list` (l.104) and `tower` is in `heading: Chat Tower` (l.5), against CLAUDE.md @fc43473 l.558-561 "do not pad a page's keywords with words already in its headings". The line is unchanged @HEAD. Drop those two keywords, or record why they stay.

### Notes
- The C6/C7 run @fc43473 needed `composer install` in a worktree (HEAD `vendor/` lacks `stevebauman/location`) and HEAD's `public/build` symlinked in. Without that, the HTTP tests threw `ViteManifestNotFoundException`, which is an environment problem and not a test failure.
- `tower.md @fc43473` l.13, l.637, l.639 point at `https://overlabels.test/...` (a dev host and a local overlay slug). This is prose, and `OverlayMarkdown::parse` does not interpret it (docblock `app/Support/OverlayMarkdown.php:17-21 @fc43473`). The text is still there @HEAD.
- Later drift, all disclosed: folder and slug renamed (OL-2609-114), `category` added (OL-2609-088), Google Fonts link in `tower.md` head swapped to Bunny (OL-2609-119), help link to `/lists` (OL-2609-129).
