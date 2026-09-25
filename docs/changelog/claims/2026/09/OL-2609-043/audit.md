## Audit of OL-2609-043 - fix(products): install and uninstall show the app's toast on the product page

**Audited:** 2026-09-25
**Commit:** 92d12fa8f64eaae4798d3882658e7685cd8ce852
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONTRADICTED (compound) | Success half CONFIRMED: `app/Http/Controllers/ProductController.php:167 @92d12fa` - `redirect()->route('products.show', $slug)->with('success', $manifest['name'].' is installed.')`; same statement at `:387 @HEAD`. "Refusal path is unchanged" CONFIRMED: `:160 @92d12fa` is not in the diff. "Flashes nothing" CONTRADICTED: `:160 @92d12fa` returns `->withErrors(['install' => $e->getMessage()])`, which flashes an `errors` bag to the session. It flashes no `success`, `message` or `error` key, so `flash.message` stays empty. |
| C2 | CONTRADICTED (compound) | Success half CONFIRMED: `ProductController.php:197 @92d12fa` - `->with('success', $manifest['name'].' is uninstalled.')` after `$this->installer->uninstall($instance)` at `:188`; same statement at `:524 @HEAD`. No-instance path CONFIRMED: `:184 @92d12fa` is a bare redirect. Refusal path "flashes nothing" CONTRADICTED: `:190 @92d12fa` returns `->withErrors(['uninstall' => $e->getMessage()])`, which flashes an `errors` bag (no `success`, `message` or `error` key). |
| C3 | CONFIRMED | `resources/js/pages/products/show.vue:61-76,125 @92d12fa` - an `immediate` watcher on `page.props.flash` returns early without `message`, otherwise sets message and type and increments `flashKey`; `<RekaToast v-if="flashMessage" :key="flashKey" ...>` is identical to `resources/js/layouts/AppLayout.vue:29-42,67 @92d12fa`. At @92d12fa `show.vue` imports and wraps no layout. At @HEAD the block is gone, removed by 49eca947, which carries no claim (see F3). |
| C4 | CONFIRMED | `tests/Feature/ProductInstallTest.php:187 @92d12fa` ("installs the product on POST and shows the page in its installed state") asserts `assertSessionHas('success', 'Chat Checkin is installed.')`. `tests/Feature/ProductUninstallTest.php:140 @92d12fa` ("uninstalls from the product page and shows the page uninstalled") asserts `assertSessionHas('success', 'Follower Bowling is uninstalled.')`. Both assert the value as well as the key. Both files ran @HEAD with `php artisan test tests/Feature/ProductInstallTest.php tests/Feature/ProductUninstallTest.php`: 27 passed, 211 assertions. |

### Surface
Complete.

### Findings
- **F1** compound claim, halves differ - C1 says the install refusal path "flashes nothing", but `ProductController.php:160 @92d12fa` flashes an `errors` bag through `withErrors()`. Only "no success flash / no toast" is true. A remedy claim should restate C1 as "flashes no `success` key".
- **F2** compound claim, halves differ - C2 says the uninstall refusal path "flashes nothing", but `ProductController.php:190 @92d12fa` flashes an `errors` bag through `withErrors()`. A remedy claim should restate C2 the same way as C1.
- **F3** changed without a claim - the page-level `RekaToast` and flash watcher that C3 describes were deleted from `resources/js/pages/products/show.vue` by 49eca947 ("the products pages live inside the dashboard chrome"), which has no `Changelog:` trailer and no claim. That commit touched only `.vue` files, so the path rule exempted it, and its body discloses the removal. A remedy claim should record that C3 no longer holds @HEAD and that the toast now comes from `AppLayout` through `ProductsLayout` (`resources/js/layouts/ProductsLayout.vue:5,101 @HEAD`).

### Notes
- The C4 tests were run at HEAD, not at 92d12fa. The asserted strings are unchanged. Only the URL slugs moved from `_` to `-` (0212f7b3).
- The comment at `ProductController.php:149-150 @92d12fa` ("the page turns into its installed state. No toast on top of that.") predates this change and was left in place, where it now contradicts the new success toast. It is still there at `:361-362 @HEAD`.
- The comment at `app/Http/Middleware/HandleInertiaRequests.php:63 @92d12fa` ("One toast, rendered once in AppLayout") was untrue from this commit until 49eca947, because this commit added a second mount outside AppLayout.
- Unchanged is confirmed: `RekaToast.vue`, `AppLayout.vue` and `HandleInertiaRequests.php` are not in `git show --stat 92d12fa`.
