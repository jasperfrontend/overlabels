## OL-2609-109 - feat(products): a chat designer, with the real overlay in the frame and invented chat in it

**Shipped:** 2026-09-19
**Commit:** `git log --grep=OL-2609-109`

Third slice of the Twitch Chat chapter, after OL-2609-089 (the product) and OL-2609-090 (the ten
looks). A page at `/products/twitch_chat/design` with every look control on the left and the
product's own overlay, running, on the right.

### Surface
- `app/Support/ChatDesigner.php` - new. Choice vocabularies for the text controls, the knob groups, the preset bundles, the control payload, and the preview frame's token and URL
- `app/Http/Controllers/ProductController.php` - `design()` added; `applyPreset()` gains a JSON branch and a `JsonResponse` return type
- `routes/web.php` - `products.design`, `GET /products/{slug}/design`, in the auth group
- `routes/settings.php` - `settings.foreach-caps` gains a JSON branch
- `resources/js/pages/products/design.vue` - new. The page
- `resources/js/pages/products/show.vue` - a row under "Pick a look" linking into the designer; `Sliders` imported
- `resources/recipes/twitch_chat/manifest.json` - `ready_message` now says "or design your own". No version bump: `RecipeCatalog::find()` reads this file on every request and the field is not part of what an install is pinned to
- `resources/js/pages/products/index.vue` - prettier reflow of one existing paragraph. No content change; `npm run format:check` was failing on it before this diff
- `resources/js/utils/chatSample.ts` - new. The IRC line synthesis lifted out of the dev hose, plus `createChatSampleFeed`
- `resources/js/utils/chatSample.test.ts` - moved from `resources/js/dev/chatHose.test.ts`, retargeted at the new module, six tests added
- `resources/js/dev/chatHose.ts` - keeps the load-testing driver, imports the fixtures
- `resources/js/components/OverlayRenderer.vue` - `sample` prop, `startSampleChat()`, `handleSampleMessage()`, teardown
- `resources/js/overlay/app.js` - reads `?chat=sample` into the `sample` prop
- `tests/Feature/ProductChatDesignerTest.php` - new, 11 tests

### Claims

Lifting the generator:
- **C1** [code] `resources/js/utils/chatSample.ts` exports `privmsgLine`, `clearmsgLine` and `clearchatLine`. `resources/js/dev/chatHose.ts` imports all three and defines none of them.
- **C2** [code] `chatHose.ts` still defines `installChatHose`, `window.__olChatHose`, `sampleFrames()` and the rate/moderation driver. None of those are in `chatSample.ts`.
- **C3** [code] `ChatHoseOptions` extends `ChatSampleOptions` and adds exactly `rate` and `moderationChance`.
- **C4** [code] `privmsgLine` takes a `firstChance` option where it previously hardcoded `0.02`. `CHAT_SAMPLE_DEFAULTS.firstChance` is `0.02`.
- **C5** [test] `DevToolsExcludedFromBuildTest` passes against a production build: no built chunk contains `__olChatHose`, none is named after `chatHose`, and nothing imports `dev/chatHose` statically.

Sample mode in the overlay:
- **C6** [code] `OverlayRenderer.vue` imports `@/utils/chatSample` only dynamically, inside `startSampleChat()`, plus one `import type`. A production overlay chunk therefore carries neither the fixtures nor the feed.
- **C7** [code] When `props.sample` is false the chat branch calls `twitchChat.connect(chatChannel)`, as before. `setFilters`, `setWindowSize` and the badge-manifest load sit outside the sample/connect if-else and run in both modes.
- **C8** [code] When `props.sample` is true, `twitchChat.connect()` is not called anywhere in the component.
- **C9** [code] The `message` listener is added only inside `startSampleChat()` and returns early unless `event.source === window.parent` and `event.data.ol === 'chat-sample'`. It is removed in `onUnmounted` together with `sampleFeed.stop()`.
- **C10** [code] `overlay/app.js` sets `sample` from `new URLSearchParams(window.location.search).get('chat') === 'sample'`. No other value enables it.
- **C11** [test] `createChatSampleFeed(...).burst(n)` emits `n` lines that `parseIrcLine` + `toChatMessage` turn into chat messages.
- **C12** [test] `setRate` is messages per MINUTE: 60 produces 5 lines in 5 simulated seconds, and raising it to 120 produces 10 more in the next 5.
- **C13** [test] `setRate(0)` stops the feed and injects nothing over the next simulated minute.
- **C14** [test] `clear()` emits a line `toModerationAction` reads as `{ type: 'clear_all' }`.
- **C15** [test] The feed seeds from `Math.random`, not the module counter: with `Math.random` pinned to 0.5 and a pool of 4, three burst messages are all `chatter2`.

