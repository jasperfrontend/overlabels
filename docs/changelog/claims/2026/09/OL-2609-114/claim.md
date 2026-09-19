## OL-2609-114 - feat(products): hyphenated product URLs, 301s from the old ones, and the SEO head they never had

**Shipped:** 2026-09-20
**Commit:** `git log --grep=OL-2609-114`

### Surface
- `resources/recipes/{bmac_alert => buy-me-a-coffee-alerts}/manifest.json` - slug, `url_aliases`
- `resources/recipes/{bmac_alert => buy-me-a-coffee-alerts}/tip_alert.md` - moved with its manifest
- `resources/recipes/{chat_checkin => chat-checkin}/manifest.json` - slug, `url_aliases`
- `resources/recipes/{chat_checkin => chat-checkin}/globe.md` - moved with its manifest
- `resources/recipes/{chat_tower => chat-tower}/manifest.json` - slug, `url_aliases`
- `resources/recipes/{chat_tower => chat-tower}/tower.md` - moved with its manifest
- `resources/recipes/{follower_bowling => follower-bowling}/manifest.json` - slug, `url_aliases`
- `resources/recipes/{follower_bowling => follower-bowling}/lane.md` - moved with its manifest
- `resources/recipes/{fourthwall_alert => fourthwall-alerts}/manifest.json` - slug, `url_aliases`
- `resources/recipes/{fourthwall_alert => fourthwall-alerts}/tip_alert.md` - moved with its manifest
- `resources/recipes/{kofi_alert => ko-fi-alerts}/manifest.json` - slug, `url_aliases`
- `resources/recipes/{kofi_alert => ko-fi-alerts}/tip_alert.md` - moved with its manifest
- `resources/recipes/{streamlabs_alert => streamlabs-alerts}/manifest.json` - slug, `url_aliases`
- `resources/recipes/{streamlabs_alert => streamlabs-alerts}/tip_alert.md` - moved with its manifest
- `resources/recipes/{throne_alert => throne-alerts}/manifest.json` - slug, `url_aliases`
- `resources/recipes/{throne_alert => throne-alerts}/tip_alert.md` - moved with its manifest
- `resources/recipes/{twitch_chat => twitch-chat-overlay}/manifest.json` - slug, `url_aliases`, `name` "Twitch Chat" becomes "Twitch Chat Overlay"
- `resources/recipes/{twitch_chat => twitch-chat-overlay}/chat.md` - product URL in the footer line, `generator` meta
- `resources/recipes/recipe-manifest.schema.json` - slug `pattern` admits a hyphen, new `url_aliases` property
- `database/migrations/2026_09_19_120000_rename_product_slugs_to_url_form.php` - new file, renames `recipes.slug`
- `app/Services/Recipes/RecipeCatalog.php` - `canonicalSlugFor()`, `find()` regex admits a hyphen
- `app/Services/Recipes/RecipeManifestValidator.php` - refuses a hyphenated slug on a manifest with `control_exports`
- `app/Models/RecipeInstance.php` - `instanceSlugFrom()`
- `app/Http/Controllers/ProductController.php` - `movedPermanently()`, `canonical()`, install uses `instanceSlugFrom()`, `canonical`/`jsonLd`/`robots` shares
- `app/Http/Controllers/SitemapController.php` - `RecipeCatalog` injected, `/products` and every listed product emitted
- `app/Support/ChatPresets.php` - `PRODUCT` constant follows the rename
- `routes/web.php` - five product slug constraints admit a hyphen
- `resources/js/composables/useUiMode.ts` - `SLUG` regex admits a hyphen
- `resources/js/layouts/ProductsLayout.vue` - optional `heading`/`lead` props
- `resources/js/pages/products/show.vue` - passes the product as `heading`/`lead`, drops the duplicated `h1` and description
- `resources/help/pages/chat.md` - `keywords`, a tip linking the product, a See also entry
- `resources/help/pages/tutorials/show-chat-on-screen.md` - Where next entry linking the product
- `resources/help/pages/chat-tower.md` - product link follows the rename
- `tests/Feature/ProductSlugRenameTest.php` - new file
- `tests/Feature/ProductCategoryTest.php` - slug literals; the alert-shelf assertion now expects `-alerts`
- `tests/Feature/ProductChatDesignerTest.php` - slug literals, `RecipeInstance` import, install derives its instance slug
- `tests/Feature/ProductUninstallTest.php` - slug literals, `installProduct()` helper derives its instance slug
- `tests/Feature/ProductInstallTest.php` - slug literals
- `tests/Feature/ProductServiceConnectTest.php` - slug literals
- `tests/Feature/ProductSetupFlowTest.php` - slug literals
- `tests/Feature/ProductDonationServicesTest.php` - slug literals
- `tests/Feature/ProductBowlingTest.php` - slug literals
- `tests/Feature/ProductTowerTest.php` - slug literals
- `tests/Feature/ProductTwitchChatTest.php` - slug literals
- `tests/Feature/ProductChatInstallsTest.php` - slug literals
- `tests/Feature/ProductChatPresetsTest.php` - slug literals
- `tests/Feature/ProductInstallRateLimitTest.php` - slug literals
- `tests/Feature/IntegrationReadinessTest.php` - slug literals
- `tests/Feature/IntegrationsPageOrderTest.php` - slug literals
- `tests/Feature/BotModeratedChannelsTest.php` - slug literals
- `resources/js/composables/useUiMode.test.ts` - slug literals
- `resources/js/composables/useAddressableTabs.test.ts` - slug literals
- `resources/js/utils/integrationRows.test.ts` - slug literals

