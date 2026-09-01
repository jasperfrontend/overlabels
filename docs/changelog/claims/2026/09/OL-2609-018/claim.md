## OL-2609-018 - refactor(routes): developer tools move under /settings, old paths 301

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-018`

### Surface
- `routes/web.php` - `/tokens`, `/tags`, `/twitchdata` (and its seven refresh routes) and `/testing` re-pathed under `/settings/`; a 301 from each old GET path
- `resources/js/layouts/settings/Layout.vue` - the four Developer Tools nav hrefs
- `resources/js/pages/overlaytokens/index.vue` - breadcrumb and the four axios URLs
- `resources/js/pages/TwitchData.vue` - breadcrumb and the six refresh button URLs
- `resources/js/pages/TemplateTagGenerator.vue` - breadcrumb
- `resources/js/pages/testing/index.vue` - breadcrumb
- `resources/help/pages/conditionals.md` - links to `/settings/tags` and `/settings/testing`
- `resources/help/pages/lists-realtime.md` - two links to `/settings/tokens`
- `resources/help/pages/tokens.md` - two links to `/settings/tokens`
- `resources/help/pages/testing.md` - link to `/settings/testing`
- `tests/Feature/OverlayTokenAllowedIpsTest.php` - posts to `/settings/tokens`
- `tests/Feature/TemplateTagCatalogTest.php` - gets `/settings/tags`
- `tests/Feature/DeveloperToolsUnderSettingsTest.php` - new file

### Claims
- **C1** [code] No route name changed: `tokens.index`, `tokens.store`, `tokens.revoke`, `tokens.destroy`, `tags.generator`, `twitchdata`, `twitchdata.refresh.*` and `testing.index` are all still registered under those names.
- **C2** [code] `routes/web.php` registers `Route::redirect()` with status 301 for `/tokens`, `/tags`, `/twitchdata` and `/testing`, each pointing at the same path prefixed with `/settings`.
- **C3** [code] The `tokens.` group is now `Route::prefix('settings/tokens')`, so `tokens.store`, `tokens.revoke` and `tokens.destroy` moved with the index.
- **C4** [code] The eight `twitchdata*` routes and the redirect are the only routes in `web.php` whose path begins `/settings/twitchdata`.
- **C5** [code] No `.vue`, `.ts`, `.md` or `.php` file under `resources/`, `tests/` or `routes/` references `/tokens`, `/tags`, `/twitchdata` or `/testing` at the root except the `Route::redirect()` lines, `routes/admin.php` (`/admin/tokens`, a different page) and `routes/api.php` (the bot token endpoint).
- **C6** [test] `DeveloperToolsUnderSettingsTest` asserts `route()` for each of the four names resolves to its `/settings/...` path, and that a GET to each old path returns 301 to the new one.
- **C7** [code] `SettingsLayout`'s `isActive()` (unchanged) now matches the four Developer Tools entries, because their hrefs start with `/settings/`.

### Unchanged
- `HelpContext` and the help pages' `context:` frontmatter key on route names, which C1 keeps; no help page frontmatter is in the diff.
- `CommandPalette.vue` builds the four links with `route()` by name and is not in the diff.
- `routes/admin.php` `/admin/tokens` is the admin token list and is not in the diff.
