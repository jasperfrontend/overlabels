## OL-2609-046 - fix(products): Follower Bowling racks ten pins for a channel with fewer followers, and the product page says how many are real

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-046`

### Surface
- `resources/recipes/follower_bowling/lane.md` - ten gated stand-in pins after the followers loop, `.pin-filler` styles, the mods line names `!fbfirst` and `!fbdraw`
- `resources/recipes/follower_bowling/manifest.json` - `follower_pins: 10`
- `resources/recipes/recipe-manifest.schema.json` - optional `follower_pins`, 1 to 100
- `app/Services/TwitchApiService.php` - new public `getCachedFollowersTotal()`
- `app/Support/WiringFacts.php` - `productSubject()` adds a context line for a product with `follower_pins` when the channel has fewer followers than pins
- `tests/Feature/ProductBowlingTest.php` - two new tests

### Claims
- **C1** [code] `lane.md` contains, after the `channel_followers` loop, ten blocks `[[[if:channel_followers.count <= N]]]` for N 0 to 9, each rendering `<div class="pin pin-filler" style="--fall: var(--pinN)">` with `https://images.overlabels.com/overlays/twitch-avatar.png` as the image; block N renders exactly when the capped follower list has N or fewer entries, so slots N and up are stand-ins.
- **C2** [code] Stand-ins are appended after the real pins in DOM order, so the existing `.pin:nth-child()` positions place them in the slots the loop left empty, and each carries the fall flag of its own slot; `bowl_knocked` and the ten `pin_*` expression controls are unchanged.
- **C3** [code] `TwitchApiService::getCachedFollowersTotal()` returns the `total` of the same cached `channel_followers` payload the overlay render reads, cast to int, or null on any throwable or a non-numeric total.
- **C4** [code] `WiringFacts::productSubject()` appends "Your rack has X of N pins from real followers; the rest are stand-ins until more followers arrive" only when the manifest's `follower_pins` is positive, the user has an `access_token`, the lookup returns a number, and that number is below `follower_pins`.
- **C5** [test] `ProductBowlingTest` asserts C1 for all ten slots against the parsed document, and C4 with a mocked `getCachedFollowersTotal` returning 1, then 25, then null.
- **C6** [unverified] The overlay's capped `channel_followers.count` is the number of pins the loop renders, which is the followers foreach cap (default 5) or the follower count, whichever is lower; with the default cap a channel with more than five followers shows five real pins and five stand-ins.
- **C7** [unverified] The padded lane was checked against the parsed document (C5) and not rendered in a browser in this change; `[[[if:channel_followers.count <= N]]]` relies on the renderer exposing `<iterable>.count` to conditions, which `OverlayRenderer.vue` documents at the comment near line 1032.

### Unchanged
- The followers foreach cap on `/settings/account` is not touched by the install; C6 is the consequence and is reported, not changed.
- Existing installs keep the lane document they were installed with; uninstall then install picks up this one.

### Risk
A channel above the default cap of five followers now sees stand-ins in slots six to ten unless it raises the cap. Before this change those slots were simply empty.
