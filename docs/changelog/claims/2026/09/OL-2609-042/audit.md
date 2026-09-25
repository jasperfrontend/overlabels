## Audit of OL-2609-042 - feat(products): a setup banner on every app page reels a mid-install streamer back to the product

**Audited:** 2026-09-25
**Commit:** ba3c804ef9c6f0c883fa7c3396d586c4a5b9f78b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/ProductSetup.php:26 @ba3c804` - `setPreference(self::PREFERENCE, ['slug' => $slug, 'started_at' => now()->timestamp])->save()`; `:35` writes null and saves (after an early return at `:31-33` when no flow is active); no path under `database/` in `git show --stat ba3c804`. Same at @HEAD `:50`, `:59` |
| C2 | CONFIRMED | `app/Http/Controllers/ProductController.php:158 @ba3c804` - `installer->install()` inside `try`, `catch (RuntimeException)` returns with errors; `ProductSetup::start()` at `:165` after it. @HEAD `:378`/`:385`, same order |
| C3 | CONFIRMED | `app/Http/Middleware/HandleInertiaRequests.php:127-133 @ba3c804` - null when `! $user` or `activeSlug()` null, else `ProductSetup::banner()`; `ProductSetup.php:66-75 @ba3c804` - `remaining` = count of `MISSING`, `next` = `{label, message}` of first missing or null, `ready` = `$missing === []`. @HEAD `next` also carries `todo`, `target` (OL-2609-049) and `url` (OL-2609-072) |
| C4 | CONFIRMED | `app/Support/ProductSetup.php:59-62 @ba3c804` - returns null when `$catalog->find()` or `instanceFor()` is null; same at @HEAD `:83-87` |
| C5 | CONFIRMED | `app/Http/Controllers/ProductController.php:75-77 @ba3c804` - `forget()` only when `$inSetup` (user, instance, and `activeSlug() === $slug`); `:101-102` `end()` when `$installed['remaining'] === 0`. @HEAD `:226-228`, `:300-301` |
| C6 | CONFIRMED | `app/Http/Controllers/ProductController.php:193-194 @ba3c804` - uninstall ends only when `activeSlug() === $slug`; `:172-174` `dismissSetup()` calls `end()` unconditionally and returns `back()`. @HEAD `:520-521`, `:496-498` |
| C7 | CONFIRMED | `resources/js/components/ProductSetupBanner.vue:30 @ba3c804` `v-if="setup"`; `:24` posts `products.setup.dismiss`; `:49` "Not now" is `v-if="!setup.ready"`; `git grep ProductSetupBanner ba3c804` finds only `AppSidebarLayout.vue:8,27`. @HEAD still only `AppSidebarLayout.vue:40` |
| C8 | CONTRADICTED | `php artisan test --filter=ProductSetupFlowTest` @HEAD: 11 passed (9 from this commit, 2 added by OL-2609-072). C2, C3, C4, C5's end-on-ready, C6 both roads are asserted. C5's "cache forget (mid-setup only)" is asserted for the not-mid-setup half only: `tests/Feature/ProductSetupFlowTest.php:124 @ba3c804` expects `'unknown'` after seeding `'unknown'`, which also holds with no `forget()` call. The `steps()` test (`:171-173`) asserts order at index 0 only, plus membership |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - `tests/Feature/ProductSetupFlowTest.php:115-124 @ba3c804` (unchanged in substance @HEAD) seeds `bot:moderated_channels` with `'unknown'` and asserts `'unknown'` after loading the product page mid-setup; `BotModeratedChannels::broadcasterIds()` @ba3c804 returns early without writing when the cache is non-null, so the assertion passes whether or not `ProductController::show()` calls `forget()`. The mid-setup half of C8's "cache forget" is untested. Reasoned from the code, not mutation-run (this audit modifies no files besides itself). Seed a sentinel the refetch cannot produce (e.g. `['stale']`) and assert it is gone.
- **F2** test narrower than claim - C8 says the test asserts `steps()` "keeps circuit order"; `tests/Feature/ProductSetupFlowTest.php:171-173 @ba3c804` asserts only that `$labels[0]` is "The bot is switched on", that two labels are present, and that "Its integration is connected" is absent. Order past index 0 is not asserted. Assert the full label list, or restate the claim to match.

### Notes
- Tests were run at HEAD (fdc76c6c), not at ba3c804. @HEAD the test file uses hyphenated slugs (`chat-checkin`), changed by OL-2609-114.
- Test `:68 @ba3c804` is named "for a guest and for a member" but only exercises a member. C8 does not claim the guest case, so this is not a finding.
- `ProductSetupBanner.vue` and `AppSidebarLayout.vue` have changed since (OL-2609-044, -048, -049, -072, -128). Each of those claims discloses its change.
