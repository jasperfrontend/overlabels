## Audit of OL-2609-030 - style(settings): the "On Twitch right now" block on /settings/title becomes a bordered card with icons

**Audited:** 2026-09-25
**Commit:** df0b433b25262bda1e73bf538d8a008dfb80eefc
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/pages/settings/Title.vue:218 @df0b433` - `v-if="channel && (channel.title \|\| channel.game_name)"` on the card; `:223 @df0b433` `<p v-if="channel.title">`, `:224 @df0b433` `<p v-if="channel.game_name">` (truthiness, so an empty string hides the line). Same lines @HEAD; `git log df0b433..HEAD -- resources/js/pages/settings/Title.vue` is empty |
| C2 | CONFIRMED | Structural half: `Title.vue:219-222 @df0b433` - the label is a bare text node inside its own `<div>` with the `Tv` icon, and the title is in a separate `<p>` at `:223`; the removed hunk had `<span>On Twitch right now:</span>` and `<span>{{ channel.title }}</span>` as sibling elements on separate lines. Causal half: `node_modules/@vue/compiler-core/dist/compiler-core.cjs.js:2920` removes a whitespace-only text node between two elements when `hasNewlineChar(node.content)` and whitespace is not `"preserve"`; `vite.config.mts` sets no `whitespace` option (grep for `whitespace` returns nothing) |
| C3 | CONFIRMED | `Title.vue:218 @df0b433` - card class is `border border-sidebar-border p-4`; no `bg-*`, gradient or background-image class on the card or its children (`:219-227 @df0b433`). Same @HEAD |

### Surface
Complete.

### Findings
None.

### Notes
- The replaced markup also dropped the visible "Category:" label text and the trailing colon on "On Twitch right now:" (removed hunk in `Title.vue @df0b433`); Surface's "the two label/value lines replaced by a bordered card ... the category on its own line with a `Gamepad2` icon" covers it, so it is not counted as scope.
- Unchanged line confirmed: the diff touches only `Title.vue` and the claim file; `LivingTitleController.php` is not in it, and the `channel: ChannelProps \| null` prop at `Title.vue:38 @df0b433` sits outside the changed hunks.
- No tests were run; all three claims are `[code]`.