The page:
- **C16** [code] `GET /products/{slug}/design` is named `products.design` and is declared inside the authenticated route group in `routes/web.php`.
- **C17** [test] It returns 404 for a product with no presets, for an account with no install, and for an account that is not the overlay's owner.
- **C18** [test] It redirects a logged-out visitor.
- **C19** [test] It renders `products/design` with all thirteen controls keyed by control key, ten presets each carrying its full `values` bundle, ten skins, six fonts, five groups, and the account's chat foreach cap.
- **C20** [code] `ChatDesigner::skins()` is built from `ChatPresets::PRESETS` keys and labels. There is no second list of skins.
- **C21** [test] Every `ChatDesigner::CHOICES` value exists in the recipe: each layout has a `.layout-<value> ` rule, each background a `.bg-<value> ` rule, and each font appears as `family=<value>:` in the overlay's head block.
- **C22** [unverified] C21 was run against a tree whose font list named `Comic Sans MS` and failed on the head-block assertion.
- **C23** [test] Every key of `ChatPresets::KEYS` except `skin` appears in exactly one `ChatDesigner::GROUPS` entry, and no key appears twice.
- **C24** [unverified] C23 was run against a tree with the `Badges` group removed and failed.
- **C25** [code] The designer writes a control by POSTing to `/templates/{template}/controls/{control}/value`, the endpoint `ControlPanel.vue` already uses. It adds no control-writing route of its own.
- **C26** [code] `activePreset` in `design.vue` is computed from the page's current control values against each preset's `values`. No preset key is sent to the server or stored.
- **C27** [code] `resources/recipes/twitch_chat/manifest.json` keeps `"version": 2`. `RecipeCatalog::find()` reads the file rather than the `recipes` row, so the changed `ready_message` reaches the product page without a new row, and no existing install's `resolvedManifest()` changes shape.
- **C28** [test] `POST /products/{slug}/presets/{preset}` returns `{values: {...}}` as JSON to a JSON request, and the thirteen controls hold the bundle afterwards.
- **C29** [test] `PATCH /settings/foreach-caps` returns `{foreach_caps: {...}}` to a JSON request, and the designer then reports the new `chat_window`.

The preview token:
- **C30** [test] `preview_url` is relative, starts `/overlay/<slug>?chat=sample#`, and its fragment is 64 hex characters resolving through `OverlayAccessToken::findByToken()` to a token owned by that account.
- **C31** [test] Two renders in one session return the same `preview_url`, and the account holds exactly one token: named `Chat designer preview`, with `metadata.purpose` of `chat_designer`, expiring in under two days.
- **C32** [unverified] C31 was run against a tree where `previewToken()` ignored the session value, and failed on the two URLs being equal.
- **C33** [test] When the held token no longer resolves, the next render mints a different one and the account still holds exactly one.
- **C34** [code] `ChatDesigner::TOKEN_PURPOSE` is set in `metadata` so a later reader can tell a preview's access rows from a browser source's. `ProductController::yourOverlays()` reads `overlay_access_logs` by slug and does not yet filter on it; it also returns early for a product with a static overlay, so Twitch Chat never reaches that query today.

Browser observations on overlabels.test, 2026-09-19:
- **C35** [unverified] Clicking the Terminal skin changed the framed overlay's root element to `ol-chat skin-terminal layout-bottom bg-glass`.
- **C36** [unverified] Applying the Neon preset moved all thirteen: the root became `ol-chat skin-neon layout-bottom bg-none` with `--accent: #22d3ee`, `--font: Space Grotesk` and `--text: #e0f2fe`, matching that bundle. A read 1.2 s after the click still showed `bg-glass`, `#9146ff` and `Albert Sans`; the thirteen broadcasts had not all arrived by then.
- **C37** [unverified] Setting layout to `ticker` resized the frame element to 1920x80 and the caption to "at 1920x80".
- **C38** [unverified] The sample-rate slider, More and Clear each acted on the framed overlay: 120/min added messages over time, Clear emptied the window to 0, More added 6.
- **C39** [unverified] The same overlay URL with `?chat=sample` removed rendered with 0 messages, no generated chatter names and `window.__olChatHose` undefined, and fetched `/api/overlay/badges/73327367` - the request that sits in the same block as `twitchChat.connect()`.

### Unchanged
- `app/Support/ChatPresets.php` is not in the diff. `KEYS`, `PRESETS`, `apply()`, `overlayFor()` and `forProduct()` are as OL-2609-090 shipped them, and the product page still gets `active` computed server-side by `forProduct()`. The designer needs the whole bundle rather than a boolean, so `ChatDesigner::presets()` is a second reader of the same constant rather than a widening of `forProduct()`.
- `resources/recipes/twitch_chat/` is not in the diff. The overlay's head, html and css blocks, its thirteen declared controls and the manifest version are untouched; the designer writes controls an install already creates, and the CSS stays on the compiled-bindings fast path OL-2609-089 put it on.
- `app/Http/Controllers/OverlayControlController.php` is not in the diff. `setValue()` is what validates, clamps, writes and broadcasts every knob the designer turns, and it does all of that unchanged.
- `resources/js/composables/useTwitchChat.ts` is not in the diff. `injectRawLine()` was already the seam the dev hose fed, so the sample feed reaches the renderer through the same parsing, filtering, queueing, flush batching and window trimming with nothing adjusted for it.
- The load-testing behaviour of `dev/chatHose.ts` did not move with its fixtures: `createChatHose`, the 100 ms batching tick, `sampleFrames()` and the stop report are as they were, and `DEFAULTS` keeps `channel: 'loadtest'` by overriding the shared default.
- The hosted-overlay origin is not used by the preview. `HandleInertiaRequests` still shares `overlayOrigin`, and `AddToObsButton` - which the designer renders - still builds the OBS link on it, so the link a streamer copies is unchanged while the frame stays same-origin.
- `products.preset`'s redirect target is unchanged. The JSON branch is reached only by a request that asks for JSON, so the product page still lands back on `/products/{slug}`.

### Risk
- The overlay route now honours `?chat=sample`. A browser source loaded with that query renders invented chat and never connects to Twitch. Nothing mints such a URL except the designer.
- A first visit to the designer creates an overlay access token named `Chat designer preview`, which appears on `/settings/tokens` like any other. It expires after a day and the next visit past its expiry replaces it.
