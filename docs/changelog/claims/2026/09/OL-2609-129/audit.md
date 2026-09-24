## Audit of OL-2609-129 - feat(lists): the Lists pages live at /lists, out from under /dashboard

**Audited:** 2026-09-24
**Commit:** 290db402ce59b18122ce4b74f4b68c45ba84e9d7
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `routes/web.php:612 @290db402` - `Route::prefix('lists')->name('lists.')->group(`; the only other hunk in the file is the redirect block at :211-215, so the group body (names, controllers, order) is untouched by the diff; same at @HEAD |
| C2 | CONFIRMED | `routes/web.php:214-215 @290db402` - both `Route::permanentRedirect` calls sit at top level between `dashboard.recents` (:207) and `dashboard.gps-sessions` (:217); the `auth.redirect` group opens at :589. @HEAD same lines; the comment above them was reworded by OL-2609-130 (`b7092eed`) |
| C3 | CONFIRMED | `tests/Feature/ListControllerTest.php:41-44 @290db402` - asserts `assertRedirect('/lists')->assertStatus(301)` and `assertRedirect('/lists/pizza')->assertStatus(301)`; `php artisan test tests/Feature/ListControllerTest.php` - 21 passed |
| C4 | CONFIRMED | `bootstrap/app.php:47,50 @290db402` - both callbacks are `$request->is('lists', 'lists/*')`; `git grep dashboard/lists 290db402 -- bootstrap` finds nothing; same at @HEAD |
| C5 | CONTRADICTED | The empty/duplicate/whitespace cases are asserted only in `store creates a user-authored list preserving exactly what was sent` (:103, POST `/lists`) and `store strips NUL bytes but preserves everything else verbatim` (:144, POST `/lists`) @290db402. No test in the file sends empty, duplicate or whitespace items to `/lists/{list}`; the PUT tests (:167-316) send only values such as `['b', 'c']`. The tests pass (21 passed) but exercise only the `lists` half of C4, not `lists/*` |
| C6 | CONFIRMED | `app/Services/Lists/ListActionService.php:111 @290db402` - `"no list named '$slug'. Check your lists at /lists."`; same at @HEAD |
| C7 | CONFIRMED | `app/Services/OverlayShareService.php:666 @290db402` - `"copied with an overlay - create one with a matching slug under /lists.\n\n"`; same at @HEAD |
| C8 | CONFIRMED | `git grep -n "/dashboard/lists" 290db402 -- app routes bootstrap resources/js resources/help tests` returns only `routes/web.php:214,215` and `tests/Feature/ListControllerTest.php:41,42,43`; identical at @HEAD. This is C8 as it reads @HEAD; the text at @290db402 differs (see F2) |
| C9 | CONFIRMED | `app/Http/Controllers/ListController.php:76,100 @290db402` - `Inertia::render('dashboard/lists/index'` and `'dashboard/lists/show'`; the diff modifies `resources/js/pages/dashboard/lists/index.vue` and `show.vue` in place, no rename; same at @HEAD |

### Surface
Complete.

### Findings
- **F1** [test] claim overstated - C5 says the preservation tests post to `/lists` and `/lists/{list}`, but `tests/Feature/ListControllerTest.php @290db402` asserts empty/duplicate/whitespace preservation only on POST `/lists` (:108, :147); nothing covers the `lists/*` exemption in `bootstrap/app.php:47,50` on a PUT to `/lists/{list}`. Record the narrower truth in a new claim, or add a PUT preservation test.
- **F2** record edited after the fact - `276d3ab4` rewrote C8 inside this shipped `claim.md` (was "No file under `app/`, `routes/`, ... or `tests/` contains the string `/dashboard/lists`.", which was false @290db402 per `routes/web.php:214-215` and `ListControllerTest.php:41-43`) with no new claim; CLAUDE.md says "Shipped `claim.md` and `audit.md` are never edited; every correction is a new claim citing the old one inline." Whether `276d3ab4` was pushed together with `290db402` is not determinable from the repo; if it was not, record the C8 correction in a new claim citing OL-2609-129 C8.

### Notes
- Other changed test files also run and pass: `ListActionTest`, `ListReadTagsTest`, `EventFeedTest`, `HelpContextTest` - 93 passed.
- @HEAD drift in `routes/web.php`, `resources/js/pages/dashboard/lists/index.vue` and `recents.vue` comes from OL-2609-130 (`b7092eed`) and the style-only `48a98b5c`; none touches the `lists.` group, the two redirects or the Lists axios URLs.
- Unchanged lines hold: `AppSidebar.vue:55 @290db402` uses `route('lists.index')` and is not in the diff; `resources/recipes/chat-tower/tower.md:611 @290db402` still carries `/dashboard/lists` and is not in the diff.
