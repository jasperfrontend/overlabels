## Audit of OL-2609-036 - fix(dashboard): opening a post marks it seen on the What's New card, and the visit middleware is gone

**Audited:** 2026-09-25
**Commit:** e262dc09082815d2f18b023ebd908346e424e133
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/UpdateController.php:59 @e262dc0` - `show()` calls `$this->markSeen($request, $update)`; `:103-106 @e262dc0` - `UpdateInteraction::query()->updateOrCreate(['user_id' => $user->id, 'update_id' => $update->id], ['dismissed_at' => now()])`; same lines @HEAD |
| C2 | CONFIRMED | `app/Http/Controllers/UpdateController.php:88-92 @e262dc0` - `$user = $request->user(); if ($user === null) { return; }`; same @HEAD |
| C3 | CONFIRMED | `app/Http/Controllers/UpdateController.php:94-101 @e262dc0` - `Update::query()->unseenBy($user)->whereKey($update->id)->exists()`, returns when false; `app/Models/Update.php:133-142 @e262dc0` - `scopeUnseenBy` applies `published()` (`published_at <= now()`, `:112-115`), `whereJsonContains('tags', CARD_TAG)`, `published_at > $user->created_at`, and `whereDoesntHave` dismissed interaction; unchanged @HEAD |
| C4 | CONFIRMED | `tests/Feature/WhatsNewCardTest.php:225-239 @e262dc0` asserts `items` empty, `total` 0, `canUndo` true, `dismissed_at` not null after `GET updates.show`; `whatsNewProp()` (`:38-43`) reads the `/dashboard` Inertia prop. `php artisan test --filter=WhatsNewCardTest` @HEAD: 35 passed (120 assertions) |
| C5 | CONFIRMED | `tests/Feature/WhatsNewCardTest.php:251 @e262dc0` (guest), `:259` (predates account; also asserts `canUndo` false), `:272` (`tags => ['release']`) each assert `UpdateInteraction::count()` is 0; passed in the run above |
| C6 | CONFIRMED | `tests/Feature/WhatsNewCardTest.php:281 @e262dc0` - `travel(5)->minutes()`, second `GET updates.show`, asserts count 1 and `dismissed_at->timestamp` equals the first; passed |
| C7 | CONFIRMED | `tests/Feature/WhatsNewCardTest.php:296 @e262dc0` - `DELETE dashboard.whats-new.undo` after opening, asserts item ids equal `[$update->id]`; passed |
| C8 | CONFIRMED | `tests/Feature/WhatsNewCardTest.php:316 @e262dc0` - CTA `route: dashboard.recents`, `GET dashboard.recents`, asserts `UpdateInteraction::count()` 0, one item on card, `Route::has('dashboard.whats-new.visited')` false; passed @HEAD (route URL since moved by OL-2609-130, name kept) |
| C9 | CONFIRMED | `app/Http/Middleware/MarkWhatsNewVisited.php` deleted in e262dc0 and absent @HEAD; `git show e262dc0:bootstrap/app.php \| grep -c MarkWhatsNew` = 0; `git grep MarkWhatsNewVisited HEAD -- app bootstrap routes resources tests` empty |
| C10 | CONFIRMED | `app/Models/Update.php @e262dc0` - no `ctaTargets`; `boot()` (`:63`) registers only `saving`; `cta()` `:305-308` returns `['label' => ..., 'href' => $href]`; same @HEAD (`:283`, `:307`) |
| C11 | CONFIRMED | `database/migrations/2026_09_08_233000_drop_visited_at_from_update_interactions.php:24 @e262dc0` - `dropColumn('visited_at')`; `:31` - `timestamp('visited_at')->nullable()->after('update_id')`; unchanged @HEAD |
| C12 | UNVERIFIABLE | tagged [unverified] (local DB run) |
| C13 | CONFIRMED | `resources/js/components/WhatsNewCard.vue:64-73 @e262dc0` - `router.replaceProp('whatsNew', ...)` returns `items` filtered by `row.id !== item.id`, `total: Math.max(0, value.total - 1)`, `canUndo: true`; same @HEAD `:64-70` |
| C14 | CONFIRMED (compound, see F1) | Half 1: `resources/js/components/WhatsNewCard.vue:111 @e262dc0` - `<Link ... @click.capture="opened(item)">` (`:136 @HEAD`). Half 2: `node_modules/@inertiajs/vue3/dist/index.js:1898-1912` (installed 3.7.0) - Link render spreads `...attrs` first, then `regularEvents` carrying `onClick`; `package-lock.json @e262dc0` resolves `@inertiajs/vue3` to 3.7.0, not the 3.4 the claim names |
| C15 | UNVERIFIABLE | tagged [unverified] (browser observation) |
| C16 | CONFIRMED | `git show e262dc0:app/Http/Controllers/WhatsNewController.php` - only `stale` match is prose at `:64`, no `'stale'` key; `resources/js/types/index.d.ts:213-220 @e262dc0` - `WhatsNewItem` has no `stale`, `cta: { label: string; href: string } \| null` (`:236 @HEAD`) |

### Surface
Complete.

### Findings
- **F1** compound claim / inaccurate detail - C14 joins two assertions (the `@click.capture` binding and Inertia's Link overriding a non-capture `@click`) and attributes the override to `@inertiajs/vue3` 3.4, while `package-lock.json @e262dc0` pins 3.7.0 (`package.json` only declares `^3.4.0`); the mechanism is true of 3.7.0 at `node_modules/@inertiajs/vue3/dist/index.js:1898-1912`, but 3.4 is not in the tree to check. A follow-up claim should split C14 and name the locked version.

### Notes
- Unchanged line 5 ("the row's CTA link ... no longer has a click handler") is superseded by OL-2609-037, which binds `followCta()` to that anchor; 037 cites 036 in its Unchanged section.
- OL-2609-071 C16 removed `WhatsNewCard` from `dashboard/index.vue`; @HEAD no `.vue` imports the component, while `DashboardController.php:60 @HEAD` still emits `whatsNew`, which is why the tests still pass.
- OL-2609-130 moved the Recent page URL to `/recents` and kept the `dashboard.recents` name that C8's test uses.
- The removed visited/stale design is recorded in prose in `docs/changelog/changelog-2026-08.md:374`; that predates claim files, so it is not a contradicted claim.
- Tests: `php artisan test --filter=WhatsNewCardTest` run @HEAD (these files have no PHP drift since e262dc0): 35 passed, no build needed.
