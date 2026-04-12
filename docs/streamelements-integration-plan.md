# StreamElements Tipping Integration

## Context

StreamElements is one of the most widely used streaming platforms alongside StreamLabs. Adding it as an External Integration brings tip/donation support to a large user base. The integration follows the exact same pattern as the existing StreamLabs integration: OAuth2 for authentication, a Node.js Socket.IO listener for real-time tip events, and the established ExternalServiceDriver pipeline for controls and alerts.

The one novel requirement is **token refresh** - StreamElements OAuth tokens expire every 7 days (unlike StreamLabs which never expire). This is handled proactively inside the internal API endpoint that the listener already polls every 60 seconds.

**Auth choice: OAuth2 over JWT.** JWT tokens from the StreamElements dashboard can be invalidated when the user changes account settings, with no refresh mechanism. OAuth2 provides refresh tokens and a predictable expiry cycle.

**WebSocket choice: Legacy Socket.IO over Astro Gateway.** The legacy Socket.IO endpoint (`realtime.streamelements.com`) mirrors the StreamLabs listener pattern exactly. The newer Astro gateway (`wss://astro.streamelements.com`) uses raw WebSocket with a more complex subscribe/nonce/heartbeat protocol. Socket.IO is simpler and reuses the existing `socket.io-client` dependency.

---

## StreamElements API Reference

### OAuth2 Endpoints
- **Authorize**: `GET https://api.streamelements.com/oauth2/authorize?client_id=...&redirect_uri=...&response_type=code&scope=tips:read`
- **Token exchange**: `POST https://api.streamelements.com/oauth2/token` (Content-Type: `application/x-www-form-urlencoded`)
  - Params: `grant_type=authorization_code`, `client_id`, `client_secret`, `code`, `redirect_uri`
- **Token response**: `{ access_token, token_type: "Bearer", expires_in: 604800, refresh_token, scope }`
- **Refresh**: `POST https://api.streamelements.com/oauth2/token`
  - Params: `grant_type=refresh_token`, `client_id`, `client_secret`, `refresh_token`
  - Note: refresh tokens may also rotate - always save the new refresh_token from the response
- **Validate**: `POST https://api.streamelements.com/oauth2/validate` (header: `Authorization: OAuth <token>`)
- **Revoke**: `POST https://api.streamelements.com/oauth2/revoke?client_id=...&token=...`
- **API auth header**: `Authorization: oAuth <access_token>` (lowercase 'o')

### Available Scopes
`channel:read`, `tips:read`, `tips:write`, `activities:read`, `activities:write`, `loyalty:read`, `loyalty:write`, `overlays:read`, `overlays:write`, `bot:read`, `bot:write`

### WebSocket (Legacy Socket.IO)
- **URL**: `https://realtime.streamelements.com` (Socket.IO, WebSocket transport)
- **Auth**: After connect, `socket.emit('authenticate', { method: 'oauth2', token: accessToken })`
- **Events**: `authenticated` (success, returns `channelId`), `unauthorized` (auth failed), `event` (incoming events)

### Tip Event Payload (from `event` listener where `type === 'tip'`)
```json
{
  "_id": "hex24chars",
  "channel": "hex24chars",
  "type": "tip",
  "provider": "paypal",
  "flagged": false,
  "data": {
    "username": "tipper_name",
    "displayName": "Tipper Name",
    "amount": 4.20,
    "message": "Great stream!",
    "currency": "USD",
    "tipId": "hex24chars"
  },
  "createdAt": "2026-04-11T12:00:00.000Z",
  "updatedAt": "2026-04-11T12:00:00.000Z"
}
```

---

## Files to Create

### 1. Driver: `app/Services/External/Drivers/StreamElementsServiceDriver.php`
Implements `ExternalServiceDriver`. Mirror `StreamLabsServiceDriver`.

- `getServiceKey()` -> `'streamelements'`
- `verifyRequest()` -> Check `X-Listener-Secret` header (same pattern as StreamLabs)
- `parseEventType()` -> `type === 'tip'` maps to `'tip'`
- `normalizeEvent()` -> Map SE tip payload:
  - `messageId`: `$payload['_id']` > `$payload['data']['tipId']` > UUID fallback with `se_` prefix
  - `fromName`: `$payload['data']['displayName']` > `$payload['data']['username']`
  - `message`: `$payload['data']['message']`
  - `amount`: `(string) $payload['data']['amount']`
  - `currency`: `$payload['data']['currency']`
  - Template tags: `event.from_name`, `event.message`, `event.amount`, `event.currency`, `event.type`, `event.source` (= "StreamElements"), `event.transaction_id`
