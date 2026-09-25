## Audit of OL-2609-122 - fix(products): a chat designer frame whose token died asks the page for a new one instead of reloading itself forever

**Audited:** 2026-09-25
**Commit:** 1269ecc9900e30c690c2ae5588b9950fde3dc375
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:226-227 @1269ecc` - `if (status !== 'auth_error' \|\| !props.sample \|\| window.parent === window) return;`; `sample` is true only for `?chat=sample` (`resources/js/overlay/app.js:77 @HEAD`), and a top-level OBS source has `window.parent === window`. File unchanged @HEAD (`git diff 1269ecc HEAD` empty) |
| C2 | CONFIRMED | `OverlayRenderer.vue:229-230 @1269ecc` - `health.cancelAutoReload()` then `window.parent.postMessage({ ol: 'chat-sample', expired: true }, '*')`. The watch uses default (`pre`) flush, so it runs after the synchronous `status = 'auth_error'; scheduleAutoReload()` pairs at `useOverlayHealth.ts:81-84,90-93,141-149 @1269ecc` and cancels the timer they set. Unchanged @HEAD |
| C3 | CONFIRMED | `resources/js/composables/useOverlayHealth.ts:254 @1269ecc` - `cancelAutoReload()` clears `autoReloadTimer` and `countdownTimer` (and resets `willAutoReload`/`autoReloadIn`); same function at `:254 @1269ecc^`. The diff is one line, `cancelAutoReload,` at `:290 @1269ecc` in the returned object. Unchanged @HEAD |
| C4 | CONFIRMED | `resources/js/pages/products/design.vue:573-584 @1269ecc` - `router.reload({ only: ['preview_url'], onSuccess: ... })`, `frameSrc` assigned only inside `onSuccess`. `app/Http/Controllers/ProductController.php:463 @1269ecc` computes `preview_url` eagerly via `ChatDesigner::previewToken($user)`. Unchanged @HEAD; `previewToken()` itself changed by OL-2609-124 |
| C5 | CONFIRMED | `design.vue:582-583 @1269ecc` - splits `props.preview_url` on `#` and builds `${path}${path.includes('?') ? '&' : '?'}reload=${now}#${token}`; `ChatDesigner::previewUrl()` @1269ecc returns `/overlay/{slug}?chat=sample#{token}`, so the nonce lands in the query before the fragment. Unchanged @HEAD |
| C6 | CONFIRMED | `design.vue:565,570-571 @1269ecc` - `RECOVERY_WINDOW_MS = 15_000`; `if (now - lastRecoveryAt < RECOVERY_WINDOW_MS) return;` before `router.reload`. Unchanged @HEAD |
| C7 | CONFIRMED | `design.vue:576-577 @1269ecc` - `pendingKeys.clear(); clearTimeout(veilBackstop);` in `onSuccess`; `applyingLook` is `computed(() => pendingKeys.size > 0)` over a `reactive` Set (`:530-531 @1269ecc`). Unchanged @HEAD |
| C8 | CONFIRMED | `design.vue:593,596 @1269ecc` - `event.source !== frame.value?.contentWindow` return and `payload?.ol !== 'chat-sample'` return precede all branches, as at `:557,560 @1269ecc^`. Unchanged @HEAD |
| C9 | UNVERIFIABLE | tagged [unverified] (manual browser run on overlabels.test) |
| C10 | UNVERIFIABLE | tagged [unverified] (manual two-browser run) |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged line 1 ("Two designer sessions on one account still take turns invalidating each other") is superseded by OL-2609-124, which narrows the `previewToken()` sweep to expired/inactive tokens and cites OL-2609-122 inline.
- `auth_error` is set for 401, 403 and 400 ("Twitch connection lost") at `useOverlayHealth.ts:80-94,140-149 @1269ecc`, so the framed-sample recovery also fires on 400 and 403, not only the 401 the prose names; C1 states the condition exactly as coded.
- The `expired` post uses target origin `'*'`, as the pre-existing `ready`/`applied` posts do (OL-2609-109 audit F8, OL-2609-120 C3); no claim here states the origin.
- No `[test]` claims; no tests were run.
