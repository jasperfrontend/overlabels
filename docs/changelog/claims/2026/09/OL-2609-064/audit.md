## Audit of OL-2609-064 - fix(streamlabs): move the OAuth flow to API v2.0 and check the state on the way back

**Audited:** 2026-09-25
**Commit:** 0a660893c29ed9ddf43de51c7b002c9bf8661d76
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/Settings/StreamLabsIntegrationController.php:35 @0a66089` - `API_BASE = 'https://streamlabs.com/api/v2.0'`; `:48-59 @0a66089` - `bin2hex(random_bytes(20))` (40 hex) put in session under `OAUTH_STATE_KEY` (`:37`, `streamlabs_oauth_state`), query has `client_id`, `redirect_uri`, `response_type=code`, scope `socket.token donations.read donations.create`, `state`; redirects to `API_BASE."/authorize?..."`. @HEAD scope is `socket.token donations.read` (OL-2609-097 C25) and `redirect()` also calls `rememberReturnTo()` (OL-2609-084) |
| C2 | CONFIRMED | `StreamLabsIntegrationController.php:76-84 @0a66089` - `session()->pull(self::OAUTH_STATE_KEY, '')`, `$expectedState === '' \|\| ! hash_equals(...)` returns a redirect to `settings.integrations.streamlabs.show` with `error` before the first `Http::` call at `:88`. @HEAD the redirect is `$this->returnTo()`, which falls back to the show route (OL-2609-084 C7, C8) |
| C3 | CONFIRMED | `StreamLabsIntegrationController.php:88-94 @0a66089` - `Http::asForm()->post(self::API_BASE.'/token', [...])` with `grant_type=authorization_code`, `client_id`, `client_secret`, `redirect_uri`, `code`; same @HEAD |
| C4 | CONFIRMED | `StreamLabsIntegrationController.php:116-117 @0a66089` - `Http::withToken($accessToken)->get(self::API_BASE.'/socket/token')`; the only `v1.0` strings in the file @0a66089 are docblock lines 29 and 31, no request; `www.streamlabs` absent; same @HEAD |
| C5 | CONFIRMED | `StreamLabsIntegrationController.php:141-146 @0a66089` - `setCredentialsEncrypted` with `access_token`, `refresh_token => $tokenData['refresh_token'] ?? null`, `socket_token`, `listener_secret`; same @HEAD |
| C6 | CONFIRMED | `tests/Feature/StreamLabsOAuthTest.php:28-42 @0a66089` - `toStartWith('https://streamlabs.com/api/v2.0/authorize')`, `not->toContain('v1.0')`, `assertSessionHas('streamlabs_oauth_state', $query['state'])`, `toHaveLength(40)`. File identical @HEAD. `php artisan test --filter='StreamLabsOAuthTest\|IntegrationProvisioningTest'` @HEAD: 22 passed |
| C7 | CONFIRMED | `StreamLabsOAuthTest.php:56-95 @0a66089` - `assertSent` on v2.0 token URL with `isForm()` and `code === 'test-auth-code'` (:76-79), socket request with `hasHeader('Authorization', 'Bearer test-access-token')` (:81-82), `assertNotSent` on `v1.0` (:83), `refresh_token` toBe `test-refresh-token` (:95); passed |
| C8 | CONFIRMED | `StreamLabsOAuthTest.php:109-121` forged state, `:123-134` absent session state, both `Http::assertNothingSent()` and integration `exists()` false; `:136-148` "the state is single use" asserts `assertSessionMissing('streamlabs_oauth_state')` after a successful callback, as the claim says; all @0a66089, identical @HEAD, passed |
| C9 | CONFIRMED | `tests/Feature/IntegrationProvisioningTest.php:122-133 @0a66089` - fakes `streamlabs.com/api/v2.0/token` and `/socket/token`, `withSession(['streamlabs_oauth_state' => 'fake-state'])` with `state=fake-state`, asserts `expectedControlKeys('streamlabs')`; passed |
| C10 | UNVERIFIABLE | tagged [unverified] (third-party documentation) |
| C11 | UNVERIFIABLE | tagged [unverified] (live Streamlabs run and environment secrets) |

### Surface
Complete.

### Findings
- **F1** inaccurate Surface description - the Surface line for `tests/Feature/StreamLabsOAuthTest.php` says "four new tests over `state`", but the diff @0a66089 adds three `test(...)` blocks (`:109`, `:123`, `:136`), matching C8's "Three new tests"; the fourth state-related change is the pre-existing "callback when token exchange fails" test (`:150`) gaining a session state, which is not new. A later claim should restate the count as three new tests plus one existing test given a state.

### Notes
- Tests were run at HEAD; both test files are byte-identical between @0a66089 and HEAD (`git diff 0a66089 HEAD -- tests/...` empty), only the controller differs.
- C1 drift: scope narrowed to `socket.token donations.read` by OL-2609-097 (C25), disclosed. C2 drift: exits go through `returnTo()` since OL-2609-084 (C8), disclosed.
- "the state is single use" does not replay the state in a second request; rejection of a reused state follows from the `pull()` (C2) plus the absent-state test, not from that test alone.
- The commit reverses CLAUDE.md's prior "API version: v1.0 (NOT v2.0 ...)" line, but rewrites that line in the same diff citing OL-2609-064 inline, so it is not recorded as a contradiction.
- Unchanged line 29 checked: `STREAMLABS_CLIENT_ID`/`_SECRET` present @HEAD in `config/deploy.yml:285-286`, `.github/workflows/deploy.yml:89-90,127`, `config/services.php:27-28`; none of the Unchanged paths is in the diff.