- `getSupportedEventTypes()` -> `['tip']`
- `getAutoProvisionedControls()` -> 6 controls:
  - `tips_received` (counter, "StreamElements Tips Received", "0")
  - `latest_tipper_name` (text, "Latest Tipper Name", "")
  - `latest_tip_amount` (number, "Latest Tip Amount", "0")
  - `latest_tip_message` (text, "Latest Tip Message", "")
  - `latest_tip_currency` (text, "Latest Tip Currency", "")
  - `total_tips_received` (number, "Total StreamElements Amount (session)", "0")
- `getControlUpdates()` -> For `tip` events: increment `tips_received`, set latest fields, add to `total_tips_received`

### 2. Token Service: `app/Services/External/StreamElementsTokenService.php`
Handles OAuth token refresh logic.

- `shouldRefresh(array $credentials): bool` - true if `token_expires_at` is within 24 hours or already expired
- `isExpired(array $credentials): bool` - true if `token_expires_at` is past
- `refresh(ExternalIntegration $integration): bool`:
  - `POST https://api.streamelements.com/oauth2/token` with `grant_type=refresh_token`, `client_id`, `client_secret`, `refresh_token` (form-encoded)
  - On success: update encrypted credentials with new `access_token`, `refresh_token`, `token_expires_at`
  - On failure + expired: set `enabled = false`, log error
  - On failure + not expired: log warning, return false (retry next poll)
  - Use `Cache::lock('se_refresh_' . $integration->id, 30)` to prevent concurrent refresh races

### 3. Controller: `app/Http/Controllers/Settings/StreamElementsIntegrationController.php`
Mirror `StreamLabsIntegrationController` with these differences:

- `show()` -> Render `settings/integrations/streamelements`. Include `token_expires_at` and `token_healthy` (not expired/about to expire) in integration data
- `redirect()` -> Build URL for `https://api.streamelements.com/oauth2/authorize` with params: `client_id`, `redirect_uri` (`/auth/callback/streamelements`), `response_type=code`, `scope=tips:read`
- `callback()`:
  - Exchange code at `POST https://api.streamelements.com/oauth2/token` (Content-Type: `application/x-www-form-urlencoded`)
  - Store: `access_token`, `refresh_token`, `token_expires_at` (= `now()->addSeconds($expires_in)`), `listener_secret` (generated)
  - No separate socket token fetch needed (SE uses access_token directly for Socket.IO)
  - Auto-provision controls on first connection
- `setTestMode()` -> Same pattern, uses `tips_received` key and `tips_seed_value` setting
- `seedTipCount()` -> Same as `seedDonationCount()`, uses `tips_received` key, `tips_seed_set`/`tips_seed_value` settings
- `disconnect()` -> Same pattern

### 4. Listener: `streamelements-listener.mjs`
Separate Node.js Socket.IO listener, mirrors `streamlabs-listener.mjs`.

- Env: `STREAMELEMENTS_LISTENER_SECRET` from `.env`
- Internal API: `GET {APP_URL}/api/internal/streamelements/integrations` with `X-Internal-Secret` header
- Socket.IO connect: `io('https://realtime.streamelements.com', { transports: ['websocket'] })`
- After connect: `socket.emit('authenticate', { method: 'oauth2', token: accessToken })`
- Listen for `'authenticated'` event (confirms connection with `channelId`)
- Listen for `'event'` events, filter `eventData.type === 'tip'`
- Relay: `POST {APP_URL}/api/webhooks/streamelements/{webhookToken}` with `X-Listener-Secret` header and JSON body
- Handle `'unauthorized'` event: log error, disconnect (token may have expired - will get refreshed on next poll)
- Same reconnection and graceful shutdown patterns as StreamLabs listener

### 5. Dockerfile: `Dockerfile.streamelements-listener`
Same structure as `Dockerfile.streamlabs-listener` - Node 22 Alpine, copy `streamelements-listener.mjs`, run it.

### 6. Frontend: `resources/js/pages/settings/integrations/streamelements.vue`
Mirror `streamlabs.vue` with these differences:

