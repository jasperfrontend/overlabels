## Audit of OL-2609-035 - fix(help): the search indexes are served with Cache-Control no-cache, so a browser sees a new deploy on the next open

**Audited:** 2026-09-25
**Commit:** aae2c9b7060c9d2054c1d497acd6aee378d27a2f
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `docker/frankenphp.Caddyfile:81-82 @aae2c9b` - `@search_index path /help-index.json /help-reference-index.json` and `header @search_index Cache-Control "no-cache"`, inside the `{$SERVER_NAME::80} {` site block opened at line 42 and closed at line 85; `git diff aae2c9b HEAD -- docker/frankenphp.Caddyfile` is empty, so same @HEAD |
| C2 | UNVERIFIABLE | tagged [unverified] (prod HTTP headers observed with curl) |
| C3 | UNVERIFIABLE | tagged [unverified] (browser-cache behaviour on one machine) |
| C4 | CONFIRMED | `resources/js/utils/helpSearch.ts:458-459 @aae2c9b` - `buildHelpSearch()` calls `buildEngine(docs)`, which at line 225 reads `doc.sections.length` for each doc (TypeError on a missing `sections`); `resources/js/composables/useHelpReference.ts:51-55 @aae2c9b` - `.catch()` sets `failed.value = true`; `resources/js/help/main.ts:59-70 @aae2c9b` - `searcher` stays `null` because the `.catch()` is empty; none of the three files is in `git show --stat aae2c9b`. @HEAD: `helpSearch.ts` and `useHelpReference.ts` unchanged; `main.ts` changed by 55ae69c7 (adds `wireThemeMenus` import and call only, `wireSearch()` untouched) |

### Surface
Complete.

### Findings
None.

### Notes
- C4 is a compound claim (the throw, two catch sites, and not-in-diff); every part is true, so one verdict covers it. It says "Neither file" while naming three files; all three are absent from the diff.
- Unchanged line 2: `docker/docker-entrypoint.sh:47-48 @aae2c9b` runs `help:build-index` gated on `ENTRYPOINT_RUN_HELP_INDEX`, which `config/deploy.yml:68 @aae2c9b` sets to `"1"` on the web role; `BuildHelpReferenceIndex.php:71-73 @aae2c9b` writes exactly the two files the matcher names.
- Unchanged lines 1 and 3 hold: `useHelpReference.ts:42 @aae2c9b` is a plain `fetch('/help-index.json')`, and the `@machine_readable` lines (69-70) show up only as diff context.
- No tests were named, so none were run. No contradiction found in `CLAUDE.md` ("Do not restore `cache: 'force-cache'`" is consistent with this change) or in OL-2609-032/034/067/087.
