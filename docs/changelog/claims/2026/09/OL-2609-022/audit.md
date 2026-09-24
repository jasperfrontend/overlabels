## Audit of OL-2609-022 - fix(deploy): the Streamlabs listener resolves overlabels.com to the host so its polls skip the Cloudflare edge

**Audited:** 2026-09-24
**Commit:** bde93a1af3b110a158f1dab46344179967fc4651
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `config/deploy.yml:374 @bde93a1` - `add-host: "overlabels.com:host-gateway"` under `streamlabs-listener` `options` (block starts :355); same at `config/deploy.yml:396 @HEAD` |
| C2 | CONFIRMED | `config/deploy.yml:360 @bde93a1` - `APP_URL: https://overlabels.com` under `env.clear`; same at `config/deploy.yml:382 @HEAD` |
| C3 | CONTRADICTED (compound) | First half ("every outbound request ... is a `fetch` to a URL built from `APP_URL`") is false: `streamlabs-listener.mjs:132 @bde93a1` opens `io(\`https://sockets.streamlabs.com?token=${socketToken}\`)`, an outbound Socket.IO connection that is neither a `fetch` nor built from `APP_URL`. Second half ("covers all of the listener's traffic to the app") is true: the only two `fetch` calls, `:79` and `:106 @bde93a1`, use URLs built from `APP_URL` at `:76` and `:103`. Same lines at @HEAD |
| C4 | CONFIRMED | `.github/workflows/deploy.yml:152 @bde93a1` - `kamal accessory boot streamlabs-listener \|\| true` directly after `:151` `kamal accessory boot expression-engine \|\| true`; same at @HEAD |
| C5 | UNVERIFIABLE | tagged [unverified]; prod host observation |
| C6 | UNVERIFIABLE | tagged [unverified]; prod host observation and bot-repo commit `96b734d`, outside this repo |

### Surface
Complete.

### Findings
- **F1** claim contradicted - C3 says every outbound request in `streamlabs-listener.mjs` is an `APP_URL` `fetch`, but `streamlabs-listener.mjs:132 @bde93a1` (unchanged @HEAD) also connects out to `https://sockets.streamlabs.com`. Only the narrower statement holds: every request to the app is an `APP_URL` `fetch`. Record the correction in a new claim that cites OL-2609-022 C3.

### Notes
- Unchanged lines checked: `streamlabs-listener.mjs` is not in the diff, and `REFRESH_INTERVAL_MS = 60_000` is at `:59 @bde93a1`. The only `config/deploy.yml` hunk is at the accessory (@@ -362). The `web` (:15) and `reverb` (:99) proxy blocks are not touched. The bot-repo line cannot be checked here.
- No commit after bde93a1 changed the `streamlabs-listener` accessory block or the boot line. The two later commits that touch `config/deploy.yml` (79e3301a, 71a413a6) leave both as shipped.
- No tests named or run; the claim has no [test] lines.
