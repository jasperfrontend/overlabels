## OL-2609-043 - fix(products): install and uninstall show the app's toast on the product page

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-043`

### Surface
- `app/Http/Controllers/ProductController.php` - `install()` and `uninstall()` redirect with a `success` flash naming the product
- `resources/js/pages/products/show.vue` - mounts `RekaToast` with the same flash watcher `AppLayout` uses
- `tests/Feature/ProductInstallTest.php` - the install test asserts the `success` flash
- `tests/Feature/ProductUninstallTest.php` - the page uninstall test asserts the `success` flash

### Claims
- **C1** [code] `ProductController::install()` returns to `products.show` with session `success` = `<name> is installed.` after a successful install; the refusal path is unchanged and flashes nothing.
- **C2** [code] `ProductController::uninstall()` returns to `products.show` with session `success` = `<name> is uninstalled.` after `RecipeInstaller::uninstall()` returns; the no-instance and refusal paths flash nothing.
- **C3** [code] `products/show.vue` renders `RekaToast` keyed on a counter that increments on every `page.props.flash` change with a message, mirroring the block in `AppLayout.vue`, because the product pages render outside that layout.
- **C4** [test] `ProductInstallTest` and `ProductUninstallTest` assert `assertSessionHas('success')` on the install and uninstall redirects respectively.

### Unchanged
- `RekaToast.vue`, `AppLayout.vue` and `HandleInertiaRequests` (which folds `success` into `flash.message` and `flash.type`) are not in the diff.
