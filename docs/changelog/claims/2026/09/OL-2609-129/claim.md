## OL-2609-129 - feat(lists): the Lists pages live at /lists, out from under /dashboard

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-129`

### Surface
- `routes/web.php` - the `lists.` group's prefix is `lists`; two permanent redirects from the old addresses; group comment reworded
- `bootstrap/app.php` - the `trimStrings` and `convertEmptyStringsToNull` exemptions match `lists` and `lists/*`
- `app/Http/Controllers/ListController.php` - docblock URLs
- `app/Http/Controllers/ListActionWebController.php` - docblock URLs
- `app/Http/Controllers/ListAppenderController.php` - docblock URLs
- `app/Services/Lists/ListActionService.php` - the "no list named" bot reply points at `/lists`
- `app/Services/Lists/ListAppendService.php` - comment
- `app/Services/OverlayShareService.php` - the exported overlay's note about Lists points at `/lists`
- `app/Services/Recipes/RecipeInstaller.php` - docblock
- `resources/recipes/recipe-manifest.schema.json` - the `lists` property's description text
- `resources/help/pages/lists.md` - three links
- `resources/help/pages/lists-realtime.md` - one link
- `resources/help/pages/chat-tower.md` - one link
- `resources/js/components/CollectionList.vue` - comment
- `resources/js/components/lists/AppendCommandsCard.vue` - axios URLs
- `resources/js/components/lists/AppenderCommandDialog.vue` - axios URLs
- `resources/js/components/lists/SnapshotsCard.vue` - axios URLs
- `resources/js/pages/dashboard/lists/index.vue` - breadcrumb href, meta-command axios URLs, comment
- `resources/js/pages/dashboard/lists/show.vue` - breadcrumb hrefs, actions and meta-command axios URLs
- `resources/js/pages/dashboard/recents.vue` - three links to Lists pages
- `tests/Feature/ListControllerTest.php` - URLs; new redirect test
- `tests/Feature/ListActionTest.php` - URLs
- `tests/Feature/ListReadTagsTest.php` - URLs
- `tests/Feature/EventFeedTest.php` - URLs
- `tests/Feature/HelpContextTest.php` - URL

### Claims
- **C1** [code] Every route in the `lists.` name group in `routes/web.php` is served under the path prefix `lists`, with the same names, controllers and order as before.
- **C2** [code] `routes/web.php` registers `Route::permanentRedirect('/dashboard/lists', '/lists')` and `Route::permanentRedirect('/dashboard/lists/{slug}', '/lists/{slug}')`, outside the `auth.redirect` group.
- **C3** [test] `ListControllerTest` "sends the old /dashboard/lists addresses to /lists permanently" asserts both redirects respond 301 to the new path, the slug carried over.
- **C4** [code] `bootstrap/app.php` exempts requests matching `lists` and `lists/*` from `TrimStrings` and `ConvertEmptyStringsToNull`; no request path starting `dashboard/lists` is exempted any more.
- **C5** [test] The whitespace, empty-line and duplicate preservation tests in `ListControllerTest` post to `/lists` and `/lists/{list}` and pass, which exercises C4 on the new prefix.
- **C6** [code] `ListActionService` returns the reply `no list named '<slug>'. Check your lists at /lists.` when the slug is unknown.
- **C7** [code] `OverlayShareService` writes `create one with a matching slug under /lists.` into an exported overlay's Lists note.
- **C8** [code] No file under `app/`, `routes/`, `bootstrap/`, `resources/js/`, `resources/help/` or `tests/` contains the string `/dashboard/lists`.
- **C9** [code] `ListController::index()` and `show()` still render the Inertia components `dashboard/lists/index` and `dashboard/lists/show`; the page files under `resources/js/pages/dashboard/lists/` did not move.

### Unchanged
- The route names `lists.index`, `lists.show`, `lists.store`, `lists.update`, `lists.destroy`, `lists.meta-command.*`, `lists.appenders.*`, `lists.actions.run` and `lists.event-feed` are what `AppSidebar.vue`, the help pages' `context:` lines and the tests address the routes by; none is renamed, and `route('lists.index')` in `AppSidebar.vue` is not in the diff.
- `resources/recipes/chat-tower/tower.md` line 611 carries the exported note with the old address. It is an export of `docs/private/engine/chat-tower/` and is not hand-edited; the redirect in C2 covers the link.
- `docs/changelog/*.md`, `docs/MILESTONES.md` and `CLAUDE.md` mention `/dashboard/lists` as history and are not in the diff.

### Risk
A bookmark or shared link to `/dashboard/lists?search=...` lands on `/lists` without its query string; the redirect carries the path only.
