## OL-2611-026 - fix(designer): a preview token read off a stream works nowhere else

**Shipped:** 2026-11-19
**Commit:** `git log --grep=OL-2611-026`

### Surface
- `app/Support/ProductDesigner.php` - `TOKEN_LIFETIME_MINUTES`, `BINDING_COOKIE`, `BINDING_SESSION_KEY`, `BINDING_COOKIE_PATH`; `previewToken()` binds and re-sets the cookie; new `renewPreviewToken()`, `isPreviewToken()`, `admits()`, `heldPreviewToken()`, `queueBindingCookie()`
- `bootstrap/app.php` - `ol_preview` excluded from cookie encryption
- `app/Http/Controllers/OverlayTemplateController.php` - `renderAuthenticated()` refuses a token `admits()` refuses
- `app/Http/Controllers/Api/OverlayAlertSourceController.php` - same refusal
- `app/Http/Controllers/OverlayBroadcastingAuthController.php` - same refusal
- `app/Http/Controllers/Api/EventFeedController.php` - `resolveToken()` refuses any preview token
- `app/Http/Controllers/Api/ListReadController.php` - `show()` refuses any preview token
- `app/Http/Controllers/ProductController.php` - new `renewPreviewToken()`
- `routes/web.php` - `POST /products/{slug}/design/preview-token`, named `products.design.preview-token`
- `resources/js/pages/products/design.vue` - `renewPreviewToken()` on a 20-minute interval and on `visibilitychange`; a 409 calls `recoverPreview()`
- `tests/Feature/DesignerPreviewTokenTest.php` - new: 8 tests
- `tests/Feature/ProductInstallHistoryTest.php` - "does not count the designer preview link as a load" gives its preview token a binding and sends the cookie

### Claims
- **C1** [code] `ProductDesigner::previewToken()` mints a token expiring `TOKEN_LIFETIME_MINUTES` (120) from now, with `metadata.binding` = sha256 of a random 64-hex secret held in the session under `BINDING_SESSION_KEY`.
- **C2** [code] Every `previewToken()` call that returns a token queues the `ol_preview` cookie holding the secret, path `/api/overlay`, http-only, SameSite strict, lifetime `TOKEN_LIFETIME_MINUTES`.
- **C3** [code] `previewToken()` reuses the held token only when `findByToken()` finds it, it belongs to the user, it carries `TOKEN_PURPOSE`, and its `binding` matches the session secret; otherwise it mints, so a token minted before this change is replaced.
- **C4** [code] `ProductDesigner::admits()` returns true for any token without `TOKEN_PURPOSE`, and for a preview token only when the request's `ol_preview` cookie hashes to its `metadata.binding`.
- **C5** [code] `renderAuthenticated()`, `OverlayAlertSourceController` and `OverlayBroadcastingAuthController::authenticate()` answer 401 when `admits()` is false.
- **C6** [code] `EventFeedController::resolveToken()` and `ListReadController::show()` answer 401 for any token `isPreviewToken()` is true for, cookie or not.
- **C7** [code] `ProductDesigner::renewPreviewToken()` sets the held token's `expires_at` to now + `TOKEN_LIFETIME_MINUTES` and re-queues the cookie, keeping the same plaintext; it returns false when the held token fails the C3 check.
- **C8** [code] `ProductController::renewPreviewToken()` answers 204 when the renewal succeeded and 409 otherwise.
- **C9** [code] `design.vue` posts to the renew route every 20 minutes and on becoming visible when the last renewal is over 5 minutes old, and calls `recoverPreview()` on a 409.
- **C10** [test] `DesignerPreviewTokenTest` asserts C1, C2 (cookie path, http-only, strict), the 401 / 200 split for render and broadcasting auth with no, a wrong and the right cookie, 401 / 404 for alert-source, C6 against an ordinary token on both routes, an ordinary token working with no cookie, C7 after 100 minutes, a 409 after 121 minutes, and C3's replacement of an unbound held token.
- **C11** [test] With `admits()` returning true unconditionally, the render and broadcasting/alert-source tests in `DesignerPreviewTokenTest` fail.
- **C12** [unverified] In Firefox on the local designer, the frame rendered bubbles, the frame's token answered 200 on `/api/overlay/render` with credentials and 401 without, `document.cookie` did not contain `ol_preview`, and the renew route answered 204.

### Unchanged
- OBS overlay tokens: no cookie is read or required for a token without `metadata.purpose = chat_designer`.
- `ProductDesigner::previewUrl()` still puts the token in the frame's URL fragment on the same origin.

### Risk
Preview tokens minted before this deploy carry no binding, so every overlay endpoint refuses them.
A designer open across the deploy reloads its frame once onto a fresh token through `recoverPreview()`.
