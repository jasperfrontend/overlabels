# Image storage on Cloudflare R2

Template screenshots and kit thumbnails. Uploaded through
`POST /images/upload`, resized and cropped by Intervention Image, stored on
the `images` disk (a Cloudflare R2 bucket), and served publicly from
`https://images.overlabels.com`.

This replaced Cloudinary on 2026-08-18. The reason was cost shape, not
features: Cloudinary's free tier is generous but the next tier up is $99/month
with no step in between, so the failure mode was waking up to a $99 bill the
month the free tier ran out. R2 is $0.015/GB-month with **zero egress**, and
the free tier is 10 GB storage, 1M writes and 10M reads per month. The entire
asset library at the time of the move was about 14 MB.

## What the app does

| Piece | Where |
|---|---|
| Disk config | `config/filesystems.php`, the `images` entry |
| Upload endpoint | `POST /images/upload` -> `ImageUploadController` |
| Resize, crop, encode, store | `App\Services\ImageUploadService` |
| Row per object | `image_uploads` table / `App\Models\ImageUpload` |
| Orphan sweep | `routes/console.php`, `images:sweep-orphans`, every 15 min |

Uploads are cropped to 16:9 and encoded as WebP at quality 82 with metadata
stripped. Two kinds, with different ceilings:

- `template_screenshot` -> `overlays/screenshots/`, capped at 1280x720
- `kit_thumbnail` -> `kits/thumbnails/`, capped at 2560x1440

`coverDown` is used rather than `cover`, so a small upload is cropped to the
ratio but never scaled up to the ceiling.

Object keys carry a ULID and are never rewritten, so objects are written with
`Cache-Control: public, max-age=31536000, immutable`. Replacing an image
always writes a new key; the old one is deleted by `deleteByUrl` once nothing
references it.

There is no watermark. Cloudinary applied "Powered by Overlabels" as a
delivery-time URL transformation, and R2 is plain object storage with no
transformation layer. Reinstating it would mean baking a second derivative at
upload time.

## Cloudflare setup

### 1. Move the overlabels.com zone to Cloudflare DNS

Unavoidable, and worth understanding before you start. An R2 custom domain
requires the hostname to be in a zone **in the same Cloudflare account**, and
both ways of attaching a single subdomain without moving the apex are paid:

- **Subdomain zones** (`images.overlabels.com` as its own zone) - Enterprise only.
- **Partial / CNAME setup** - Business or Enterprise only.
- **`pub-<hash>.r2.dev`** - Cloudflare rate-limits it and documents it as
  development-only. Not viable for overlay screenshots loading in OBS.

Moving the zone does **not** mean putting overlabels.com behind the Cloudflare
proxy. Set every existing record to **DNS only** (grey cloud) and Cloudflare is
just an authoritative DNS host. Only the `images` record ends up proxied,
because R2 custom domains require it.

1. Cloudflare dashboard -> **Add a site** -> `overlabels.com` -> **Free** plan.
2. Cloudflare imports what it can see. **Verify the import against the current
   provider's zone file record by record** - there is no zone transfer, so
   anything it could not guess is silently missing. Pay attention to `MX` and
   any `TXT` used for SPF/DKIM/DMARC or domain verification: a missed record
   here is silent broken email, not an error page.
3. Set **every** record to **DNS only** (grey cloud).
4. At your registrar, replace the nameservers with the two Cloudflare gives you.
5. Wait for the zone to go **Active**, then re-verify the site, Reverb
   websockets and email still work before moving on.

### 2. Create the bucket

1. Cloudflare dashboard -> **R2** -> **Create bucket**.
2. Name: `overlabels-images`. Location: **EU**, matching `overlabels-backups`.
3. Leave public access off - the custom domain in the next step is what makes
   it readable, and it gives you Cloudflare's cache and WAF in front.

### 3. Attach the custom domain

1. Bucket -> **Settings** -> **Public access** -> **Custom domains** ->
   **Connect domain**.
2. Enter `images.overlabels.com`. Cloudflare creates the proxied CNAME in the
   zone itself; you do not add a DNS record by hand.
3. Wait for status **Active**.
4. Confirm: `curl -I https://images.overlabels.com/` should answer from
   Cloudflare rather than time out.

### 4. Create a scoped API token

1. R2 -> **API** -> **Manage API tokens** -> **Create API token**.
2. Permission **Object Read & Write**, scoped to **`overlabels-images` only**.
3. This must be a **different token from the backup one**. The images token is
   used on the web request path, so a leak of it must not be able to touch
   `overlabels-backups`.
