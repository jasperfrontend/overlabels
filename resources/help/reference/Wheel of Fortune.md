#InternalIntegrations #BotOutboxController #Controls #Alerts

## A new Internal Integration for Overlabels: the Wheel of Fortune

**Internal**, not External. The `ExternalServiceDriver` contract is shaped around parsing and verifying payloads that originate outside Overlabels (Ko-fi, StreamLabs, StreamElements, BMAC, Fourthwall). The Wheel has no outside payload, no credentials, no listener, no signature to verify. The spin originates inside Overlabels, the RNG runs inside Overlabels, the result is authoritative the moment it is computed. Forcing it through `ExternalServiceDriver` would mean stubbing `verifyRequest` to `return true` and `normalizeEvent` would just rewrap data we already had in process. That is the contract lying about what is happening.

Internally the name is `wheel`.

### Motivation

A lot of streamers reach for WheelofNames.com or similar when they want a quick wheel-spin segment, because it is trivial to paste a list and go. It is also incredibly limited: the wheel is a black box, you cannot wire its result into anything, and the visual is whatever the third party shipped. A first-class wheel inside Overlabels is a much richer source of data: the result feeds controls, fires alerts, runs through the chat bot via BotOutbox, and themes match the streamer's overlay aesthetic.

### What a Wheel of Fortune is, technically

- An ordered list of slots
- A server-side RNG that picks `winning_index` in `[0, slots.length - 1]`
- A broadcast carrying `(slots, winning_index, duration, easing, theme)` so the overlay can animate to a predetermined landing spot
- Fan-out: control updates, optional alert, optional chat message via BotOutbox

The visible spin is purely cosmetic. The result is decided before the wheel starts moving.

## Why "fake wheel" is the only correct design

Two reasons, both load-bearing:

1. **Overlays never phone home.** The day-one security invariant is that rendered overlays cannot send data back to the server. If the overlay rolled the RNG and reported the result, that breaks the invariant.
2. **Result must be set at spin-start, not spin-end.** Any alert template that reads `[[[c:wheel:result]]]` runs the moment the broadcast fires. If `result` only flips when the animation lands, every consumer desyncs from the visible wheel by the spin duration. Contract: **the result is authoritative at broadcast time, overlays merely take time to show it.**

## Architecture

### Models

- **`Wheel`** - per-user CRUD record. Holds wheel metadata, theme JSON, behavior settings (`remove_after_spin`, `reshuffle_on_empty`, `spin_duration`, `easing`, `chat_message_template`).
- **`WheelSlot`** - ordered list of options for a wheel. `wheel_id`, `sort_order`, `label`, optional `weight` (future), `consumed_at` (nullable, for `remove_after_spin` mode).
- **`WheelSpin`** - append-only audit log. `wheel_id`, `winning_index`, `winning_label`, `spun_at`, `triggered_by` (`dashboard` / `chat` / `automation`), `triggered_by_twitch_login` (nullable).

`WheelSpin` is cheap now and painful to retrofit later. It powers "last 10 winners" alert lookups, "show me every spin tonight", fairness debugging, and the eventual stats page.

### Service

`WheelService::spin(Wheel $wheel, string $triggeredBy, ?string $triggeredByLogin = null)`:

1. Validate that no spin is currently in flight for this wheel (concurrent-spin policy below).
2. Roll the RNG server-side, pick `winning_index`.
3. Persist a `WheelSpin` row.
4. Update controls via the existing control-write path (the same machinery `ExternalControlService::applyUpdates` uses, called directly, not via the External driver interface):
   - `c:wheel:result` = winning label
   - `c:wheel:result_at` = unix timestamp (per the timestamp contract, every control gets an automatic `_at` companion)
   - `c:wheel:active` = wheel id
   - `c:wheel:active_at`
   - `c:wheel:spinning` = `true`
   - `c:wheel:spinning_at`
   - `c:wheel:duration` = configured spin seconds
5. Broadcast a `WheelSpun` event on the user's existing private alerts channel (`alerts.{user_twitch_id}`) carrying:
   ```
   {
     "wheel_id": 42,
     "slots": ["Pizza", "Sushi", "Tacos"],
     "winning_index": 7,
     "duration": 5.0,
     "easing": "natural",
     "theme": { /* full theme JSON */ }
   }
   ```
6. Schedule a delayed job (`FinaliseWheelSpin`) that fires when the duration ends:
   - flips `c:wheel:spinning=false`
   - if `remove_after_spin`, marks the slot `consumed_at` and re-broadcasts the new slot list
   - inserts the chat-message row into `BotChatOutbox` if a template is configured

If `remove_after_spin` and all slots have been consumed, then on the *next* spin attempt: if `reshuffle_on_empty` is true, clear all `consumed_at` and re-spin; otherwise reject with a clear error.

### Triggers

- **Dashboard button** posts to an authenticated internal route -> `WheelService::spin($wheel, 'dashboard', $user->login)`.
- **`!spin` chat command** follows the existing bot-stays-dumb pattern: bot POSTs to an internal API, the controller calls `WheelService::spin($wheel, 'chat', $chatterLogin)`. Same shape as `!join` for Gamejam.
- **Automation** (future Flows) calls the service directly.

The bot does not roll the RNG, does not pick a slot, does not validate. It is a chat-shaped pipe.

## Tag namespace: controls vs components

Two distinct things travel under `[[[wheel:...]]]` and they must not be confused.

### Controls (string substitutions, parsed once at render time)

