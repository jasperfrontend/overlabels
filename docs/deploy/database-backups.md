# Database backups and restore

Nightly Postgres dump from the Linode box to **two** providers - Cloudflare R2
and Scaleway Object Storage - plus the part that actually matters: how to get
the data back.

## What runs

`php artisan backup:database` runs at **03:00 UTC** (05:00 Amsterdam) in the
scheduler container. It:

1. runs `pg_dump --format=plain --compress=9 --no-owner --no-privileges`
2. refuses anything under 10 KB as an implausible dump
3. streams the `.sql.gz` to **every** disk in `BACKUP_DISKS`, under one shared
   key: `daily/overlabels-YYYY-MM-DD-HHmmss.sql.gz`
4. reads each object's size back and compares it against the local file
5. deletes the local copy, success or failure
6. on any failure, posts to the Discord webhook in `BACKUP_ALERT_WEBHOOK_URL`

At ~52 MB uncompressed the dump is single-digit MB gzipped, so 30 days of them
sits far inside R2's 10 GB free tier and inside the 1 GB Scaleway bucket.

Retention is **not** in the code. Each bucket carries its own 30-day lifecycle
rule (see below), which is one less thing that can have a bug and delete the
wrong object. The two rules are configured independently in two dashboards -
nothing keeps them in sync, so changing the retention window means changing it
in both places.

## Why two providers

This is the "2" in 3-2-1. R2 alone is one bucket behind one Cloudflare login: an
account suspension, a billing lapse, a leaked API token or a fat-fingered bucket
delete takes out every copy at once, and none of those are exotic. Scaleway is a
separate company, a separate account and a separate credential, so no single one
of those events can reach both.

Two properties make that real, and both are load-bearing:

- **The dump is taken once** and the same file is pushed to each disk under an
  identical key. Two separate dumps would be two different databases, seconds
  apart, and you would not know which one you were restoring.
- **Every destination is attempted even after one fails.** A fail-fast loop
  would mean a Cloudflare outage also costs you the Scaleway copy - the exact
  correlated failure the second provider exists to prevent. `uploadAll()` never
  throws for an upload failure; it collects a `disk => error` map and the caller
  decides the exit code from that.

### A partial success is still a failure

If one leg lands and the other does not, the command exits **1**, Discord gets
an alert naming which leg died and which copies did land, and the Healthchecks
dead-man's switch flips red.

That is deliberate, and it is the noisier of the two options. Exiting 0 on
"well, we got one copy" would mean a destination that quietly stopped working
never surfaces anywhere except a Discord message you might scroll past, and you
would discover you had been running on one copy for months at the worst possible
moment. A transient blip at one provider costs one false alarm; the alert names
the surviving copy, so you can tell "there is no backup tonight" from "R2 has it,
Scaleway does not" without opening a dashboard.

Adding a third destination is one entry in `BACKUP_DISKS` plus a disk in
`config/filesystems.php`. Nothing in the command is written for exactly two.

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

## Scaleway setup

The bucket name lives in `SCW_BUCKET` (`config/deploy.yml` `env.clear`) and in
your local `.env`. It is deliberately not repeated in this document, so there is
one place to change it and no second copy to drift.

The region is part of the endpoint hostname:

```
https://s3.fr-par.scw.cloud
```

Region is **not** global on Scaleway. A bucket that lives in `fr-par` returns
`404 NotFound` on the `nl-ams` host - which reads as "the bucket has been
deleted" rather than "you asked the wrong host", and will send you hunting in
entirely the wrong place. Check `SCW_REGION` first.

Only two of the four Scaleway credentials are used. `SCW_ACCESS_KEY` and
`SCW_SECRET_KEY` are enough: the S3-compatible API resolves the project from the
access key itself, so `SCW_DEFAULT_ORGANIZATION_ID` and `SCW_DEFAULT_PROJECT_ID`
are Scaleway-CLI concepts that never appear in an S3 request. They are not
deployed, and the Scaleway CLI is not installed anywhere.

### Bucket checklist

- [ ] **No public access.** Bucket visibility must be Private, with no website
      configuration. Same reasoning as R2: the dump contains every user's Twitch
      OAuth refresh tokens.
- [ ] **Lifecycle rule.** Expire current versions after **30 days**, scoped to
      **all objects in the bucket** rather than the `daily/` prefix. The bucket
      holds nothing but these backups, so an unscoped rule also catches anything
      ever written outside that prefix instead of letting it accumulate forever.
      Without the rule the 1 GB bucket fills in about 100 days and the Scaleway
      leg starts failing every night.
      Versioning is off, so the noncurrent-version and delete-marker options do
      nothing here; leave them unset unless versioning is ever enabled.
- [ ] **Delete incomplete multipart uploads** after ~7 days, in the same rule.
      This is the non-obvious one. aws-sdk-php switches to a multipart upload
      above `ObjectUploader::DEFAULT_MULTIPART_THRESHOLD`, 16 MB - which the
      gzipped dump will cross on its own as the database grows, with no
      announcement. A multipart upload that dies halfway leaves its parts
      behind: they consume the 1 GB quota, they do **not** appear in any object
      listing, and an expiration rule will not touch them because they are not
      objects yet. So the failure mode this bucket exists to survive is also the
      one that silently eats it. Costs nothing to set before it applies.
