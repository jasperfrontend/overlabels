## OL-2609-130 - feat(recents): the Recent page lives at /recents, out from under /dashboard

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-130`

### Surface
- `routes/web.php` - the `dashboard.recents` route's path is `/recents`; a permanent redirect from `/dashboard/recents`; the redirect block's comment names Recent beside Lists
- `app/Services/Lists/EventFeedService.php` - docblock
- `resources/js/pages/dashboard/recents.vue` - the breadcrumb href
- `resources/js/pages/settings/integrations/bmac.vue` - the "Recent Events" link
- `resources/js/pages/dashboard/lists/index.vue` - comment
- `tests/Feature/RecentActivityPartialReloadTest.php` - new path and redirect test

### Claims
- **C1** [code] `routes/web.php` serves `DashboardController::recentActivity` at `GET /recents` under the route name `dashboard.recents`, behind `auth.redirect`.
- **C2** [test] `RecentActivityPartialReloadTest` "answers at /recents and sends the old /dashboard/recents address there permanently" asserts `route('dashboard.recents', absolute: false)` is `/recents` and that `GET /dashboard/recents` responds 301 to `/recents`.
- **C3** [code] `routes/web.php` registers `Route::permanentRedirect('/dashboard/recents', '/recents')` next to the two Lists redirects from OL-2609-129, outside the `auth.redirect` group.
- **C4** [code] `recents.vue` builds its breadcrumb with `href: '/recents'` and its filter reloads with `route('dashboard.recents')`, which is unchanged.
- **C5** [code] The string `/dashboard/recents` occurs under `app/`, `routes/`, `bootstrap/`, `resources/js/`, `resources/help/` and `tests/` only in the redirect in C3 and the test in C2.
- **C6** [code] `DashboardController::recentActivity()` still renders the Inertia component `dashboard/recents`; the page file `resources/js/pages/dashboard/recents.vue` did not move.

### Unchanged
- The route name `dashboard.recents` is how `AppSidebar.vue`, `CommandPalette.vue`, `pages/dashboard/index.vue`, `recents.vue` and the `WhatsNewCardTest` update rows' `cta_route` address the page; the name is kept so a stored `cta_route` on an existing update row keeps resolving, and none of those callers is in the diff.
- `docs/changelog/*.md` and `docs/private/*.md` mention `/dashboard/recents` as history and are not in the diff.

### Risk
A bookmark to `/dashboard/recents?search=...` lands on `/recents` without its query string; the redirect carries the path only.
