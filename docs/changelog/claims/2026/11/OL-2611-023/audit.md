## Audit of OL-2611-023 - feat(controls): a knob greys itself out while another holds a value; a Plain look for Emote Bubbles; the control dialog keeps config it does not draw; a per-product sample-chat ceiling

**Audited:** 2026-11-19
**Commit:** 8a3dceda7c92356242cc460b9ce8c0be4ccaa66e (the only commit carrying the trailer; it is HEAD, so @8a3dceda and @HEAD are the same tree)

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/ControlConditions.php:39-59 @8a3dceda` - a string is wrapped to a list; `! is_array($list) \|\| ! array_is_list($list) \|\| $list === []` returns null; any non-string or `trim() === ''` entry returns null; otherwise the trimmed list |
| C2 | CONFIRMED | `app/Support/ControlConditions.php:83 @8a3dceda` - `! in_array($control['type'], ProductDesigner::KNOB_TYPES, true)` adds "...can declare enabled_if." |
| C3 | CONFIRMED | `app/Support/ControlConditions.php:99 @8a3dceda` - `! str_starts_with($subject, 'c:') \|\| ! isset($declared[substr($subject, 2)])` adds "...is not a control of this overlay." |
| C4 | CONFIRMED | `app/Support/ControlConditions.php:112 @8a3dceda` - subject type not in `ProductDesigner::KNOB_TYPES` adds "...a condition may only read text, number, boolean and color controls." |
| C5 | CONFIRMED | `app/Support/ControlConditions.php:110-111 @8a3dceda` - `$other['key'] === $key` adds "depends on itself." |
| C6 | CONFIRMED | `app/Support/ControlConditions.php:114-117,138 @8a3dceda` - `split()` maps `=` to `==`; for `==`/`!=` a value not in the subject's non-empty `config.choices` values is refused |
| C7 | CONFIRMED | `app/Support/ControlConditions.php:107 @8a3dceda` with `resources/dsl/dsl.json:45` (operators `>=, <=, !=, >, <, =`) - `c:x == v` parses as `=` against `= v`, and `$operator === '==' && str_starts_with($value, '=')` is refused |
| C8 | CONFIRMED | `app/Support/ControlConditions.php:135 @8a3dceda` calls `Dsl::conditionBodyPattern()`; `resources/js/utils/controlConditions.ts:29 @8a3dceda` destructures `parseCondition`, `evaluateCondition` from `useConditionalTemplates()` |
| C9 | CONFIRMED | `app/Services/Recipes/RecipeManifestValidator.php:261,273-290 @8a3dceda` - `semanticErrors()` merges `controlConditionErrors()`, which writes `"{$document['file']}: {$problem}"` |
| C10 | CONFIRMED | `resources/js/utils/controlConditions.ts:54-74 @8a3dceda` - loops conditions, `continue` when `evaluateCondition` holds, returns the sentence for the first that does not, `null` after the loop |
| C11 | CONFIRMED | `resources/js/utils/controlConditions.ts:58 @8a3dceda` - `if (!subject) continue;` |
| C12 | CONFIRMED | `resources/js/components/controls/ControlKnob.vue:85-90,190-192 @8a3dceda` - `<fieldset :disabled="!!disabledReason">`; `<p v-if="disabledReason">` after `</fieldset>` |
| C13 | CONFIRMED | `design.vue @8a3dceda` - `conditionSubjects`/`reasonFor()` (145-151) feed only `:disabled-reason` (855); `ControlPanel.vue @8a3dceda` - `conditionSubjects` feeds only `:disabled-reason` (476); no write call references either |
| C14 | CONFIRMED | `resources/recipes/chat-emote-bubbles/bubbles.md:241,243,245 @8a3dceda` - `enabled_if=["c:look != plain"]` on `bubble_color`, `bubble_color_2`, `gradient_angle` |
| C15 | CONFIRMED | `resources/recipes/chat-emote-bubbles/bubbles.md:257,259 @8a3dceda` - `enabled_if=["c:spawn = cannon"]` on `spawn_x`, `spawn_y` |
| C16 | CONFIRMED | `resources/recipes/twitch-chat-overlay/chat.md:254 @8a3dceda` - `enabled_if=["c:background != none"]` under `background_color` |
| C17 | CONFIRMED | `resources/recipes/chat-emote-bubbles/manifest.json` `designer.presets @8a3dceda` - `plain.values` equals `soap.values` on all 11 keys other than `look`, which is `plain` (compared with `php -r` over the decoded JSON) |
| C18 | CONTRADICTED | `tests/Unit/ControlConditionsTest.php @8a3dceda` - 8 tests, `php artisan test --filter=ControlConditionsTest` passes (8 passed, 16 assertions). Narrower than C1-C7: no case gives `of()` a list holding an empty or whitespace-only string (C1's "non-empty strings"), and the only bad-choice case is `c:look != plian` (53-56), so C6's `=` half is not asserted here |
| C19 | CONFIRMED | `resources/js/utils/controlConditions.test.ts:38-48,60-63 @8a3dceda` - null when conditions hold, first failing condition named, missing subject skipped; `npm test` on the file passes |
| C20 | CONFIRMED | `tests/Feature/RecipeDesignerBlockTest.php:220-237 @8a3dceda` - expects `bubbles.md: Control "spawn_x" compares spawn to "canon", ...` and the same for `spawn_y`; filter run: 21 passed |
| C21 | CONTRADICTED | Compound. (a) CONFIRMED: `tests/Feature/BubblesPlainLookBackfillTest.php:59-76 @8a3dceda` asserts `look` config and CSS equal a fresh install's. (b) narrower: the "runs twice" test (78-87) asserts only that the choice values are `[bubble, snow, heart, plain]` and that `.look-plain` occurs once after two runs; it does not compare config or CSS between the first and second run. 4 passed |
| C22 | CONFIRMED | `tests/Feature/BubblesPlainLookBackfillTest.php:102-121 @8a3dceda` - a custom one-choice list and a hand-made `look` control keep their config; passes |
| C23 | CONFIRMED | `tests/Feature/BubblesPlainLookBackfillTest.php:89-100 @8a3dceda` - rewritten heart rule, CSS unchanged, choices gain `plain`; passes |
| C24 | CONTRADICTED | Compound. (a) CONFIRMED: `tests/Feature/EnabledIfBackfillTest.php:59-76 @8a3dceda` asserts every control config equals a fresh install's for both `chat-emote-bubbles` and `twitch-chat-overlay`. (b) narrower: "values untouched" is asserted for one row only, `bubble_color` on the Bubbles install (75); no Twitch Chat value and no other Bubbles value is asserted. 2 passed |
| C25 | CONFIRMED | `tests/Feature/EnabledIfBackfillTest.php:78-93 @8a3dceda` - pre-declared `spawn_x` keeps `c:spawn != random`; hand-made `bubble_color` config unchanged; passes |
| C26 | UNVERIFIABLE | tagged [unverified] (browser observation) |
| C27 | CONFIRMED | `resources/js/utils/controlConfigPayload.ts:51-76 @8a3dceda` - `kept` = existing minus `DIALOG_KEYS`; number/counter return `{...kept, form fields}`; other non-timer types return `kept` or `null` when empty |
| C28 | CONFIRMED | `resources/js/utils/controlConfigPayload.ts:65-74 @8a3dceda` - timer object uses no `kept`; its six fields match the removed `else if (t === 'timer')` branch of `ControlFormModal.vue @8a3dceda^` |
| C29 | CONFIRMED | `resources/js/components/ControlFormModal.vue:462 @8a3dceda` - `controlConfigPayload(t, form.value.config, props.control?.config ?? props.copyFrom?.config ?? null)` |
| C30 | CONFIRMED | `resources/js/utils/controlConfigPayload.test.ts @8a3dceda` - asserts `choices` on text (12), `webfont` on text and `enabled_if` on color (16-19), `enabled_if` on number (21-33), other-type keys dropped on text (40-42); `npm test` on the file passes |
| C31 | UNVERIFIABLE | tagged [unverified] (fail-first run against a tree that no longer exists) |
| C32 | CONFIRMED | `app/Support/ProductDesigner.php:56,239-244 @8a3dceda` - `is_int($max) && $max > 0 ? $max : self::DEFAULT_SAMPLE_CHAT_MAX` (120) |
| C33 | CONFIRMED | `app/Http/Controllers/ProductController.php:541-543 @8a3dceda` - `if (in_array('sample_chat', $extras, true))` guards the prop |
| C34 | CONFIRMED | `resources/recipes/recipe-manifest.schema.json:469-473 @8a3dceda` - `type: integer`, `minimum: 10`, `maximum: 400` |
| C35 | CONFIRMED | `tests/Feature/ProductChatEmoteBubblesTest.php:271 @8a3dceda` `sample_chat_max` 300; `tests/Feature/ProductChatDesignerTest.php:181 @8a3dceda` `DEFAULT_SAMPLE_CHAT_MAX`; 14 and 16 passed |
| C36 | CONFIRMED | `tests/Feature/RecipeDesignerBlockTest.php:239-245 @8a3dceda` - extras `['chat_filters']` yields the `sample_chat_max` message; passes |
| C37 | CONFIRMED | `resources/js/pages/products/design.vue:548-566 @8a3dceda` - `rate > 0` sets 0; else `lastRate \|\| Math.max(10, Math.round(rateMax / 4 / 10) * 10)`; `setRate()` stores every non-zero rate in `lastRate` |

### Surface
Complete. 28 paths in the diff besides the claim and `docs/changelog/changelog-2026-11.md`; every one is listed, none listed is absent.

### Findings
- **F1** test narrower than claim - `tests/Unit/ControlConditionsTest.php @8a3dceda` is cited by C18 as covering C1-C7, but has no case for an empty or whitespace-only string inside an `enabled_if` list (C1) and no `=` comparison against a value outside the subject's choices (C6 asserts only `!=` at line 54); add those two cases or restate C18 in a new claim.
- **F2** test narrower than claim - C21's "a second run changes nothing" is backed by `tests/Feature/BubblesPlainLookBackfillTest.php:78-87 @8a3dceda`, which checks only the choice values and the `.look-plain` count after two runs; compare the full `look` config and CSS after run one and run two, or restate C21.
- **F3** test narrower than claim - C24's "values untouched" is backed by one assertion, `bubble_color` on the Bubbles install (`tests/Feature/EnabledIfBackfillTest.php:75 @8a3dceda`); no Twitch Chat control value is checked; assert every row's value on both installs, or restate C24.
- **F4** declared unchanged but modified - the Unchanged line "`ProductDesigner` builds the `controls` prop ... it is not in the diff" is false as written: `app/Support/ProductDesigner.php` is in the diff (`DEFAULT_SAMPLE_CHAT_MAX` at 56, `sampleChatMax()` at 239, both listed in Surface). `ProductDesigner::controls()` (line 359) itself has no hunk; a new claim should name that method rather than the class.
- **F5** contradiction with the record, uncited - OL-2609-066 C10 records that `ControlFormModal.buildPayload()` "sends `config: null` for a `color` control ... No config marker is stored for a color control". This change makes the dialog send back an edited color control's existing config (`ControlFormModal.vue:462 @8a3dceda`), and installs plus the backfill now store `enabled_if` on `bubble_color`, `bubble_color_2` and `background_color`. OL-2611-023 does not cite OL-2609-066; a follow-up claim should cite it inline as superseded.

### Notes
- Tests run, all passing: Pest `ControlConditionsTest`, `BubblesPlainLookBackfillTest`, `EnabledIfBackfillTest`, `RecipeDesignerBlockTest`, `ProductChatEmoteBubblesTest`, `ProductChatDesignerTest`, `BubblesGradientBackfillTest`, `ControlChoicesBackfillTest`; Vitest `controlConditions.test.ts` and `controlConfigPayload.test.ts` (13 passed).
- C6's `=` half is exercised end to end by `RecipeDesignerBlockTest:220` (`c:spawn = canon`), just not by the test C18 names.
- C30 is read distributively: each named key and each named type appears in at least one case; not every key-type pair is asserted.
- CLAUDE.md:913 still lists the designer block's keys without `sample_chat_max`; not a rule violation, a documentation gap.
- Both migrations use `DB::table()` only, per CLAUDE.md's migration rule.
