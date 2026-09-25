## OL-2609-138 - feat(home): the homepage sells the products, not the syntax

**Shipped:** 2026-09-25
**Commit:** `git log --grep=OL-2609-138`

### Surface
- `routes/web.php` - the `/` route reads `RecipeCatalog::listed()` and passes `games` (category `product`) and `alerts` (category `alert`) to the view; `RecipeCatalog` imported
- `routes/api.php` - new `GET /api/checkin/resolve` route, named `api.checkin.resolve`, middleware `throttle:30,1` and `lockdown`, without `EnsureFrontendRequestsAreStateful`
- `app/Http/Controllers/Api/CheckinResolveController.php` - new single-action controller: validates `q`, refuses cross-site callers, resolves through `PlaceResolverService`, caches hits 24 h and misses 5 min
- `resources/views/welcome.blade.php` - title, description, Open Graph and Twitter meta reworded; include order is hero, products, how, alerts, why, build, then the six original sections, onboarding, cta, footer
- `resources/views/welcome/hero.blade.php` - rewritten: streamer headline, two buttons, and a tabbed showcase (`data-showcase`) of the four demo partials, first tab visible, the rest `hidden`
- `resources/views/welcome/products.blade.php` - new: one full-width row per entry in `$games` (demo partial or hero image, then chip, name, first sentence, three hand-kept lines, button)
- `resources/views/welcome/how.blade.php` - new: three steps
- `resources/views/welcome/alerts.blade.php` - new: one tile per alert product from `$alerts`, brand mark and name
- `resources/views/welcome/_service-mark.blade.php` - new: the five service marks as Blade, copied from `ServiceLogo.vue`
- `resources/views/welcome/why.blade.php` - new: four tiles
- `resources/views/welcome/build.blade.php` - new: the fold introducing the original sections, with the llms.txt paragraph
- `resources/views/welcome/demos/tower.blade.php` - new: the Chat Tower CSS demo (nine chat lines, nine blocks, bot line)
- `resources/views/welcome/demos/bowling.blade.php` - new: the Follower Bowling CSS demo (queue panel, lane, ten pins)
- `resources/views/welcome/demos/chat.blade.php` - new: the Twitch Chat Overlay CSS demo (four messages in three looks)
- `resources/views/welcome/demos/checkin.blade.php` - new: the live Chat Checkin demo (`data-checkin-demo` root, chat column, HUD, globe slot with CSS fallback, chat-box form; input id suffixed by `$checkinId`)
- `resources/views/welcome/navbar.blade.php` - desktop and mobile links are Products, How it works, Alerts, Build your own, Help, Why Overlabels
- `resources/views/welcome/footer.blade.php` - Products plus the six section anchors added to the link row
- `resources/views/welcome/cta.blade.php` - heading and paragraph reworded
- `resources/css/welcome.css` - new, generated: the Chat Tower demo, 16 s loop, sizes in a capped `--u` unit, reduced-motion fallback
- `resources/css/welcome-demos.css` - new, generated: the shared demo frame, Follower Bowling, Chat Checkin fallback and Twitch Chat Overlay demos
- `resources/css/welcome-checkin.css` - new: the live Chat Checkin demo layout, HUD, chat box and globe labels
- `resources/js/welcome/app.ts` - imports the three stylesheets; `wireShowcase()` auto-advances the hero tabs; calls `initCheckinDemo()`
- `resources/js/welcome/checkinDemo.ts` - new: mounts the product globe per `[data-checkin-demo]` instance on intersection via `import('@/globe/checkinGlobe')`, seeds four checkins, handles the chat box
- `resources/js/welcome/checkinDemoState.ts` - new: pure helpers (`parseCommand`, `haversineKm`, `nextState`, `hudLines`, `successReply`, `toPin`, `HOME`)
- `resources/js/welcome/checkinDemoState.test.ts` - new file, 20 tests
- `resources/js/globe/checkinGlobe.ts` - `GlobeInstance` gains an additive `setPaused(boolean)`; the frame loop returns while paused
- `public/ogimage.jpg`, `public/ogimage.png` - replaced, 1200x630, headline plus the four product artworks
- `tests/Feature/HomepageTest.php` - new file, 4 tests
- `tests/Feature/CheckinResolveEndpointTest.php` - new file, 12 tests
- `docs/changelog/changelog-2026-09.md` - prose entry

