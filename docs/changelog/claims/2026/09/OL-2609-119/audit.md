## Audit of OL-2609-119 - feat(chat): search every Bunny font from the chat designer, and drop Google Fonts

**Audited:** 2026-09-25
**Commit:** c6e754095e696dd7df4614c05b17287324a37ac9
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/BunnyFonts.php:132 @c6e7540` - `HOST.'/css?family='.$slug.':'.WEIGHTS.'&display=swap'`, `HOST` (:49) `https://fonts.bunny.net`, `WEIGHTS` (:55) `400,700`, `slug()` (:97) lowercases and hyphenates; file unchanged @HEAD |
| C2 | CONFIRMED | `app/Support/BunnyFonts.php:129 @c6e7540` - returns null when the slug is not a key of `all()`; unchanged @HEAD |
| C3 | CONFIRMED | `public/fonts-bunny.json @c6e7540` - 1969 entries, 0 where `BunnyFonts::slug(name) !== key` (checked with a php one-liner over the file); file unchanged @HEAD |
| C4 | CONFIRMED | `public/fonts-bunny.json @c6e7540` - neither `buda` nor `coda-caption` is a key |
| C5 | CONFIRMED | `app/Console/Commands/SyncBunnyFonts.php:58 @c6e7540` collects mismatches and `continue`s; `:94` returns `self::FAILURE` when any exist, before `file_put_contents` at `:99`; unchanged @HEAD |
| C6 | CONFIRMED | `app/Http/Controllers/OverlayTemplateController.php:702-703 @c6e7540` appends `$control->tagIdentifier()` when `config['webfont']` is non-empty; `:959` emits `'webfont_controls' => $webfonts` |
| C7 | CONFIRMED | `app/Http/Controllers/OverlayControlController.php:423-431 @c6e7540` - `canonical()` null returns 422 before `writeValue()` at `:437` |
| C8 | CONFIRMED | `app/Http/Controllers/OverlayControlController.php:433 @c6e7540` - `$sanitized = $canonical`; `canonical()` returns the catalogue `name` (`BunnyFonts.php:114`) |
| C9 | CONFIRMED | `app/Http/Controllers/OverlayControlController.php:423 @c6e7540` - both checks sit inside `if (! empty($config['webfont']))` |
| C10 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:754,759 @c6e7540` - regex guard returns (removing any existing link) before `href` is built from `toLowerCase().replace(/ /g, '-')`, `WEBFONT_HOST` (:724), `WEBFONT_WEIGHTS` (:729); same @HEAD `:772,777` |
| C11 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:761-765 @c6e7540` - `existing instanceof HTMLLinkElement` sets `existing.href` and returns; @HEAD `:779-785` now also returns the link (OL-2609-120) |
| C12 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:1264 @c6e7540` - `if (webfontControls.value.includes(event.key))`; @HEAD `:1310` assigns the return to `fontLink` (OL-2609-120) |
| C13 | CONFIRMED | `resources/recipes/twitch-chat-overlay/chat.md @c6e7540` - head block is the generator `meta` and a Bunny `preconnect` only |
| C14 | CONFIRMED | `resources/recipes/twitch-chat-overlay/chat.md:236 @c6e7540` `  - webfont=true`; `app/Support/OverlayMarkdown.php:216-217,247 @c6e7540` passes sub-bullets to `behaviourPairs()`, which maps `'true'` to `true` |
| C15 | CONFIRMED | `app/Support/ChatDesigner.php:52 @c6e7540` - `CHOICES` keys are `layout`, `background` only; no font key @HEAD (OL-2609-121/124 touched the file, not this) |
| C16 | CONFIRMED | `app/Support/ChatDesigner.php:80 @c6e7540` six values; `ProductChatDesignerTest` asserts `canonical($v) === $v` for each and passed |
| C17 | CONFIRMED | `app/Support/ChatPresets.php:47-128 @c6e7540` - fonts are Albert Sans, JetBrains Mono, Fredoka, Space Grotesk, Inter, Silkscreen; `ProductChatPresetsTest` asserts canonical equality and passed |
| C18 | CONFIRMED | `git grep -e fonts.googleapis.com -e fonts.gstatic.com c6e7540 -- resources/recipes/` - no matches; same @HEAD |
| C19 | CONFIRMED | `resources/js/components/products/FontPicker.vue:67 @c6e7540` - `[...top, ...pool.filter(...)]`, then `.slice(0, LIMIT)` with `LIMIT = 60` (:85), so at most 60 rows, not the whole remaining catalogue |
| C20 | CONFIRMED | `resources/js/components/products/FontPicker.vue:204 @c6e7540` - `v-if="results.length < matchCount"` renders `Showing {{ results.length }} of {{ matchCount }}` |
| C21 | CONFIRMED | `tests/Feature/WebfontControlTest.php @c6e7540` - covers C1, C2 (`cssUrl` null), C3 (slug loop), C4 (`not->toHaveKey`), C6 (`assertJsonPath('webfont_controls', ['font'])`), C7 (422 + value unchanged), C8 (`space grotesk` -> `Space Grotesk`), C9 (plain control stores `Not A Font At All`); `php artisan test --filter=WebfontControlTest` 5 passed @HEAD |
| C22 | CONFIRMED | `tests/Feature/ProductChatDesignerTest.php @c6e7540` - C13 via `not->toContain` of `fonts.googleapis.com`, `/css?family=`, `/css2?family=`; C14 via parsed `config['webfont']` true; C15 `not->toHaveKey('font')`; C16 loop; install row test asserts `config['webfont']` true; 17 passed @HEAD |
| C23 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a modified tree |
| C24 | UNVERIFIABLE | tagged [unverified]; browser observation |
| C25 | UNVERIFIABLE | tagged [unverified]; browser observation |
| C26 | UNVERIFIABLE | tagged [unverified]; third-party behaviour |
| C27 | UNVERIFIABLE | tagged [unverified]; third-party behaviour |
| C28 | UNVERIFIABLE | tagged [unverified]; third-party behaviour |
| C29 | UNVERIFIABLE | tagged [unverified]; third-party endpoints and a local `.env` |

### Surface
Complete.

### Findings
- **F1** contradicts CLAUDE.md - `resources/recipes/chat-tower/tower.md:28-29 @c6e7540` was edited by hand while `CLAUDE.md:438-440 @c6e7540` says "its inputs live in gitignored `docs/private/engine/chat-tower/`. Re-export from there, never hand-edit the recipe copy", and the claim's own Unchanged line confirms no re-export (the `overlabels:engine` line at `:27` keeps its 2026-09-10 date and sha256); `follower-bowling/lane.md` carries the same `/engine` meta line and got the same edit. The claim does not cite the rule. Update the private `/engine` inputs to Bunny too, or a future re-export restores the Google link.
- **F2** false Unchanged line - the claim says "`resources/fonts/*.ttf` are read only by `OgImageService`", but `app/Console/Commands/OgRenderTest.php:24 @c6e7540` (`og:test`) also defaults its font directory to `base_path('resources/fonts')`. Record the correction in a new claim.
- **F3** scope - `BunnyFonts::DEFAULT_FAMILY` (`app/Support/BunnyFonts.php:58 @c6e7540`) is added, named by no Surface line or claim, and referenced nowhere in `app/`, `resources/`, `tests/` or `database/` at c6e7540 or @HEAD. Either disclose it or remove it.

### Notes
- Tests were run at HEAD, not at c6e7540: 30 passed across `WebfontControlTest`, `ProductChatDesignerTest` and `ProductChatPresetsTest`. `WebfontControlTest.php` and `fonts-bunny.json` are unchanged since c6e7540; `ProductChatDesignerTest` gained a test in OL-2609-124.
- The webfont allowlist in `setValue()` is only in the `value` branch (`OverlayControlController.php:399-435 @c6e7540`). An `action` of increment/decrement/reset on a webfont control writes a numeric string without the catalogue check. C7/C8 speak only of a submitted value, so they are not contradicted.
- `BunnyFonts::choices()` has no caller at c6e7540 or @HEAD. The picker builds its rows from the JSON on the client.
- `resources/js/components/builder/BuilderStylePanel.vue:216 @c6e7540` still shows a `fonts.googleapis.com` link as placeholder text. No claim covers the scope of "drop Google Fonts" beyond `resources/recipes/` (C18).