### Claims
- **C1** [code] The nine listed manifests carry the slugs `buy-me-a-coffee-alerts`, `chat-checkin`, `chat-tower`, `follower-bowling`, `fourthwall-alerts`, `ko-fi-alerts`, `streamlabs-alerts`, `throne-alerts`, `twitch-chat-overlay`, each equal to its own `name` kebab-cased.
- **C2** [code] Each of those nine carries its former slug in `url_aliases`, and no `url_aliases` value is repeated across manifests.
- **C3** [code] `coin_flip` and `dice` keep snake_case slugs and gain no `url_aliases`. Both are `listed: false` and neither has a `/products` URL.
- **C4** [code] `RecipeCatalog::canonicalSlugFor()` returns the current slug of the manifest listing the argument in `url_aliases`, else null.
- **C5** [code] `ProductController::show()` and `design()` answer HTTP 301 to the canonical route when the URL slug is an alias, before any other work in the method.
- **C6** [code] `ProductController::install()`, `uninstall()` and `applyPreset()` each replace `$slug` with `canonical($slug)` on their first line, so a form posted with an old slug resolves to the existing install rather than building a second one.
- **C7** [code] `RecipeInstance::instanceSlugFrom()` returns the argument with hyphens replaced by underscores, and `ProductController::install()` passes its result to `RecipeInstaller::install()` rather than the product slug.
- **C8** [code] `RecipeInstance::SLUG_PATTERN` is unchanged at `/^[a-z][a-z0-9_]{0,49}$/`.
- **C9** [code] The migration updates `recipes.slug` for exactly those nine values, holds the map as a literal, names no Eloquent model, and its `down()` reverses each one.
- **C10** [code] The migration writes no other table. `recipe_instances.recipe_id` is a foreign key to `recipes.id`, so instances follow the rename without being written.
- **C11** [code] `RecipeManifestValidator::semanticErrors()` adds an error at pointer `/slug` when the slug contains a hyphen and `control_exports` is non-empty.
- **C12** [code] The schema's slug `pattern` is `^[a-z][a-z0-9_-]{0,49}$` and `url_aliases` is declared as an array of strings with `uniqueItems`.
- **C13** [code] All five product slug route constraints in `routes/web.php` are `[a-z][a-z0-9_-]*`; the `preset` constraint beside one of them stays `[a-z][a-z0-9_]*`.
- **C14** [code] `useUiMode.ts` `SLUG` is `/^[a-z][a-z0-9_-]{0,49}$/`, so `?state=product&product=<slug>` still resolves for a hyphenated product.
- **C15** [code] `SitemapController` emits `/products` and one `<loc>` per `RecipeCatalog::listed()` slug, neither hand-listed. Before this change the sitemap contained no `/products` URL at all.
- **C16** [code] `ProductController::show()` shares `canonical` and a `jsonLd` `@graph` holding one `SoftwareApplication` node and one `BreadcrumbList` node.
- **C17** [code] `ProductController::index()` shares `robots` of `noindex, follow` and a `canonical` of the bare listing when `category` is `installed`, and a self-canonical otherwise.
- **C18** [code] `ProductsLayout.vue` renders `heading`/`lead` when given and the catalogue's own copy when not; `index.vue` passes neither.
- **C19** [code] `show.vue` holds no `<h1>` and no second render of `product.description`, so the page has one `h1` and states the description once.
- **C20** [test] `ProductSlugRenameTest` asserts the 301 for every alias, that every listed product has one, that the sitemap names current slugs and no alias, that a hyphenated product installs under a `SLUG_PATTERN`-legal instance slug, and that the validator refuses a hyphenated slug beside `control_exports`.
- **C21** [unverified] Two of those were run against a tree with `movedPermanently()` stubbed to return null and the validator guard short-circuited, and both failed. C7 failed on the unmodified rename before `instanceSlugFrom()` existed: 33 product tests threw `Instance slug must match`.
- **C22** [unverified] `php artisan test` passes whole at 2307 passed and 6 skipped; `npm test` at 324 passed; `pint`, `format:check`, `lint:check` and `typecheck` clean.

### Unchanged
- `recipe_instances.instance_slug` keeps whatever it was given at install time. The migration does not write it and no lookup matches a product slug against it: `ProductController::instanceFor()` and `ProductSetup::instanceFor()` both resolve through `Recipe::where('slug', ...)` and the `recipe_id` foreign key, which is why renaming one column is enough.
- `RecipeInstaller::primitiveSlug()` still builds an overlay's slug as `"<instance_slug>_<ref>"` and is not in the diff. Overlay slugs already stored are therefore untouched, and an OBS browser source keeps working across the rename.
- `OverlayControl::broadcastKey()` still spells a recipe-managed control `"<recipe_slug>:<instance_slug>:<key>"` and is not in the diff. Only `coin_flip` and `dice` declare `control_exports` and neither was renamed, so no `[[[c:...]]]` tag in a user-owned overlay changes.
- `RecipeCatalog::sync()` is not in the diff and still upserts on `(slug, version)`. It follows that `recipes.name` on an existing row is refreshed only by an install or the seeder, so `/dashboard/recipes` shows the pre-rename name for a product nobody has installed since the deploy.
- The og `image` for a product page is still unset and still falls back to `/ogimage.jpg`. The hero art that `jsonLd.image` points at is SVG, which structured data accepts and which is not what a social scraper reads.

### Risk
Every `/products/<old_slug>` URL changes. The old URLs answer 301, so links in the help corpus, in
chat and in anything already shared keep working, and no `/products` URL was in the sitemap before
this change, so none is indexed under its old form.

**The migration is required.** `ProductController` finds an account's install by joining `recipes`
and matching `slug`; without the rename every installed product would read as not installed, offer
Install again, and empty the Installed shelf.
