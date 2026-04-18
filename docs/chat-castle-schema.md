# Chat Castle - Data Model & Bot Contract

Implementation-side companion to the Chat Castle GDD (`chat_castle_gdd_v2.html`). This doc locks table shapes and the bot-to-Laravel action contract so the pieces can be built in isolation.

Scope: one game per Twitch channel at a time. Game state is server-authoritative; overlay and bot are both clients.

Last updated: 2026-04-18

---

## Design invariants

- **Votes are state, not events.** Each active joiner has a single `current_vote` field. Bot upserts it. Resolver reads standing votes at round close.
- **Resolution is a pure function.** `resolve(snapshot, winning_vote) -> (new_snapshot, event_log)`. No ambient randomness except the confined list below.
- **HP floor on leave is always 1.** A mass leave cannot kill; remainder damage is absorbed.
- **LoS is pure geometry.** No smell, no hearing, no random detection roll. Zombie sees player if no solid occluder lies on the segment.
- **Randomness is confined to four sources:** hidden tile contents (per tile), hiding-spot open sides (rolled once per room spawn), late-join zombie spawn roll, HP restore amount (1-5 per pickup). Everything else is deterministic.

---

## Tables

### `games`

One row per live game session. Soft-completed when `status` becomes `won` or `lost`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `twitch_channel` | string | Channel the game runs on (one active per channel) |
| `created_by_user_id` | FK `users` | Streamer owner |
| `status` | enum | `waiting`, `running`, `won`, `lost` |
| `current_room` | tinyint | 1-5 |
| `current_round` | int | Monotonic per game |
| `player_x` | tinyint | 1-9 |
| `player_y` | tinyint | 1-9 |
| `player_hp` | int | Floored at 1 on leaver damage; can hit 0 from combat |
| `player_hiding_this_round` | bool | Set on `!h` resolve, cleared at next round start |
| `weapon_slot_1` | enum | `fists`, `regular_sword` |
| `weapon_slot_2` | enum null | `de_sword` or null |
| `weapon_slot_1_uses` | int null | Uses remaining for `regular_sword` (null for fists) |
| `wears_iron_fists` | bool | Negates fist self-damage; true from room 4 onwards once picked up |
| `round_started_at` | timestamp null | When the current vote window opened |
| `round_window_ms` | int | Tunable via `c:vote_window_ms` (default 15000) |
| `threshold_pct` | tinyint | Tunable via `c:vote_threshold_pct` (default 60) |
| `created_at`, `updated_at` | timestamps | |

### `game_joiners`

Chat members participating in the game. Unique `(game_id, twitch_user_id)`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `game_id` | FK `games` | cascade on delete |
| `twitch_user_id` | string | External id |
| `username` | string | Display name snapshot |
| `status` | enum | `pending`, `active`, `inactive` |
| `joined_round` | int | Round at which `!join` was accepted |
| `current_vote` | string null | `p:up`, `p:down`, `p:left`, `p:right`, `h`, `a`, `a:1`, `a:2` |
| `last_vote_round` | int null | Round number the vote was last upserted |
| `blocks_remaining` | tinyint | 3 when fresh, decrements each unresolved round. 0 -> move to `inactive` |
| `hp_contributed` | bool | True while pending/active, false once refunded |
| `created_at`, `updated_at` | timestamps | |

Semantics:
- `pending`: joined this round. HP contributed immediately (+1 to `games.player_hp`). Vote does not count toward tally or threshold denominator this round.
- `active`: promoted at next resolve. Votes and counts toward denominator.
- `inactive`: blocks ran out. Not counted. On transition: `hp_contributed` -> false, `games.player_hp` decremented with `max(1, hp - 1)` floor.

### `game_zombies`

Living and dead zombies for the game. Query living via `died_at_round IS NULL`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `game_id` | FK `games` | cascade |
| `room` | tinyint | Room the zombie belongs to |
| `kind` | enum | `regular`, `weakling`, `boss` |
| `x`, `y` | tinyint | Current tile |
| `hp` | int | |
| `facing` | enum | `up`, `down`, `left`, `right`. Spawn default `right`. |
| `chasing` | bool | true = chasing player, false = drifting |
| `died_at_round` | int null | Set when hp hits 0 |
| `created_at`, `updated_at` | timestamps | |

### `game_hiding_spots`

Per-room. For rooms 1-2, `open_sides` is `[]`. For rooms 3-4, exactly two sides rolled once at room spawn.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `game_id` | FK `games` | cascade |
| `room` | tinyint | |
| `x`, `y` | tinyint | |
| `open_sides` | json | Array of `top`, `right`, `bottom`, `left`. Empty means fully enclosed. |

### `game_hidden_tiles`

Per-room. Includes scripted pickups (room 1 regular sword, room 3 DE-sword, room 4 Iron Fists) and random tiles.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `game_id` | FK `games` | cascade |
| `room` | tinyint | |
| `x`, `y` | tinyint | |
| `content` | enum | `bomb`, `hp_restore`, `zombie_spawn`, `regular_sword`, `de_sword`, `iron_fists` |
| `payload` | json null | e.g. `{"amount": 4}` for hp_restore, rolled at placement for replay determinism |
| `revealed_at_round` | int null | Null until walked past |

Random pool for generic hidden tiles is `{bomb, hp_restore, zombie_spawn}` only. Weapons/fists are never in the random pool — they are placed deterministically per room.

### `game_doors`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `game_id` | FK `games` | cascade |
| `room` | tinyint | |
| `x`, `y` | tinyint | |
| `state` | enum | `closed`, `opening`, `open` |
| `turns_remaining` | tinyint null | 2 (regular sword/fists) or 1 (DE-sword). Null when open. |

