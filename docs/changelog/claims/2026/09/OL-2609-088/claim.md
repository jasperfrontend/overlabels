## OL-2609-088 - feat(products): a category per listed recipe, and /products becomes shelves with a sidebar filter

**Shipped:** 2026-09-16
**Commit:** `git log --grep=OL-2609-088`

### Surface
- `resources/recipes/recipe-manifest.schema.json` - new optional top-level `category` property, enum `product` | `alert`
- `resources/recipes/chat_checkin/manifest.json` - `"category": "product"`
- `resources/recipes/chat_tower/manifest.json` - `"category": "product"`
- `resources/recipes/follower_bowling/manifest.json` - `"category": "product"`
- `resources/recipes/bmac_alert/manifest.json` - `"category": "alert"`
- `resources/recipes/fourthwall_alert/manifest.json` - `"category": "alert"`
- `resources/recipes/kofi_alert/manifest.json` - `"category": "alert"`
- `resources/recipes/streamlabs_alert/manifest.json` - `"category": "alert"`
- `resources/recipes/throne_alert/manifest.json` - `"category": "alert"`
- `app/Services/Recipes/RecipeCatalog.php` - new `CATEGORIES` constant (key => label + lead)
- `app/Http/Controllers/ProductController.php` - `index()` reads `?category=`, filters and sorts server-side, publishes `categories`, `category`, `shelf`, `installed_count`, and per-product `category`, `service`, `installs`; `show()` publishes `categories`, `installed_count` and `product.category`; new private `INSTALLED_FILTER`, `isFilter()`, `shelfFor()`, `categoryRank()`, `installChips()`, `categories()`, `installedCount()`
- `resources/js/layouts/ProductsLayout.vue` - new file: the /products shell (brand bar with `DarkModeToggle`, Products heading, `Breadcrumbs` starting at Products, category sidebar built on `NavigationMenu` with an icon and count per entry, a divider between sidebar and content, default slot)
- `resources/js/pages/products/index.vue` - rewritten inside `ProductsLayout`: one shelf per category with heading and lead, service icon in place of a missing hero, install chips, "Connects <service>" line, per-filter `<title>`
- `resources/js/pages/products/show.vue` - its own brand bar and "All products" link replaced by `ProductsLayout`; content wrapped in `max-w-4xl`; new `categories` and `installed_count` props and `product.category`; header badge only for `category === 'product'`, always violet, with `ServiceLogo` for an alert's service in its place; the installed header is opaque `bg-green-50` with `text-green-700` in light mode and keeps `bg-green-950/40` / `text-green-400` under `dark:`
- `resources/js/components/ProductBadge.vue` - with a `label`, the svg is a `TooltipTrigger` inside its own `TooltipProvider`, with the label as a top-centred `TooltipContent`; `inheritAttrs: false` with `$attrs` bound onto the svg
- `resources/js/components/ServiceLogo.vue` - new file: a service's brand mark as one `currentColor` path with its own viewbox (kofi, streamlabs, bmac from Simple Icons; fourthwall and throne from their brand asset kits), falling back to `ProviderIcon` for a service without one
- `tests/Feature/ProductCategoryTest.php` - new file
- `tests/Feature/ProductInstallTest.php` - two index-order assertions moved to the new positions
- `tests/Feature/ProductBowlingTest.php` - index-order assertions moved (bowling 3 -> 2, checkin 1 -> 0)
- `tests/Feature/ProductTowerTest.php` - index-order assertion moved (tower 2 -> 1)