- [ ] **Key scope.** Ideally an application key limited to Object Storage rather
      than a full-account key.

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
| `SCW_ACCESS_KEY` | GitHub Actions secret | Scaleway leg |
| `SCW_SECRET_KEY` | GitHub Actions secret | Scaleway leg |
| `BACKUP_ALERT_WEBHOOK_URL` | GitHub Actions secret | optional; empty = log only |
| `R2_BUCKET` | `config/deploy.yml` `env.clear` | not a secret |
| `R2_JURISDICTION` | `config/deploy.yml` `env.clear` | must stay `eu` |
| `SCW_BUCKET` | `config/deploy.yml` `env.clear` | not a secret |
| `SCW_REGION` | `config/deploy.yml` `env.clear` | must stay `fr-par` |

`SCW_DEFAULT_ORGANIZATION_ID` and `SCW_DEFAULT_PROJECT_ID` exist in the local
`.env` but are deliberately **not** deployed - see the Scaleway section above.

GitHub is the source of truth. `.kamal/secrets` is gitignored and regenerated
from those on every deploy by `.github/workflows/deploy.yml`, so adding a secret
means touching three places in the repo: the workflow's `env:` block, the
variable list in the loop that writes `.kamal/secrets`, and `env.secret:` in
`config/deploy.yml`. Your local `.kamal/secrets` only matters if you deploy from
your own machine.

## Restoring into local dev

This is the half that has to work. Do it at least once after any change here.

Restore with the **PostgreSQL 18** client at `C:\Program Files\PostgreSQL\18\bin`,
which is **not** on PATH by default in Git Bash. Restoring a 16.x plain-SQL dump
into the local 17.5 server is fine - plain SQL restores forward across major
versions.

> **Use 18, not 17.** These dumps open with `\restrict` and close with
> `\unrestrict` - a psql meta-command added in PostgreSQL 18 and backpatched
> only as far as 17.6, added to stop a malicious object name from smuggling
> psql commands into a restore. The local psql is 17.5, which predates it.
> Verified behaviour:
>
> | client | result |
> | --- | --- |
> | psql 17.5, default | `invalid command \restrict`, then continues - restores, but the guard silently does not apply |
> | psql 17.5, `ON_ERROR_STOP=on` | aborts immediately, exit code 3 |
> | psql 18.3 | clean, exit 0 |
>
> Since the restore below sets `ON_ERROR_STOP=on` - which you want, so a failure
> stops rather than leaving a half-populated database - psql 17 fails outright.

### 1. Get a dump

From the Cloudflare dashboard: R2 > overlabels-backups > `daily/` > pick the
newest object > Download. Or with rclone/aws-cli if you have one configured.

Or from Scaleway: Object Storage > the bucket named in `SCW_BUCKET`
(fr-par) > `daily/`. The two copies are byte-identical and share a key, so the same
filename exists in both and either one restores the same way. If you are
restoring because something went wrong with the first provider, this is the
whole point - go to the other one and carry on.

### 2. Restore it

```bash
export PATH="/c/Program Files/PostgreSQL/18/bin:$PATH"

# Fresh target database, so a half-restore can't leave you with a mix of
# local junk and prod rows.
dropdb -U postgres -h 127.0.0.1 --if-exists laravel_foxes
createdb -U postgres -h 127.0.0.1 laravel_foxes

# The dump is gzipped plain SQL, so psql eats it directly off a pipe.
# ON_ERROR_STOP=on is what turns a partial restore into a loud failure instead
# of a database that looks fine and is missing a table somewhere in the middle.
gunzip -c overlabels-2026-08-05-030000.sql.gz \
  | psql -v ON_ERROR_STOP=on -U postgres -h 127.0.0.1 -d laravel_foxes
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

That writes one object to each provider and touches nothing in the database. The
output names every destination and its result:

```
  ok    r2:daily/overlabels-2026-08-11-030012.sql.gz
  ok    scaleway:daily/overlabels-2026-08-11-030012.sql.gz
