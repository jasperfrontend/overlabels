## Audit of OL-2609-024 - fix(twitch): the bot account can grant user:read:chat and user:bot to this app, which channel.chat.notification needs

**Audited:** 2026-09-24
**Commit:** a0586ddedfcaabfc4c294e57687aa5a659182bd6 (single commit carrying `Changelog: OL-2609-024`)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/TwitchScopeService.php:60-63 @a0586dd` - `BOT_ACCOUNT_SCOPES = ['user:read:chat', 'user:bot']`; neither string appears in `REQUIRED_SCOPES` (:23-47 @a0586dd). @HEAD the constant is unchanged at :64-67; `REQUIRED_SCOPES` has since gained `channel:manage:broadcast` (:50 @HEAD, OL-2609-026) and is still disjoint from it |
| C2 | CONFIRMED | `git show a0586dd` touches no line of `REQUIRED_SCOPES`; `routes/web.php:393-399 @a0586dd` - `$scopes = TwitchScopeService::REQUIRED_SCOPES` is passed unchanged to `$driver->scopes($scopes)` unless `scopes === 'bot'`, where the old line was `$driver->scopes(TwitchScopeService::REQUIRED_SCOPES)`. Same at `routes/web.php:388-393 @HEAD` |
| C3 | CONFIRMED | `tests/Feature/TwitchLoginScopesTest.php:27-29 @a0586dd` - asserts `array_diff(REQUIRED_SCOPES, $scopes) === []`, `array_intersect(BOT_ACCOUNT_SCOPES, $scopes) === []`, `force_verify=true` absent. `php artisan test --filter=TwitchLoginScopesTest` @HEAD: 3 passed (10 assertions); file unchanged since a0586dd |
| C4 | CONFIRMED | `tests/Feature/TwitchLoginScopesTest.php:36-38 @a0586dd` - for `?scopes=bot`, asserts both lists fully contained and `force_verify=true` present; passed in the same run |
| C5 | CONFIRMED | `/auth/callback/twitch` (`routes/web.php:419 @a0586dd`) has no hunk in the diff; `:440` sanitizes `approvedScopes`, `:464` stores them on create, `:485-486` overwrites `twitch_scopes` when non-empty; `getMissingScopes()` (`TwitchScopeService.php` @a0586dd) is `array_diff(REQUIRED_SCOPES, getUserScopes())`, empty for a superset. Same logic at `routes/web.php:434-480 @HEAD` |
| C6 | UNVERIFIABLE | tagged [unverified] (production 403 responses and user ids) |
| C7 | UNVERIFIABLE | tagged [unverified] (production secret values; the bot container is not configured in this repo's `config/deploy.yml`, which lists `TWITCH_CLIENT_ID` and `TWITCHBOT_CLIENT_ID` only as secret names at :258/:262 @a0586dd) |
| C8 | UNVERIFIABLE | tagged [unverified] (Twitch API behaviour) |

### Surface
Complete.

### Findings
- **F1** scope - the `CLAUDE.md` hunk @a0586dd adds a third bullet at `CLAUDE.md:336` ("Corollary worth knowing: the bot sends chat "as app" through the BOT app... Not investigated further") about bot chat SENDS, which neither the Surface line (per-client-id finding and the authorization step) nor any claim accounts for; a follow-up claim should record it, noting OL-2609-025 has since replaced that bullet.

### Notes
- Unchanged lines checked: `UserEventSubManager`, `AdminTwitchBotController`, `TwitchEventSubService`, `SetupUserEventSubSubscriptions` and `eventsub:backfill-goals` have no path in `git show --stat a0586dd`.
- The docblock on `REQUIRED_SCOPES` (`app/Services/TwitchScopeService.php:19-21 @a0586dd`, still present @HEAD) calls it the "full list of scopes the platform currently asks for at /auth/redirect/twitch"; the `?scopes=bot` path now asks for more. Stale comment, not a finding.
- The "Corollary" bullet named in F1 was replaced by OL-2609-025 (commit 053b77c9), which discloses it in its own Surface; absent from `CLAUDE.md @HEAD`.
- OL-2609-023's "no re-auth" wording is narrowed by this change; 024 cites OL-2609-023 inline (Unchanged line 1, C6), and the 023 audit already records it as disclosed drift.
