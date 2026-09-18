## OL-2609-095 - fix(account): a copied kit no longer makes account deletion throw after logging the user out

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-095`

### Surface
- `app/Models/Kit.php` - new `protected static bool $forkGuardDisabled`, new `withoutForkGuard(callable)`, and the `deleting` hook's throw now also tests that flag
- `app/Services/UserDeletionService.php` - the kit loop in `eraseAccount()` is wrapped in `Kit::withoutForkGuard()`
- `app/Http/Controllers/Settings/AccountController.php` - `eraseAccount()` moved above the logout/invalidate/regenerate block in `destroy()`
- `tests/Feature/AccountDeletionForkedKitTest.php` - new file, 4 tests

### Claims
- **C1** [code] `Kit::boot()`'s `deleting` closure throws `Cannot delete a kit that has been forked.` only when `$kit->fork_count > 0` AND `self::$forkGuardDisabled` is false.
- **C2** [code] `Kit::withoutForkGuard()` sets `$forkGuardDisabled` true, calls the callback, and resets it to false in a `finally`, so a throwing callback cannot leave the guard down.
- **C3** [code] `UserDeletionService::eraseAccount()` deletes the user's kits inside `Kit::withoutForkGuard()`. No other call site in `app/` lifts the guard.
- **C4** [code] `AccountController::destroy()` calls `$deletion->eraseAccount($user)` before `Auth::guard('web')->logout()`. Previously the logout, `session()->invalidate()` and `session()->regenerateToken()` all ran first.
- **C5** [code] `KitController::destroy()` still refuses a copied kit via its own `canBeDeleted()` check and is not in the diff, so the interactive guard is unchanged.
- **C6** [test] `AccountDeletionForkedKitTest` asserts: an account owning a kit with `fork_count > 0` is deleted and leaves no `users` or `kits` row; a copy owned by another user survives with `forked_from_id` null; `kits.destroy` still returns a session error for a copied kit; and a later `$kit->delete()` still throws after an erasure has run.
- **C7** [unverified] Against the pre-fix tree, two of those four tests fail: "deletes an account whose kit has been copied by someone else" and "leaves a copy standing when the account it was copied from is deleted". The other two pass, which is the point - they pin the guard that must not move. Run 2026-09-18 by reverting only the `$forkGuardDisabled` term in the `deleting` hook.
- **C8** [code] `kits.forked_from_id` is declared `onDelete('set null')` in `database/migrations/2025_08_25_173309_create_kits_table.php`, so deleting a parent kit cannot cascade into a copy.

### Unchanged
- `OverlayTemplate::boot()`'s `deleting` hook has the same shape (it throws when the template is in any kit) and is NOT in the diff. `eraseAccount()` already calls `$template->kits()->detach()` before `$template->delete()`, which empties the relation the hook counts, so that path was never able to throw.
- `Kit::canBeDeleted()` still returns `fork_count === 0` and is untouched. It is the interactive check and reads the column, not the flag, so lifting the flag does not change what the kits page shows.
- The `deleting` hook's local-thumbnail cleanup runs as before. The guard was made conditional rather than the delete being made event-free, precisely so that cleanup still fires during an erasure.
- `UserDeletionService` still does not delete R2 images, deregister a Fourthwall webhook, or revoke the Twitch grant. Those are pre-existing gaps, documented in `resources/help/pages/your-data.md` (OL-2609-096), and are not in this diff.

### Risk
Deleting an account now also deletes kits that other people have copied. Their copies are unaffected;
only the `forked_from_id` link is cleared. Any account that previously could not be deleted for this
reason can be deleted now.
