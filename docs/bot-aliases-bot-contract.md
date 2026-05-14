# Bot Aliases: bot-side dispatch contract

Backend ships the `bot_aliases` table and routes 2026-05-14. The bot repo
(`overlabels-bot`) needs the matching dispatch code before aliases actually fire.
This doc is the contract the bot needs to honour - nothing here lives in this repo.

## What changed in the command map

`GET /api/internal/bot/commands` now emits a new entry type:

```json
{
  "command": "w",
  "permission_level": "moderator",
  "type": "alias",
  "target_template": "increment wins {1}"
}
```

`target_template` is unique to aliases. It's the rewritten command, **without
a leading `!`**, with positional placeholders:

- `{1}`, `{2}`, ... `{N}` - replaced with the chatter's Nth arg (1-indexed by
  whitespace split). Missing args substitute to empty string.
- `{*}` - replaced with every remaining arg from the highest-numbered placeholder
  onward, space-joined. If only `{*}` is present, it's all args.

Resolution priority for collisions: `builtin > expression > alias > recipe_trigger > list_append > list_meta`.
Backend validation prevents collisions at save time, but the order keeps the bot
deterministic if a stale row sneaks in.

## What the bot needs to do on `type: "alias"`

When a chat message matches a command of type `alias`:

1. **Enforce the alias's own permission and cooldown** (same logic the bot
   already runs for built-ins). Silent-on-block - no chat reply if the chatter
   isn't allowed or the alias is on cooldown.
2. **Substitute placeholders into `target_template`**:
   - Split the chatter's message body into args by whitespace.
   - Replace each `{N}` with `args[N-1]` (empty if absent).
   - Replace `{*}` with `args.slice(highestN).join(' ')` where `highestN` is the
     max `{N}` used in the template (0 if none, so `{*}` alone = all args).
3. **Re-dispatch the rewritten command** through the bot's normal routing,
   preserving the **original chatter context** (chatter_id, login, badges, etc.).
   The rewritten command goes through the same builtin/expression/recipe_trigger/etc.
   resolution as if the chatter typed it directly. The target's own
   `permission_level` therefore still gates - a VIP-permitted alias that rewrites
   to `!increment` will be denied at the second hop because `!increment` is
   moderator-only, and the chatter context still carries the same badges.

## Hard rules - don't bend these

- **One hop only.** If the rewritten command's first token is itself an alias,
  drop the dispatch. Don't recurse, don't loop. The backend already refuses to
  save an alias whose target is another alias for the same user, but defence in
  depth: bot should also refuse to follow alias->alias chains. Stale map data is
  the realistic failure mode.
- **No call back to Laravel for rewrite.** The substitution happens entirely
  bot-side using `target_template` from the command map. Backend doesn't expose
  a fire/expand endpoint for aliases; the round-trip would burn a request per
  alias use for no benefit.
- **Preserve chatter context across the hop.** The rewrite changes the *command*,
  not the *who*. Without this, badge-based permission checks on the target
  become meaningless.

## Example flows

### `!w 2` -> `!increment wins 2`

- Alias: `command: "w"`, `target_template: "increment wins {1}"`, `permission_level: "moderator"`
- Chatter is a mod, types `!w 2`
- Bot sees `type: "alias"`, mod check passes, cooldown check passes
- Substitute: `{1}` -> `"2"` -> `"increment wins 2"`
- Re-dispatch `!increment wins 2` with same chatter context
- `!increment` is a builtin (moderator), passes, increments the `wins` control

### `!shout hello world` -> `!echo says hello world` (using `{*}`)

- Alias: `command: "shout"`, `target_template: "echo says {*}"`
- Chatter types `!shout hello world`
- args = `["hello", "world"]`, no `{N}` used so highestN = 0
- `{*}` -> `"hello world"` -> `"echo says hello world"`

### `!gift @alice @bob` -> `!give @alice from @bob`

- Alias: `command: "gift"`, `target_template: "give {1} from {2}"`
- `{1}` -> `"@alice"`, `{2}` -> `"@bob"` -> `"give @alice from @bob"`

### Block: chain rejected at save time

The backend refuses to save an alias whose `target_template` starts with the
name of another alias the user already has. So if `!w -> increment wins {1}`
exists, an alias `!ww -> w {1}` is rejected with a validation error pointing at
`target_template`. Bot should additionally drop any chain that does sneak
through (one-hop rule above).

## Optional: last_fired_at telemetry

The `bot_aliases.last_fired_at` column exists but is currently unused. If/when
the bot wants to report fires back, the contract would be a fire-and-forget
`POST /api/internal/bot/aliases/{alias_id}/touch` - not built yet. The settings
UI already renders "Last fired: never" without it; populating is a v2 nicety.
