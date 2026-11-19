## OL-2611-024 - docs(claims): restate the enabled_if and backfill test coverage of OL-2611-023, and cite the color config it superseded

Remedies OL-2611-023 audit F1, F2, F3, F4, F5.

**Shipped:** 2026-11-19
**Commit:** `git log --grep=OL-2611-024`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-023/remedy.md` - new: the remedy of the OL-2611-023 audit

### Claims
- **C1** [test] `ControlConditionsTest` "reads one condition or a list, and nothing else" asserts `ControlConditions::of()` for a string, a list (trimmed), an empty list, a keyed array, a list holding a non-string, a config without `enabled_if`, and null; no case gives it a list holding an empty or whitespace-only string (corrects OL-2611-023 C18, audit F1).
- **C2** [test] `ControlConditionsTest` asserts the not-one-of-the-choices refusal for `!=` only, with `c:look != plian` (corrects OL-2611-023 C18, audit F1).
- **C3** [test] The `=` half of OL-2611-023 C6 is asserted by `RecipeDesignerBlockTest` "rejects an enabled_if that names a choice the control does not have", which writes `c:spawn = canon` into `bubbles.md` and expects the not-one-of-its-choices message for `spawn_x` and `spawn_y` (corrects OL-2611-023 C18, audit F1).
- **C4** [test] `BubblesPlainLookBackfillTest` "runs twice without adding Plain twice" asserts, after two runs, only that the `look` choice values are `bubble, snow, heart, plain` and that `.look-plain` occurs once in the overlay CSS; it does not compare the full `look` config or the CSS between the first run and the second (corrects OL-2611-023 C21, audit F2).
- **C5** [test] `EnabledIfBackfillTest` "gives installs from before exactly the declarations a fresh install has" asserts one control value after the backfill, the user's `bubble_color` row, set on the Emote Bubbles install; no Twitch Chat control value is asserted (corrects OL-2611-023 C24, audit F3).
- **C6** [code] In `app/Support/ProductDesigner.php`, the OL-2611-023 commit adds `DEFAULT_SAMPLE_CHAT_MAX` and `sampleChatMax()` and has no hunk in `ProductDesigner::controls()`, the method that builds the designer's `controls` prop (corrects OL-2611-023 Unchanged, audit F4).
- **C7** [code] `ControlFormModal.vue` `buildPayload()` sends `controlConfigPayload(t, form.value.config, props.control?.config ?? props.copyFrom?.config ?? null)` as `config` for a `color` control, which is that existing config minus `DIALOG_KEYS`, and `null` only when nothing is left; this supersedes OL-2609-066 C10's "sends `config: null` for a `color` control" (audit F5).
- **C8** [code] `bubbles.md` declares `enabled_if` on the color controls `bubble_color` and `bubble_color_2`, and `chat.md` on the color control `background_color`, so an install of either product stores a non-null config on a color control; this supersedes OL-2609-066 C10's "No config marker is stored for a color control" (audit F5).