4. Copy the Access Key ID and Secret Access Key.

### 5. Wire up the secrets

Per the standing rule, a new Kamal secret means **three** places:

1. `env:` block in `.github/workflows/deploy.yml`
2. the variable list in the loop that writes `.kamal/secrets`
3. `env.secret:` in `config/deploy.yml`

All three already name `R2_IMAGES_ACCESS_KEY_ID` and
`R2_IMAGES_SECRET_ACCESS_KEY`. What is left is adding both as **GitHub
repository secrets** - GitHub is the source of truth, `.kamal/secrets` is
regenerated every deploy.

`R2_IMAGES_BUCKET` and `R2_IMAGES_URL` have working defaults in
`config/filesystems.php` and do not need to be set unless they differ.
`R2_IMAGES_ACCOUNT_ID` and `R2_IMAGES_JURISDICTION` fall back to
`R2_ACCOUNT_ID` and `eu`.

### 6. Set a lifecycle rule (optional)

Unlike the backups bucket there is nothing to expire here - these objects are
referenced by live rows. Do **not** add a retention rule; it would delete
screenshots out from under templates that still point at them.

## The move off Cloudinary (history)

Production migrated on **2026-08-18** and the importer that did it has been
removed. This is here because the rename it left behind is otherwise
unexplained, and because the local situation is different from prod.

A one-shot `images:migrate-from-cloudinary` command pulled each image from its
live Cloudinary delivery URL and re-uploaded it to R2, rewriting
`overlay_templates.screenshot_url`, `kits.thumbnail` and `image_uploads`. It
ran from the entrypoint rather than by hand, because deploys here are
push-to-GitHub. Both it and its `ENTRYPOINT_RUN_IMAGE_MIGRATION` flag are gone;
it was built with an explicit removal trigger so it would not become permanent
scaffolding.

Notes worth keeping:

- **It read the live URLs, never the archive zip** in `docs/private/`. That
  export is a flat directory of sanitised filenames with the folder prefix
  stripped and collisions resolved by `-1` suffixes, so nothing in it maps
  back to the `public_id` a database row referenced. If images ever need
  recovering from it, that mapping is a manual job.
- **`image_uploads` was `cloudinary_uploads`**, with `public_id` -> `path` and
  `secure_url` -> `url`. See
  `database/migrations/2026_08_18_210000_rename_cloudinary_uploads_to_image_uploads.php`.
- **Local development was deliberately not migrated.** `overlabels.test` may
  still render screenshots from Cloudinary. Uploads there fail unless the disk
  is configured, and they fail safely - the controller catches it and shows
  "Upload failed. Please try again."
- **Do not point a local install at the production images bucket.**
  `deleteByUrl()` checks the *local* database for remaining references before
  deleting an object, so deleting a template or kit locally would destroy an
  image production still points at. A local setup needs its own bucket.

To check whether anything anywhere still references the old host:

```sql
SELECT count(*) FROM overlay_templates WHERE screenshot_url LIKE '%cloudinary%';
SELECT count(*) FROM kits              WHERE thumbnail      LIKE '%cloudinary%';
SELECT count(*) FROM image_uploads     WHERE url            LIKE '%cloudinary%';
```

## Gotchas

- **The jurisdiction is part of the endpoint hostname.** An EU bucket answers
  on `<account>.eu.r2.cloudflarestorage.com` and returns 403 on the plain
  `<account>.r2.cloudflarestorage.com` host. That 403 looks exactly like a bad
  credential - check `R2_IMAGES_JURISDICTION` before rotating keys.
- **`R2_IMAGES_URL` is persisted, not derived.** It is baked into every stored
  image URL at upload time. Changing it orphans every existing image until the
  rows are rewritten. It has no trailing slash.
- **`request_checksum_calculation` and `response_checksum_validation` are
  pinned to `when_required`.** aws-sdk-php >= 3.337 defaults to CRC32 trailers
  that R2 has been inconsistent about accepting, failing with an opaque error.
- **The `images` disk is `throw => false`, unlike the backup disks.** An upload
  failure here is a user-facing request that `ImageUploadController` already
  catches and turns into a friendly message; letting it throw would bypass that.
- **GD needs WebP support.** Present locally via Herd and in the production
  image (`install-php-extensions gd` enables it). If encoding starts failing
  with "not supported", check that first.
