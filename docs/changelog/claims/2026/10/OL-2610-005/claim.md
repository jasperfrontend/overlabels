## OL-2610-005 - feat(admin): a funnel page that shows how far new accounts get

**Shipped:** 2026-10-23
**Commit:** `git log --grep=OL-2610-005`

### Surface
- `app/Support/WelcomeFunnel.php` - new file: `reachedListing()`, `openedProduct()`, `installed()`, `SOURCES`, `DIRECT`, `PREFERENCE`
- `app/Http/Controllers/ProductController.php` - `index()` calls `WelcomeFunnel::reachedListing()`, `pitch()` calls `WelcomeFunnel::openedProduct()`, `install()` calls `WelcomeFunnel::installed()` after a successful install
- `app/Http/Controllers/Admin/AdminFunnelController.php` - new file
- `routes/admin.php` - `GET /admin/funnel`, named `admin.funnel.index`
- `resources/js/pages/admin/funnel/index.vue` - new file
- `resources/js/components/AppSidebar.vue` - Funnel entry in the admin nav
- `resources/js/components/WelcomeBanner.vue` - link carries `from=welcome-banner`
- `resources/js/components/WelcomeDialog.vue` - button carries `from=welcome-dialog`
- `tests/Feature/WelcomeFunnelTest.php` - new file
- `docs/changelog/changelog-2026-10.md` - prose entry

### Claims
- **C1** [code] Every stamp lives under `preferences.welcome_funnel` as Unix seconds, and `stamp()` writes a step only when it is null, so no step ever moves after its first write.
- **C2** [code] `reachedListing()` sets `listing_from` to the `from` query value when it is a key of `SOURCES` (`welcome-banner`, `welcome-dialog`), otherwise to `direct`, and stamps that source's own `banner_at` / `dialog_at` independently of `listing_at`.
- **C3** [code] The user is saved only when a stamp changed, so a repeat visit issues no write.
- **C4** [code] `subject()` returns null for a guest and whenever the session has `impersonating_user_id`, so neither stamps anything.
- **C5** [code] `AdminFunnelController::index()` accepts `days` of 7, 30, 90 or 0 (all), defaults to 30, excludes `is_system_user` accounts and caps rows at 500.
- **C6** [code] A row's `installed_at` is the funnel stamp, or else the earliest `recipe_instances.created_at` for that user.
- **C7** [code] Each total is the number of rows with that step set; `signed_up` is the row count.
- **C8** [test] `WelcomeFunnelTest` asserts: banner link stamps listing + banner; first visit and its source are kept while a later banner click is still stamped; unknown source reads as `direct`; dialog link stamps `dialog_at`; only the first product page is kept; an install survives deleting its instance row; guests and impersonation stamp nothing; non-admins get 404; the admin page returns the rows and totals consistent with them, with a pre-funnel install counted via its row.
- **C9** [unverified] Seven of the nine tests failed with the `ProductController` change stashed; the impersonation test failed with the impersonation guard removed.
- **C10** [unverified] The admin page was not viewed in a browser before the push.

### Unchanged
- The privacy policy and `/help/your-data` were not edited. Jasper's call, made in the session.
- `recipe_instances` and the welcome dialog/banner logic in `DashboardController` are untouched.

### Risk
Funnel stamps exist only from this deploy, so accounts older than it show blanks for the listing,
product page and welcome-link columns even if they got further.
