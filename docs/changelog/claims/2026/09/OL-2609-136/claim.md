## OL-2609-136 - fix: resolve the four code findings from the September claims audit

**Shipped:** 2026-09-25
**Commit:** `git log --grep=OL-2609-136`

### Surface
- `routes/api.php` - the emote and badge routes are named `api.overlay.emotes` and `api.overlay.badges`
- `tests/Feature/OverlayHostRestrictionTest.php` - new test: both manifests answer 200 on the overlay host and the app host
- `resources/recipes/follower-bowling/lane.md` - the ten stand-in pins gate on their own slot being empty instead of on `channel_followers.count`
- `app/Support/WiringFacts.php` - the "pins from real followers" sentence counts `min(total, followers cap)` and names the cap when the cap is the limiter
- `tests/Feature/ProductBowlingTest.php` - "pads the rack" asserts the new gate shape; new test for the sentence above the cap
- `resources/js/utils/renderTemplate.test.ts` - new describe block running the gate shape through `renderTemplateSource`
- `app/Services/Messages/PipeFormatter.php` - `translateDateFormat()` backslash-escapes every character outside the six tokens
- `tests/Unit/PipeFormatterParityTest.php` - new test: `yy` and prose in a custom date pattern stay literal
- `app/Http/Controllers/KitController.php` - flash string says "copied", not "forked"
- `app/Http/Controllers/OverlayTemplateController.php` - two flash strings say "copied", not "forked"

### Claims
- **C1** [code] `routes/api.php` names the `GET /api/overlay/emotes/{channelId}` route `api.overlay.emotes` and the `GET /api/overlay/badges/{channelId}` route `api.overlay.badges`, so `RestrictOverlayHost::isAllowed()` returns true for both. Resolves OL-2609-087 audit F1.
- **C2** [test] `OverlayHostRestrictionTest` "the overlay host serves the emote and badge manifests the chat overlay fetches" asserts 200 for both paths on `https://overlabels.net` and on `https://overlabels.com`, with `TwitchEventSubService::getAppAccessToken()` mocked to null.
- **C3** [unverified] On 2026-09-25, before this change, `curl` to both paths returned 404 on `https://overlabels.net` and 200 on `https://overlabels.com`.
- **C4** [code] In `resources/recipes/follower-bowling/lane.md`, stand-in pin N (0-9) is `[[[if:channel_followers.N.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pinN)">...[[[endif]]]`, and the string `channel_followers.count <=` no longer appears in the file. Resolves OL-2609-046 audit F1 and F2: `channel_followers.N.*` keys exist only for the first `min(count, cap)` items (`TemplateDataMapperService::buildUserScopeIndexedKeys()`), so a stand-in renders exactly when its slot has no real follower, and real pins then stand-ins are contiguous, so `nth-child` and `--pinN` agree.
- **C5** [test] `ProductBowlingTest` "pads the rack with stand-in pins for the followers a channel does not have" asserts C4 for all ten slots.
- **C6** [test] `renderTemplate.test.ts` "renders a stand-in for every slot past the capped data, whatever count says" asserts through `renderTemplateSource` that a payload with `channel_followers.count` 25 and indexed data for slots 0 and 1 renders two real items and stand-ins for slots 2 and 3.
- **C7** [code] `WiringFacts::productSubject()` computes `$cap = $user->foreachCaps()['followers']` and emits the rack sentence when `min($total, $cap) < $pins`, with `$filled = min($total, $cap)`; when `$total > $cap` the sentence ends "because your followers cap is {cap}", otherwise "until more followers arrive". Resolves OL-2609-046 audit F6.
- **C8** [test] `ProductBowlingTest` "counts the real pins the lane renders, which is the followers cap when the channel is above it" asserts C7 for totals 25, 7 and 3 on the default cap of 5.
- **C9** [code] `PipeFormatter::translateDateFormat()` splits the pattern on the six tokens, maps each token to its Carbon character, and prefixes every other character with a backslash before the result reaches `Carbon::format()`. Resolves OL-2609-012 audit F1: the code comment "a bare 'yy' stays literal here" is now true.
- **C10** [test] `PipeFormatterParityTest` "date custom patterns leave everything outside the six tokens literal, as formatters.ts does" asserts `dd-MM-yy` renders `01-09-yy`, `dd-MM at HH:mm` renders `01-09 at 11:25`, and `Day dd, yyyy` renders `Day 01, 2026` for timestamp 1788261900 in en-US.
- **C11** [unverified] The tests in C2, C5, C6 (first case), C8 and C10 were each run against the pre-fix tree with the matching source file stashed and failed; C2 with 404, C10 with `01-09-2626`.
- **C12** [code] No flash string under `app/Http/Controllers/` contains "forked"; `KitController::fork()` flashes "Kit copied successfully!" and `OverlayTemplateController` flashes "Template copied successfully" and "Template copied successfully! The template ... has been added to your templates.". Resolves OL-2609-016 audit F3.

### Unchanged
- OL-2609-016 audit F2 (the mute/unmute flash on `/dashboard/events` is shown by nothing) is deliberately not restored. The button that posts it flips its own label and colour in place, and the confirmation rule is that a result visible where you clicked gets no toast. `resources/js/pages/dashboard/events.vue` and `AlertMuteController` are not in the diff.
- `resources/js/utils/formatters.ts` is not in the diff. Its `formatDate()` already leaves non-token characters literal; C9 brings PHP to it, not the reverse.
- `TemplateDataMapperService` is not in the diff. `channel_followers.count` stays the raw Twitch total and no capped-length key was added; C4 reads the presence of a slot's data instead.
- Installing Follower Bowling does not raise the streamer's followers foreach cap. `RecipeInstaller` and `User::PREFERENCE_DEFAULTS` are not in the diff; the cap-limited rack is padded (C4) and named on the product page (C7), not silently lifted.
- `[[[msg.badges]]]` and `badgeRenderer.ts` are not in the diff. C1 changes which host answers the manifest fetch, not what the manifest holds.

### Risk
A channel with more followers than its followers cap now gets a full ten-pin rack on the overlay host and everywhere else, stand-ins filling the slots the cap left empty. The product page sentence names the cap but offers no control to raise it; that lives on `/settings/account`.
