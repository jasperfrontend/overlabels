## Audit of OL-2609-136 - fix: resolve the four code findings from the September claims audit

**Audited:** 2026-11-15
**Commit:** a9fb8de4caf0a9d31a707b49dc169ad0f01221ab
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `routes/api.php:120 @a9fb8de` - `GET /overlay/emotes/{channelId}` (inside `Route::prefix('/overlay')` at `:83`) `->name('api.overlay.emotes')`; `:151 @a9fb8de` - badges route `->name('api.overlay.badges')`. `RestrictOverlayHost::ALLOWED_ROUTES` holds `'api.overlay.*'` and `isAllowed()` is `Str::is(self::ALLOWED_ROUTES, $routeName)` (`app/Http/Middleware/RestrictOverlayHost.php:38-41,76-79 @a9fb8de`). Same names @HEAD (`routes/api.php:130,161`) |
| C2 | CONFIRMED | `tests/Feature/OverlayHostRestrictionTest.php:62 @a9fb8de` - mocks `TwitchEventSubService::getAppAccessToken` to null, `assertOk()` on `OVERLAY_HOST` (`https://overlabels.net`) and `APP_HOST` (`https://overlabels.com`) for both paths; `beforeEach` sets `app.overlay_url` to the .net host. `php artisan test --filter="pins\|emote and badge manifests"`: both datasets passed |
| C3 | UNVERIFIABLE | tagged [unverified] (prod curl) |
| C4 | CONFIRMED | `resources/recipes/follower-bowling/lane.md:63-72 @a9fb8de` - slot N is `[[[if:channel_followers.N.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pinN)">...[[[endif]]]` for N 0-9; `channel_followers.count <=` absent from the file. Indexed keys come from `array_slice($list, 0, $cap)` (`app/Services/TemplateDataMapperService.php:387 @a9fb8de`). Same lines @HEAD |
| C5 | CONFIRMED | `tests/Feature/ProductBowlingTest.php:159-176 @a9fb8de` - per slot asserts the string `[[[if:channel_followers.{slot}.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin{slot})">`, plus `not->toContain('channel_followers.count <=')` and 10 `pin pin-filler`; passed (run above). Test body unchanged @HEAD |
| C6 | CONFIRMED | `resources/js/utils/renderTemplate.test.ts:392-396 @a9fb8de` - payload `channel_followers.count` 25 with keys for slots 0 and 1; expects `<b>ana</b><b>bo</b><i>2</i><i>3</i>`. `npx vitest run resources/js/utils/renderTemplate.test.ts -t "capped list padding"`: 2 passed; full file 29 passed |
| C7 | CONFIRMED | `app/Support/WiringFacts.php:461-466 @a9fb8de` - `$cap = $user->foreachCaps()['followers']`; condition `min($total, $cap) < $pins`; `$filled = max(0, min($total, $cap))`; `$total <= $cap` picks "until more followers arrive", else "because your followers cap is {$cap}". Same @HEAD `:455-460` |
| C8 | CONFIRMED | `tests/Feature/ProductBowlingTest.php:191 @a9fb8de` - mock returns 25, 7, 3; expects "5 of 10 ... because your followers cap is 5" twice, then "3 of 10 ... until more followers arrive"; default cap 5 at `app/Models/User.php:132 @a9fb8de`. Passed @HEAD, where `:194` sets the cap of 5 explicitly (OL-2609-137) |
| C9 | CONFIRMED | `app/Services/Messages/PipeFormatter.php:213-218 @a9fb8de` - `preg_split('/(yyyy\|MM\|dd\|HH\|mm\|ss)/', ..., PREG_SPLIT_DELIM_CAPTURE \| PREG_SPLIT_NO_EMPTY)`, tokens mapped via `$map`, other parts `preg_replace('/(.)/su', '\\\\$1', $part)`; result passed to `$date->format()` at `:189`. File unchanged @HEAD |
| C10 | CONFIRMED | `tests/Unit/PipeFormatterParityTest.php:134-141 @a9fb8de` - the three patterns and expected strings exactly as claimed, timestamp `1788261900`, `en-US`; passed (`php artisan test tests/Unit/PipeFormatterParityTest.php`) |
| C11 | UNVERIFIABLE | tagged [unverified] (fail-first runs against a pre-fix tree) |
| C12 | CONTRADICTED (first half) / CONFIRMED (second half) | Compound. "No flash string under `app/Http/Controllers/` contains forked" is false: `app/Http/Controllers/KitController.php:248 @a9fb8de` flashes `back()->withErrors(['error' => 'This kit has been forked and cannot be deleted.'])`, still present @HEAD `:305`. The three named strings are true: `KitController.php:281`, `OverlayTemplateController.php:1697` and `:1708 @a9fb8de`. @HEAD the kit flash is `'Kit copied!'` (`KitController.php:340`, OL-2611-006) |

### Surface
Complete.

### Findings
- **F1** claim contradicted (C12 first half) - `app/Http/Controllers/KitController.php:248 @a9fb8de` (`:305 @HEAD`) flashes "This kit has been forked and cannot be deleted." through `withErrors()`. `KitCard.vue:46-49 @a9fb8de` posts the delete with no `fork_count` pre-check, so that request can reach it. Either reword the string to "copied" or restate C12 in a new claim as limited to the `success` flashes.
- **F2** scope - the diff changes the fixture of the existing test "tells a small channel how many pins are real, and says nothing when Twitch cannot be asked" (`tests/Feature/ProductBowlingTest.php:179 @a9fb8de`). It now sets `foreach_caps.followers` to 10, so its "silent at 25 followers" assertion holds only above the default cap. The Surface line for that file and the claims mention only "pads the rack" and the new test. Record this in a new claim.
- **F3** Unchanged line misstates the symbol - the third Unchanged line says `channel_followers.count` "stays the raw Twitch total". `TemplateDataMapperService.php:385 @a9fb8de` sets it to `count($list)`, the length of the list fetched with `first = User::FOREACH_CAP_MAX` (50) (`app/Services/TwitchApiService.php:334,348 @a9fb8de`). It is not Twitch's `total`, which is `followers_total` (`TemplateDataMapperService.php:189`). The same wording appears in the comments of `renderTemplate.test.ts:377-381` and `ProductBowlingTest.php:162-167 @a9fb8de`. The reasoning in C4 still holds, because count can exceed the cap. Correct the description in a new claim.

### Notes
- Both bowling tests call `installProduct()`, which is defined in `tests/Feature/ProductUninstallTest.php:33`. Run alone with `php artisan test tests/Feature/ProductBowlingTest.php`, they error with "Call to undefined function installProduct()". With `--filter` or the full suite they pass. The dependency predates this commit.
- C7: the code reads `max(0, min($total, $cap))`, not the bare `min($total, $cap)` the claim quotes. The two agree for any non-negative total.
- Unchanged line 2 ("C9 brings PHP to it"): `formatters.ts:285-290 @a9fb8de` uses `String.replace` with string patterns, so it replaces only the FIRST occurrence of each token. PHP now replaces every occurrence, so a pattern that repeats a token (`dd dd`) still renders differently in JS and PHP. No claim asserts parity for that case.
- The defaults for the C8 test drifted, and OL-2609-137 C16/C17 disclose it: the default followers cap is 10 @HEAD, and the test pins 5 explicitly.
