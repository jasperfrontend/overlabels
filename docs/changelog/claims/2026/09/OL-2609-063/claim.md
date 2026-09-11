## OL-2609-063 - feat(settings): product integrations get their own spot, the rest sort by connection state then A-Z

**Shipped:** 2026-09-11
**Commit:** `git log --grep=OL-2609-063`

### Surface
- `app/Http/Controllers/Settings/IntegrationController.php` - `index()` takes a `RecipeCatalog`, adds `product` to every service entry, sorts the list; new private `productIntegrations()`
- `resources/js/pages/settings/integrations/index.vue` - `ServiceInfo.product`, the `productServices` / `externalServices` split, a new "Overlabels products" section above "External Integrations", the filter now scoped to the external list
- `tests/Feature/IntegrationsPageOrderTest.php` - new file, three tests over `GET /settings/integrations`

### Claims
- **C1** [code] `IntegrationController::index()` sorts `services` with `usort`: entries whose `connected` is false come before those whose `connected` is true, and within each half by `strcasecmp` on `name`. Registry order (`ExternalServiceRegistry::$drivers`, insertion order, which put `tower` last) no longer reaches the page.
- **C2** [code] Every entry in `services` carries a `product` key: the slug of the listed product manifest whose `requires_integrations` names the service (`checkin` -> `chat_checkin`, `tower` -> `chat_tower`), or `null`. `productIntegrations()` reads this from `RecipeCatalog::listed()`; there is no hand-kept list of product services.
- **C3** [code] `index.vue` splits `props.services` into `productServices` (`product !== null`) and `externalServices` (`product === null`), both computed, both keeping the server order.
- **C4** [code] `index.vue` renders a section headed "Overlabels products" above "External Integrations", `v-if` on `productServices.length > 0`, as a `md:grid-cols-2` grid. Each card carries `ProductBadge` (green when connected, violet-400 otherwise), a matching `border-green-500/60` / `border-violet-400/60` border, the test-mode flask, the last-event line when connected, and the same Manage / Connect link to `/settings/integrations/{url_slug}` the external rows use.
- **C5** [code] A not-connected product card says "Not connected. Install the product to connect it." with "Install the product" linking to `/products/{product}`.
- **C6** [code] `useCollectionFilter` on that page now takes `externalServices` as its source, so the filter box and the "No integrations match" message cover the third-party list only; the filter box is shown when `externalServices.length > 0`.
- **C7** [test] `IntegrationsPageOrderTest` > "with nothing connected every service is listed A-Z by name" asserts the `services` prop's names equal their `strcasecmp`-sorted copy and include Chat Tower, Ko-fi and Throne.
- **C8** [test] `IntegrationsPageOrderTest` > "connected services sink below the disconnected ones, each half still A-Z" connects `kofi` and `bmac` and asserts the connected names are exactly `['Buy Me a Coffee', 'Ko-fi']`, the full list is the disconnected names followed by the connected names, and the disconnected half is sorted.
- **C9** [test] `IntegrationsPageOrderTest` > "the checkin and tower integrations carry their product slug and the third-party services carry none" asserts `checkin` -> `chat_checkin`, `tower` -> `chat_tower`, and `product` is null for kofi, streamlabs, fourthwall, bmac, throne and gps.
- **C10** [unverified] All three tests were run with the controller change stashed and failed (C7 and C8 on order, C9 on the missing `product` key), then passed with it restored.
- **C11** [unverified] Rendered on `overlabels.test` for an account with checkin, tower, bmac, kofi, gps and throne connected: the two product cards sit above the external list; the external list reads Fourthwall, Streamlabs, Buy Me a Coffee, Ko-fi, Overlabels GPS, Throne.

### Unchanged
- `ExternalServiceRegistry::$drivers` keeps its order and `services()` still returns it; `ExternalServiceRegistry::displayName()` is untouched. Nothing else reads the registry order for display.
- `Overlabels GPS` is not a product: no listed manifest requires `gps`, so it stays in the external list. Marking it would mean a manifest, not a page edit.
- The Twitch and bot panels, `useProductTarget('bot-toggle')` and the `product-target` styling are not in the diff. The product cards do not read the product UI mode: this page is not a step a product install sends anyone to.
- The per-service settings pages under `/settings/integrations/{slug}` and every driver are untouched.

### Risk
`index()` now reads and validates every manifest under `resources/recipes/` on each visit to `/settings/integrations` (six files today, same cost `ProductSetup::banner()` already pays per page for one). A manifest that fails `RecipeManifestValidator` throws from `RecipeCatalog::read()` and would take this page down with it, the same way it already takes `/products` down.