| Tag                           | Type      | Notes                                                                               |
|-------------------------------|-----------|-------------------------------------------------------------------------------------|
| `[[[c:wheel:result]]]`        | string    | Last winning label. Authoritative at broadcast time.                                |
| `[[[c:wheel:result_at]]]`     | timestamp | Unix seconds when the last spin was broadcast.                                      |
| `[[[c:wheel:active]]]`        | int       | Currently active wheel id. See "Active wheel" below.                                |
| `[[[c:wheel:spinning]]]`      | bool      | True between broadcast and animation end.                                           |
| `[[[c:wheel:duration]]]`      | number    | Configured spin duration for the active wheel, in seconds.                          |
| `[[[c:wheel:online]]]`        | bool      | True if the user has provisioned a wheel. For `[[[if:]]]` gating.                   |

Standard pipe formatters apply, e.g. `[[[c:wheel:result_at|date:HH:mm:ss]]]`.

### Components (Vue components rendered into the overlay)

A spinning wheel SVG is **not** a control value. It is a UI component. It needs its own namespace so the renderer knows to mount a Vue component, not substitute a string:

```
[[[component:wheel:<themename>]]]
```

`OverlayRenderer.vue` maps this to a `<WheelOfFortune>` component instance themed by `<themename>`. Slots come from the user's wheel record, fetched once on overlay mount via `OverlayTemplateController::show()` (same path already used to thread `connectedServices` through to props). Only the spin event rides on Reverb.

Omitting `themename` renders the wheel in a default presentable state.

### Example

```html
[[[if:c:wheel:online]]]
<div class="wheel wheel-[[[c:wheel:active]]]">
    <h2>Wheel of Fitness</h2>
    [[[if:c:wheel:spinning]]]
        [[[component:wheel:neon]]]
    [[[endif]]]
    <p>Last result: [[[c:wheel:result]]] at [[[c:wheel:result_at|date:HH:mm:ss]]]</p>
</div>
[[[endif]]]
```

## Alerts

Register `wheel_landed` as an alert event type in `EventTemplateMapping` so users can write:

```
[[[if:event.type = wheel_landed]]]
    The wheel landed on [[[c:wheel:result]]]!
[[[endif]]]
```

The alert fires from `FinaliseWheelSpin` (when the spin finishes), not at broadcast time. Two distinct moments:

- **Broadcast time**: controls are authoritative, overlay starts animating, anyone reading the result via control gets the right value.
- **Land time**: alert template fires, chat message hits BotOutbox, overlay animation finishes.

## BotOutbox integration

Per-wheel configurable message, stored on the `Wheel` model as `chat_message_template`. Tokens substituted server-side **before** the row is inserted into `bot_chat_outbox`:

```
And the winner is: {result}! Spun by @{spinner}.
```

Available tokens: `{result}`, `{spinner}`, `{wheel_name}`. Empty template = no chat message.

Template substitution happens in `WheelService` / `FinaliseWheelSpin`. **Do not** put `[[[c:...]]]` syntax in the chat message column - the bot does not parse template tags, it just speaks the string.

## Concurrency policy

While a spin is in flight (`c:wheel:spinning = true`), additional spin attempts are **rejected** server-side with a clear error. Most physical wheels behave this way and it sidesteps the queueing complexity entirely. The dashboard button greys out, `!spin` from chat replies "the wheel is already spinning". Revisit if streamers actually ask for queueing.

## Active wheel

Per-user, not per-overlay. Spinning a wheel auto-sets it as the user's active wheel (`c:wheel:active = <wheel_id>`). Overlays that want to display "the current wheel" read `c:wheel:active` and render the matching component. Streamers with multiple wheels can switch the active wheel from the dashboard without spinning, which sets `c:wheel:active` directly.

This keeps `[[[component:wheel:...]]]` simple: it always renders the user's currently active wheel.

## What needs to exist before users can use it

- **Page under "My stuff": Wheels** - dashboard for CRUD on wheels.
- **Wheel editor**:
  - Slots tab: add/edit/reorder/remove slot labels. Bulk paste (one per line) for parity with WheelofNames muscle memory.
  - Theming tab: colours, fonts, pointer style, centre cap (Lucide icon picker or Cloudinary upload), background.
  - Behavior tab: `remove_after_spin`, `reshuffle_on_empty`, spin duration, easing, tick / win sounds, chat message template.
- **Spin controls**: a Spin button on the dashboard, and the `!spin` chat command path through the bot.
- **Alert template hook**: `wheel_landed` registered so users can target it.
- **Theme sharing**: themes are JSON, copy/paste from the editor for sharing on socials or GitHub. Import accepts pasted JSON, validates, saves as a new wheel preset.

## Open questions to resolve before implementation

These are not blockers for design but they are blockers for code. None of them have an obvious right answer yet.

1. **Slot weights.** Equal-probability only at v1, or weighted from the start? Weighted is cheap to add now and impossible to retrofit cleanly into existing wheels later.
2. **Permissions for `!spin`.** Anyone in chat, mods only, or streamer-configurable per wheel?
3. **Cooldowns on `!spin`.** Per chatter? Per channel? None?
4. **Multiple wheels at once.** Hard no for v1 (one active wheel per user). Revisit only if streamers ask.
5. **Spin history visibility.** Is `WheelSpin` exposed via a control like `c:wheel:last_5_results` (string-joined) or only via a future stats page?
6. **Theme JSON schema versioning.** First imports happen on day one; a `version` field on the theme JSON now saves a migration script later.

## Build order suggestion

1. Models + migrations (`wheels`, `wheel_slots`, `wheel_spins`).
2. `WheelService::spin` + `FinaliseWheelSpin` job + control fan-out + Reverb broadcast. No UI yet, drive it from tinker.
3. Wheel CRUD pages and slot editor.
4. `WheelOfFortune.vue` component + `[[[component:wheel:<theme>]]]` renderer hook.
5. Theming UI + live preview.
6. `!spin` bot command path.
7. Alert template wiring (`wheel_landed`) + BotOutbox message templating.
8. Theme import/export, share, copy/paste.