- All text references "StreamElements" and "tips" instead of "StreamLabs" and "donations"
- OAuth button links to `/settings/integrations/streamelements/redirect`
- API endpoints use `streamelements` path segment
- Token health indicator: show "Connected - token valid until {date}" or warning if expiring soon / "Re-authenticate" button if token expired
- Seed section: "Starting tip count" language
- Control syntax examples: `[[[c:streamelements:tips_received]]]`
- No closed beta banner (unless SE has app approval process)
- "What to do next" references StreamElements dashboard for test tips

### 7. Tests

**`tests/Unit/StreamElementsServiceDriverTest.php`** - Mirror `StreamLabsServiceDriverTest.php`: service key, parse event type, normalize event, auto-provisioned controls, verify request, control updates

**`tests/Unit/StreamElementsTokenServiceTest.php`** - Test shouldRefresh thresholds, isExpired, successful refresh (mock HTTP), failed refresh with expired token (disables integration), failed refresh with valid token (keeps enabled)

**`tests/Feature/StreamElementsOAuthTest.php`** - Mirror `StreamLabsOAuthTest.php`: redirect URL, callback with valid code (creates integration + provisions controls + stores refresh_token + token_expires_at), callback without code, callback with failed exchange, disconnect

**`tests/Feature/StreamElementsWebhookTest.php`** - Mirror `StreamLabsWebhookTest.php`: 404 unknown token, 403 wrong secret, 200 valid tip (stores event, updates controls), dedup, last_received_at update, non-tip events ignored

---

## Files to Modify

### 8. `app/Services/External/ExternalServiceRegistry.php`
Add to `$drivers` array:
```php
'streamelements' => StreamElementsServiceDriver::class,
```
Add `use` import for the driver class.

### 9. `app/Models/ExternalEventTemplateMapping.php`
Add to `SERVICE_EVENT_TYPES`:
```php
'streamelements' => [
    'tip' => 'StreamElements Tip',
],
```

### 10. `config/services.php`
Add:
```php
'streamelements' => [
    'client_id' => env('STREAMELEMENTS_CLIENT_ID'),
    'client_secret' => env('STREAMELEMENTS_CLIENT_SECRET'),
    'listener_secret' => env('STREAMELEMENTS_LISTENER_SECRET'),
],
```

### 11. `routes/settings.php`
Add StreamElements routes inside the `settings/integrations` group (after StreamLabs routes):
```php
Route::get('/streamelements', [StreamElementsIntegrationController::class, 'show'])->name('streamelements.show');
Route::get('/streamelements/redirect', [StreamElementsIntegrationController::class, 'redirect'])->name('streamelements.redirect');
Route::patch('/streamelements/test-mode', [StreamElementsIntegrationController::class, 'setTestMode'])->name('streamelements.test-mode');
Route::post('/streamelements/seed-count', [StreamElementsIntegrationController::class, 'seedTipCount'])->name('streamelements.seed-count');
Route::delete('/streamelements', [StreamElementsIntegrationController::class, 'disconnect'])->name('streamelements.disconnect');
```
Add `use` import for the controller.

### 12. `routes/web.php`
Add OAuth callback route (after the StreamLabs callback at line ~289):
```php
Route::get('/auth/callback/streamelements', [StreamElementsIntegrationController::class, 'callback'])
    ->middleware('auth.redirect')
    ->name('auth.callback.streamelements');
```

### 13. `routes/api.php`
Add internal API endpoint for the listener (after the StreamLabs endpoint at line ~88). This endpoint includes proactive token refresh - when the listener polls every 60s, tokens nearing expiry get refreshed server-side before returning credentials.

```php
Route::get('/internal/streamelements/integrations', function () {
    $secret = config('services.streamelements.listener_secret');
    if (empty($secret) || !hash_equals($secret, (string) request()->header('X-Internal-Secret', ''))) {
        abort(403);
    }

    $tokenService = app(\App\Services\External\StreamElementsTokenService::class);

    $integrations = \App\Models\ExternalIntegration::where('service', 'streamelements')
        ->where('enabled', true)
        ->get()
        ->map(function ($integration) use ($tokenService) {
            $credentials = $integration->getCredentialsDecrypted();
            if ($tokenService->shouldRefresh($credentials)) {
                $tokenService->refresh($integration);
                $credentials = $integration->getCredentialsDecrypted();
            }

            return [
                'id' => $integration->id,
                'user_id' => $integration->user_id,
                'webhook_token' => $integration->webhook_token,
                'access_token' => $credentials['access_token'] ?? null,
                'listener_secret' => $credentials['listener_secret'] ?? null,
            ];
        })
        ->filter(fn ($i) => $i['access_token'] && $i['listener_secret'])
        ->values();

    return response()->json(['integrations' => $integrations]);
})
    ->middleware(['throttle:10,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class]);
```