### Claims
- **C1** [code] The schema's `properties.category` is `{"type": "string", "enum": ["product", "alert"]}` and is not in `required`; `additionalProperties: false` at the top level is unchanged.
- **C2** [code] `RecipeCatalog::CATEGORIES` has exactly the keys `product` and `alert`, in that order, each with a `label` and a `lead` string.
- **C3** [code] `ProductController::index()` sorts the listed manifests by `categoryRank()` (position of the category in `CATEGORIES`, uncategorised last) with a stable sort, so slug order is kept within a shelf.
- **C4** [code] `index()` accepts `?category=` only when it is a string that is a key of `CATEGORIES`, or `installed` for an authenticated request; anything else renders Show all with `category` null, never a 404.
- **C5** [code] With a filter, `products` is only the products whose `category` equals it (or whose `installed` is true for `installed`), and `shelf` is `{label, lead}` for it; without one `shelf` is null.
- **C6** [code] `installed_count` is null for a visitor and, for an account, `installedCount()`: the size of `installedSlugs()` intersected with the keys of `RecipeCatalog::listed()`, so an instance of an unlisted recipe never counts.
- **C26** [test] `ProductCategoryTest` asserts an account holding `dice` and `coin_flip` instances plus an installed `chat_checkin` gets `installed_count` 1 on the listing and the product page, and one card under `?category=installed`.
- **C27** [unverified] C26 was run with the `ProductController.php` change stashed and failed; with it restored it passed.
- **C7** [code] `installChips()` yields, in order and deduplicated: `Alert` or `Overlay` per overlay document by its parsed `type`, then `Integration`, `List`, `Chat command` when `installs.integrations`, `installs.lists`, and `installs.list_appenders` or `installs.bot_aliases` are non-empty.
- **C8** [code] Per product, `service` is `installs.integrations[0]` or null.
- **C9** [code] `ProductsLayout.vue` renders every sidebar entry as an Inertia `<Link>` to `/products` or `/products?category=<key>`, on the product page too; `index.vue` does no client-side filtering of `props.products` except the split into shelves under Show all.
- **C10** [code] `ProductsLayout.vue` renders the `Installed` link only when `installedCount` is not null; `index.vue` renders the "More to come!" placeholder only in the shelf whose key is `product`.
- **C20** [code] `ProductsLayout.vue` renders `Breadcrumbs` with `Products` linked to `/products` and, when the `crumb` prop is set, that text as the current page; `show.vue` passes `product.name` as `crumb`.
- **C21** [code] The sidebar's active entry is styled through `data-[active]` (reka sets `data-active=""`, not `"true"`), and the `NavigationMenu` root carries `[&>div]:w-full` because reka wraps the list in a positioned div sized to content.
- **C22** [code] On a listing card `ProductBadge` renders only when `product.category === 'product'`, always `text-violet-400`, with the label "An official Overlabels product"; the installed state is one absolutely positioned tag over the artwork box and appears nowhere else on the card.
- **C23** [code] On the product page the header renders `ProductBadge` in `text-violet-400` only for `category === 'product'`, and `ProviderIcon` for `product.integrations[0]` otherwise; the installed colour is carried by the header's green classes and the Installed line, not the badge.
- **C24** [code] `ProductBadge.vue` renders `TooltipProvider > Tooltip > TooltipTrigger(as-child) > svg` when `label` is set, with `TooltipContent side="top" align="center"` showing the label, and a bare `aria-hidden` svg otherwise; caller classes reach the svg through `v-bind="$attrs"` under `inheritAttrs: false`.
- **C25** [code] On a listing card the badge carries `relative z-10`, so it sits above the card's stretched `<Link>` and can be hovered.
- **C28** [code] The Alerts shelf renders as compact tiles (service icon, name, Installed tag, "Connects <service>"), five across at `xl`, with no description, hero box or chips; only the Products shelf renders the full card.
- **C29** [code] The page lead in `ProductsLayout.vue`, the default meta description in `index.vue` and the default `og` description in `ProductController::index()` are the same sentence, beginning "Everything here is free, made by Overlabels".
- **C30** [code] The Alerts tiles on the listing and the alert product page header render `ServiceLogo`, not `ProviderIcon`; `ServiceLogo` has marks for exactly `kofi`, `streamlabs`, `bmac`, `fourthwall` and `throne` and renders `ProviderIcon` for any other source.
- **C31** [code] `ProductsLayout.vue` renders `DarkModeToggle` in the brand bar beside the Dashboard or Log in link, the same component `AppSidebarHeader.vue` uses.
- **C18** [code] `show.vue` passes `product.category` as the layout's active entry, so a product page highlights its own shelf, and `show()` publishes `categories` and `installed_count` from the same `categories()` and `installedSlugs()` the listing uses.
- **C19** [test] `ProductCategoryTest` asserts `/products/kofi_alert` carries `product.category` `alert`, both categories with counts 3 and 5, `installed_count` null for a visitor and 1 for an account that installed `chat_checkin`.
- **C11** [code] A card with no `hero` and a `service` renders `ProviderIcon` for that service inside an `aspect-video` box coloured by `useEventColors().eventTypeDotClass('product', service)`.
- **C12** [code] The `<title>` is `<shelf label> - Products` when `shelf` is set and `Products` otherwise; the `og` view share uses the same label and the shelf's lead.
- **C13** [test] `ProductCategoryTest` asserts the schema enum equals `array_keys(RecipeCatalog::CATEGORIES)`; every listed manifest has a category in the taxonomy; the three chat products are `product` and the five `*_alert` recipes are `alert`; a manifest with `category: game` fails validation at `/category` and `coin_flip` (no category) passes.
- **C14** [test] `ProductCategoryTest` asserts Show all orders `chat_checkin, chat_tower, follower_bowling, bmac_alert, fourthwall_alert, kofi_alert, streamlabs_alert, throne_alert`; `?category=alert` yields 5 and `?category=product` yields 3 with the matching `shelf`; `?category=games` and `?category[]=alert` yield all 8 with `category` null.
- **C15** [test] `ProductCategoryTest` asserts the chips and service for `chat_checkin`, `chat_tower`, `follower_bowling` and `kofi_alert` as C7 and C8 describe.
- **C16** [test] `ProductCategoryTest` asserts `?category=installed` is Show all for a visitor, an empty shelf with `installed_count` 0 for a fresh account, and exactly `chat_checkin` after that account installs it.
- **C17** [unverified] The page was viewed at desktop width on `overlabels.test` as a visitor, on Show all and on `?category=alert`; the phone-width row of links was not screenshotted.

### Unchanged
- `ProductController::install()`, `uninstall()` and `dismissSetup()` are not in the diff: the category is a listing concern and nothing in the install reads it.
- `show.vue`'s header, install button, setup steps and installed view are not in the diff beyond the shell swap: the layout replaces only the brand bar and the "All products" link, which the sidebar's Show all now covers.
- `RecipeCatalog::sync()` is not in the diff: the `recipes` table row keeps the whole manifest in its `manifest` column, so the category is stored there without a new column or migration, and nothing queries recipes by category.
- `Settings/IntegrationController::productIntegrations()` reads `listed()` for the service-to-product map; `listed()` is unchanged and that method is not in the diff.
- `coin_flip` and `dice` manifests are not in the diff: they are unlisted and the key is optional.
- `useEventColors.ts`, `providerIcons.ts`, `ProviderIcon.vue` and `services.ts` are not in the diff: the card reuses their existing service colours, shapes and labels as they stand.

### Risk
None for existing data. A listed manifest added later without a `category` still validates and shows under Show all after both shelves, and under no filter.
