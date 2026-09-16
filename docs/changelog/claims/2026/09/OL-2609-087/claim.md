## OL-2609-087 - feat(overlay): hosted overlays answer on overlabels.net, and only the hosted overlay does

**Shipped:** 2026-09-16
**Commit:** `git log --grep=OL-2609-087`

### Surface
- `app/Http/Middleware/RestrictOverlayHost.php` - new file; 404s every route on the overlay host except `overlay.authenticated` and `api.overlay.*`
- `bootstrap/app.php` - `RestrictOverlayHost` prepended to the `web` and `api` middleware groups
- `config/app.php` - new `overlay_url` key from `OVERLAY_URL`
- `app/Http/Middleware/HandleInertiaRequests.php` - new shared prop `overlayOrigin`
- `resources/js/types/index.d.ts` - `overlayOrigin: string | null` on `AppPageProps`
- `resources/js/components/TokenUrlDialog.vue` - new optional `origin` prop used in place of `window.location.origin`
- `resources/js/components/AddToObsButton.vue` - passes the shared `overlayOrigin` as that prop
- `config/deploy.yml` - `overlabels.net` added to the web role's `proxy.hosts`; `OVERLAY_URL: https://overlabels.net` added to the shared clear env
- `.env.example` - `OVERLAY_URL=` documented, empty
- `tests/Feature/OverlayHostRestrictionTest.php` - new file
- `resources/help/pages/tokens.md` - the example overlay URL now shows `overlabels.net`, plus two sentences on why and that `.com` URLs still work

### Claims
- **C1** [code] `RestrictOverlayHost::ALLOWED_ROUTES` is exactly `['overlay.authenticated', 'api.overlay.*']`, matched with `Str::is()` against the matched route's name.
- **C2** [code] `RestrictOverlayHost::handle()` passes the request through untouched when `config('app.overlay_url')` is unset or its host differs (case-insensitively) from `$request->getHost()`; otherwise it `abort(404)`s any route whose name is not allowed.
- **C3** [code] `bootstrap/app.php` registers `RestrictOverlayHost` via `$middleware->web(prepend:)` and as the first entry of `$middleware->api(prepend:)`, and nowhere globally.
- **C4** [code] `HandleInertiaRequests::share()` exposes `overlayOrigin` as `rtrim(config('app.overlay_url'), '/')` when that config is a non-empty string, else `null`.
- **C5** [code] `TokenUrlDialog.vue` builds `generatedUrl` from `props.origin ?? window.location.origin`; `AddToObsButton.vue` passes `usePage().props.overlayOrigin ?? undefined` as `origin`; `EventsFeedLinkButton.vue` does not pass `origin` and is not in the diff.
- **C6** [code] `config/deploy.yml` lists `overlabels.net` under the web role's `proxy.hosts` and does not list `www.overlabels.net`; the shared `env.clear` block sets `OVERLAY_URL: https://overlabels.net`.
- **C7** [test] `OverlayHostRestrictionTest` asserts, with `app.overlay_url` set to `https://overlabels.net`: `/overlay/{slug}` and `/overlay/{slug}/` return 200 on that host; `POST /api/overlay/render` is not 404 there; `/overlay/{slug}/public`, `/overlay/{slug}/public.md`, `/overlay/{slug}/public/screenshot`, `/`, `/login`, `/help`, `/products` and `GET /api/events` are 404 there; the same paths are not 404 on `https://overlabels.com`; a mixed-case host is still gated; with the config null the `.net` host serves `/` and the public page.
- **C8** [test] `OverlayHostRestrictionTest` asserts the `dashboard.index` Inertia response carries `overlayOrigin` equal to `https://overlabels.net` when the config is `https://overlabels.net/`, and `null` when the config is null.
- **C9** [unverified] With the `bootstrap/app.php` registration stashed, 8 of the 17 tests in C7/C8 failed (every 404 assertion on the overlay host) and the rest passed.
- **C10** [unverified] Live probe on 2026-09-16: `overlabels.net` and `www.overlabels.net` resolve to Cloudflare edge addresses; `https://overlabels.net/` answered 525 (no origin certificate yet); `http://overlabels.net/.well-known/acme-challenge/probe` answered 301 to HTTPS while the same path on `overlabels.com` and `www.overlabels.com` answered 404 without a redirect; the `overlabels.com` origin certificate was issued 2026-08-20, after the zone went proxied.
- **C12** [code] `resources/help/pages/tokens.md` is the only file under `resources/help/` whose text contains a hosted-overlay URL (`/overlay/<slug>#<token>` with a domain); the public share URLs (`/overlay/<slug>/public`, `/public.md`) in the deep dives and `markdown-endpoints.md` still say `overlabels.com`, and the `auth_endpoint` examples in `lists.md` and `lists-realtime.md` still say `overlabels.com`.
- **C11** [unverified] A second probe later the same day, after the `.net` zone's HTTP-to-HTTPS Redirect Rule was removed, had `http://overlabels.net/.well-known/acme-challenge/probe` answer 404 with no redirect, matching `.com`.

### Unchanged
- `routes/web.php` and `routes/api.php` are not in the diff: the gate is a middleware over route names, so `overlay.authenticated` and the three `overlay.public*` routes keep the same definitions and the same middleware they had.
- `docker/frankenphp.Caddyfile` is not in the diff: its only host-specific block is the `www.overlabels.com` 301, and `www.overlabels.net` is redirected by a Cloudflare rule at the edge, so the origin never sees that hostname.
- `resources/views/overlay/authenticate.blade.php` is not in the diff: it reads the token from the fragment client-side with no origin in it, and its broken-link copy still sends people to `overlabels.com`, where the dashboard is.
- `config/cors.php` and `config/reverb.php` are not in the diff: both already allow any origin for `api/*` and the Reverb app, so the overlay's fetches and its channel-auth handshake from the new origin need nothing.
- `EventsFeedLinkButton.vue`, the other caller of `TokenUrlDialog`, is not in the diff and keeps minting `/events/feed` URLs on the app's own origin.

### Risk
`.com` keeps serving every existing OBS URL unchanged; nothing redirects or deprecates them. `.net`
only works once kamal-proxy has obtained its Let's Encrypt certificate, which needs
`/.well-known/acme-challenge/` to reach the origin over plain HTTP. The `.net` Cloudflare zone had a
Redirect Rule that 301'd that path (C10); it was removed before the push (C11). Until the first
deploy after this change, `https://overlabels.net` answers 525 and "Add to OBS" is still on `.com`.
If `.net` still answers 525 after the deploy, re-check that path first: five failed challenges in an
hour lock Let's Encrypt out for an hour. `www.overlabels.net` is redirected at the Cloudflare edge
and has no origin certificate by design; grey-clouding that record would break it.
