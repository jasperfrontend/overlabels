## OL-2611-009 - feat(kits): new accounts pick a kit, and ready-made kits lead the Alerts shelf

**Shipped:** 2026-11-10
**Commit:** `git log --grep=OL-2611-009`

### Surface
- `database/migrations/2026_11_10_140000_add_featured_at_to_kits.php` - new, nullable `kits.featured_at`
- `app/Models/Kit.php` - `featured_at` datetime cast; `fork()` sets it to null on the copy
- `app/Services/Kits/FeaturedKits.php` - new, `cards()`
- `app/Http/Controllers/KitController.php` - `start()`, renders `kits/start`
- `app/Http/Controllers/ProductController.php` - `index()` sends `featured_kits`
- `app/Jobs/OnboardNewUser.php` - starter kit copy and keyword event wiring removed; only the webhook secret remains
- `routes/web.php` - `GET /start` named `kits.start`; Twitch callback sends a new account there when no intended URL applies
- `resources/js/components/kits/FeaturedKitCard.vue` - new, one featured kit card
- `resources/js/pages/kits/start.vue` - new, the kit picker
- `resources/js/pages/products/index.vue` - Alerts shelf: featured kits first, "Basic alerts" heading above the existing content
- `tests/Feature/KitPickerTest.php` - new
- `tests/Feature/PruneIdleAccountsTest.php` - `signedUp()` copies the starter kit and maps its alerts itself
- `CLAUDE.md` - "Kits are the front door" section

### Claims
- **C1** [test] `FeaturedKits::cards()` returns public kits with `featured_at` set, ordered by `featured_at` ascending, and omits unfeatured and private kits.
- **C2** [test] A card's `covers` lists its kit's declared trigger services in declared order without duplicates, and `alerts` counts its alert templates.
- **C3** [test] `Kit::fork()` of a featured kit leaves the copy's `featured_at` null.
- **C4** [test] `GET /start` renders `kits/start` with the featured kits as `kits`.
- **C5** [test] `GET /products` and `GET /products?category=alert` send the featured kits as `featured_kits`; `GET /products?category=product` sends an empty list.
- **C6** [test] `OnboardNewUser::handle()` creates no kit, template or event mapping for the user, with a starter kit present, and sets `webhook_secret`.
- **C7** [test] The Twitch callback for an account it creates, with no stored intended URL, redirects to `kits.start`.
- **C8** [test] The Twitch callback for an account it creates, with a safe intended URL, redirects to that URL.
- **C9** [test] `PruneIdleAccountsTest` passes with `signedUp()` building the starter copy through `Kit::fork()` and `EventTemplateMapping::create()`.
- **C10** [unverified] C3, C4, C5, C6 and C7 failed with `OnboardNewUser.php`, `routes/web.php`, `ProductController.php`, `KitController.php` and `Kit.php` reverted to the parent commit.
- **C11** [unverified] On local, in Firefox: `/start` showed two featured kit cards and "Skip for now"; `/products?category=alert` showed "Ready-made alert kits" above "Basic alerts".

### Unchanged
- `IdleAccountSweep` is not in the diff: it still recognises a starter copy by `is_starter_kit` on the kit it was forked from.
- The Twitch callback's intended-URL safety check is not changed; only the fallback after it is.
- The admin kits page and its starter flag are not in the diff.
- No kit has `featured_at` set by this change; the column starts null everywhere.

### Risk
New accounts no longer get Twitch alerts wired automatically: they have none until they copy a kit
and assign it. Until `featured_at` is set on prod, `/start` shows "No ready-made kits yet" and the
Alerts shelf shows only the basic alerts. A new column; the migration runs on deploy.