### Claims
- **C1** [code] The `/` route closure takes `RecipeCatalog $catalog` and passes `games` and `alerts`, filtered on `category` from `$catalog->listed()`.
- **C2** [code] `products.blade.php` renders one `<article>` per entry in `$games` containing an `<a>` to `route('products.show', slug)`, and `alerts.blade.php` renders one `<li>` per entry in `$alerts` with the same link.
- **C3** [test] `HomepageTest` "lists every product on the Products shelf with a link to its page" and "lists every alert product with a link to its page" assert C2 against the live catalogue.
- **C4** [code] `welcome.blade.php` still includes `welcome.syntax`, `welcome.controls`, `welcome.conditionals`, `welcome.events`, `welcome.integrations`, `welcome.kits` and `welcome.onboarding`, and none of those seven files is in the diff.
- **C5** [test] `HomepageTest` "keeps every builder section and its anchor under the fold" asserts the ids `tags`, `controls`, `conditionals`, `events`, `integrations`, `kits`, `get-started` and the six original H2 strings are in the response.
- **C6** [test] `HomepageTest` "sells to streamers in the title, not to coders" asserts the response does not contain `for people who code`.
- **C7** [code] `CheckinResolveController::__invoke()` returns 422 `{place: null, reply}` when `q` is missing, blank or longer than 120 characters, 404 with the reply string `Couldn't find that place. Try City, CC - like Rotterdam, NL` when `PlaceResolverService::resolve()` returns null, and 200 `{place: {name, country_code, country, label, lat, lng}}` otherwise.
- **C8** [code] `CheckinResolveController::isFirstParty()` returns false when an `Origin` header names a different scheme and host than the request, or when `Sec-Fetch-Site` is present and neither `same-origin` nor `none`; `__invoke()` answers 404 in that case.
- **C9** [code] A hit is cached under `CheckinResolveController::cacheKey()` for `HIT_CACHE_HOURS` (24) and a miss for `MISS_CACHE_MINUTES` (5).
- **C10** [test] `CheckinResolveEndpointTest` asserts C7 (found shape, miss reply, missing, blank and 121-character `q`), C8 (a cross-site `Origin` and a `cross-site` `Sec-Fetch-Site` both 404) and C9 (a hit survives six minutes, a miss does not).
- **C11** [test] `CheckinResolveEndpointTest` asserts the route carries `throttle:30,1` and is reachable without a session.
- **C12** [code] `public/build/manifest.json` after `npm run build` lists `resources/js/globe/checkinGlobe.ts` under the welcome entry's `dynamicImports` and not under `imports`.
- **C13** [code] `initCheckinDemo()` wires every `[data-checkin-demo]` element on the page, each with its own `IntersectionObserver` that starts the mount on first intersection and calls `setVisible()` on later changes.
- **C14** [code] `checkinDemoState.ts` exports `HOME` as Avarua (`-21.2075, -159.77546`), and `hudLines()` computes the farthest line from `haversineKm(HOME, pin)`.
- **C15** [code] `successReply()` returns `{name} checked in from {label}!` and appends ` That is {km} km away.` when the distance is at least 1 km, matching `BotCheckinController`.
- **C16** [test] `checkinDemoState.test.ts` asserts C14 (Avarua to Osaka between 9,000 and 10,000 km), C15, the plural wording for one and two checkins, and `parseCommand()` for `!checkin X`, `checkin X`, `X` and a bare `!checkin`.
- **C17** [code] `checkinDemo.ts` never assigns `innerHTML`; every chat line is built with `createElement` and `textContent`.
- **C18** [code] `wireShowcase()` in `app.ts` schedules the next tab after the active panel's `data-duration` seconds and stops scheduling once a click with `event.isTrusted` has been seen.
- **C19** [code] `welcome.css` and `welcome-demos.css` size the CSS demos in `--u: clamp(3.2px, 1cqw, 6.4px)` declared on the demo frame, so a full-width demo scales at most 1.5x the hero size.
- **C20** [code] `welcome.css` sets `animation: none` on the tower, blocks and chat lines and `display: none` on the bot line under `prefers-reduced-motion: reduce`; `welcome-demos.css` stops every loop on its finished state under the same query.
- **C21** [unverified] The page was walked in Chrome at desktop width: the hero showcase advanced tower, bowling, checkin unattended and held on a clicked tab; typing `!checkin Avarua`, `Rotterdam, NL` and a bare `!checkin` produced a pin, a moved pin with a distance line, and the bot's `Where are you?` line respectively.
- **C22** [unverified] The live globe at a true phone width was not viewed in a real browser; the static fallback and the rest of the page were viewed at 390 px through an iframe in headless Chrome.

### Unchanged
- `/products` and its Vue pages: the homepage shelf reads the same `RecipeCatalog::listed()` that `ProductController::index()` reads, and the controller and `resources/js/pages/products/*` are not in the diff.
- The recipe manifests and `recipe-manifest.schema.json`: the row copy is derived from `description` at render time, so no manifest gained a field.
- `OverlayRenderer.vue` mounts the globe exactly as before; `setPaused()` is additive on `GlobeInstance` and nothing in the overlay calls it.
- `PlaceResolverService` is called, not changed; its fuzzy matching (`xyzzy` resolving to Khizi, AZ) is the product's behaviour and the demo shows it as-is.

### Risk
The title and meta description changed, which is the one deliberate search-ranking risk. Every
original heading, body and anchor is still on the page. The new endpoint is public and costs one
gazetteer lookup per uncached query, bounded by the per-IP throttle and the same-origin check.
