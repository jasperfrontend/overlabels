## OL-2609-116 - build(docker): pin install-php-extensions to a tagged release

**Shipped:** 2026-09-20
**Commit:** `git log --grep=OL-2609-116`

### Surface
- `Dockerfile` - runtime stage: `ARG PHP_EXT_INSTALLER_VERSION` added, and the `ADD` that fetches
  `install-php-extensions` moved from the `releases/latest/download/` URL to
  `releases/download/${PHP_EXT_INSTALLER_VERSION}/`

### Claims
- **C1** [code] The runtime stage of `Dockerfile` declares `ARG PHP_EXT_INSTALLER_VERSION=2.11.27` after `FROM dunglas/frankenphp:1-php8.4 AS runtime` and before the `ADD` that consumes it.
- **C2** [code] No line in `Dockerfile` contains the string `releases/latest/download`.
- **C3** [code] The `ADD --chmod=0755` for `install-php-extensions` targets `https://github.com/mlocati/docker-php-extension-installer/releases/download/${PHP_EXT_INSTALLER_VERSION}/install-php-extensions` and writes to `/usr/local/bin/`.
- **C4** [code] The `RUN install-php-extensions` invocation below it is byte-identical to the pre-change tree: the same nine extensions in the same order, and the same PGDG block chained after them.
- **C5** [code] `PHP_EXT_INSTALLER_VERSION` appears on exactly two lines of `Dockerfile`: the `ARG` that declares it and the `ADD` that interpolates it. Nothing else in the repository sets or reads it, including `config/deploy.yml` and `.github/workflows/deploy.yml`.
- **C6** [unverified] `2.11.27` is the release tag that `releases/latest/download/` resolved to on 2026-09-20, published 2026-09-18, so the image builds from the same artefact the preceding successful deploy used.
- **C7** [unverified] `GET https://github.com/mlocati/docker-php-extension-installer/releases/download/2.11.27/install-php-extensions` returned HTTP 200 and 235115 bytes when checked on 2026-09-20.
- **C8** [unverified] Deploy run 35472789945 failed on its first attempt at build step `[runtime 2/16]` with `read: connection reset by peer` fetching that asset over the `latest` URL, and the same run passed on re-run with no change to the tree.
- **C9** [unverified] The image has not been built from this tree. Docker was not running on the machine that made the change; the deploy build is the first execution of the new line.

### Unchanged
- `RESVG_VERSION` a few lines below is the other pinned binary download in the runtime stage and
  already used the `ARG` + tagged-URL shape this change copies. It is not in the diff.
- The extension list itself is not in the diff. Only the delivery URL of the installer script moved;
  which extensions it then installs, and the `postgresql-client-16` PGDG block chained onto the same
  `RUN`, are untouched. See C4.
- `config/deploy.yml` is where Kamal's `builder.args` block lists the build args the image is built
  with; it carries the five `VITE_*` values and `APP_COMMIT_SHA` and is not in the diff. No entry
  for `PHP_EXT_INSTALLER_VERSION` was added there, so the `ARG` default in `Dockerfile` is what
  every build uses.

### Risk
Upgrading `install-php-extensions` is now an edit to `Dockerfile` rather than something that happens
on its own at the next deploy. The version will go stale silently until someone bumps it.