Backed up 1.7 MB to r2 + scaleway (key daily/overlabels-2026-08-11-030012.sql.gz) in 4.1s.
```

To exercise one leg on its own - useful when you have just rotated one
provider's credentials and want to know which half you broke - pass `--disk`:

```bash
docker exec $(docker ps -qf name=overlabels-scheduler) php artisan backup:database --disk=scaleway
```

Note that a manual run does **not** satisfy the dead-man's switch; the ping is
attached to the schedule, not the command. That is deliberate (see below).

## Verified end to end (2026-08-06)

The whole circuit has been walked once, by hand, against real production data.
A backup nobody has restored is a guess, so this is the record that it stopped
being one.

- The 03:00 UTC scheduled run fired on its own and produced
  `daily/overlabels-2026-08-06-030015.sql.gz` (1.7 MB).
- That object was pulled back out of R2 and restored into local dev with psql 18
  and `ON_ERROR_STOP=on`: **zero errors**, 54,061 rows across 61 tables.
- Integrity after restore: row-for-row parity with every `COPY` block in the
  dump, 55 sequences all ahead of their table's max id, 71 foreign keys
  validated, 0 pending migrations.
- The restored database then ran the application: Twitch login, templates and
  controls listing, a static overlay authenticating off its URL-fragment token,
  and a live follower alert arriving through EventSub and Reverb.
- Production was unaffected throughout - EventSub subscription count identical
  before and after (421/388 enabled).

Worth redoing after any change to the dump flags, the pinned client major, or
either object-storage disk config. Those are the things that can break a restore
while leaving the nightly run looking perfectly healthy.

## Scaleway leg added (2026-08-10)

What was verified when the second provider went in, and what was not:

- The write path was exercised against the **real** Scaleway bucket from local
  dev: a 9.1 MB dump uploaded to `fr-par` and passed the read-back size check.
  The test objects were deleted afterwards; the bucket is empty.
- The partial-failure path was exercised for real, not just faked. With R2
  credentials absent locally, `r2` (listed first) failed and `scaleway` still
  received its copy, and the run exited 1.
- Two tests in `BackupDatabaseTest` were verified to go **red** when
  `uploadAll()` is reverted to fail-fast. Keep them.
- The lifecycle rule was read back off the bucket rather than trusted from the
  dashboard summary: `expire-backups-30d`, **Status Enabled** (a rule can be
  created disabled and then simply never run), `Filter.Prefix` empty so it
  covers all objects, `Expiration.Days` 30, and
  `AbortIncompleteMultipartUpload.DaysAfterInitiation` 7.
- The bucket is not publicly readable, verified three ways: the ACL grants
  `FULL_CONTROL` to the owning project only with no `AllUsers` or
  `AuthenticatedUsers` grant, there is no website configuration, and a
  `ListObjectsV2` with credentials explicitly disabled returns `AccessDenied`.
  The last of those is the one that counts - the dump contains every user's
  Twitch OAuth refresh tokens, and an unauthenticated request actually bouncing
  is a stronger claim than a dashboard toggle reading "Private".

Not yet proven, and the honest gap: **no Scaleway object has been restored.**
The R2 leg has a full restore behind it (above); this one has a verified upload
and a size check. Do a real restore from the Scaleway copy before relying on it
as the surviving copy.

## Dead-man's switch (Healthchecks.io)

The Discord webhook covers "the backup ran and failed". It cannot cover "the
backup never ran" - a dead scheduler container sends nothing, and silence is
indistinguishable from success. Healthchecks alerts on the **absence** of a
ping, so the thing doing the checking is not the thing that might be down.

Wired in `routes/console.php` with Laravel's built-in scheduler pings:

- success -> `GET $HC_PING_URL`
- failure -> `GET $HC_PING_URL/fail`, so a failed backup flips the check
  immediately instead of waiting out the grace period

Attached to the **schedule**, not the command. That is deliberate: a manual
`php artisan backup:database` therefore cannot satisfy the switch, so a
scheduler that has quietly stopped firing still gets reported.

Laravel's pings are best-effort by design (`Event::pingCallback()` catches
transport exceptions and reports them), so a Healthchecks outage can never turn
a good backup into a failed one. Leaving `HC_PING_URL` empty registers no pings
at all.

### Check configuration

- **Period: 1 day.** The backup is daily at 03:00 UTC.
- **Grace: 30 minutes.** The dump itself takes about two seconds. Thirty minutes
  absorbs a slow night or a deploy that overlaps 03:00, without being so loose
  that a dead scheduler goes unnoticed for a whole day.

An alert therefore fires at about **03:30 UTC** on the first night the backup
does not run.

## Known gaps

- **The restore is not automated.** The test suite verifies a dump is real gzip
  containing real SQL, and the nightly run exercises the write path to both
  providers every night. The *read* and the restore itself only happen when
  someone does them by hand, as above. Nothing detects a dump that uploads fine
  and will not restore.
- **The switch cannot tell "backed up" from "backed up something useless".** It
  proves the command exited 0. The 10 KB implausibility floor and the upload
  size check are what make that exit code mean something, but no assertion about
  the dump's *contents* reaches Healthchecks.
- **Nothing checks that the two copies stayed identical.** Both are verified by
  size at upload time and never compared again. A silent bit-rot at one provider
  would not be noticed, and no checksum is stored alongside the object.
- **This is still not 3-2-1 in full.** Two providers gives the "2"; the offsite
  "1" is satisfied twice over. What is missing is a copy on *different media* in
  a meaningfully different failure domain - both legs are cloud object storage
  reached over the same network by the same command, so a bug in that command
  (a bad dump flag, a truncated stream) writes the same broken object to both.
  The verified-restore habit above is the only thing covering that.