### `game_pillars` (room 5 only)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `game_id` | FK `games` | cascade |
| `x`, `y` | tinyint | Fixed: (2,2), (2,8), (8,2), (8,8) |

### `game_hp_pickups` (room 5 only)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `game_id` | FK `games` | cascade |
| `x`, `y` | tinyint | Corner tile |
| `amount` | tinyint | Fixed per spawn |
| `consumed_at_round` | int null | Set when player hides on this tile |

### `game_rounds`

Append-only history. No `updated_at`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `game_id` | FK `games` | cascade |
| `round_number` | int | |
| `room` | tinyint | |
| `winning_vote` | string null | Null if no vote landed (game ended before resolve) |
| `vote_counts` | json | `{"p:up": 5, "h": 3, ...}` |
| `winning_voters` | json | Array of twitch_user_ids — required for kill-cam / Wall of Shame |
| `events` | json | Event log: `[{"kind":"player_moved","from":[5,5],"to":[5,4]}, ...]` |
| `hp_delta` | int | Net HP change this round |
| `resolved_at` | timestamp | |

### Streamer tunables (existing `overlay_controls`)

No new table. Game provisions user-scoped controls on create:

- `c:vote_window_ms` (default 15000)
- `c:vote_threshold_pct` (default 60)
- `c:hp_restore_min` (default 1)
- `c:hp_restore_max` (default 5)
- `c:late_join_spawn_chance` (per-room defaults, TBD)

---

## Resolver order of operations

Per round:

1. Tally `current_vote` of all `active` joiners. Compute winner. Write `game_rounds` row.
2. Apply winning player action (`!p` / `!h` / `!a`): movement, bump/bounce, attack, weapon uses, door progress, hidden-tile reveal.
3. Move all living zombies (drift or chase based on LoS to player). Resolve zombie attacks on arrival.
4. Decrement `blocks_remaining` for all `active` joiners whose `last_vote_round < current_round`. Any hitting 0 -> `inactive`, refund HP with `max(1, hp - 1)` clamp.
5. Promote `pending` -> `active`. (Order matters: step 4 before step 5 so fresh joiners aren't swept.)
6. Evaluate end state: player hp <= 0 -> `lost`. Boss dead in room 5 -> `won`. Player on exit tile -> advance `current_room`, spawn new room entities.
7. Broadcast state diff via Reverb.
8. Open next vote window.

The `!p direction` -> closed-door special case: if target tile is a closed door, don't move; decrement `turns_remaining`; door opens when it hits 0.

---

## Bot <-> Laravel contract

### Ingress: game action

`POST /api/internal/bot/gamejam/action/{login}`

Auth: `X-Internal-Secret` header (existing internal bot auth).

Request body:
```json
{
  "twitch_user_id": "12345",
  "username": "Girly456",
  "action": "join" | "vote_move" | "vote_hide" | "vote_attack",
  "direction": "up" | "down" | "left" | "right" | null,
  "weapon_slot": 1 | 2 | null
}
```

Response (200):
```json
{
  "accepted": true,
  "reply": "joined the raid - HP now 14" | null
}
```

`reply` is the full message the bot should post in chat, un-prefixed. The bot adds `@username ` automatically. Null means stay silent.

Error responses:

| Status | Meaning | `reply` contract |
|---|---|---|
| 404 | No active game on this channel | null (bot stays silent) |
| 409 | Action not allowed right now (e.g. pending joiner trying to vote) | Short human message |
| 422 | Malformed action (shouldn't happen if bot parses correctly) | Short human message |

### Bot behaviour

- **`!join`**: always relays the reply (success or error). New joiners need confirmation.
- **`!p`, `!h`, `!a`**: silent on success. Reply only on error. Per-vote confirmations would flood chat; bulk round-resolution confirms come from a separate broadcast path.
- Bot does zero validation on direction, weapon slot, game state. Everything is Laravel-authoritative.

### Commands registry

For this to dispatch, the four commands must be registered in the channel's bot command table with `everyone` permission: `join`, `p`, `h`, `a`. The existing `fetchCommands` flow picks them up on `refresh()`.

---

## Open questions
1. Late-join zombie spawn chance per room: numeric defaults TBD. 
2. DE-sword durability: GDD says "does not break" - modeled as null `weapon_slot_1_uses` for DE-sword in slot 2. (Slot 2 uses column not needed until a second breakable weapon exists.)
3. Player exit tile per room: fixed coordinate or any edge tile? 
4. Broadcast payload shape: full snapshot vs. diff - start with full snapshot for simplicity, optimise later if bandwidth matters.

## Answers to open questions
1: Late-join zombie spawn chance per room:
- Room 1: 0%. There's no late join on room 1.
- Room 2: 2%. Slight chance, but not too much. Should be fun if it happens.
- Room 3: 20%. Room 3 is where you find the DE-sword. Put that sword to use on the potential zombie that spawns one 
  in five of the time.
- Room 4: 50%. Room 4 is where you find the Iron Fists. You now also possess the DE-sword, so you can use it to kill 
  the zombie that spawns 50% of times.
- Room 5: 0%. Room 5 is where you find the boss. No late join zombies spawn here.

2: DE-sword durability: it has endless durability, confirmed.

3: Player exit tile per room: Exit tiles are fixed on row 1, column 5 like this Zelda level [screenshot](https://cdn.mobygames.com/50df8784-ab83-11ed-81e3-02420a000199.webp). Do watch out: this Zelda level uses an event 
amount of tiles, so the door is divided over two tiles because there's no mid tile because they used 12 blocks. We 
use 11 blocks in our game, so the door is always on tile (1,5).

4: Broadcast payload shape: as suggested: start with full snapshot for simplicity. Should be fine.
