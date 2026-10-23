## OL-2610-004 - feat(dashboard): welcome new accounts and push them to the products

**Shipped:** 2026-10-23
**Commit:** `git log --grep=OL-2610-004`

### Surface
- `app/Http/Controllers/DashboardController.php` - `isNewUser` and `showWelcomeDialog` props, `claimWelcomeDialog()`, `WELCOME_DIALOG_REPEAT_SECONDS`
- `app/Models/User.php` - `welcome_shown_at` added to `PREFERENCE_DEFAULTS`
- `resources/js/pages/dashboard/index.vue` - mounts `WelcomeDialog` and, when `isNewUser`, `WelcomeBanner` above the tabs
- `resources/js/components/WelcomeDialog.vue` - new file
- `resources/js/components/WelcomeBanner.vue` - new file
- `resources/js/layouts/ProductsLayout.vue` - `ProductBadge` class `text-violet-400` replaced with `text-cyan-300`
- `resources/js/pages/products/index.vue` - same badge colour swap
- `resources/js/pages/products/show.vue` - same badge colour swap
- `tests/Feature/DashboardWelcomeTest.php` - new file

### Claims
- **C1** [code] `DashboardController::index()` sets `isNewUser` to true exactly when no `RecipeInstance` row has the user's `user_id`.
- **C2** [code] `showWelcomeDialog` is passed as a closure, so `claimWelcomeDialog()` runs only on a render that includes the prop.
- **C3** [code] `claimWelcomeDialog()` returns false when `preferences.welcome_shown_at` is less than `WELCOME_DIALOG_REPEAT_SECONDS` (30 days) old.
- **C4** [code] Otherwise `claimWelcomeDialog()` writes the current Unix timestamp to `preferences.welcome_shown_at`, saves the user and returns true.
- **C5** [code] `User::PREFERENCE_DEFAULTS['welcome_shown_at']` is null.
- **C6** [code] `WelcomeBanner.vue`'s root element is an Inertia `Link` to `route('products.index')`.
- **C7** [code] `WelcomeBanner` renders the `avatar` prop as an `img` when set, and a `Package` icon otherwise.
- **C8** [code] `WelcomeDialog.vue` takes its initial open state from the `show` prop into a local ref; after setup that ref is only ever set to false (the "Maybe later" button or the dialog closing itself), so a later render cannot reopen it.
- **C9** [code] No `ProductBadge` call site sets `text-violet-400`.
- **C10** [test] `DashboardWelcomeTest` asserts: an account with no install gets `isNewUser` and `showWelcomeDialog` true, and `welcome_shown_at` is recorded.
- **C11** [test] `DashboardWelcomeTest` asserts: a second visit gets `showWelcomeDialog` false while `isNewUser` stays true.
- **C12** [test] `DashboardWelcomeTest` asserts: a `welcome_shown_at` 31 days old gives `showWelcomeDialog` true; 10 days old gives false.
- **C13** [test] `DashboardWelcomeTest` asserts: an account with a `chat-checkin` install gets both props false and no `welcome_shown_at` written.
- **C14** [unverified] All five `DashboardWelcomeTest` tests failed with the `DashboardController` change stashed.
- **C15** [unverified] Neither the dialog nor the banner was viewed in a browser before the push.

### Unchanged
- The dashboard's "Get Started with Your First Template" card, rendered when the user has no overlays and no alerts, still sits below the tabs; it is not in the diff.
- `NudgeBar.vue` was tried for the banner and reverted; it is not in the diff.
- `OnboardNewUser` still forks the starter kit at signup. That is why `isNewUser` reads `RecipeInstance` rather than overlay counts. The job is not in the diff.

### Risk
An existing account with no product installed, however active, sees the dialog on its next
dashboard visit and every 30 days after that, and sees the banner until it installs a product.
