## Audit of OL-2609-088 - feat(products): a category per listed recipe, and /products becomes shelves with a sidebar filter

**Audited:** 2026-09-25
**Commit:** b943701edb2a63f265d0c1c23acccf763cfc568f
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/recipe-manifest.schema.json:88-92 @b943701` - `category` is `type: string`, `enum: ["product","alert"]`; `required` at :8 does not list it; top-level `additionalProperties: false` at :7, same as :7 @b943701^; unchanged @HEAD (:95-99) |
| C2 | CONFIRMED | `app/Services/Recipes/RecipeCatalog.php:22-31 @b943701` - keys `product`, `alert` in that order, each with `label` and `lead`; identical @HEAD |
| C3 | CONFIRMED | `app/Http/Controllers/ProductController.php:76 @b943701` - `sortBy(categoryRank(...), SORT_NUMERIC)`; `categoryRank()` :153-158 returns index in `array_keys(CATEGORIES)` or `count(CATEGORIES)`; `listed()` comes from `all()`, which `ksort`s by slug (`RecipeCatalog.php:51 @b943701`); PHP 8 sorts are stable; same @HEAD :82 |
| C4 | CONFIRMED | `ProductController.php:82-83 @b943701` - `is_string($category) && isFilter(...)` else null; `isFilter()` :132-139 allows `installed` only when authed, else `array_key_exists` on `CATEGORIES`; no abort on this path; same @HEAD :88-89 |
| C5 | CONFIRMED | `ProductController.php:85-94 @b943701` - filter matches `installed` or `category ===`; `shelf` null without a filter, else `shelfFor()` :144-151 `{label, lead}`; same @HEAD |
| C6 | CONFIRMED | `ProductController.php:107 @b943701` - `$user ? installedCount($user) : null`; :370-373 `count(array_intersect(installedSlugs(), array_keys(listed())))`; same @HEAD :124, :600-603 |
| C26 | CONFIRMED | `tests/Feature/ProductCategoryTest.php:233-255 @b943701` - installs `dice`, `coin_flip`, `chat_checkin`; asserts `installed_count` 1 and 1 product under `?category=installed`, and `installed_count` 1 on `/products/chat_checkin`. Ran `php artisan test --filter=ProductCategoryTest` on an extract of @b943701: passed. Passes @HEAD |
| C27 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a stashed tree |
| C7 | CONFIRMED | `ProductController.php:169-193 @b943701` - overlays first (`Alert`/`Overlay` by `OverlayMarkdown::parse()['type']`), then `Integration`, `List`, `Chat command` (list_appenders or bot_aliases), `array_values(array_unique())`; same @HEAD :186-210 |
| C8 | CONFIRMED | `ProductController.php:70 @b943701` - `$manifest['installs']['integrations'][0] ?? null`; same @HEAD :76 |
| C9 | CONFIRMED | `resources/js/layouts/ProductsLayout.vue:61,69,78 @b943701` - hrefs `/products?category=<key>`, `/products`, `/products?category=installed`, rendered through Inertia `<Link>`; `show.vue:281 @b943701` uses the same layout; `index.vue:53-72 @b943701` passes `props.products` through as one shelf under a filter and only splits by category under Show all; same @HEAD |
| C10 | CONFIRMED | `ProductsLayout.vue:73 @b943701` - Installed pushed only when `installedCount !== null`; `index.vue:182-183 @b943701` - "More to come!" `v-if="shelf.key === 'product'"`; same @HEAD (:83, :184) |
| C20 | CONFIRMED | `ProductsLayout.vue:37,121 @b943701` - `[{Products, /products}, {crumb}]` into `<Breadcrumbs>`; `Breadcrumbs.vue @b943701` renders the last item as `BreadcrumbPage` (so on the listing, where Products is the only crumb, it is not a link); `show.vue:281` passes `:crumb="product.name"`. @HEAD `<Breadcrumbs>` is gone from ProductsLayout and the crumbs go to `AppLayout` with `href: page.url` (49eca947, no claim) |
| C21 | CONFIRMED | `ProductsLayout.vue:133,145 @b943701` - root carries `[&>div]:w-full`, link styled via `data-[active]:`; `node_modules/reka-ui/dist/NavigationMenu/NavigationMenuLink.js:50` sets `"data-active": active ? "" : undefined`; same @HEAD :132,:144 |
| C22 | CONFIRMED | `index.vue:155-158 @b943701` - `ProductBadge v-if="product.category === 'product'"`, label "An official Overlabels product", `text-violet-400`; installed tag :145 is the only installed marker in the card (`absolute top-6 right-6`); same @HEAD |
| C23 | CONTRADICTED | `resources/js/pages/products/show.vue:307 @b943701` - the non-product branch renders `ServiceLogo`, not `ProviderIcon`; this contradicts C30 and the claim's own Surface line. Badge half true: :302 `v-if="product.category === 'product'"`, `text-violet-400`; green carried by :294 and :314. Same @HEAD :306-307 |
| C24 | CONFIRMED | `resources/js/components/ProductBadge.vue:15,20-34 @b943701` - `inheritAttrs: false`; `TooltipProvider > Tooltip > TooltipTrigger as-child > svg v-bind="$attrs"`; `TooltipContent side="top" align="center"`; else-branch svg `aria-hidden="true"` with `$attrs`; file unchanged @HEAD |
| C25 | CONFIRMED | `index.vue:158 @b943701` - `class="relative z-10 size-5 ..."`; same @HEAD |
| C28 | CONTRADICTED | Tile half true: `index.vue:100-119 @b943701` - `xl:grid-cols-5`, `ServiceLogo`, name, Installed tag, "Connects" line, no description/hero/chips. "Only the Products shelf renders the full card" is false: :121 `v-else-if` renders the full card for every shelf whose key is not `alert`, i.e. the `installed` shelf (:54-55) and the uncategorised `other` shelf (:67-69), so an installed alert renders as a full card with `ProviderIcon` under `?category=installed`. Same @HEAD :123 |
| C29 | CONFIRMED | Same text at `ProductsLayout.vue:114-115`, `index.vue:79`, `ProductController.php:98`, all @b943701; @HEAD it is the layout's `lead` fallback (:116-117, OL-2609-114) |
| C30 | CONFIRMED | `ServiceLogo.vue:25-44 @b943701` - `MARKS` keys exactly kofi, streamlabs, bmac, fourthwall, throne; :60 `ProviderIcon v-else`; used at `index.vue:104` and `show.vue:307`; unchanged @HEAD |
| C31 | CONFIRMED | `ProductsLayout.vue:100-102 @b943701` - `DarkModeToggle` after the Dashboard/Log in link; `AppSidebarHeader.vue:50 @b943701` uses the same component. @HEAD removed from ProductsLayout by 49eca947 (no claim) |
| C18 | CONFIRMED | `show.vue:281 @b943701` - `:category="product.category"`; `ProductController.php:278-279` - `categories()` and `installedCount()` (which calls `installedSlugs()`); same @HEAD show.vue:276, controller :350-351 |
| C19 | CONFIRMED | `ProductCategoryTest.php:211-231 @b943701` - `/products/kofi_alert`: `product.category` alert, 2 categories with counts 3 and 5, `installed_count` null; the account assertion (`installed_count` 1) is made on `/products/chat_checkin` (:228-230). Passed @b943701 and @HEAD |
| C11 | CONFIRMED | `index.vue:133-139 @b943701` - `v-else-if="product.service"` `aspect-video` box with `:class="serviceColor(...)"` (:48 = `eventTypeDotClass('product', service)`) and `ProviderIcon`; same @HEAD :140 |
| C12 | CONFIRMED | `index.vue:75 @b943701` - title; `ProductController.php:96-98 @b943701` - og title `<label> - Products - Overlabels`, description `$shelf['lead']`; same @HEAD |
| C13 | CONFIRMED | `ProductCategoryTest.php:39-77 @b943701` - enum === `array_keys(CATEGORIES)`, every listed manifest categorised, grouping 3 product / 5 alert, `game` fails at `/category`, `coin_flip` has no key and validates. Passed @b943701 |
| C14 | CONFIRMED | `ProductCategoryTest.php:83-146 @b943701` - exact 8-slug order; `?category=alert` 5 with shelf label and lead; `?category=product` 3 with shelf label; `games` and `category[]=alert` give 8 with `category` null. Passed @b943701 |
| C15 | CONTRADICTED | `ProductCategoryTest.php:148-166 @b943701` asserts chips for all four products and `service` for `chat_checkin` (checkin), `follower_bowling` (null) and `kofi_alert` (kofi); it does not assert `service` for `chat_tower` (:155-156 check only slug and installs). Test passed @b943701 |
| C16 | CONFIRMED | `ProductCategoryTest.php:168-201 @b943701` - visitor gets `category` null and 8; fresh account `installed`, `installed_count` 0, 0 products; after install exactly `['chat_checkin']`. Passed @b943701 |
| C17 | UNVERIFIABLE | tagged [unverified]; a manual view on `overlabels.test` |

### Surface
Complete.

### Findings
- **F1** contradicted claim - C23 says the alert product page header renders `ProviderIcon`, but `show.vue:307 @b943701` (and @HEAD :307) renders `ServiceLogo`; restate C23 in a new claim to match C30 and the Surface line.
- **F2** contradicted claim - C28's "only the Products shelf renders the full card" is false: `index.vue:121 @b943701` gives the full card to every non-`alert` shelf, including `installed` and the uncategorised `other` shelf, so installed alerts show as full cards with `ProviderIcon` under `?category=installed`; either restate the claim or decide whether alerts on the Installed shelf should be tiles.
- **F3** contradicted test claim - C15 says `ProductCategoryTest` asserts the service for `chat_tower`, but `ProductCategoryTest.php:155-156 @b943701` checks only its slug and chips; add `->where('products.1.service', 'tower')` or narrow the claim.
- **F4** declared unchanged but modified - Unchanged says `show.vue`'s header is not in the diff beyond the shell swap, but the diff rewrites the header at `show.vue:294-314 @b943701` (installed classes, badge `v-if`, new `ServiceLogo` branch, Installed line colours), which the Surface line itself lists; correct the Unchanged line in a new claim.
- **F5** scope - `installedCount()` was inserted between `installedSlugs()` and its `@return list<string>` docblock (`ProductController.php:361-375 @b943701`, still so @HEAD :592-605), so that docblock is orphaned and `installedSlugs()` lost its return type annotation; move the docblock back above `installedSlugs()`.
- **F6** scope - the Log in link's redirect changed from the fixed `/login?redirect_to=/products` (`index.vue @b943701^`) to `page.url` (`ProductsLayout.vue:35 @b943701`), and product pages gained a Log in link for visitors they did not have before; no claim or Surface line covers this. Superseded @HEAD by 49eca947, which removed the brand bar.
- **F7** contradiction with record - OL-2609-044's Surface records the product-listing badge as "badge in the heading and on each card, green when installed" (`OL-2609-044/claim.md:9`); this change drops the badge from alert cards and the alert page header and removes the green installed colour (`index.vue:155-158`, `show.vue:302-306 @b943701`) without citing OL-2609-044 inline; a new claim should cite it.
- **F8** changed without a claim - commit 49eca947 ("the products pages live inside the dashboard chrome") removed `<Breadcrumbs>` and `DarkModeToggle` from `ProductsLayout.vue`, which changes what C20 and C31 describe @HEAD. It has no `Changelog:` trailer. It touches only `.vue` files, so the path rule does not require a claim, but no record accounts for the drift.

### Notes
- Test runs on @b943701 used a `git archive` extract with HEAD's `vendor/` (hardlinked) and HEAD's `public/build` manifest, because the shipped `composer.json` still lists `stevebauman/location`. `ProductCategoryTest`, `ProductInstallTest`, `ProductBowlingTest` and `ProductTowerTest` all passed: 46 tests, 548 assertions. `ProductCategoryTest` also passed @HEAD (13 tests), with slugs renamed by OL-2609-114 and a fourth product added by OL-2609-089.
- OL-2609-114 C17 later added `robots`/`canonical` shares inside `index()`, and C18 added `heading`/`lead` to `ProductsLayout`. Both are disclosed drift.
- CLAUDE.md "Collection List" calls `CollectionList.vue` the one list component. `/products` was already a hand-rolled grid before this change, and this change adds a second one (the Alerts tiles). That was not raised as a finding because a card grid may not count as rows.
- Claims are numbered out of order in the file (C26, C27 follow C6; C20-C25 and C28-C31 come before C18).
