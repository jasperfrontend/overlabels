## OL-2609-035 - fix(help): the search indexes are served with Cache-Control no-cache, so a browser sees a new deploy on the next open

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-035`

### Surface
- `docker/frankenphp.Caddyfile` - new `@search_index` path matcher and a `header` directive setting `Cache-Control: no-cache` on `/help-index.json` and `/help-reference-index.json`

### Claims
- **C1** [code] `docker/frankenphp.Caddyfile` declares `@search_index path /help-index.json /help-reference-index.json` inside the site block and applies `header @search_index Cache-Control "no-cache"` to it.
- **C2** [unverified] Before this change, prod served `/help-index.json` with `Last-Modified`, `ETag` and `Vary: Accept-Encoding` and no `Cache-Control` header. Observed with `curl -sI https://overlabels.com/help-index.json` on 2026-09-08.
- **C3** [unverified] After OL-2609-032 deployed, Firefox on Windows 10 answered `fetch('/help-index.json')` from its cache with the pre-change file (records carrying `body`, not `sections`) for roughly an hour, during which the Alt+R palette showed "Could not load the docs index" and the help page search box stayed inert. Chrome on the same machine, with no cached copy, searched correctly at once. Firefox recovered on its own without a hard reload once the heuristic freshness window lapsed.
- **C4** [code] `buildHelpSearch()` in `resources/js/utils/helpSearch.ts` reads `doc.sections.length` for every document and throws a `TypeError` when a record has no `sections` key; `loadIndex()` in `useHelpReference.ts` catches that and sets `failed`, and `wireSearch()` in `help/main.ts` catches it and leaves `searcher` null. Neither file is in the diff.

### Unchanged
- `resources/js/composables/useHelpReference.ts` still fetches the index with the default cache mode and no `force-cache`; its comment describing revalidation as a 304 on a static file becomes accurate under C1 and was not edited.
- `docker/docker-entrypoint.sh` still runs `help:build-index` on every web-role start; the files the matcher names are the ones it writes, and the script is not in the diff.
- The `@machine_readable` matcher and its `Access-Control-Allow-Origin` header directly above the new block are untouched.

### Risk
None for users. Each open of the palette or a help page now costs one conditional request that returns 304 until the next deploy. Cloudflare already reported the file as `DYNAMIC` (uncached at the edge), so nothing changes there.
