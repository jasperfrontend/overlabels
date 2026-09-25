## Audit of OL-2609-118 - feat(products): one designer door on the chat product page, and American spelling on the chat surfaces

**Audited:** 2026-09-25
**Commit:** 8883ce240d8b369a2a1aa0e23f00a55bb9f622ac (only commit carrying the trailer)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/pages/products/show.vue @8883ce2` - no `Apply`, no `v-for` over presets, no `applyPreset`; `presets` is defined at :188 and read only at :640 (`v-if`) and :653 (`{{ presets.length }}`); the rest are comments (:66, :186, :635). Same at @HEAD (:169, :617, :630) |
| C2 | CONFIRMED | `show.vue:640-641 @8883ce2` - `v-if="installed && presets.length"`, `:href="withLastMileHint(route('products.design', product.slug), product.slug)"`; the removed row in the diff had the same `:href`. Same at @HEAD :617-618 |
| C3 | CONFIRMED | `show.vue:639-662 @8883ce2` - one `<Link>` containing only `<span>`s, `Sliders` and `ArrowRight`; call to action is `<span class="btn btn-primary ...">` at :658. No `<button>` inside it |
| C4 | CONFIRMED | `show.vue @8883ce2` - no `PRESET_FONTS`, no `fonts.googleapis`, no `<link rel="stylesheet">` in `<Head>`, and no match for Albert Sans / Inter / Space Grotesk / Fredoka / JetBrains Mono / Silkscreen / `fontFamily` |
| C5 | CONFIRMED | `show.vue:814-818 @8883ce2` - `box-shadow` outer `rgb(139 92 246 / 0.35)` plus `inset ... / 0.12`, `animation: product-design-breathe 5s ease-in-out infinite`; keyframes at :836; the only "gradient" in the file is the comment at :810. Reduced-motion block :892-906 lists `.product-design` under `animation: none` and sets `.product-design:hover .product-design-arrow { transform: none; }` |
| C6 | CONFIRMED | `app/Http/Controllers/ProductController.php:345 @8883ce2` - `'presets' => ChatPresets::forProduct($slug, $instance)`; `app/Support/ChatPresets.php:174-177 @8883ce2` - `label`, `blurb`, `active`, `preview`; `apply()` at :201; `routes/web.php:739-740 @8883ce2` names the POST `products.preset`; `resources/js/pages/products/design.vue:157 @8883ce2` posts to `/products/${slug}/presets/${key}`. Neither PHP file nor the route is in the diff. @HEAD: `ProductController.php:347`, `web.php:748`; `ChatPresets.php` has since changed under OL-2609-121 |
| C7 | CONFIRMED | Neither test file is in the diff. `php artisan test --filter='ProductChatPresetsTest\|ProductChatDesignerTest'` in a detached worktree at 8883ce2: 22 passed (369 assertions). At @HEAD: 25 passed (383 assertions) |
| C8 | CONFIRMED | `git grep -n olour 8883ce2 -- app/Support/ChatDesigner.php app/Support/ChatPresets.php resources/recipes/twitch-chat-overlay/ resources/js/pages/products/design.vue resources/js/pages/products/show.vue` - no output, exit 1. Same at @HEAD |
| C9 | CONFIRMED | `app/Support/ChatDesigner.php:85 @8883ce2` - `['title' => 'Colors', 'keys' => ['twitch_colors', 'name_color', 'text_color', 'accent']]`; @8883ce2^ :85 is the same `keys` array with `'Colours'`. Only the title and one hint string changed in the hunk. @HEAD :103, unchanged |
| C10 | CONFIRMED | migration `:29-33 @8883ce2` - `->where('key', $key)->where('label', $old)`; old labels in `LABELS` (:20-25) equal the recipe labels at `chat.md:223-229 @8883ce2^` (e.g. `Name colour` at :224) |
| C11 | CONFIRMED | migration `@8883ce2` - `DB::table('overlay_controls')` at :30 and :40, no `App\Models` import, `private const LABELS` at :20, `down()` at :37-45 matches `label = $new` and writes `$old`. File unchanged at @HEAD |

### Surface
Complete.

### Findings
- **F1** Contradiction with the record - OL-2609-090 C8 records "`show.vue` renders the 'Pick a look' section only when `installed` is truthy..." and C9 records the card swatch. This change deletes both (`show.vue` hunk at @8883ce2^ :662-738), but the claim never cites OL-2609-090 inline, although `claims-guide.md` says "Reference other IDs inline when a change corrects or builds on an earlier one". Next step: a follow-up claim that says it supersedes OL-2609-090 C8 and C9, and the "Pick a look" row OL-2609-109 added to `show.vue` (its Surface line 16).

### Notes
- C7 at the shipped commit required a worktree with a copied `vendor/` and `composer dump-autoload`. With `vendor/` symlinked, the autoloader resolved `app/` to the main checkout and 21 tests errored. That failure came from the audit setup, not from the code.
- `show.vue` changed after this commit in 49eca947 (no Changelog trailer; `.vue` only, so no claim was required). The change removed the ConfirmDialog/RekaToast/flash wiring. None of the symbols in C1-C5 were touched.
- `ChatPresets::forProduct()` still publishes `label`, `blurb`, `active` and `preview` (C6). After this change `show.vue` narrows `Preset` to `{ key }` and reads none of those fields. The OL-2609-109 Unchanged line "the product page still gets `active` computed server-side" is true of the payload but not of any reader.
- `ChatPresets.php` and `ChatDesigner.php` have since changed under OL-2609-119, OL-2609-121 and OL-2609-124. `GROUPS` and the two blurbs from this diff are the same at @HEAD.
