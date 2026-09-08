## OL-2609-036 - fix(dashboard): opening a post marks it seen on the What's New card, and the visit middleware is gone

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-036`

### Surface
- `app/Http/Middleware/MarkWhatsNewVisited.php` - deleted, whole file
- `bootstrap/app.php` - the import and the `web` group entry for that middleware removed
- `routes/web.php` - `dashboard.whats-new.visited` route and its comment removed; the What's New block comment reworded
- `app/Http/Controllers/UpdateController.php` - `show()` takes `Request`, calls new private `markSeen()`; `UpdateInteraction` import
- `app/Http/Controllers/WhatsNewController.php` - `markVisited()` removed; `props()` no longer eager-loads `interactions` or emits `stale`; docblocks reworded
- `app/Models/Update.php` - `CTA_TARGETS_KEY`, `ctaTargets()`, the `saved`/`deleted` cache hooks and the `Cache` import removed; `cta()` no longer returns `external`
- `app/Models/UpdateInteraction.php` - `visited_at` removed from `$fillable` and `$casts`; docblock rewritten
- `database/migrations/2026_09_08_233000_drop_visited_at_from_update_interactions.php` - new file, drops the column
- `resources/js/components/WhatsNewCard.vue` - `onCtaClick()` and every `stale` branch removed; new `opened()` bound to the title link with `@click.capture`
- `resources/js/types/index.d.ts` - `WhatsNewItem` loses `stale` and `cta.external`
- `tests/Feature/WhatsNewCardTest.php` - the "Going stale on a visit" section replaced by "Opening the post is reading it"; one dismiss test and one `external` assertion removed; `Route` facade import

### Claims
- **C1** [code] `UpdateController::show()` calls `markSeen()`, which writes `dismissed_at = now()` via `UpdateInteraction::updateOrCreate` for the current user and the requested post.
- **C2** [code] `markSeen()` returns without writing when `$request->user()` is null.
- **C3** [code] `markSeen()` returns without writing unless `Update::query()->unseenBy($user)->whereKey($update->id)->exists()` is true, so a post that predates the account, lacks the `whatsnew` tag, is future-dated or is already dismissed produces no write.
- **C4** [test] `WhatsNewCardTest` "marks a post seen when a logged in reader opens it": after `GET updates.show`, the dashboard prop has no items, `total` 0, `canUndo` true, and the row's `dismissed_at` is set.
- **C5** [test] `WhatsNewCardTest` "writes nothing when a guest opens a post", "writes nothing for a post that was never on the reader's card" and "writes nothing for a post without the card tag" each assert `UpdateInteraction::count()` is 0 after the visit.
- **C6** [test] `WhatsNewCardTest` "does not move the batch when a post is opened again": a second `GET updates.show` five minutes later leaves one row with the original `dismissed_at`.
- **C7** [test] `WhatsNewCardTest` "brings an opened post back with undo": `DELETE dashboard.whats-new.undo` after opening returns the post to the card.
- **C8** [test] `WhatsNewCardTest` "has no visited route and no visit middleware": a logged-in `GET dashboard.recents` with a card post whose CTA names that route writes no `UpdateInteraction`, the post stays on the card, and `Route::has('dashboard.whats-new.visited')` is false.
- **C9** [code] `app/Http/Middleware/MarkWhatsNewVisited.php` does not exist in the tree, and `bootstrap/app.php` contains no reference to it.
- **C10** [code] `Update` has no `ctaTargets()` method and no `saved`/`deleted` model hooks; `cta()` returns exactly `label` and `href`.
- **C11** [code] The migration drops `update_interactions.visited_at` in `up()` and re-adds it nullable after `update_id` in `down()`.
- **C12** [unverified] The migration was run, rolled back with `--step=1` and run again on the local Postgres on 2026-09-08; `Schema::getColumnListing('update_interactions')` afterwards was `id,user_id,update_id,dismissed_at,created_at,updated_at`.
- **C13** [code] `WhatsNewCard.vue` `opened()` calls `router.replaceProp('whatsNew', ...)` returning the current value with the clicked item filtered out of `items`, `total` decremented (floored at 0) and `canUndo` true.
- **C14** [code] `opened()` is bound to the title `<Link>` with `@click.capture`. `@inertiajs/vue3` 3.4's `Link` render spreads its own `onClick` after `attrs`, so a non-capture `@click` on `Link` is overwritten and never fires.
- **C15** [unverified] In Chrome against the built assets on `overlabels.test`, clicking a card title, reading the post and pressing Back returned to a dashboard whose card no longer listed that post. Without `opened()`, Inertia's `handlePopstateEvent` restores the dashboard from `history.state` and the card still lists it.
- **C16** [code] `WhatsNewController::props()` no longer emits a `stale` key, and `WhatsNewItem` in `resources/js/types/index.d.ts` has no `stale` or `cta.external` field.

### Unchanged
- `Update::scopeUnseenBy()` is the selection query the card, `markSeen()` in `WhatsNewController` and the new `markSeen()` in `UpdateController` all share; its four conditions are not in the diff.
- The `cta_route`, `cta_params`, `cta_url` and `cta_label` columns and `Update::projectCta()` still exist. They were introduced so the middleware could match a visit in SQL, but `cta()` reads them to build the per-row link, so they stay with that consumer. Only `ctaTargets()`, which had no other caller, is removed.
- `WhatsNewController::markSeen()`, `dismiss()` and `undo()` are not in the diff beyond docblock wording; the undo batch rule (`max(dismissed_at)`) is untouched.
- `UpdateController::index()`, `sharePostMeta()` and everything below `show()` are not in the diff.
- The row's CTA link in `WhatsNewCard.vue` is still a plain `<a>` and no longer has a click handler; visiting a CTA target is not reading the post and records nothing.

### Risk
Deploy runs the migration and drops `visited_at`. Any prod row holding only `visited_at` becomes an empty row, which the card reads as no row. Readers who had rows greyed but not dismissed will see those rows back in teal once; opening any of them clears it.

When a card shows 5 of more than 5 posts and one is opened, the client-side `replaceProp` leaves 4 rows and an overflow count one lower than the server would render; the next full load of the dashboard shows the server's 5.
