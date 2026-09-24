## Audit of OL-2609-018 - refactor(routes): developer tools move under /settings, old paths 301

**Audited:** 2026-09-24
**Commit:** 806c8dfd0213f3ad7f25f80bbd3b106075a0ce77
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `routes/web.php:286,290,294,298,302,306,310,314,318 @806c8dfd` - `twitchdata` and `twitchdata.refresh.{expensive,all,user,info,following,followers,subscribers,goals}`; `:596` `testing.index`; `:600-604` `tokens.` prefix with `index`, `store`, `revoke`, `destroy`; `:742-743` `tags.generator`. Same names at `routes/web.php:270-304,600,604-608,784 @HEAD` |
| C2 | CONFIRMED | `routes/web.php:283,595,599,741 @806c8dfd` - `Route::redirect('/twitchdata' / '/testing' / '/tokens' / '/tags', '/settings/<same>', 301)`; same at `:269,599,603,783 @HEAD` |
| C3 | CONFIRMED | `routes/web.php:600-604 @806c8dfd` - `Route::prefix('settings/tokens')->name('tokens.')` holding `index`, `store`, `revoke`, `destroy`; same at `:604-608 @HEAD` |
| C4 | CONTRADICTED | `routes/web.php:284-318 @806c8dfd` registers NINE `twitchdata*` routes under `/settings/twitchdata` (the index plus eight `refresh/*`: expensive, all, user, info, following, followers, subscribers, goals), not eight. The set is right: no other `web.php` line @806c8dfd begins `/settings/twitchdata`. Same nine @HEAD |
| C5 | CONTRADICTED | `git grep` @806c8dfd finds root references beyond the three stated exceptions: `resources/views/welcome/onboarding.blade.php:49` (`/testing` in page copy), `resources/js/components/GroupedCollection.vue:6,9` (`/tags` in a comment), `tests/Feature/DeveloperToolsUnderSettingsTest.php:15-18` (old paths as test fixtures). All three are still present @HEAD |
| C6 | CONFIRMED | `tests/Feature/DeveloperToolsUnderSettingsTest.php @806c8dfd` - test 1 asserts `route($name, absolute: false)` equals the `/settings/...` path for all four names; test 2 asserts `assertStatus(301)->assertRedirect($newPath)` for all four old paths. `php artisan test --filter=DeveloperToolsUnderSettingsTest` @HEAD: 2 passed, 16 assertions. File unchanged since 806c8dfd |
| C7 | CONTRADICTED (compound) | Half 1 CONFIRMED: `resources/js/layouts/settings/Layout.vue:46 @806c8dfd` `isActive` is not in the diff and matches `/settings/tokens` etc. Half 2 ("because their hrefs start with /settings/") CONTRADICTED: `isActive` at `Layout.vue:46 @806c8dfd^` is identical and opens with `currentPath === href`, so the old root hrefs already matched their root URLs; all four pages used `SettingsLayout` before the change (e.g. `overlaytokens/index.vue:6 @806c8dfd`) |

### Surface
Complete.

### Findings
- **F1** Claim contradicted - C4 says "eight `twitchdata*` routes" and Surface says "its seven refresh routes", but `routes/web.php:284-318 @806c8dfd` has nine routes (index plus eight refresh); a later claim should restate the count.
- **F2** Claim contradicted - C5's "no file references the old root paths" is false @806c8dfd: `resources/views/welcome/onboarding.blade.php:49` tells users about `/testing` (reaches the page only through the 301), and `GroupedCollection.vue:6,9` and `DeveloperToolsUnderSettingsTest.php:15-18` name old paths too; either update the onboarding copy or record the exceptions in a new claim.
- **F3** Claim contradicted (compound) - C7's reason is false: `Layout.vue:46` (`currentPath === href || ...`, unchanged since `806c8dfd^`) already matched the root hrefs on the root pages, so the move did not change whether the nav highlights; the commit message's "Side effect: the settings nav now highlights the active developer tool" has the same problem. A new claim should restate or drop C7.
- **F4** Scope - Surface says `overlaytokens/index.vue` changes "the four axios URLs", but the diff changes three axios calls (`:87,112,124 @806c8dfd`) and one commented-out `router.visit` (`:134`), which no claim or Surface line mentions; record the correct description.

### Notes
- To run C6, the local Postgres 16 cluster had to be started (`pg_ctlcluster 16 main start`). The first run failed with connection refused. No repo files were changed.
- Later commits added more root `/tags` links that rely on the 301: `resources/help/pages/living-title.md:30,43 @HEAD` and `resources/js/pages/settings/Title.vue:277 @HEAD` (living title, f58bff84 / 116797d3, which have no Changelog trailer). They are outside this claim's shipped tree, so they are not counted as findings here.
- OL-2609-016 claim line 44 says these four paths "keep their paths in this commit (separate change)". This claim is that separate change and does not contradict it.
