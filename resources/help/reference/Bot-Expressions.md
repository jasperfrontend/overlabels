#BotExpressions #Bot #Controls #Spec

## Bot Expressions — spec

User-defined chat commands whose response is a templated string that reads from the controls layer + Twitch Helix data + per-invocation bot context. The bot speaks the resolved string back into chat.

Originated in [issue #104](https://github.com/jasperfrontend/overlabels/issues/104). This document is the design spec.

## What a Bot Expression is

A Bot Expression is one row in a streamer's library, configured through a builder UI. It has:

- A **command** the chatter types (e.g. `!distance`).
- A **permission level** (`everyone` / `subscriber` / `vip` / `moderator` / `broadcaster`).
- A **cooldown** in seconds (per channel).
- An **expression template** that mixes literal text with `[[[c:...]]]`, bare Twitch tags, and `[[[bot:...]]]` substitutions.
- Optional **flags** (e.g. enabled / disabled, hidden from `!commands` listings).

When a chatter types the command:

1. Bot recognises the command, hits an internal API on Overlabels with chatter context.
2. Overlabels checks permission and cooldown.
3. If allowed, Overlabels resolves the expression template against current control values + Helix data + the bot context for this invocation.
4. The resolved string is written into `bot_chat_outbox`.
5. The bot polls, pulls the row, speaks it.

The bot itself remains dumb — it does not parse tags, does not know about controls, does not enforce permissions on expressions. Same architecture as today, just with a richer Overlabels-side step before the row hits the outbox.

## Syntax: colons inside brackets

Overlabels has two long-standing tag conventions, distinguished by whether the tag is bracketed:

| Context                       | Syntax                                  | Example                                    |
|-------------------------------|-----------------------------------------|--------------------------------------------|
| Bracketed (overlay templates) | colons for namespaces, dots for indices | `[[[c:gps:lat]]]`, `[[[followers_total]]]` |
| Bare (Expression Controls)    | dots with source prefix                 | `c.gps.lat`, `t.followers_total`           |

**Bot Expressions are bracketed, so they use the bracketed convention.** Same as overlay templates, plus one new namespace (`bot:`) for invocation context. The mental model is "Bot Expressions are overlay tags that live in chat output instead of HTML output."

Pattern recap inside brackets:

- **Colons** separate namespace levels: `c:goal_km` (single level), `c:gps:lat` (service-namespaced), `bot:from_user` (bot context).
- **Dots** access properties or indices: `event.choices.0.title` (existing precedent), `bot:args.0` (first arg token).
- **Bare names** are Twitch Helix data: `followers_total`, `channel_name`, etc.
- Pipe formatters work after `|`: `[[[c:gps:lat|number]]]`, `[[[c:overlabels-mobile:gps_distance|distance:mi]]]`.

This means authors copy-paste freely between overlay templates and Bot Expressions. The only Bot-Expression-specific addition is `bot:*`.

## Where it sits in the architecture

Bot Expressions are **consumers** of the controls layer. Together with Alerts and Overlay Templates, they form the read side of:

```
[Recipes]       ──writes──▶                ──reads──▶  [Bot Expressions]
[Twitch Helix]  ──writes──▶  [Controls]    ──reads──▶  [Alert Templates]
[Integrations]  ──writes──▶                ──reads──▶  [Overlay Templates]
```

Bot Expressions can ship before Recipes do. They are useful immediately on top of Helix + existing integration controls. Issue #104's `!distance` example uses GPS controls from the existing overlabels-mobile integration — no recipe machinery required.

## Anatomy of a Bot Expression row

Working list of fields. Names are illustrative; final naming is up to the migration.

- `id` — primary key.
- `user_id` — owning streamer.
- `command` — string, must start with `!`, unique per user, must not collide with a builtin in `BotCommand::DEFAULTS`.
- `permission_level` — enum, see below. Reuses `BotCommand::PERMISSION_LEVELS` exactly.
- `cooldown_seconds` — integer ≥ 0. 0 = no cooldown. Per-channel scope (matches existing bot pattern).
- `expression` — the template string.
- `enabled` — boolean.
- `hidden_from_commands` — boolean (whether `!commands` from the bot lists it).
- `last_fired_at` — timestamp, used for cooldown gates.
- timestamps.

No paired invocations table in v1. Per-user cooldown tracking is a v2 add when someone asks for it.

## The expression template

A string that mixes literal text with three tag families, all wrapped in `[[[...]]]`.

### `c:` — controls

Resolves to the current value of an `OverlayControl` owned by the streamer who configured the expression.

```
You have [[[c:overlabels-mobile:gps_distance|distance:mi]]] miles cycled today.
```

For namespaced service controls: `[[[c:kofi:donations_received]]]`.
For user-defined controls: `[[[c:goal_km]]]`.

Pipe formatters (`|distance:mi`, `|date:HH:mm`, `|number`) work the same as in overlay templates.

### Bare names — Twitch Helix

Live-resolved Helix data, sourced via `TemplateDataMapperService`. Same tag names as overlay templates (no prefix).

```
We're approaching [[[followers_total]]] followers!
```

Indexed access uses dots, matching existing overlay-template precedent: `[[[event.choices.0.title]]]`.

### `bot:` — per-invocation context

Scoped to the single command invocation. **Not** persisted as a control; computed at resolve time from the chatter's identity and the command they typed.

| Tag                             | Value                                                            |
|---------------------------------|------------------------------------------------------------------|
| `bot:from_user`                 | Display name of the chatter who fired the command.               |
| `bot:from_user_login`           | Lowercase login of the same.                                     |
| `bot:from_user_id`              | Twitch user id of the same.                                      |
| `bot:command`                   | The command string fired (e.g. `!distance`).                     |
| `bot:args`                      | Everything after the command (e.g. `!sr lambada` -> `lambada`).  |
| `bot:args.0`, `bot:args.1`, ... | Individual whitespace-split arg tokens, missing = empty.         |
| `bot:channel`                   | Channel login the command was fired in (= the streamer's login). |

The colon-then-dot pattern in `bot:args.0` matches the existing `event.choices.0.title` precedent: colons separate namespaces, dots index into structured data.

`bot:` tags resolve only inside Bot Expression evaluation. They do not exist in overlay templates or in Expression Controls.

## Permissions

Reuses `BotCommand::PERMISSION_LEVELS` exactly:

- `everyone` — any chatter.
- `subscriber` — subscribers and above.
- `vip` — VIPs and above.
- `moderator` — moderators and broadcaster.
- `broadcaster` — only the streamer.

Resolution uses Twitch's chat badge / role data on the incoming command. The bot includes the chatter's roles in the API call (it already has them via `event.hasBadge('...')` in `bot.js`); Overlabels uses them for the gate.

## Cooldowns

Per-channel only for v1. One shared timer per expression, gated by `last_fired_at` on the expression row. Matches the existing bot pattern (`bot.js` already uses per-channel cooldowns with broadcaster bypass).

Cooldowns are **enforced server-side**, in `BotExpressionService::canFire()`. The bot's existing per-channel cooldown logic in `bot.js` is bypassed for expression commands so there is a single source of truth and no double-cooldown confusion. Broadcaster bypass matches the existing builtin-command pattern.

A blocked invocation is **silently ignored**. The bot does not reply with "you're on cooldown" because cooldown messages are noise and abusable for chat spam. (If a streamer later wants explicit cooldown feedback, that's a separate per-expression flag.)

Per-user cooldown is deferred to v2. When demand surfaces, add a paired `bot_expression_invocations` table; the schema change is non-breaking.

## Validation

The builder UI saves the row only if all referenced controls and Helix tags exist. This stops the "I typed the wrong tag and the bot says undefined in chat" failure mode.

Validation rules:

- All `c:<key>` and `c:<service>:<key>` references must resolve to a real `OverlayControl` owned by the user. If `overlabels-mobile` isn't connected, the form refuses to save and explains why.
- All bare-name references (Twitch tags) must be a known tag from `TemplateDataMapperService`'s registry.
- All `bot:<key>` references must be in the closed enum above (with `bot:args.N` indices accepted for any non-negative integer N).
- Pipe formatters must be valid.
- Command must match `/^![a-zA-Z0-9_-]{1,30}$/`, unique per user, must not collide with a builtin in `BotCommand::DEFAULTS`.
- Expression must resolve to ≤ 500 characters of plain text after substitution (stay under Twitch's chat message cap with headroom).

The validation step runs the same regex-based resolver in dry-run mode against a stub data context.

## Sync to the bot

The bot already polls Overlabels for outbox messages and for its `commandMap` via `GET /internal/bot/commands` (handled by `BotCommandController::index`). The bot's dispatch loop in `bot.js` looks up commands in `commandMap` and dispatches to either a builtin handler or a registered handler.

For Bot Expressions, the sync endpoint signals "this command is an expression, not a builtin", so the bot knows to call the fire endpoint instead of looking up a JS handler.

**Implementation: separate table, merged endpoint.** A new `bot_expressions` table holds user-authored content. `BotCommandController::index` joins both `bot_commands` and `bot_expressions` and emits a unified map with a `type` field (`builtin` | `expression`). Pull transport, 30s polling cadence — Reverb push is overkill for command-list changes.

## The flow, end to end

```
chatter types "!distance"
          │
          ▼
  bot recognises command (from synced commandMap)
  commandMap entry: { command: "distance", type: "expression", permission_level: "everyone" }
          │
          ▼
  bot checks permission via event.hasBadge(...)
          │
          ▼
  bot POSTs to /api/internal/bot/expressions/fire
    { channel_login, command, chatter_id, chatter_login,
      chatter_display_name, badges, args }
          │
          ▼
  Overlabels:
    - look up expression by (channel, command)
    - check enabled flag
    - check cooldown via last_fired_at + cooldown_seconds (broadcaster bypass)
    - if any check fails: 200 OK, no outbox row. Bot stays silent.
    - resolve expression template against
      current controls + Helix + bot context
    - INSERT into bot_chat_outbox
    - UPDATE bot_expressions.last_fired_at = now()
          │
          ▼
  bot's next outbox poll picks up the row
          │
          ▼
  bot speaks the resolved string in chat
```

## Sharing and forking

Bot Expressions are normal user-owned objects. Sharing follows the same rail as overlays and kits:

- Each expression has a slug.
- Public expressions can be copied by other users ("Copy", never "Fork", per the naming rule).
- A copy creates a new row owned by the copier. Validation runs against the copier's connected integrations; if a referenced control doesn't exist, the copy lands but is `enabled=false` until the user connects what's missing (mirrors the existing overlay-fork permissive UX).
- Eventually: a Kit can ship Bot Expressions as defaults at install time.

## What this is not

- **Not arbitrary scripting.** No conditionals, no loops, no math, no string manipulation. Just substitution. If a streamer needs logic, that's an Expression Control (writing into a control) or a Recipe (producer side) which the Bot Expression then reads.
- **Not a bot framework.** No multi-message responses, no follow-up state, no stored conversations. One command in, one message out.
- **Not in scope for first ship: outbound proactive messages.** "When follower count hits 100, post a message" is *not* a Bot Expression. It's a future Alert-side or Recipe-side feature that happens to use the same outbox table.

## Build order

1. **Migration**: create `bot_expressions` table.
2. **`BotExpressionResolver` service** — pure regex substitution, three resolution branches (`c:` -> `OverlayControl`, bare -> `TemplateDataMapperService`, `bot:` -> invocation context). Unit-testable in isolation.
3. **`BotExpressionService::resolve()` and `canFire()`** — orchestrator: permission + cooldown checks, calls the resolver, writes outbox row, updates `last_fired_at`.
4. **Internal API**: `BotExpressionController::fire` (POST). Extend `BotCommandController::index` to merge `bot_expressions` rows into the commandMap with `type: "expression"`. Both behind `bot.internal` middleware + `throttle:bot-internal`.
5. **Pest tests**: feature tests for the fire endpoint and the merged sync endpoint, unit tests for the resolver. Mirror `BotInternalApiTest.php` patterns for auth + payload shape.
6. **Builder UI**: Inertia page under `settings/bot/expressions`. List view, create/edit form, live-preview pane that resolves against current controls + a stub bot context.
7. **Bot wiring**: extend `bot.js` to recognise `type: "expression"` entries in commandMap and POST to the fire endpoint instead of dispatching to a JS handler. Update commandMap shape via the sync endpoint changes from step 4.
8. **Documentation**: per-tag reference page (auto-generated from `TemplateDataMapperService` for bare tags + the user's `OverlayControl` rows for `c:` tags) so users know what is available given their connected integrations.

## Locked-in decisions (recorded so future-me doesn't re-litigate)

- **Bracketed colon syntax**, matching overlay templates. `c:foo:bar`, bare Twitch tags, `bot:from_user`. No `t:` namespace.
- **Two tables**: `bot_expressions` separate from `bot_commands`.
- **Per-channel cooldowns only** for v1. No invocations table.
- **Args supported**: `bot:args` for the whole tail, `bot:args.0`/`.1`/... for tokens.
- **Builtin always wins on conflict**, validator refuses on save.
- **Server-side cooldown**, bot-side cooldown bypassed for expression commands.

## Open questions

1. **Fallback values.** `[[[c:overlabels-mobile:gps_distance]]]` when GPS is offline returns null. Per the null-over-placeholder rule, the rendered chat message has a blank in the middle. Acceptable for v1, or do we add a per-tag fallback syntax like `[[[c:foo|fallback:'N/A']]]`? Probably acceptable; users who care can wire an Expression Control that provides a default.
2. **Anti-spam at the channel level.** If 50 chatters fire `!distance` in a second, even with per-channel cooldown the resolver writes outbox rows for the first one only — but if multiple expressions exist on the same channel, total outbox writes can spike. Worth a global "max N messages per minute per channel" cap on `bot_chat_outbox` itself, not per expression. Belongs in BotChatOutbox policy, flagged here so it isn't forgotten.
3. **`!commands` listing.** Should the bot expose a `!commands` meta-command listing all enabled-and-not-hidden Bot Expressions? Easy to add, valuable for chat onboarding. v2.
4. **Live-preview data source for the builder.** When composing `[[[c:overlabels-mobile:gps_distance]]]`, the form should resolve it live against current `OverlayControl` values. `bot:from_user` stubs as `@you` or similar. The autocomplete UX is most of what makes the feature usable; worth designing before building the form.

## What needs to be true elsewhere before this ships

- **PHP-side template resolver is net-new.** All existing tag substitution happens client-side in `OverlayRenderer.vue` / `tagParser.ts`. Bot Expressions resolve server-side before writing to outbox, so we build a small dedicated resolver. Same regex shape (`/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?]]]/`) but server-side, dispatching on the prefix character (`c:` / bare / `bot:`).
- **`HtmlSanitizationService` is not used here.** Bot Expressions resolve to plain text destined for Twitch chat. No HTML in, no HTML out.
- **Bot polling cadence is reused.** The `commandMap` poll already exists (`GET /internal/bot/commands`); we extend its payload shape rather than adding a new endpoint.
- **Permission levels match `BotCommand::PERMISSION_LEVELS` exactly.** No new enum.

## Why this is the right next-shippable

- Reuses ~80% of the existing bot infrastructure: `bot.internal` middleware, `commandMap` polling, `bot_chat_outbox` writes, the `BotControlController` pattern, the existing `BotCommand` permission enum.
- Net-new code is small: one resolver service, one orchestrator service, one controller, one Inertia page, one shape extension on the existing sync endpoint, one bot.js dispatch branch.
- Immediate user value the day it ships: every connected integration's controls become bot-readable.
- Builds the consumer-side machinery (parser invocation in chat-command context, permission gates, cooldowns) that future features (Alerts subscribing to recipe events, Recipe triggers) will lean on.
- Independently useful even if Recipes never ship.

That's the point of building it first.
