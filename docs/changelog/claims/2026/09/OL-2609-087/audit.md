## Audit of OL-2609-087 - feat(overlay): hosted overlays answer on overlabels.net, and only the hosted overlay does

**Audited:** 2026-09-25
**Commit:** 79e3301af54fe14103d676a3324d531eb199a3de
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Middleware/RestrictOverlayHost.php:38 @79e3301` - `ALLOWED_ROUTES = ['overlay.authenticated', 'api.overlay.*']`; `:78` `Str::is(self::ALLOWED_ROUTES, $routeName)` against `$request->route()?->getName()` (`:51`); file unchanged @HEAD |
| C2 | CONFIRMED | `RestrictOverlayHost.php:47 @79e3301` - `$overlayHost === null \|\| strcasecmp($request->getHost(), $overlayHost) !== 0` returns `$next($request)`; `:53-54` `abort(404)` when the name is null or not allowed; unchanged @HEAD |
| C3 | CONFIRMED | `bootstrap/app.php:59-60 @79e3301` - `$middleware->web(prepend: [RestrictOverlayHost::class])`; `:72-73` first entry of `$middleware->api(prepend:)`; no other reference besides the `use` at `:12`. @HEAD the file differs only in the `trimStrings`/`convertEmptyStringsToNull` paths (OL-2609-129) |
| C4 | CONFIRMED | `app/Http/Middleware/HandleInertiaRequests.php:85 @79e3301` - `is_string(config('app.overlay_url')) && config('app.overlay_url') !== '' ? rtrim(..., '/') : null`; unchanged @HEAD |
| C5 | CONFIRMED | `resources/js/components/TokenUrlDialog.vue:96 @79e3301` - `${props.origin ?? window.location.origin}`; `AddToObsButton.vue:17,51 @79e3301` - `page.props.overlayOrigin ?? undefined` bound as `:origin`; `EventsFeedLinkButton.vue` not in `git show --stat` and has no `origin` binding @79e3301 |
| C6 | CONFIRMED | `config/deploy.yml:43 @79e3301` - `- overlabels.net` under `servers.web.proxy.hosts`, no `www.overlabels.net` entry (only in the comment at `:39`); `:182` `OVERLAY_URL: https://overlabels.net` under top-level `env.clear` |
| C7 | CONTRADICTED | `php artisan test --filter=OverlayHostRestrictionTest`: 17 passed (49 assertions). Every listed assertion exists except one: "the same paths are not 404 on `https://overlabels.com`" is not asserted for `/overlay/{slug}/public/screenshot` - `tests/Feature/OverlayHostRestrictionTest.php:73-79 @79e3301` checks only the overlay host, and its comment says there is deliberately no app-host counterpart. The `.com` counterparts for `/public`, `/public.md` (`:67`), `/`, `/login`, `/help`, `/products` (`:84`) and `GET /api/events` (`:95`) are asserted. Test file unchanged @HEAD |
| C8 | CONFIRMED | `OverlayHostRestrictionTest.php:130-144 @79e3301` - config `https://overlabels.net/` gives `overlayOrigin` `https://overlabels.net` on `dashboard.index`; config null gives `null`; both passed in the run above |
| C9 | UNVERIFIABLE | tagged [unverified] (fail-first run against a stashed tree); the suite does contain 17 tests, matching the count stated |
| C10 | UNVERIFIABLE | tagged [unverified] (live DNS/HTTP probe) |
| C11 | UNVERIFIABLE | tagged [unverified] (live HTTP probe) |
| C12 | CONFIRMED | `git grep` @79e3301: the only `resources/help` hit for `overlabels.<tld>/overlay/...#` is `resources/help/pages/tokens.md:14`; public share URLs in `deep-dives/follower-bowling-lane.md`, `math-engine-showcase.md`, `rarotonga.md` and `markdown-endpoints.md:39-40` say `overlabels.com`; `auth_endpoint` at `lists.md:166` and `lists-realtime.md:73` says `overlabels.com`; same @HEAD |

### Surface
Complete.

### Findings
- **F1** undisclosed behaviour - the hosted overlay's emote and badge endpoints are 404 on the overlay host: `routes/api.php:100` and `:131 @79e3301` (`GET /api/overlay/emotes/{channelId}`, `GET /api/overlay/badges/{channelId}`) are unnamed closures, so `RestrictOverlayHost::handle()` sees `$name === null` and aborts (`RestrictOverlayHost.php:53-54 @79e3301`), while the overlay fetches both relative to its own origin (`resources/js/composables/useEmoteParser.ts:130 @79e3301`, `resources/js/components/OverlayRenderer.vue:251 @79e3301`). Reproduced @HEAD with a kernel probe with `app.overlay_url=https://overlabels.net`: `https://overlabels.net/api/overlay/emotes/abc` and `.../badges/abc` answered 404, and the same paths on `https://overlabels.com` did not (500, not 404). `php artisan route:list --path=api/overlay` @HEAD still shows both routes without names. The claim's Surface line and C1 describe the gate by route name, and `config/deploy.yml:31-33 @79e3301` describes it as "everything on it except the overlay page and /api/overlay/*", but no claim, test or Risk line says a `.net` overlay loses third-party emotes and badge art. The reader should decide whether to name those two routes `api.overlay.*` and add them to `OverlayHostRestrictionTest`; no later claim addresses this.
- **F2** test narrower than claim - C7 states the overlay-host 404 paths are not 404 on `overlabels.com`, but `OverlayHostRestrictionTest.php:73-79 @79e3301` asserts no app-host status for `/overlay/{slug}/public/screenshot`; the reader should either narrow the statement in a new claim or add the counterpart assertion with a template that has a screenshot.

### Notes
- The claim lists C12 before C11 in the file; both were audited under their own numbers.
- The kernel probe for F1 was a throwaway script in the session scratchpad, not a repo file. The 500 on `.com` came from this environment with a non-numeric channel id; it shows only that the gate did not fire there.
- `bootstrap/app.php` @HEAD differs from @79e3301 only in the lists path callbacks (OL-2609-129); `routes/api.php` @HEAD adds `/internal/bot/forgetme` (OL-2609-099). Neither touches the registration or the routes this claim relies on.
- OL-2609-109 (Unchanged, line 83) records that `overlayOrigin` and `AddToObsButton`'s use of it remain in place; consistent with C4/C5 @HEAD.
