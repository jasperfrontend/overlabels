## OL-2609-030 - style(settings): the "On Twitch right now" block on /settings/title becomes a bordered card with icons

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-030`

### Surface
- `resources/js/pages/settings/Title.vue` - the two label/value lines replaced by a bordered card: uppercase label row with a `Tv` icon, the title as a `text-base font-medium` line, the category on its own line with a `Gamepad2` icon; the imports gain `Gamepad2` and `Tv`

### Claims
- **C1** [code] `Title.vue` renders the card only when `channel` is non-null and has a non-empty `title` or `game_name`, and each of the two value lines only when its value is non-empty.
- **C2** [code] The label text "On Twitch right now" and the value are no longer adjacent `<span>` siblings on separate lines, which is what rendered them without a space between (Vue's whitespace condensing drops a whitespace-only text node that contains a newline).
- **C3** [code] The card uses `border border-sidebar-border` and no gradient or background image.

### Unchanged
- `LivingTitleController::channel()` and the `channel` prop shape are not in the diff; only the markup that reads them changed.
