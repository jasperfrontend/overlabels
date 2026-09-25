## Audit of OL-2609-116 - build(docker): pin install-php-extensions to a tagged release

**Audited:** 2026-09-25
**Commit:** f0d423a3537893c7cff172ed26740b10577a44e0
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `Dockerfile:53 @f0d423a` - `FROM dunglas/frankenphp:1-php8.4 AS runtime`; `Dockerfile:62 @f0d423a` - `ARG PHP_EXT_INSTALLER_VERSION=2.11.27`; `Dockerfile:63 @f0d423a` - the `ADD` consuming it. Same lines @HEAD (b370bebd); no commit after f0d423a touches `Dockerfile` |
| C2 | CONFIRMED | `git show f0d423a:Dockerfile \| grep -n 'releases/latest/download'` - no match; the only occurrence was `Dockerfile:56 @f0d423a~1`, removed by this commit. Same @HEAD |
| C3 | CONFIRMED | `Dockerfile:63 @f0d423a` - `ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/${PHP_EXT_INSTALLER_VERSION}/install-php-extensions /usr/local/bin/`; same @HEAD |
| C4 | CONFIRMED | The diff has one hunk (`@@ -52,8 +52,15 @@`), ending before the `RUN`; `Dockerfile:80-100 @f0d423a` - `pcntl, pdo_pgsql, redis, intl, gd, bcmath, exif, zip, opcache` (nine) followed by the PGDG chain, not in the diff |
| C5 | CONFIRMED | `git grep -n PHP_EXT_INSTALLER_VERSION f0d423a` - `Dockerfile:62` and `Dockerfile:63`, otherwise only the claim file's own prose; `config/deploy.yml @f0d423a` and `.github/workflows/deploy.yml @f0d423a` have no match. Same result @HEAD |
| C6 | UNVERIFIABLE | tagged [unverified] (GitHub release resolution on a past date) |
| C7 | UNVERIFIABLE | tagged [unverified] (external HTTP response) |
| C8 | UNVERIFIABLE | tagged [unverified] (GitHub Actions run history) |
| C9 | UNVERIFIABLE | tagged [unverified] (state of the author's machine) |

### Surface
Complete. `git show --stat f0d423a` lists `Dockerfile` and the claim file only.

### Findings
None.

### Notes
- Unchanged lines checked: `RESVG_VERSION` is at `Dockerfile:98-99 @f0d423a~1` / `Dockerfile:105-106 @f0d423a` with the `ARG` + tagged-URL shape and is not in the diff; `config/deploy.yml:163-169 @f0d423a` `builder.args` holds five `VITE_*` keys and `APP_COMMIT_SHA`, and the file is not in the diff.
- Diff also adds a six-line comment above the `ARG` (`Dockerfile:55-61 @f0d423a`) explaining the pin; it is part of the disclosed runtime-stage edit and changes no behaviour.
- No test covers this change and none is claimed; no tests were run. The image build itself was not run by this audit (C9).
- `CLAUDE.md` and earlier claims were grepped for `install-php-extensions`, `PHP_EXT_INSTALLER` and `latest/download`; no match outside this claim.
