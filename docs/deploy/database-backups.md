# Database backups and restore

Nightly Postgres dump from the Linode box to Cloudflare R2, plus the part that
actually matters: how to get the data back.

## What runs

`php artisan backup:database` runs at **03:00 UTC** (05:00 Amsterdam) in the
scheduler container. It:

1. runs `pg_dump --format=plain --compress=9 --no-owner --no-privileges`
2. refuses anything under 10 KB as an implausible dump
3. streams the `.sql.gz` to `r2:daily/overlabels-YYYY-MM-DD-HHmmss.sql.gz`
4. reads the object size back and compares it against the local file
5. deletes the local copy, success or failure
6. on any failure, posts to the Discord webhook in `BACKUP_ALERT_WEBHOOK_URL`

At ~52 MB uncompressed the dump is single-digit MB gzipped, so 30 days of them
sits far inside R2's 10 GB free tier.

Retention is **not** in the code. The bucket carries a 30-day lifecycle rule
(see below), which is one less thing that can have a bug and delete the wrong
object.

## Cloudflare setup

The bucket is `overlabels-backups`, created with **EU jurisdiction**. That
jurisdiction is what keeps the objects physically in the EU and is also baked
into the S3 endpoint hostname:

```
https://<R2_ACCOUNT_ID>.eu.r2.cloudflarestorage.com
```

An EU bucket returns 403 on the non-jurisdictional host, which looks exactly
like a bad credential. If backups start failing with an auth-shaped error,
check `R2_JURISDICTION` before you rotate any keys.

### Bucket checklist

- [ ] **No public access.** R2 > overlabels-backups > Settings > Public access
      must show "Not allowed", and no r2.dev domain or custom domain attached.
      The dump contains every user's Twitch OAuth refresh tokens.
- [ ] **Lifecycle rule.** Settings > Object lifecycle rules > Add rule: apply to
      prefix `daily/`, action "Delete uploaded objects", 30 days after upload.
      Without this, storage grows about 10 MB/day forever.
- [ ] **Token scope.** The API token is Object Read & Write on this bucket only,
      not account-wide.

### Why the dump is not encrypted before upload

Deliberate. The EU jurisdiction bucket keeps objects in the EU, Cloudflare's DPA
with SCCs covers the processor relationship, and R2 does server-side AES-256 at
rest with TLS in transit - that is a defensible GDPR position without
client-side encryption.

The deciding factor is the other direction: a passphrase living only in GitHub
secrets is a single point of failure. Rotate it, lose the account, or fat-finger
it once and every historical backup becomes unrecoverable noise, and you find
out at the exact moment you need it. That failure mode is likelier than the one
encryption prevents here.

## Secrets

| Name | Where it lives | Notes |
| --- | --- | --- |
| `R2_ACCESS_KEY_ID` | GitHub Actions secret | |
| `R2_SECRET_ACCESS_KEY` | GitHub Actions secret | |
| `R2_ACCOUNT_ID` | GitHub Actions secret | hex id in the endpoint host |
| `BACKUP_ALERT_WEBHOOK_URL` | GitHub Actions secret | optional; empty = log only |
| `R2_BUCKET` | `config/deploy.yml` `env.clear` | not a secret |
| `R2_JURISDICTION` | `config/deploy.yml` `env.clear` | must stay `eu` |

GitHub is the source of truth. `.kamal/secrets` is gitignored and regenerated
from those on every deploy by `.github/workflows/deploy.yml`, so adding a secret
means touching three places in the repo: the workflow's `env:` block, the
variable list in the loop that writes `.kamal/secrets`, and `env.secret:` in
`config/deploy.yml`. Your local `.kamal/secrets` only matters if you deploy from
your own machine.

## Restoring into local dev

This is the half that has to work. Do it at least once after any change here.

Local dev runs Postgres 17.5 with the client tools at
`C:\Program Files\PostgreSQL\17\bin`, which is **not** on PATH by default in Git
Bash. Restoring a 16.x plain-SQL dump into 17.5 is fine - plain SQL restores
forward across major versions.

### 1. Get a dump

From the Cloudflare dashboard: R2 > overlabels-backups > `daily/` > pick the
newest object > Download. Or with rclone/aws-cli if you have one configured.

### 2. Restore it

```bash
export PATH="/c/Program Files/PostgreSQL/17/bin:$PATH"

# Fresh target database, so a half-restore can't leave you with a mix of
# local junk and prod rows.
dropdb -U postgres -h 127.0.0.1 --if-exists laravel_foxes
createdb -U postgres -h 127.0.0.1 laravel_foxes

# The dump is gzipped plain SQL, so psql eats it directly off a pipe.
gunzip -c overlabels-2026-08-05-030000.sql.gz | psql -U postgres -h 127.0.0.1 -d laravel_foxes
```

`--no-owner --no-privileges` at dump time means nothing in the file references
the `overlabels` role, so it lands cleanly as `postgres`.

### 3. Expect these to be broken, and leave them broken

- **`APP_KEY` mismatch.** Encrypted columns (integration credentials via
  `Crypt::encryptString`) are unreadable unless your local `APP_KEY` matches
  production's. Do not copy the prod key to your machine to "fix" this. Any
  integration you need locally, reconnect locally.
- **External integrations.** Ko-fi, Fourthwall, BMAC, Throne and Streamlabs
  webhooks all point at `overlabels.com`, so they will not reach localhost.
  Expected.
- **Overlay access tokens.** The stored values are `sha256(plainToken)` and the
  plaintext only ever existed in a URL fragment, so old overlay URLs will not
  authenticate locally. Mint new ones.
- **Twitch EventSub subscriptions.** Registered against the prod callback URL.
  Do not run `eventsub:monitor --fix` against a restored local database while
  pointed at real Twitch credentials.

### 4. Sanity check

```bash
php artisan tinker --execute="echo App\Models\User::count().' users, '.App\Models\OverlayTemplate::count().' templates';"
```

## Verifying the backup itself

The first scheduled run is the real test, and a failure shouts to Discord. If
you would rather not wait until 03:00 UTC, SSH to the box yourself and trigger
one:

```bash
ssh deploy@172.235.171.209
docker exec $(docker ps -qf name=overlabels-scheduler) php artisan backup:database
```

That writes an object to R2 and touches nothing in the database.

## Known gaps

- **No dead-man's switch.** A failed backup shouts. A backup that never runs at
  all - scheduler container down, container never restarted after a bad deploy -
  is silent. Closing that needs an external pinger (Healthchecks.io or similar),
  which is another third party and was not worth it on day one.
- **No automated restore test.** The test suite verifies the dump is real gzip
  containing real SQL, but the R2 network hop and the restore are manual.
