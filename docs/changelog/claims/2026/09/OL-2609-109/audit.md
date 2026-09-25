## Audit of OL-2609-109 - feat(products): a chat designer, with the real overlay in the frame and invented chat in it

**Audited:** 2026-09-25
**Commit:** a5a54f1eff00becd8f2200edf1ad613efcfd8309
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/utils/chatSample.ts:128,193,198 @a5a54f1` export `privmsgLine`, `clearmsgLine`, `clearchatLine`; `resources/js/dev/chatHose.ts:36 @a5a54f1` imports all three, and the diff deletes their definitions from it. Unchanged @HEAD |
| C2 | CONFIRMED | `chatHose.ts:75 sampleFrames`, `:116 createChatHose` (tick/moderation driver), `:203 __olChatHose`, `:207 installChatHose @a5a54f1`; none of these names occur in `chatSample.ts @a5a54f1`. Unchanged @HEAD |
| C3 | CONFIRMED | `chatHose.ts:38-43 @a5a54f1` - `ChatHoseOptions extends ChatSampleOptions` with only `rate` and `moderationChance` |
| C4 | CONFIRMED | `chatSample.ts:175 @a5a54f1` - `first-msg=${chance(options.firstChance) ...}`; `:104` `firstChance: 0.02`; the removed hunk of `chatHose.ts` shows the old `chance(0.02)` |
| C5 | CONTRADICTED | Test passes @a5a54f1 (4/4, after `npm run build` of the shipped tree; `__olChatHose` in no chunk, no chunk named `chatHose`). But "nothing imports dev/chatHose statically" is narrower in the test: `tests/Feature/DevToolsExcludedFromBuildTest.php:73 @a5a54f1` globs `resources/js/**/*.{ts,vue}` with PHP `glob()`, which is not recursive - it matches 190 files exactly one directory below `resources/js/`, so e.g. `resources/js/pages/products/design.vue` and every `.js` file are never scanned |
| C6 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:46 @a5a54f1` is `import type`; `:216` is the only other reference, a dynamic `import()` inside `startSampleChat()`. Shipped-tree build: fixture strings (`this is the best stream`) only in `chatSample-DiVcnLl_.js`; `app-*.js` carries `createChatSampleFeed`/`chat-sample` identifiers only. Same @HEAD (`:46`, `:234`) |
| C7 | CONFIRMED | `OverlayRenderer.vue:947,950 @a5a54f1` (`setFilters`, `setWindowSize`) before, and `:962-964` (badge manifest) after, the `if (props.sample) ... else twitchChat.connect(chatChannel)` at `:955-959` |
| C8 | CONFIRMED | `OverlayRenderer.vue:958 @a5a54f1` is the only `twitchChat.connect(` in the file, in the `else` branch; @HEAD `:1061`, same |
| C9 | CONFIRMED | `OverlayRenderer.vue:222 @a5a54f1` `addEventListener` inside `startSampleChat()` only; `:205-208` early returns on `event.source !== window.parent` and `payload.ol !== 'chat-sample'`; `:1069-1072` stop + `removeEventListener` in `onUnmounted` |
| C10 | CONFIRMED | `resources/js/overlay/app.js:77 @a5a54f1`; same @HEAD |
| C11 | CONFIRMED | `resources/js/utils/chatSample.test.ts:133-141 @a5a54f1` - `burst(5)`, 5 lines, each non-null through `parseIrcLine`+`toChatMessage`. `npm test -- resources/js/utils/chatSample.test.ts`: 17/17 passed @a5a54f1 and @HEAD |
| C12 | CONFIRMED | `chatSample.test.ts:156-171 @a5a54f1` - 5 lines after 5 s at 60, 15 total after 5 s more at 120; passed |
| C13 | CONFIRMED | `chatSample.test.ts:173-185 @a5a54f1` - 2 lines before `setRate(0)`, still 2 after 60 s; passed |
| C14 | CONFIRMED | `chatSample.test.ts:187-192 @a5a54f1` - `toEqual({ type: 'clear_all' })`; passed |
| C15 | CONFIRMED | `chatSample.test.ts:143-154 @a5a54f1` - `Math.random` = 0.5, `chatters: 4`, `burst(3)` -> three `chatter2`; passed |
| C16 | CONFIRMED | `routes/web.php:707-710 @a5a54f1`, inside `Route::middleware('auth.redirect')->group` opened at `:556` and closed at `:760`. @HEAD `:742`, slug pattern now `[a-z][a-z0-9_-]*` (OL-2609-114) |
| C17 | CONTRADICTED | `tests/Feature/ProductChatDesignerTest.php:199-210 @a5a54f1` asserts 404 for `chat_tower` and for a user with no install. The "not the overlay's owner" case (`:209`) is a fresh `designerUser()` with no install, so it 404s through `instanceFor()` returning null and never reaches the `$template->owner_id !== $user->id` branch at `app/Http/Controllers/ProductController.php:384 @a5a54f1`. Test passes |
| C18 | CONFIRMED | `ProductChatDesignerTest.php:212-214 @a5a54f1` - `assertRedirect()`; passed |
| C19 | CONTRADICTED | `ProductChatDesignerTest.php:117-141 @a5a54f1` asserts 13 controls (keys checked: `skin`, `font_size`), 10 presets, 10 skins, 6 fonts, 5 groups, `chat_window` 50. "Ten presets each carrying its full `values` bundle" is not asserted: only `presets.1.key` and `presets.1.values.font` are checked. Test passes. @HEAD `CHOICES` has no `font` key (OL-2609-119) |
| C20 | CONFIRMED | `app/Support/ChatDesigner.php:95-104 @a5a54f1` loops `ChatPresets::PRESETS` for value/label/hint; no other skin list in the file. Same @HEAD `:113` |
| C21 | CONFIRMED | `ProductChatDesignerTest.php:74-97 @a5a54f1`; the font check is `family=` + value with spaces as `+` + `:` (`:88`); passed. @HEAD the font assertion is gone with Google Fonts (OL-2609-119) |
| C22 | UNVERIFIABLE | tagged [unverified]; fail-first run against a tree that no longer exists |
| C23 | CONFIRMED | `ProductChatDesignerTest.php:99-115 @a5a54f1` - sorted equality against `KEYS` minus `skin`, plus count equals unique count; passed |
| C24 | UNVERIFIABLE | tagged [unverified]; fail-first run |
| C25 | CONFIRMED | `resources/js/pages/products/design.vue:107 @a5a54f1` posts to `/templates/${overlay.id}/controls/${control.id}/value`, same shape as `resources/js/components/ControlPanel.vue:327 @HEAD`; the diff adds only `GET /products/{slug}/design` to routes |
| C26 | CONTRADICTED | Compound. First half CONFIRMED: `design.vue:132-133 @a5a54f1` derives `activePreset` from `presets[].values` against current values. Second half CONTRADICTED as written: `design.vue:142 @a5a54f1` sends the preset key to the server in `POST /products/${slug}/presets/${key}`. The derived `activePreset` itself is never sent. @HEAD `:185`, rewritten by OL-2609-121 |
| C27 | CONFIRMED | `resources/recipes/twitch_chat/manifest.json:5 @a5a54f1` `"version": 2`; `app/Services/Recipes/RecipeCatalog.php:69-78 @a5a54f1` `find()` reads the file; `ProductController.php:279 @a5a54f1` takes `ready_message` from `listedManifest()` -> `find()`; `RecipeInstance::resolvedManifest()` reads `$this->recipe?->manifest` (the row) |
| C28 | CONTRADICTED | `ProductChatDesignerTest.php:216-228 @a5a54f1` asserts the JSON has `values.skin` and `values.font`, and afterwards checks only the `skin` control. It does not assert the thirteen controls hold the bundle. Test passes |
| C29 | CONFIRMED | `ProductChatDesignerTest.php:230-244 @a5a54f1` - `foreach_caps.chat` 12 in JSON, then `chat_window` 12 on the designer; passed |
| C30 | CONFIRMED | `ProductChatDesignerTest.php:143-159 @a5a54f1` - prefix, not `http`, fragment `^[0-9a-f]{64}$`, `findByToken()->user_id` equals user; passed |
| C31 | CONFIRMED | `ProductChatDesignerTest.php:161-180 @a5a54f1` - equal URLs, one token, name, `metadata.purpose`, future and under `now()->addDays(2)`; passed. @HEAD token sweep changed by OL-2609-124 |
| C32 | UNVERIFIABLE | tagged [unverified]; fail-first run |
| C33 | CONTRADICTED | `ProductChatDesignerTest.php:182-197 @a5a54f1` expires the token AND calls `$this->flushSession()` (`:191`), so the second render holds no token and goes straight to minting; the branch where a held token stops resolving (`ChatDesigner.php:185-191 @a5a54f1`) is never exercised. What it does assert: a different URL and exactly one token afterwards. Test passes |
| C34 | CONFIRMED | `ChatDesigner.php:209 @a5a54f1` sets `metadata.purpose`; `ProductController.php yourOverlays() @a5a54f1` returns `$none` when any overlay is not `alert`, then plucks `OverlayAccessLog.template_slug` with no metadata filter |
| C35 | UNVERIFIABLE | tagged [unverified]; browser observation |
| C36 | UNVERIFIABLE | tagged [unverified]; browser observation |
| C37 | UNVERIFIABLE | tagged [unverified]; browser observation |
| C38 | UNVERIFIABLE | tagged [unverified]; browser observation |
| C39 | UNVERIFIABLE | tagged [unverified]; browser observation |

