## Audit of OL-2611-050 - feat(products): rename Twitch Chat Plinko to Chat Plinko, per-product beat headings, Discord links

**Audited:** 2026-11-28
**Commit:** 0bdd896c970ecff63b55e9800426e017bfbb051c (the only commit carrying the trailer; it is also HEAD, so every @0bdd896c reading equals @HEAD)

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/chat-plinko/manifest.json:3-5 @0bdd896c` - `"slug": "chat-plinko"`, `"url_aliases": ["twitch-chat-plinko"]`, `"name": "Chat Plinko"` |
| C2 | CONFIRMED | `grep -n -i twitch resources/recipes/chat-plinko/manifest.json` @0bdd896c returns exactly lines 4 (the alias), 10 (`twitch_login`) and 31 (the quoted tagline) |
| C3 | CONFIRMED | `tests/Feature/ProductChatPlinkoTest.php:144 @0bdd896c` - `assertStatus(301)->assertRedirect(route('products.show', 'chat-plinko'))`; `php artisan test --filter=ProductChatPlinkoTest` 11 passed |
| C4 | CONFIRMED | `tests/Feature/ProductChatPlinkoTest.php:150 @0bdd896c` - both `/help/twitch-chat-plinko` and `.md` assert 301 to their `chat-plinko` paths; passed in the same run |
| C5 | CONFIRMED | `database/migrations/2026_11_28_120000_rename_twitch_chat_plinko_to_chat_plinko.php @0bdd896c` `up()` - slug update on `twitch-chat-plinko`, then name update on `slug = chat-plinko AND name = 'Twitch Chat Plinko'` |
| C6 | CONFIRMED | `tests/Feature/ProductChatPlinkoTest.php:155-169 @0bdd896c` - installs, moves the row back to old slug and name, runs the migration's `up()`, asserts `assertSee('Chat Plinko is installed')`; passed |
| C7 | CONFIRMED | Migration absent from `git show --stat 0bdd896c`; `2026_11_20_120000_give_installed_fullscreen_products_their_palette_controls.php:34,36 @0bdd896c` still key on `twitch-chat-plinko` |
| C8 | CONFIRMED | `tests/Feature/PaletteControlsMigrationTest.php:41-54 @0bdd896c` - installs from `$catalog->find('chat-plinko')`, then `DB::table('recipes')->where('slug', 'chat-plinko')->update(['slug' => 'twitch-chat-plinko'])` |
| C9 | CONFIRMED | `app/Http/Controllers/Settings/PlinkoSettingsController.php:25 @0bdd896c` - `PRODUCT_SLUG = 'chat-plinko'` |
| C10 | CONFIRMED | `app/Http/Controllers/ProductController.php:669-675 @0bdd896c` - the three defaults as stated; `$playsKicker` feeds both `plays_kicker` and `plays_nav` |
| C11 | CONFIRMED | `resources/views/products/page.blade.php:98,124,127,128 @0bdd896c` - jump label `$page['plays_nav']`, `id="what-chat-does"`, kicker and heading from `$page` |
| C12 | CONFIRMED | `tests/Feature/ProductPitchPageTest.php:127-140 @0bdd896c` - skips descriptions matching `/!\w+/`, asserts `toHaveKey('plays_heading')`, sees it, `assertDontSee('One command. Three things happen.')`; `--filter=ProductPitchPageTest` 16 passed |
| C13 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a modified tree, legitimately outside the repo |
| C14 | CONFIRMED | `config/services.php:182 @0bdd896c` - `'invite' => 'https://discord.gg/qANdj5QPpd'`; `app/Http/Middleware/HandleInertiaRequests.php:82 @0bdd896c` - `'discordUrl' => config('services.discord.invite')` |
| C15 | CONFIRMED | `tests/Feature/DiscordLinksTest.php @0bdd896c` - `/` asserts `href="<invite>"`, `/products` (Inertia) asserts `discordUrl` equals the config; `--filter=DiscordLinksTest` 3 passed |
| C16 | CONFIRMED | `resources/js/components/AppSidebar.vue:86 @0bdd896c` - last entry of `learnNavItems`: `{ title: 'Discord', ..., target: '_blank', icon: DiscordIcon }` |
| C17 | CONFIRMED | `resources/js/components/AppSidebarHeader.vue:49 @0bdd896c` (desktop `<nav>`) and `:68` (inside `DropdownMenuContent`) - both plain `<a ... target="_blank">` reading "Discord" |
| C18 | CONFIRMED | `resources/js/layouts/ProductsLayout.vue:186`, `resources/js/pages/products/show.vue:951` (inside the `<template v-if="finished && ...">` opened at :947), `resources/js/pages/products/design.vue:1048`, all @0bdd896c, each preceded by "Got questions?" |
| C19 | CONFIRMED | `resources/js/pages/products/design.vue:1041-1051 @0bdd896c` - `<ul>` with five `<li>`, replacing the removed `<p>` |

### Surface
Complete. The seven renamed files' old paths (`resources/recipes/twitch-chat-plinko/*`, `public/products/twitch-chat-plinko-hero.svg`, `public/products/twitch-chat-plinko/play-board.svg`, `resources/help/pages/twitch-chat-plinko.md`, `product-icons/TwitchChatPlinko.vue`, `tests/Feature/ProductTwitchChatPlinkoTest.php`) are each disclosed as "moved from" on the new path's line.

### Findings
- **F1** scope - `resources/js/layouts/ProductsLayout.vue:149 @0bdd896c` moves `lg:sticky lg:top-6` off `NavigationMenu` onto a new wrapping `<div>`, so on large screens the category rail and the new line now stick as one block; the Surface line says only "Discord line under the category rail" and no claim covers the sticky change, so a follow-up claim should record it.
- **F2** scope - `resources/recipes/recipe-manifest.schema.json:201 @0bdd896c` rewrites the existing `plays` description from "What chat does, ..." to "What the product does on stream, ..."; the Surface line names only the two new optional keys, so this edit is undisclosed (doc string only, no behaviour).

### Notes
- Unchanged lines hold: no production code in the diff writes `recipe_instances.instance_slug` (`RecipeInstance::instanceSlugFrom()` is not in the diff; new installs derive `chat_plinko` from the slug through it); `Privacy.vue:407` and `Terms.vue:272` hardcode the invite and are not in the diff.
- OL-2611-002 C16-C18 name `ProductTwitchChatPlinkoTest`, which this commit renames to `ProductChatPlinkoTest` (disclosed in Surface); OL-2611-002 also added `twitch-chat-plinko` to `$bornHyphenated`, which this commit removes now that the manifest carries an alias, consistent with that test's own comment.
- No remaining `twitch-chat-plinko` / "Twitch Chat Plinko" in `app/`, `resources/js`, `resources/views`, `resources/help`, `config/` or `CLAUDE.md` @HEAD outside the alias and the `routes/web.php:120-123` redirects.
