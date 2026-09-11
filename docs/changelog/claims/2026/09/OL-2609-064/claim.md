## OL-2609-064 - fix(streamlabs): move the OAuth flow to API v2.0 and check the state on the way back

**Shipped:** 2026-09-11
**Commit:** `git log --grep=OL-2609-064`

### Surface
- `app/Http/Controllers/Settings/StreamLabsIntegrationController.php` - `API_BASE` and `OAUTH_STATE_KEY` constants; `redirect()` gains a `state`; `callback()` checks it, exchanges the code as a form body, stores `refresh_token`
- `tests/Feature/StreamLabsOAuthTest.php` - redirect and callback tests moved to v2.0; four new tests over `state`
- `tests/Feature/IntegrationProvisioningTest.php` - the Streamlabs callback fake moved to v2.0 and given a state
- `CLAUDE.md` - StreamLabs section: the v1.0 line replaced with the v2.0 facts and the re-registration requirement

### Claims
- **C1** [code] `StreamLabsIntegrationController::redirect()` sends the browser to `https://streamlabs.com/api/v2.0/authorize` with `client_id`, `redirect_uri`, `response_type=code`, the unchanged scope string `socket.token donations.read donations.create`, and a `state` of 40 hex characters that it also stores in the session under `streamlabs_oauth_state`.
- **C2** [code] `callback()` pulls `streamlabs_oauth_state` from the session (removing it) and compares it to the `state` query parameter with `hash_equals`. A missing session value or a mismatch redirects to the Streamlabs settings page with an error and makes no HTTP request.
- **C3** [code] `callback()` posts the code to `https://streamlabs.com/api/v2.0/token` as a form-encoded body (`Http::asForm()`), with `grant_type=authorization_code`, `client_id`, `client_secret`, `redirect_uri` and `code`.
- **C4** [code] `callback()` fetches `https://streamlabs.com/api/v2.0/socket/token` with the access token as a Bearer header, as before; no request in the controller names `v1.0` or `www.streamlabs.com`.
- **C5** [code] The stored credentials gain `refresh_token` (the token response's value, or null). `access_token`, `socket_token` and `listener_secret` are stored as before.
- **C6** [test] `StreamLabsOAuthTest` > "redirect sends user to the Streamlabs v2.0 authorize URL with a state the session remembers" asserts the Location starts with the v2.0 authorize URL, contains no `v1.0`, and that the `state` in the query equals the session's `streamlabs_oauth_state` and is 40 characters.
- **C7** [test] `StreamLabsOAuthTest` > "callback with valid code creates integration and provisions controls" asserts the token request is a form post to the v2.0 token URL with the code, the socket-token request carries `Authorization: Bearer test-access-token`, no request touches `v1.0`, and `refresh_token` lands in the decrypted credentials.
- **C8** [test] Three new tests assert a forged state, an absent session state, and a reused state: the first two send nothing (`Http::assertNothingSent()`) and create no integration; the third asserts the session key is gone after a successful callback.
- **C9** [test] `IntegrationProvisioningTest` > "the streamlabs oauth callback provisions on connect" now fakes the v2.0 URLs and sends a matching state, and still asserts the six Streamlabs controls are provisioned.
- **C10** [unverified] `dev.streamlabs.com/docs/getting-started` (fetched 2026-09-11) says v1.0 is deprecated, "re-registration will be required for all existing users", and "In v2.0 access token cannot be passed as a query param. It can only be accessed through a Bearer Authentication Header." `dev.streamlabs.com/reference/authorize` gives `GET https://streamlabs.com/api/v2.0/authorize` with the optional `state` "useful for CSRF protection". `dev.streamlabs.com/docs/register-your-application` says an unapproved app can have up to 10 whitelist users.
- **C11** [unverified] Not exercised against Streamlabs: the environment's client id and secret are the March v1.0 registration, and a v1.0 app cannot complete the v2.0 flow. The first live run needs a v2.0 app registered on streamlabs.com/dashboard with redirect URI `https://overlabels.com/auth/callback/streamlabs`, its id and secret in the existing `STREAMLABS_CLIENT_ID` / `STREAMLABS_CLIENT_SECRET` GitHub secrets, and the tester's Streamlabs account on the app's whitelist.

### Unchanged
- `streamlabs-listener.mjs`, its Dockerfile and the Kamal accessory: the socket endpoint `https://sockets.streamlabs.com?token=` and the `event` payload are the same under v2.0 (`dev.streamlabs.com/docs/socket-api`), and the listener never calls the REST API.
- `GET /api/internal/streamlabs/integrations`, `StreamLabsServiceDriver`, the six provisioned controls and `DonationIntegrationController` are not in the diff.
- `resources/js/pages/settings/integrations/streamlabs.vue` keeps its closed-beta banner: the app is not approved until it is submitted and approved, and the banner is the truth until then.
- No secret was added: the three env names already exist in `deploy.yml`, the deploy workflow and `config/services.php`. Only their values change, in GitHub secrets.

### Risk
Every existing Streamlabs connection was made through the v1.0 app. Their socket tokens keep
working as long as Streamlabs honours them (the listener does not go through the REST API), but a
re-connect after this change goes through the v2.0 app and, until that app is approved, only
succeeds for whitelisted accounts. A prod count of existing connections was attempted and refused by
the session's permission policy, so the number affected is unknown; the closed beta bounds it at ten.