### Surface
Complete. (`resources/js/dev/chatHose.test.ts` leaves the tree as a rename, R051, into `resources/js/utils/chatSample.test.ts`, and the Surface line for the new path says so.)

### Findings
- **F1** test narrower than claim (C5) - `tests/Feature/DevToolsExcludedFromBuildTest.php:73 @a5a54f1` uses non-recursive PHP `glob()` on `resources/js/**/*.{ts,vue}`, so "nothing imports dev/chatHose statically" is checked only for `.ts`/`.vue` files one directory below `resources/js/`; make the scan recursive (and include `.js`), or restate the claim.
- **F2** test narrower than claim (C17) - `tests/Feature/ProductChatDesignerTest.php:209 @a5a54f1` uses an account with no install, so the non-owner 404 at `ProductController.php:384 @a5a54f1` (`$template->owner_id !== $user->id`) has no test; add one where the instance exists but the overlay belongs to someone else, or drop that case from the claim.
- **F3** test narrower than claim (C19) - `ProductChatDesignerTest.php:132-134 @a5a54f1` checks one preset's key and one of its values, not ten full bundles; assert each preset's `values` against `ChatPresets::PRESETS`, or restate.
- **F4** compound claim, one half false (C26) - `resources/js/pages/products/design.vue:142 @a5a54f1` sends the preset key in the URL of `POST /products/{slug}/presets/{key}`, so "No preset key is sent to the server" is false as written; restate it as "the derived `activePreset` is not sent or stored".
- **F5** test narrower than claim (C28) - `ProductChatDesignerTest.php:224-227 @a5a54f1` reads back only the `skin` control after the JSON apply, not the thirteen; assert every key of the `terminal` bundle, or restate.
- **F6** test narrower than claim (C33) - `ProductChatDesignerTest.php:191 @a5a54f1` flushes the session before the second render, so the held-token-no-longer-resolves path at `app/Support/ChatDesigner.php:185-191 @a5a54f1` is never taken; keep the session and expire only the row to cover it.
- **F7** declared unchanged but modified - the Unchanged line "`resources/recipes/twitch_chat/` is not in the diff" is false: `resources/recipes/twitch_chat/manifest.json` is in the diff (`ready_message`, and listed in Surface). The rest of that line (head/html/css blocks, controls, version) holds.
- **F8** scope - `resources/js/components/OverlayRenderer.vue:228-230 @a5a54f1` has the framed overlay post `{ ol: 'chat-sample', ready: true }` to `window.parent` with target origin `'*'`, and no claim covers any message from the overlay to its parent (C9 covers inbound only); add a claim.
- **F9** scope - `OverlayRenderer.vue:943 @a5a54f1` widens the chat gate from `chatChannel && ...` to `(chatChannel || props.sample) && ...`, so sample mode runs the chat block when `user_login` is empty; no claim states this.
- **F10** scope - `ChatDesigner.php:192-199 @a5a54f1` deletes every `chat_designer` token on the account, together with their `overlay_access_logs` rows, whenever it mints a new one, including a token still live in another session. No claim covers the deletion (C33 says only "still holds exactly one"); record it.
- **F11** narrows an earlier claim without citing it - OL-2609-090 C7 records that `applyPreset()` "then redirects to `products.show`"; `ProductController.php:356-358 @a5a54f1` now returns JSON to a JSON request. OL-2609-109 names OL-2609-090 only as background and never cites C7. A new claim should cite "narrows OL-2609-090 C7".

### Notes
- C5 and the page-rendering tests in `ProductChatDesignerTest` pass on the shipped tree only after `npm run build`. Without one they fail with a missing Vite manifest (six tests) or are skipped. The run used HEAD's `vendor/` because `composer.lock` differs between a5a54f1 and HEAD. @HEAD: 17/17 designer tests and 4/4 build tests pass.
- F10's sweep of a live token held by another session was changed at HEAD by OL-2609-124 (it now sweeps only expired or inactive tokens, `ChatDesigner.php:232 @HEAD`).
- Later claims that changed audited symbols: OL-2609-110, -114 (route slug), -118, -119 (font vocabulary gone from `CHOICES`), -120, -121 (`activePreset`), -122, -124. Commit `1ffbc595` changed `design.vue` with no trailer. That is a `.vue`-only diff, which the path rule exempts.
- `CLAUDE.md` still names `chatHose.test.ts` in "Chat load testing". That file moved to `resources/js/utils/chatSample.test.ts` in this commit.
