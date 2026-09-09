## OL-2609-038 - feat(products): Chat Checkin and Follower Bowling are installable products, on a public /products page

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-038`

### Surface
- `resources/recipes/recipe-manifest.schema.json` - `primitives`, `control_exports` and `triggers` no longer required; `pickers` and `control_exports` no longer need one item; new optional `listed`, `requires_bot`, `hero`, `notes`, `installs` (`overlays[]` of `{ref, file}`, `integrations[]`, `lists[]` of `{ref, slug, label}`, `list_appenders[]` of `{command, list, value, permissions, cooldown_seconds, dedup_policy}`, `bot_aliases[]` of `{command, target, permissions, cooldown_seconds, hidden}`, `bot_commands[]` of `{command, reply, permissions, cooldown_seconds, hidden}`)
- `resources/recipes/chat_checkin/manifest.json` - new: the product manifest, `listed: true`, one overlay, one integration
- `resources/recipes/chat_checkin/globe.md` - new: the globe overlay in the format `OverlayMarkdown::parse()` reads
- `resources/recipes/follower_bowling/manifest.json` - new: `listed: true`, `hero`, one overlay, one list, one appender, two moderator aliases (`!fbfirst`, `!fbdraw`)
- `resources/recipes/follower_bowling/lane.md` - new: the lane overlay derived from the public prod export `tiber-rakahanga-bologna-apennine`, front matter and description rewritten, `Requirements` and `Copying` sections dropped, the two `DO NOT DELETE` comments dropped
- `public/products/follower-bowling-hero.svg` - new: the hero artwork with its C2PA `<metadata>` block and `xmlns:c2pa` attribute removed
- `public/products/chat-checkin-hero.svg` - new: same treatment; 200 KB because the globe is 5,189 `<use>` dots, no raster
- `app/Services/Recipes/RecipeInstaller.php` - `installOverlay()`, `connectIntegration()`, `installList()`, `installListAppender()`, `validatedAliases()`, `validatedCommands()`, `firstMessage()`, `directoryFor()`; the three picker loops tolerate an absent section; `assertNoChatCommandCollisions()` also gathers the commands of `installs.list_appenders`, `installs.bot_aliases` and `installs.bot_commands` and checks `ListAppender` and `BotAlias`; `ExternalControlService`, `BotAliasValidator`, `BotCommandValidator` and `BotCounterService` injected
- `app/Services/Recipes/RecipeCatalog.php` - new: reads manifests from disk, `all()`, `listed()`, `find()`, `sync()`
- `database/seeders/FirstPartyRecipesSeeder.php` - delegates to `RecipeCatalog::all()` and `sync()`
- `app/Http/Controllers/ProductController.php` - new: `index` (with `hero`), `show` (with `lists` and `commands`), `install`
- `routes/web.php` - `products.index` and `products.show` outside the auth group, `products.install` inside it
- `app/Support/WiringCatalog.php` - seven `product.*` wires and a `products` circuit
- `app/Support/WiringFacts.php` - `productSubjects()` and public `productSubject()`
- `resources/js/pages/products/index.vue` - new: public list page, hero image above a card when the manifest names one
- `resources/js/pages/products/show.vue` - new: public product page with the install button, the installed checklist, and the lists and commands an install creates
- `resources/js/components/AppSidebar.vue` - `Products` entry after `Kits`
- `.prettierignore` - `resources/recipes/**/*.md`
- `tests/Feature/ProductInstallTest.php` - new file, 18 tests
- `tests/Feature/ProductBowlingTest.php` - new file, 8 tests
- `tests/Feature/ProductChatInstallsTest.php` - new file, 7 tests

### Claims
- **C1** [code] `RecipeManifestValidator` accepts the `chat_checkin` manifest, which has no `primitives`, `control_exports` or `triggers` key.
- **C2** [test] `RecipeManifestValidatorTest` "accepts every shipped first-party manifest" still passes for `coin_flip` and `dice`, so the relaxed schema accepts the old shape unchanged.
- **C3** [code] `RecipeInstaller::install()` creates one `overlay_templates` row per `installs.overlays[]` entry, owned by the installing user, `is_public` false, with `template_tags` populated by `extractTemplateTags()`, and one `overlay_controls` row per control the document defines, through `OverlayControl::createForTemplate()`.
- **C4** [code] `RecipeInstaller::install()` reads the overlay file from `resources/recipes/<slug>/<basename(file)>`; the schema pattern `^[a-z0-9_-]+\.md$` refuses a path separator in `file`, and `RecipeInstaller::directoryFor()` is the only place the directory is composed.
- **C5** [code] `RecipeInstaller::connectIntegration()` calls `ExternalIntegration::firstOrCreate` on `(user_id, service)` with `enabled` true, sets `enabled` true on an existing disabled row, and calls `ExternalControlService::provision()` with `ExternalServiceRegistry::driver($service)`, the same two calls `CheckinIntegrationController::save()` makes.
- **C6** [code] Created overlay ids are stored in `recipe_instances.primitive_map` under `overlays.<ref>`; no migration is in the diff.
- **C7** [code] `RecipeCatalog::sync()` is the same `Recipe::updateOrCreate` on `(slug, version)` the seeder had inline; `ProductController::install()` calls it before `RecipeInstaller::install()`, so a product installs on an environment that has never run `FirstPartyRecipesSeeder`.
- **C8** [code] `ProductController::listedManifest()` aborts 404 for a slug whose manifest is absent or lacks `listed: true`; `/products/dice` is a 404.
- **C9** [code] `ProductController::install()` returns a redirect to `products.show` without installing when `instanceFor()` finds an existing instance of that slug for the user.
- **C10** [code] `WiringFacts::productSubjects()` returns one subject per `RecipeInstance` of the user whose recipe manifest has an `installs` key, and none for `coin_flip` or `dice` instances.
- **C11** [code] `WiringFacts::productSubject()` sets `product.bot_on` to NOT_APPLICABLE when `requires_bot` is false, SATISFIED when `users.bot_enabled` is true, MISSING otherwise; `product.bot_hears` follows the same `BotPresence` rules as `botSubject()`.
- **C12** [code] `product.integration` is SATISFIED only when an enabled `external_integrations` row exists for every service in `installs.integrations`; `product.overlay` only when every id in `primitive_map.overlays` still resolves to an `overlay_templates` row; `product.token` only when an `overlay_access_tokens` row with `is_active` true and `expires_at` null or future exists for the user.
- **C13** [test] `ProductInstallTest` covers C3, C5, C8, C9, C10, C11 and C12 by name, and asserts that a guest GET of `/products` and `/products/chat_checkin` is 200 while a guest POST to `/products/chat_checkin/install` redirects and creates no instance.
- **C14** [test] `ProductInstallTest` "parses every overlay document a listed product ships" asserts the parsed name does not begin with a quote and the description is not null.
- **C15** [unverified] C14 was run against `globe.md` after `npm run format` had rewritten it and failed on the name assertion; `.prettierignore` now excludes `resources/recipes/**/*.md`.
- **C16** [code] `products/show.vue` hides wires whose state is `not_applicable` and renders every other wire with a tick or a warning, with the CTA link only on a `missing` wire; the install POST goes through `router.post` to `route('products.install')`.
- **C17** [code] Neither `products/index.vue` nor `products/show.vue` calls `route()` for a guest: the two public pages link with literal paths, and `route('dashboard.index')` and `route('products.install')` are behind `isAuthed` guards.
- **C18** [unverified] On `overlabels.test` on 2026-09-09, as JasperDiscovers: `/products` listed Chat Checkin, pressing Install on `/products/chat_checkin` produced `Chat Checkin globe` at `/templates/328` with the source from `globe.md`, the page showed four satisfied wires and no missing ones, and `/settings/wiring` showed a Products circuit with one subject.
- **C19** [code] `RecipeInstaller::installList()` throws a `RuntimeException` naming the slug when an `option_sets` row with that `(user_id, slug)` exists, and otherwise creates one with `recipe_instance_id` set, `items` empty, `min_items` 0, `user_editable` true, the same values `ListController::store()` writes.
- **C20** [code] `RecipeInstaller::installListAppender()` creates a `list_appenders` row with `target_list_id` pointing at the list installed under the manifest's `list` ref, `command` lower-cased without the `!`, `value_template` defaulting to `[[[bot:from_user]]]`, `dedup_policy` defaulting to `per_chatter`, `enabled` true.
- **C21** [code] `assertNoChatCommandCollisions()` runs before the transaction opens and now includes appender commands, so an install that would collide on `!bowl` creates no instance, no overlay and no list.
- **C22** [test] `ProductBowlingTest` asserts C19 and C21 with a pre-existing `lane` list and a pre-existing `bowl` `BotCommand` respectively, each leaving zero `recipe_instances` rows, and asserts a refused POST redirects to the product page with an `install` error in the session.
- **C23** [code] `product.list` is SATISFIED only when every id in `primitive_map.lists` resolves to an `option_sets` row; `product.command` only when every id in `primitive_map.list_appenders` resolves to a `list_appenders` row with `enabled` true; both are NOT_APPLICABLE for a manifest without the matching `installs` section.
- **C24** [code] Neither file under `public/products/` contains a `<metadata>` element, a `c2pa` string or a `<script>` element; `ProductBowlingTest` asserts the `c2pa` absence for the bowling file and that `/products` serves `products.0.hero` and `products.1.hero` as the two paths.
- **C27** [code] Both hero files carry the text `AN OVERLABELS.COM PRODUCT`; neither carries `INSTALLABLE OVERLAY KIT`.
- **C28** [code] `products/index.vue` lays the cards out in a `lg:grid-cols-3` grid inside a `max-w-7xl` container and always renders one extra `<li>` holding `EmptyState` with `dashed` and the message "More to come!"; `products/show.vue` renders `product.hero` as a full-width image above the header when it is set.
- **C29** [code] `RecipeInstaller::validatedAliases()` and `validatedCommands()` run before `DB::transaction()` and call `BotAliasValidator::validateAndNormalize()` and `BotCommandValidator::validateAndNormalize()` respectively with `enabled` true; a `ValidationException` from either becomes a `RuntimeException` whose message starts with `Alias !x:` or `Command !x:` followed by the validator's first field message.
- **C30** [code] Inside the transaction each validated alias becomes a `bot_aliases` row and each validated command a `bot_commands` row, ids stored under `primitive_map.bot_aliases.<command>` and `primitive_map.bot_commands.<command>`; `BotCounterService::provision()` is called with each command's reply.
- **C31** [code] `product.command` is SATISFIED only when every id under `primitive_map.list_appenders`, `.bot_aliases` and `.bot_commands` resolves to an enabled row of its table; NOT_APPLICABLE when the manifest declares none of the three sections.
- **C32** [test] `ProductChatInstallsTest` asserts C29 for a reply starting with `/`, an alias chaining onto an alias and a command named after a builtin, each leaving zero `recipe_instances` rows; asserts C30 with a reply naming `[[[counter:strikes]]]` producing an `overlay_controls` row keyed `strikes`; asserts C31 by disabling an alias and by deleting a command; and asserts the bowling install creates `fbfirst` with `target_template` `list lane pop first` and `fbdraw` with `list lane draw`, both `moderator`.
- **C25** [unverified] On `overlabels.test` on 2026-09-09, as JasperDiscovers, whose account already has a list with slug `lane`: pressing Install on `/products/follower_bowling` redrew the page with the message "You already have a list with the slug 'lane'. Rename or delete it, then install again." and created nothing.
- **C26** [unverified] The prod export of `tiber-rakahanga-bologna-apennine` fetched on 2026-09-09 already caps the pin `foreach` with `[[[if:loop.index <= 9]]]`, so the "followers cap at 10" step in the deep dive is no longer needed and `lane.md` sets no foreach cap.

### Unchanged
- `OverlayTemplateController::import()` is the reference for `installOverlay()`; its validation, `list_writer` skip and expression handling are mirrored, not extracted, and the method is not in the diff.
- `CheckinIntegrationController::save()` is the reference for `connectIntegration()`; it is not in the diff and still owns `pin_lifetime`, `home_place` and `cooldown_seconds`.
- `WiringReport::build()` and `resources/js/pages/settings/wiring/index.vue` are not in the diff; the products circuit renders through the existing report shape.
- `RecipeInstanceController` and `dashboard/recipes.vue` are not in the diff; picker recipes keep their dashboard.
- `Kit::fork()` is not in the diff. Kits stay a bundle of templates; a product is a recipe.

### Risk
`/products` and `/products/{slug}` are public and indexable. A product installs into whichever account is logged in with no confirmation step; a second press is a no-op. Uninstalling is manual: delete the overlay, disconnect the integration, delete the list and the command; the `recipe_instances` row stays. An account that already has a list with slug `lane`, a `!bowl` command, or an alias called `fbfirst` or `fbdraw` (the accounts that built Follower Bowling by hand from the deep dive, the author's included) cannot install Follower Bowling until it renames or deletes those. An account that installed Follower Bowling before the aliases were added to its manifest does not get them: there is no upgrade path, and a second install is a no-op.
