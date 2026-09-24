## Audit of OL-2609-130 - feat(recents): the Recent page lives at /recents, out from under /dashboard

**Audited:** 2026-09-24
**Commit:** b7092eed244b59eb23ab78989007b6edac361e00
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `routes/web.php:207-209 @b7092eed` - `Route::get('/recents', [DashboardController::class, 'recentActivity'])->middleware(['auth.redirect'])->name('dashboard.recents')`; same at @HEAD |
| C2 | CONFIRMED | `tests/Feature/RecentActivityPartialReloadTest.php:61-65 @b7092eed` - asserts `route('dashboard.recents', absolute: false)` is `/recents` and `GET /dashboard/recents` `assertRedirect('/recents')->assertStatus(301)`; `php artisan test --filter=RecentActivityPartialReloadTest` 6 passed (45 assertions) |
| C3 | CONFIRMED | `routes/web.php:214-216 @b7092eed` - `permanentRedirect('/dashboard/recents', '/recents')` directly after the two `/dashboard/lists` redirects, top-level with no middleware; the `auth.redirect` group opens at `:590`; same at @HEAD |
| C4 | CONFIRMED | `resources/js/pages/dashboard/recents.vue:332 @b7092eed` - `href: '/recents'`; `:85` `router.get(route('dashboard.recents'), buildQuery(), ...)`, not in the diff; @HEAD recents.vue changed only in 48a98b5c (a `View list` anchor reflow), neither line touched |
| C5 | CONFIRMED | `git grep -F "/dashboard/recents" b7092eed -- app routes bootstrap resources/js resources/help tests` returns only `routes/web.php:216` and `RecentActivityPartialReloadTest.php:61,64`; identical at @HEAD |
| C6 | CONFIRMED | `app/Http/Controllers/DashboardController.php:78 @b7092eed` - `Inertia::render('dashboard/recents', ...)`; `recents.vue` modified in place at `resources/js/pages/dashboard/recents.vue`, no rename in `git show --stat`; same at @HEAD |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged line 1 checked: `AppSidebar.vue:66`, `CommandPalette.vue:90`, `pages/dashboard/index.vue:69` and `WhatsNewCardTest.php:321` use `dashboard.recents` @HEAD and none is in the diff.
- `recents.vue` changed after ship in 48a98b5c (style, no claim; `.vue` is claim-exempt), away from any symbol this claim names.
- Git Bash rewrites a leading-slash argument such as `"/dashboard/recents"` into a Windows path; C5's grep returns nothing unless run with `MSYS_NO_PATHCONV=1`.
