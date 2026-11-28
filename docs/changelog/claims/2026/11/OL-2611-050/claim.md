## OL-2611-050 - feat(products): rename Twitch Chat Plinko to Chat Plinko, per-product beat headings, Discord links

**Shipped:** 2026-11-28
**Commit:** `git log --grep=OL-2611-050`

### Surface
- `resources/recipes/chat-plinko/manifest.json` - moved from `twitch-chat-plinko/`; slug, name, `url_aliases`, hero paths and copy
- `resources/recipes/chat-plinko/plinko.md` - moved from `twitch-chat-plinko/`; overlay name and description
- `public/products/chat-plinko-hero.jpg` - new file, the new hero
- `public/products/twitch-chat-plinko-hero.jpg` - deleted, replaced by the file above
- `public/products/chat-plinko-hero.svg` - moved from `twitch-chat-plinko-hero.svg`
- `public/products/chat-plinko/play-board.svg` - moved from `twitch-chat-plinko/`
- `resources/help/pages/chat-plinko.md` - moved from `twitch-chat-plinko.md`; name and copy
- `resources/help/pages/index.md` - link to the moved help page
- `resources/js/components/product-icons/ChatPlinko.vue` - moved from `TwitchChatPlinko.vue`
- `resources/js/components/product-icons/registry.ts` - keyed `chat-plinko`
- `database/migrations/2026_11_28_120000_rename_twitch_chat_plinko_to_chat_plinko.php` - new file, moves `recipes.slug` and `recipes.name`
- `routes/web.php` - 301s for `/help/twitch-chat-plinko` and its `.md` twin
- `routes/settings.php` - comment
- `app/Http/Controllers/Settings/PlinkoSettingsController.php` - `PRODUCT_SLUG` and comment
- `app/Http/Controllers/Settings/IntegrationController.php` - comment
- `app/Models/User.php` - comment
- `app/Services/TemplateDataMapperService.php` - `plinko_command` description
- `public/llms.txt` - product table row
- `resources/css/welcome-plinko.css` - comments
- `resources/js/welcome/plinkoDemo.ts` - comment
- `resources/js/pages/settings/integrations/plinko.vue` - product link and its text
- `resources/views/about.blade.php` - two links
- `resources/views/products/pitch.blade.php` - demo map key
- `resources/views/welcome/demos/plinko.blade.php` - copy, hero path and alt
- `resources/views/welcome/hero.blade.php` - anchor and card keys; a fourth promise line linking the Discord
- `resources/views/welcome/products.blade.php` - demo map key
- `resources/views/products/page.blade.php` - demo map key; beat kicker, heading and jump label from `$page`
- `app/Http/Controllers/ProductController.php` - `pitchPage()` supplies `plays_kicker`, `plays_nav`, `plays_heading`
- `resources/recipes/recipe-manifest.schema.json` - optional `plays_kicker` and `plays_heading`
- `resources/recipes/snow-overlay/manifest.json` - `plays_kicker`, `plays_heading`
- `resources/recipes/chat-emote-bubbles/manifest.json` - `plays_heading`
- `resources/recipes/twitch-chat-overlay/manifest.json` - `plays_heading`
- `config/services.php` - `discord.invite`
- `app/Http/Middleware/HandleInertiaRequests.php` - shares `discordUrl`
- `resources/js/types/index.d.ts` - `discordUrl`; `NavItem.icon` accepts a component
- `resources/js/components/DiscordIcon.vue` - new file
- `resources/js/components/AppSidebar.vue` - Discord under Learn
- `resources/js/components/AppSidebarHeader.vue` - Discord in the header and the phone menu
- `resources/js/layouts/ProductsLayout.vue` - Discord line under the category rail
- `resources/js/pages/products/show.vue` - Discord line under the thank-you heading
- `resources/js/pages/products/design.vue` - the Add to OBS paragraph as a list, with the Discord line
- `CLAUDE.md` - Plinko section heading
- `resources/js/utils/integrationRows.test.ts` - product slug
- `tests/Feature/ProductChatPlinkoTest.php` - moved from `ProductTwitchChatPlinkoTest.php`; slug, shelf position, rename tests
- `tests/Feature/PaletteControlsMigrationTest.php` - installs Plinko under the new slug and restores the old row
- `tests/Feature/ProductSlugRenameTest.php` - Plinko off the no-alias list
- `tests/Feature/ProductPitchPageTest.php` - per-product heading, no-command guard, kicker test
- `tests/Feature/DiscordLinksTest.php` - new file
- `tests/Feature/ChatRedactSettingTest.php` - slug
- `tests/Feature/KitPickerTest.php` - slug
- `tests/Feature/LoginRedirectTargetTest.php` - slug
- `tests/Feature/PlinkoCommandSettingTest.php` - slug
- `tests/Feature/ProductCategoryTest.php` - slug and shelf positions
- `tests/Feature/ProductDemoTest.php` - slug
- `tests/Feature/ProductInstallHistoryTest.php` - slug
- `tests/Feature/ProductInstallTest.php` - listing order
- `tests/Feature/RecipeDesignerBlockTest.php` - slug
- `tests/Feature/ProductBowlingTest.php` - shelf position
- `tests/Feature/ProductTowerTest.php` - shelf position
- `tests/Feature/ProductTwitchChatTest.php` - shelf position

