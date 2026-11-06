## OL-2611-001 - feat(users): delete accounts that did nothing in their first 48 hours

**Shipped:** 2026-11-06
**Commit:** `git log --grep=OL-2611-001`

### Surface
- `app/Services/IdleAccountSweep.php` - new file; `idle()` query and `erase()`
- `app/Console/Commands/PruneIdleAccounts.php` - new file; `users:prune-idle` with `--dry-run`
- `routes/console.php` - schedules `users:prune-idle` hourly
- `tests/Feature/PruneIdleAccountsTest.php` - new file

### Claims
- **C1** [code] `IdleAccountSweep::idle()` only returns users whose `created_at` is at least `GRACE_HOURS` (48) hours ago.
- **C2** [code] `idle()` excludes users with `is_system_user` true, `role` = `admin`, any `bans` row, or `bot_enabled` true.
- **C3** [code] `idle()` excludes users whose `preferences.welcome_funnel.installed_at` is set.
- **C4** [code] `idle()` excludes users with an `overlay_access_tokens` row that has `is_active` true and `expires_at` null or in the future.
- **C5** [code] `idle()` excludes users with any row in each table listed in `IdleAccountSweep::ACTIVITY_TABLES`, matched on `user_id`.
- **C6** [code] `idle()` excludes users owning a kit whose `forked_from_id` is null or is not a kit with `is_starter_kit` true.
- **C7** [code] `idle()` excludes users owning an overlay template, an overlay control, or an event mapping whose template is not in `kit_templates` for one of their starter-copy kits; a control with a null `overlay_template_id` also excludes.
- **C8** [code] `IdleAccountSweep::erase()` re-runs `idle()` for that user and calls `UserDeletionService::eraseAccount()` only if it still matches.
- **C9** [code] `PruneIdleAccounts` erases at most `LIMIT` (100) users per run; with `--dry-run` it lists every match and erases none.
- **C10** [code] `routes/console.php` schedules `users:prune-idle` hourly with `withoutOverlapping()`.
- **C11** [test] `PruneIdleAccountsTest` asserts an account onboarded through `OnboardNewUser::handle()` and 49 hours old is deleted, including after its starter templates and controls were edited.
- **C12** [test] `PruneIdleAccountsTest` asserts an account is kept when 47 hours old, holding an active token, carrying the funnel install stamp, owning its own overlay, kit, user-scoped control or integration, having the bot on, being admin, system or banned.
- **C13** [test] `PruneIdleAccountsTest` asserts revoked and expired tokens do not keep an account, and that `--dry-run` deletes nothing.
- **C14** [unverified] The token-guard test was run with the active-token condition disabled and failed.

### Unchanged
- `UserDeletionService::eraseAccount()` is what the sweep calls to delete; it is not in the diff.
- `OnboardNewUser` and `Kit::fork()` create the starter copy the sweep recognises; neither is in the diff.

### Risk
Applies retroactively: the first scheduled run after deploy hard-deletes existing idle accounts, up
to 100 per hour, with no undo beyond the database backups.