### 14. `resources/js/pages/settings/integrations/index.vue`
Line 202: Add `'streamelements'` to the service key list so the Manage/Connect button renders:
```js
['kofi', 'gpslogger', 'streamlabs', 'streamelements'].includes(service.key)
```

### 15. `.env.example`
Add:
```
STREAMELEMENTS_CLIENT_ID=
STREAMELEMENTS_CLIENT_SECRET=
STREAMELEMENTS_LISTENER_SECRET=
```

---

## Implementation Order

1. **Backend core**: Driver + TokenService + register in registry + SERVICE_EVENT_TYPES + config
2. **Controller + routes**: Controller + settings routes + OAuth callback + internal API endpoint
3. **Unit + feature tests**: Driver tests, token service tests, OAuth tests, webhook tests
4. **Frontend**: streamelements.vue + update index.vue
5. **Listener + deployment**: streamelements-listener.mjs + Dockerfile
6. **Finalize**: .env vars, changelog, CLAUDE.md update

---

## Verification

1. **Unit tests**: `php artisan test --filter=StreamElements` - driver logic, token refresh logic
2. **Feature tests**: OAuth flow, webhook processing, control updates, dedup
3. **Manual OAuth flow**: Click "Authenticate with StreamElements" -> authorize -> verify integration created with credentials
4. **Listener test**: Start listener (`node streamelements-listener.mjs`), verify it connects and fetches integrations
5. **End-to-end**: Enable test mode, trigger a test tip from StreamElements dashboard, verify:
   - Webhook received and processed (check `external_events` table)
   - Controls updated (check `overlay_controls` table)
   - Alert dispatched if mapping configured
   - Overlay renders updated control values in real-time
6. **Token refresh**: Manually set `token_expires_at` to near-expiry, verify the internal API endpoint triggers refresh
7. **Frontend responsive**: Test the settings page at mobile, tablet, and desktop widths

---

## Key Reference Files

| Purpose | Path |
|---------|------|
| StreamLabs driver (template) | `app/Services/External/Drivers/StreamLabsServiceDriver.php` |
| StreamLabs controller (template) | `app/Http/Controllers/Settings/StreamLabsIntegrationController.php` |
| StreamLabs listener (template) | `streamlabs-listener.mjs` |
| StreamLabs Dockerfile (template) | `Dockerfile.streamlabs-listener` |
| StreamLabs Vue page (template) | `resources/js/pages/settings/integrations/streamlabs.vue` |
| StreamLabs OAuth test (template) | `tests/Feature/StreamLabsOAuthTest.php` |
| StreamLabs webhook test (template) | `tests/Feature/StreamLabsWebhookTest.php` |
| StreamLabs driver test (template) | `tests/Unit/StreamLabsServiceDriverTest.php` |
| ExternalServiceDriver interface | `app/Contracts/ExternalServiceDriver.php` |
| ExternalServiceRegistry | `app/Services/External/ExternalServiceRegistry.php` |
| ExternalWebhookController | `app/Http/Controllers/Api/ExternalWebhookController.php` |
| ExternalControlService | `app/Services/External/ExternalControlService.php` |
| ExternalAlertService | `app/Services/External/ExternalAlertService.php` |
| NormalizedExternalEvent DTO | `app/Services/External/NormalizedExternalEvent.php` |
| ExternalEventTemplateMapping | `app/Models/ExternalEventTemplateMapping.php` |
| Settings routes | `routes/settings.php` |
| Web routes (OAuth callback) | `routes/web.php` |
| API routes (internal endpoint) | `routes/api.php` |
| Services config | `config/services.php` |
| Integrations index page | `resources/js/pages/settings/integrations/index.vue` |
| IntegrationController | `app/Http/Controllers/Settings/IntegrationController.php` |
