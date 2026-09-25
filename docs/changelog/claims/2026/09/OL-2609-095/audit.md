## Audit of OL-2609-095 - fix(account): a copied kit no longer makes account deletion throw after logging the user out

**Audited:** 2026-09-25
**Commit:** e62641f88757e0990f42b218a8858563692488a6
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Models/Kit.php:138-139 @e62641f` - `if (! self::$forkGuardDisabled && $kit->fork_count > 0) { throw new Exception('Cannot delete a kit that has been forked.'); }`; same at `app/Models/Kit.php:138 @HEAD` |
| C2 | CONFIRMED | `app/Models/Kit.php:118-126 @e62641f` - sets `self::$forkGuardDisabled = true`, `return $callback()` in `try`, resets to false in `finally`; unchanged @HEAD (no commit after e62641f touches `Kit.php`) |
| C3 | CONFIRMED | `app/Services/UserDeletionService.php:26-31 @e62641f` - the `Kit::where('owner_id', ...)->each(...)` loop runs inside `Kit::withoutForkGuard()`. `git grep 'withoutForkGuard(\|forkGuardDisabled' -- app` @e62641f and @HEAD: the only call site outside `Kit.php` is `UserDeletionService.php` (line 26 @e62641f, line 79 @HEAD, file reworked by OL-2609-097) |
| C4 | CONFIRMED | `app/Http/Controllers/Settings/AccountController.php:44 @e62641f` calls `$deletion->eraseAccount($user)` before `logout()` at :46, `invalidate()` :47, `regenerateToken()` :48. @e62641f^ the three ran at :41-43 and `eraseAccount` at :45. @HEAD the call is `eraseAccount($user, redactAuditTrail: true)` at :47, still before `logout()` at :49 (OL-2609-097) |
| C5 | CONFIRMED | `app/Http/Controllers/KitController.php:247 @e62641f` - `if (! $kit->canBeDeleted())` returns `back()->withErrors(['error' => ...])`; file not in the diff, no later commit touches it, same line @HEAD |
| C6 | CONFIRMED | `tests/Feature/AccountDeletionForkedKitTest.php @e62641f` - four tests: `assertDatabaseMissing('users', ...)` and `assertDatabaseMissing('kits', ['owner_id' => ...])`; copy row kept with `forked_from_id` null; `kits.destroy` `assertSessionHasErrors('error')`; later `$kit->delete()` `toThrow(Exception::class)`. `php artisan test --filter=AccountDeletionForkedKitTest` @HEAD: 4 passed (9 assertions) |
| C7 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a pre-fix tree, which the guide assigns to that tag |
| C8 | CONFIRMED | `database/migrations/2025_08_25_173309_create_kits_table.php:25 @e62641f` - `$table->foreign('forked_from_id')->references('id')->on('kits')->onDelete('set null')`; same @HEAD |

### Surface
Complete.

### Findings
None.

### Notes
- C6 was run at HEAD only. A worktree at e62641f could not boot: `Class "Stevebauman\Location\LocationServiceProvider" not found` (that package was removed from `vendor` by OL-2609-097, but the old tree's `bootstrap/cache/packages.php` still lists it).
- `tests/Feature/AccountDeletionForkedKitTest.php` was changed after shipping by 8ad02ed3 (OL-2609-097), which added `Http::fake` / `Storage::fake('images')` in a `beforeEach`. The four assertions are the same. OL-2609-097's Surface does not list this file; that belongs in 097's audit, not this one.
- Unchanged line 4 (`UserDeletionService` does not delete R2 images, deregister the Fourthwall webhook or revoke the Twitch grant) was true @e62641f and has been superseded by OL-2609-097, which added all three.
- Unchanged lines 1-3 checked @e62641f: `OverlayTemplate::boot()` `deleting` hook (`app/Models/OverlayTemplate.php:144-149`), `Kit::canBeDeleted()` (`Kit.php:243-245`) and the thumbnail cleanup (`Kit.php:142-145`) are all outside the diff hunks. `$template->kits()->detach()` comes before `$template->delete()` at `UserDeletionService.php:35,38`.
