## Audit of OL-2611-026 - fix(designer): a preview token read off a stream works nowhere else

**Audited:** 2026-11-19
**Commit:** 355823c73bb5fdaddf25222440fc1c9df4bcc686 (one commit carries the trailer; it is HEAD, so @355823c and @HEAD are the same tree)

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/ProductDesigner.php:84 @355823c` - `TOKEN_LIFETIME_MINUTES = 120`; `:474` `$secret = bin2hex(random_bytes(32))`; `:480` `expires_at => now()->addMinutes(self::TOKEN_LIFETIME_MINUTES)`; `:482` `'binding' => hash('sha256', $secret)`; `:485` secret stored under `self::BINDING_SESSION_KEY`. Same @HEAD |
| C2 | CONFIRMED | `ProductDesigner.php:457` (reuse path) and `:486` (mint path) @355823c both call `queueBindingCookie($secret)`; `:559-572` `Cookie::make(BINDING_COOKIE, $secret, TOKEN_LIFETIME_MINUTES, BINDING_COOKIE_PATH, null, null, true, false, 'strict')` - httpOnly true, SameSite strict; `:91,97` `ol_preview`, `/api/overlay`. Same @HEAD |
| C3 | CONFIRMED | `ProductDesigner.php:450-461 @355823c` reuses only when `heldPreviewToken()` returns a row; `:546-557` returns null unless `findByToken($held)` finds it, `user_id === $user->id`, `isPreviewToken()`, and `hash_equals($binding, hash('sha256', $secret))`; otherwise falls through to the mint at `:463-489`. Same @HEAD |
| C4 | CONFIRMED | `ProductDesigner.php:533-544 @355823c` - returns true when `! isPreviewToken($token)`; else requires string `metadata.binding`, non-empty `ol_preview` cookie, and `hash_equals($binding, hash('sha256', $cookie))`. Same @HEAD |
| C5 | CONFIRMED | `OverlayTemplateController.php:631`, `Api/OverlayAlertSourceController.php:37`, `OverlayBroadcastingAuthController.php:49` @355823c - each returns a 401 JSON response when `! ProductDesigner::admits($token, $request)`. Same @HEAD |
| C6 | CONFIRMED | `Api/EventFeedController.php:156` and `Api/ListReadController.php:51` @355823c - 401 when `ProductDesigner::isPreviewToken($accessToken)`; neither reads the cookie. Same @HEAD |
| C7 | CONFIRMED | `ProductDesigner.php:498-517 @355823c` - false when session values are missing or `heldPreviewToken()` (the C3 check) returns null; otherwise `update(['expires_at' => now()->addMinutes(TOKEN_LIFETIME_MINUTES)])` and `queueBindingCookie($secret)`, plaintext untouched. Same @HEAD |
| C8 | CONFIRMED | `app/Http/Controllers/ProductController.php:574 @355823c` - `response()->noContent(ProductDesigner::renewPreviewToken(...) ? 204 : 409)`; route `routes/web.php:787-789 @355823c` named `products.design.preview-token`. Same @HEAD |
| C9 | CONFIRMED | `resources/js/pages/products/design.vue:667 @355823c` `RENEW_EVERY_MS = 20 * 60_000`, `:702` `setInterval`; `:668,682` visible and `Date.now() - lastRenewAt > 5 * 60_000`; `:675` posts to `/products/${slug}/design/preview-token`; `:677` `recoverPreview()` on status 409. Same @HEAD |
| C10 | CONTRADICTED | `php artisan test --filter=DesignerPreviewTokenTest`: 8 passed (32 assertions). Narrower than claimed in two places: (1) broadcasting auth is asserted with no cookie (`tests/Feature/DesignerPreviewTokenTest.php:122 @355823c`) and the right cookie (`:126`) only - no wrong-cookie case, which the render route does have (`:108-109`); (2) C6's "cookie or not" is asserted only WITH the right cookie (`:137-140`) - no request to `/api/events` or `/api/lists` sends a preview token without a cookie. The other listed items are present: C1 (`:91-97`, session half via the same-token re-open at `:172`), C2 path/http-only/strict (`:91-93`), render 401/401/200 (`:107-111`), alert-source 401/404 (`:123,128`), ordinary token on both feed routes (`:143-144`), ordinary token with no cookie (`:152,155`), renewal after 100 minutes (`:163-172`), 409 after 121 minutes (`:180-182`), unbound held token replaced (`:190-208`) |
| C11 | CONFIRMED | Read, not executed (see Notes): the first assertion of "renders the overlay..." (`:107 @355823c`, no cookie, expects 401) and of "signs the live channel..." (`:122`, no cookie, expects 401) would reach the same 200 path that `:111` and `:126` reach with the cookie, so both tests fail with `admits()` forced true |
| C12 | UNVERIFIABLE | tagged [unverified]; a browser observation, not checkable in-repo |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - C10 says the test covers broadcasting auth "with no, a wrong and the right cookie", but `tests/Feature/DesignerPreviewTokenTest.php:122-126 @355823c` has no wrong-cookie request to `/api/overlay/broadcasting/auth`; add one, or restate C10 in a new claim.
- **F2** test narrower than claim - C10 says the test asserts C6 ("cookie or not"), but `DesignerPreviewTokenTest.php:137-140 @355823c` sends the binding cookie on both preview-token requests and never sends one without it; the test name ("cookie or not") over-promises the same way. Add the no-cookie requests, or restate.
- **F3** narrows an earlier claim without citing it - `docs/changelog/claims/2026/09/OL-2609-124/claim.md:22` records "Preview tokens still expire a day after minting (`expires_at => now()->addDay()` ...)"; this change sets 120 minutes (`ProductDesigner.php:480 @355823c`) and the claim does not cite OL-2609-124. A follow-up claim should cite it inline.

### Notes
- C11 was judged by reading the passing assertions; the mutation (`admits()` forced true) was not run, because this audit may modify no file but `audit.md`.
- Also ran `ProductInstallHistoryTest` (6 passed) and `ProductChatDesignerTest` (16 passed) at HEAD; both cover preview-token behaviour this commit touches.
- Unchanged line 1 holds: `admits()` returns before reading the cookie for a non-preview token (`ProductDesigner.php:535-537 @355823c`). Unchanged line 2 holds: `previewUrl()` (`:585-588`) shows up in the diff only as context lines.