### Claims
- **C1** [code] The Plinko manifest declares `"slug": "chat-plinko"`, `"name": "Chat Plinko"` and `"url_aliases": ["twitch-chat-plinko"]`.
- **C2** [code] The Plinko manifest's only remaining occurrences of "Twitch" are the alias, `author.twitch_login` and the tagline "Introducing Plinko for Twitch Chat: The perfect game for downtime."
- **C3** [test] `ProductChatPlinkoTest` asserts `/products/twitch-chat-plinko` answers 301 to `products.show` for `chat-plinko`.
- **C4** [test] `ProductChatPlinkoTest` asserts `/help/twitch-chat-plinko` and `/help/twitch-chat-plinko.md` answer 301 to their `chat-plinko` paths.
- **C5** [code] The migration renames the `recipes` row with slug `twitch-chat-plinko` to `chat-plinko`, and its name to `Chat Plinko` only where it is still `Twitch Chat Plinko`.
- **C6** [test] `ProductChatPlinkoTest` asserts that after the migration an install made under the old slug shows "Chat Plinko is installed" on the product page.
- **C7** [code] `2026_11_20_120000_give_installed_fullscreen_products_their_palette_controls.php` is not in the diff and still matches `twitch-chat-plinko`.
- **C8** [code] `PaletteControlsMigrationTest` installs Plinko from the `chat-plinko` manifest and sets the `recipes` row back to `twitch-chat-plinko` before running that migration.
- **C9** [code] `PlinkoSettingsController::PRODUCT_SLUG` is `chat-plinko`.
- **C10** [code] `pitchPage()` returns `plays_kicker` defaulting to "What your chat does", `plays_nav` defaulting to "What chat does", and `plays_heading` defaulting to "One command. Three things happen."; a manifest `plays_kicker` sets both the kicker and the nav label.
- **C11** [code] `products/page.blade.php` renders the beats' kicker, heading and jump-bar label from `$page`, and the section keeps `id="what-chat-does"`.
- **C12** [test] `ProductPitchPageTest` asserts every listed product whose `description` names no `!word` declares `plays_heading` and its page does not show "One command. Three things happen."
- **C13** [unverified] With Snow's `plays_heading` removed, the C12 test failed with "Failed asserting that an array has the key [plays_heading]".
- **C14** [code] `config('services.discord.invite')` is `https://discord.gg/qANdj5QPpd`, and `HandleInertiaRequests::share()` returns it as `discordUrl`.
- **C15** [test] `DiscordLinksTest` asserts the homepage links the invite and an app page receives `discordUrl`.
- **C16** [code] `AppSidebar.vue`'s Learn group ends with a `Discord` item, `target: '_blank'`, icon `DiscordIcon`.
- **C17** [code] `AppSidebarHeader.vue` renders a `Discord` link in the desktop nav and in the phone dropdown, both plain `<a>` with `target="_blank"`.
- **C18** [code] `ProductsLayout.vue`, `products/show.vue` (inside the thank-you `v-if`) and `products/design.vue` each render "Got questions?" with a link to `page.props.discordUrl`.
- **C19** [code] `design.vue`'s "Happy with your design?" block is a `<ul>` of five items in place of the paragraph.

### Unchanged
- `recipe_instances.instance_slug` keeps `twitch_chat_plinko` on existing installs; it is matched against nothing that changed, and no code in the diff writes it.
- Overlays already installed from Plinko keep their stored template name; only new installs get "Chat Plinko" from `plinko.md`.
- `Privacy.vue` and `Terms.vue` hardcode the same Discord invite and are not in the diff.

### Risk
`/products` lists products in slug order, so Chat Plinko moves from last to third on the products
shelf. The migration runs on deploy; until it has, an existing Plinko install reads as not
installed.
