## OL-2609-022 - fix(deploy): the Streamlabs listener resolves overlabels.com to the host so its polls skip the Cloudflare edge

**Shipped:** 2026-09-06
**Commit:** `git log --grep=OL-2609-022`

### Surface
- `config/deploy.yml` - `add-host: "overlabels.com:host-gateway"` added under the `streamlabs-listener` accessory's `options`, with a comment
- `.github/workflows/deploy.yml` - `kamal accessory boot streamlabs-listener || true` appended to the post-deploy boot step

### Claims
- **C1** [code] The `streamlabs-listener` accessory in `config/deploy.yml` has `options.add-host` set to `overlabels.com:host-gateway`.
- **C2** [code] The same accessory's `env.clear.APP_URL` is still `https://overlabels.com`.
- **C3** [code] Every outbound request in `streamlabs-listener.mjs` is a `fetch` to a URL built from `APP_URL`, so the one hosts entry covers all of the listener's traffic to the app.
- **C4** [code] The post-deploy step in `.github/workflows/deploy.yml` runs `kamal accessory boot streamlabs-listener || true` immediately after `kamal accessory boot expression-engine || true`.
- **C5** [unverified] On the prod host on 2026-09-06, `curl --resolve overlabels.com:443:172.17.0.1 https://overlabels.com/up` returned HTTP 200 with `ssl_verify=0`.
- **C6** [unverified] On the prod host (Docker 29.4.1), `host-gateway` resolves to `172.17.0.1`, the `docker0` address; the bot container deployed with the identical option (bot repo commit `96b734d`) resolves `overlabels.com` to that address.

### Unchanged
- `streamlabs-listener.mjs` is not in the diff. `REFRESH_INTERVAL_MS` (60 s) and the two request sites (`/api/internal/streamlabs/integrations` and `/api/webhooks/streamlabs/{token}`) are as they were; only where the hostname resolves changes.
- The `web` and `reverb` proxy blocks in `config/deploy.yml`, which hold the Let's Encrypt configuration this relies on, are not in the diff.
- The `bot` role in the bot repo received the same option separately (`96b734d`, 2026-09-06) and is not part of this change.

### Risk
`kamal deploy` does not recreate a running accessory whose config changed, and `kamal accessory boot` is a no-op while the old container exists. The option takes effect only after the running `overlabels-streamlabs-listener` container is removed on the host and the deploy workflow runs again, at which point the new boot line creates it with the option. Until then the listener keeps resolving the name through Cloudflare. The listener is offline between the removal and that boot, so Streamlabs donations in that window are not received.
