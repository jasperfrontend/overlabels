<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface GamePayload {
  id: number;
  status: 'waiting' | 'running' | 'won' | 'lost';
  current_round: number;
  current_room: number;
  player_hp: number;
  player_x: number | null;
  player_y: number | null;
  player_hiding_this_round: boolean;
  weapon_slot_1: string;
  weapon_slot_2: string | null;
  weapon_slot_1_uses: number | null;
  wears_iron_fists: boolean;
  round_duration_seconds: number;
  round_started_at: string | null;
  last_resolved_action: string | null;
  last_resolved_tally: Record<string, number> | null;
  last_resolved_at: string | null;
}

interface JoinerPayload {
  twitch_user_id: string;
  username: string;
  status: 'pending' | 'active' | 'inactive';
  joined_round: number;
  current_vote: string | null;
  last_vote_round: number | null;
  blocks_remaining: number;
}

interface HiddenTilePayload {
  x: number;
  y: number;
  content: string | null;
  revealed_at_round: number | null;
}

interface DoorPayload {
  x: number;
  y: number;
  state: 'closed' | 'opening' | 'open';
  turns_remaining: number | null;
}

interface HidingSpotPayload {
  x: number;
  y: number;
  open_sides: string[];
}

interface WorldPayload {
  hidden_tiles: HiddenTilePayload[];
  doors: DoorPayload[];
  hiding_spots: HidingSpotPayload[];
}

interface Snapshot {
  game: GamePayload;
  joiners: JoinerPayload[];
  world: WorldPayload;
}

const props = defineProps<{
  broadcasterId: string;
  broadcasterLogin: string;
  snapshot: Snapshot | null;
}>();

const emptyWorld: WorldPayload = { hidden_tiles: [], doors: [], hiding_spots: [] };

const game = ref<GamePayload | null>(props.snapshot?.game ?? null);
const joiners = ref<JoinerPayload[]>(props.snapshot?.joiners ?? []);
const world = ref<WorldPayload>(props.snapshot?.world ?? emptyWorld);
const connected = ref(false);
const now = ref(Date.now());
const attackFlashTiles = ref<Set<string>>(new Set());

let channel: any = null;
let tickInterval: ReturnType<typeof setInterval> | null = null;
let attackFlashTimeout: ReturnType<typeof setTimeout> | null = null;
let lastFlashedResolvedAt: string | null = props.snapshot?.game?.last_resolved_at ?? null;

const ATTACK_FLASH_MS = 900;

const GRID_SIZE = 9;
const rows = Array.from({ length: GRID_SIZE }, (_, i) => i + 1);
const cols = Array.from({ length: GRID_SIZE }, (_, i) => i + 1);

const secondsUntilNextTick = computed(() => {
  if (!game.value?.round_started_at || game.value.status !== 'running') return null;
  const started = new Date(game.value.round_started_at).getTime();
  const deadline = started + game.value.round_duration_seconds * 1000;
  return Math.max(0, Math.ceil((deadline - now.value) / 1000));
});

const lastResolvedTallyEntries = computed(() => {
  const tally = game.value?.last_resolved_tally;
  if (!tally) return [];
  return Object.entries(tally).sort((a, b) => b[1] - a[1]);
});

const grouped = computed(() => ({
  active: joiners.value.filter((j) => j.status === 'active'),
  pending: joiners.value.filter((j) => j.status === 'pending'),
  inactive: joiners.value.filter((j) => j.status === 'inactive'),
}));

function readableVote(vote: string | null): string {
  if (!vote) return '-';
  if (vote === 'h') return 'hide';
  if (vote === 'a') return 'attack';
  if (vote.startsWith('a:')) return `attack slot ${vote.slice(2)}`;
  if (vote.startsWith('p:')) {
    const arrows: Record<string, string> = { left: '←', right: '→', up: '↑', down: '↓' };
    const parts = vote.slice(2).split(':');
    const dir = parts[0];
    const steps = parts[1] ? parseInt(parts[1], 10) : 1;
    const base = `${arrows[dir] ?? ''} ${dir}`.trim();
    return steps > 1 ? `${base} x${steps}` : base;
  }
  return vote;
}

function tileAt(x: number, y: number) {
  const player =
    game.value && game.value.player_x === x && game.value.player_y === y ? game.value : null;
  const door = world.value.doors.find((d) => d.x === x && d.y === y) ?? null;
  const hidingSpot = world.value.hiding_spots.find((s) => s.x === x && s.y === y) ?? null;
  const hiddenTile = world.value.hidden_tiles.find((t) => t.x === x && t.y === y) ?? null;
  return { player, door, hidingSpot, hiddenTile };
}

function tileGlyph(x: number, y: number): string {
  const { player, door, hiddenTile } = tileAt(x, y);
  if (player) return 'P';
  if (door) {
    if (door.state === 'open') return 'D.';
    if (door.state === 'opening') return 'D-';
    return 'D';
  }
  if (hiddenTile) {
    if (hiddenTile.revealed_at_round === null) return '?';
    switch (hiddenTile.content) {
      case 'regular_sword':
        return 'sw';
      case 'de_sword':
        return 'de';
      case 'iron_fists':
        return 'if';
      case 'bomb':
        return 'b';
      case 'hp_restore':
        return '+';
      case 'zombie_spawn':
        return 'z';
      default:
        return '.';
    }
  }
  return '';
}

function tileClasses(x: number, y: number): string[] {
  const { player, door, hidingSpot, hiddenTile } = tileAt(x, y);
  const classes: string[] = [];
  if (player) classes.push('tile-player');
  if (door) classes.push(`tile-door tile-door-${door.state}`);
  if (hidingSpot) classes.push('tile-hiding');
  if (hiddenTile) {
    classes.push(hiddenTile.revealed_at_round === null ? 'tile-hidden' : 'tile-revealed');
  }
  if (attackFlashTiles.value.has(`${x},${y}`)) classes.push('tile-attack-flash');
  return classes;
}

function triggerAttackFlash(px: number, py: number) {
  const tiles = new Set<string>();
  for (let dx = -1; dx <= 1; dx++) {
    for (let dy = -1; dy <= 1; dy++) {
      if (dx === 0 && dy === 0) continue;
      const tx = px + dx;
      const ty = py + dy;
      if (tx < 1 || tx > GRID_SIZE || ty < 1 || ty > GRID_SIZE) continue;
      tiles.add(`${tx},${ty}`);
    }
  }

  if (attackFlashTimeout) {
    clearTimeout(attackFlashTimeout);
    attackFlashTimeout = null;
  }
  attackFlashTiles.value = new Set();

  requestAnimationFrame(() => {
    attackFlashTiles.value = tiles;
    attackFlashTimeout = setTimeout(() => {
      attackFlashTiles.value = new Set();
      attackFlashTimeout = null;
    }, ATTACK_FLASH_MS);
  });
}

function maybeFlashAttack(g: GamePayload) {
  const action = g.last_resolved_action;
  if (action !== 'a' && !action?.startsWith('a:')) return;
  if (!g.last_resolved_at || g.last_resolved_at === lastFlashedResolvedAt) return;
  if (g.player_x === null || g.player_y === null) return;
  lastFlashedResolvedAt = g.last_resolved_at;
  triggerAttackFlash(g.player_x, g.player_y);
}

onMounted(() => {
  document.documentElement.classList.add('gamejam-fullbleed');
  tickInterval = setInterval(() => (now.value = Date.now()), 250);

  const echo = (window as any).Echo;
  if (!echo) return;

  const conn = echo.connector?.pusher?.connection;
  if (conn) {
    connected.value = conn.state === 'connected';
    conn.bind('connected', () => (connected.value = true));
    conn.bind('disconnected', () => (connected.value = false));
  }

  channel = echo.channel(`gamejam.${props.broadcasterId}`);
  channel.listen('.gamejam.state', (payload: Snapshot & { updated_at: number }) => {
    game.value = payload.game;
    joiners.value = payload.joiners;
    world.value = payload.world ?? emptyWorld;
    maybeFlashAttack(payload.game);
  });
});

onUnmounted(() => {
  document.documentElement.classList.remove('gamejam-fullbleed');
  if (tickInterval) clearInterval(tickInterval);
  if (attackFlashTimeout) clearTimeout(attackFlashTimeout);
  if (channel) channel.stopListening('.gamejam.state');
});
</script>

<template>
  <div class="live-board">
    <aside class="sidebar">
      <header class="side-header">
        <div class="title">
          <h1>Chat Castle</h1>
          <span class="login">@{{ broadcasterLogin }}</span>
        </div>
        <div class="conn" :class="{ on: connected }">
          <span class="dot"></span>
          {{ connected ? 'live' : 'offline' }}
        </div>
      </header>

      <section v-if="game" class="stats-row">
        <div class="stat">
          <span class="label">Status</span>
          <span class="value status" :class="`status-${game.status}`">{{ game.status }}</span>
        </div>
        <div class="stat">
          <span class="label">Room</span>
          <span class="value">{{ game.current_room }}</span>
        </div>
        <div class="stat">
          <span class="label">Round</span>
          <span class="value">{{ game.current_round }}</span>
        </div>
        <div class="stat">
          <span class="label">HP</span>
          <span class="value hp">{{ game.player_hp }}</span>
        </div>
        <div class="stat">
          <span class="label">Joiners</span>
          <span class="value">{{ joiners.length }}</span>
        </div>
      </section>

      <section v-if="game" class="weapons-row">
        <div class="weapon">
          <span class="label">Slot 1</span>
          <span class="value">
            {{ game.weapon_slot_1 }}
            <small v-if="game.weapon_slot_1_uses !== null">({{ game.weapon_slot_1_uses }})</small>
          </span>
        </div>
        <div class="weapon">
          <span class="label">Slot 2</span>
          <span class="value">{{ game.weapon_slot_2 ?? '-' }}</span>
        </div>
        <div class="weapon">
          <span class="label">Iron Fists</span>
          <span class="value">{{ game.wears_iron_fists ? 'yes' : 'no' }}</span>
        </div>
      </section>

      <section v-if="game" class="resolver-row">
        <div class="resolver-card countdown">
          <span class="label">Next tick</span>
          <span class="value" :class="{ urgent: (secondsUntilNextTick ?? 99) < 5 }">
            {{ secondsUntilNextTick !== null ? `${secondsUntilNextTick}s` : '-' }}
          </span>
          <span class="sub">round of {{ game.round_duration_seconds }}s</span>
        </div>
        <div class="resolver-card resolved">
          <span class="label">Last resolved</span>
          <span class="value">{{
            game.last_resolved_action ? readableVote(game.last_resolved_action) : 'nothing yet'
          }}</span>
          <div v-if="lastResolvedTallyEntries.length" class="tally">
            <span
              v-for="[action, count] in lastResolvedTallyEntries"
              :key="action"
              class="tally-entry"
            >
              {{ readableVote(action) }}: <b>{{ count }}</b>
            </span>
          </div>
          <span v-else class="sub">no votes cast</span>
        </div>
      </section>

      <section v-if="game" class="joiners-col">
        <div class="joiners-group">
          <h2>Active <span class="count">{{ grouped.active.length }}</span></h2>
          <ul>
            <li v-for="j in grouped.active" :key="j.twitch_user_id" class="joiner">
              <div class="name">{{ j.username }}</div>
              <div class="vote">{{ readableVote(j.current_vote) }}</div>
              <div class="meta">
                <span>r{{ j.joined_round }}</span>
                <span>blocks: {{ j.blocks_remaining }}</span>
                <span v-if="j.last_vote_round">last: r{{ j.last_vote_round }}</span>
              </div>
            </li>
            <li v-if="!grouped.active.length" class="placeholder">no active voters</li>
          </ul>
        </div>

        <div class="joiners-group">
          <h2>Pending <span class="count">{{ grouped.pending.length }}</span></h2>
          <ul>
            <li v-for="j in grouped.pending" :key="j.twitch_user_id" class="joiner">
              <div class="name">{{ j.username }}</div>
              <div class="vote dim">joined r{{ j.joined_round }}</div>
            </li>
            <li v-if="!grouped.pending.length" class="placeholder">nobody waiting</li>
          </ul>
        </div>

        <div class="joiners-group">
          <h2>Inactive <span class="count">{{ grouped.inactive.length }}</span></h2>
          <ul>
            <li v-for="j in grouped.inactive" :key="j.twitch_user_id" class="joiner dim">
              <div class="name">{{ j.username }}</div>
              <div class="vote">left at r{{ j.last_vote_round ?? j.joined_round }}</div>
            </li>
            <li v-if="!grouped.inactive.length" class="placeholder">none</li>
          </ul>
        </div>
      </section>

      <div v-else class="empty-state">
        No active game. Run
        <code>php artisan gamejam:start {{ broadcasterLogin }}</code>
      </div>
    </aside>

    <main class="grid-area">
      <div v-if="game" class="grid" :style="{ '--tile': '120px' }">
        <div v-for="y in rows" :key="`row-${y}`" class="grid-row">
          <div
            v-for="x in cols"
            :key="`${x}-${y}`"
            class="tile"
            :class="tileClasses(x, y)"
            :data-x="x"
            :data-y="y"
          >
            <span class="glyph">{{ tileGlyph(x, y) }}</span>
            <span class="coords">{{ x }},{{ y }}</span>
          </div>
        </div>
      </div>
      <div v-else class="grid-empty">Waiting for a game to start...</div>
    </main>
  </div>
</template>

<style scoped>
.live-board {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1080px;
  background: #0e0e10;
  color: #eee;
  font-family: system-ui, sans-serif;
}

.sidebar {
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  overflow-y: auto;
  max-height: 100vh;
}

.side-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.title h1 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
}
.title .login {
  color: #888;
  font-size: 0.9rem;
}
.conn {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  color: #888;
  font-size: 0.8rem;
}
.conn .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #555;
}
.conn.on .dot {
  background: #2a9d90;
  box-shadow: 0 0 8px #2a9d90;
}

.stats-row,
.weapons-row {
  display: flex;
  gap: 1rem;
  background: #1a1a1a;
  padding: 0.75rem 1rem;
  border-radius: 6px;
}
.stat,
.weapon {
  display: flex;
  flex-direction: column;
  min-width: 60px;
}
.stat .label,
.weapon .label {
  font-size: 0.7rem;
  text-transform: uppercase;
  color: #888;
  letter-spacing: 0.05em;
}
.stat .value,
.weapon .value {
  font-size: 1.25rem;
  font-weight: 700;
}
.weapon .value small {
  font-size: 0.75rem;
  color: #888;
  font-weight: 400;
}
.stat .value.hp {
  color: #ff5a5a;
}
.stat .status {
  font-size: 0.9rem;
  padding: 0.1rem 0.5rem;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  align-self: flex-start;
}
.status-running { background: #2a9d90; color: #fff; }
.status-waiting { background: #b0823d; color: #fff; }
.status-won { background: #4f8ef7; color: #fff; }
.status-lost { background: #7a2b2b; color: #fff; }

.resolver-row {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.75rem;
}
.resolver-card {
  background: #1a1a1a;
  border-radius: 6px;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.resolver-card .label {
  font-size: 0.7rem;
  text-transform: uppercase;
  color: #888;
  letter-spacing: 0.05em;
}
.resolver-card .value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #2a9d90;
}
.resolver-card.countdown .value {
  font-variant-numeric: tabular-nums;
  color: #4f8ef7;
}
.resolver-card.countdown .value.urgent {
  color: #ff5a5a;
}
.resolver-card .sub {
  font-size: 0.75rem;
  color: #666;
}
.tally {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem 0.9rem;
  margin-top: 0.2rem;
  font-size: 0.8rem;
  color: #bbb;
}
.tally-entry b { color: #2a9d90; }

.joiners-col {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  flex: 1;
  min-height: 0;
}
.joiners-group {
  background: #1a1a1a;
  border-radius: 6px;
  padding: 0.75rem 1rem;
}
.joiners-group h2 {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #aaa;
  margin: 0 0 0.5rem;
  display: flex;
  justify-content: space-between;
}
.joiners-group h2 .count { color: #2a9d90; font-weight: 700; }
.joiners-group ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.joiner {
  background: #242424;
  padding: 0.5rem 0.7rem;
  border-radius: 4px;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.joiner.dim { opacity: 0.5; }
.joiner .name { font-weight: 600; font-size: 0.9rem; }
.joiner .vote { font-size: 0.85rem; color: #2a9d90; }
.joiner .vote.dim { color: #888; }
.joiner .meta {
  display: flex;
  gap: 0.7rem;
  font-size: 0.7rem;
  color: #888;
}
.placeholder {
  color: #555;
  font-style: italic;
  font-size: 0.8rem;
}
.empty-state {
  color: #888;
  font-size: 0.9rem;
  padding: 1rem;
  background: #1a1a1a;
  border-radius: 6px;
}
.empty-state code {
  background: #0e0e10;
  padding: 0.1rem 0.4rem;
  border-radius: 3px;
  color: #ccc;
}

.grid-area {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  background: #0a0a0c;
  border-left: 1px solid #1a1a1a;
}
.grid {
  display: grid;
  grid-template-rows: repeat(9, var(--tile));
  gap: 0;
  border: none;
}
.grid-row {
  display: grid;
  grid-template-columns: repeat(9, var(--tile));
}
.tile {
  width: var(--tile);
  height: var(--tile);
  box-sizing: border-box;
  border: 1px solid #1a1a1f;
  background: #15151a;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
}
.tile .glyph {
  font-size: 2.5rem;
  font-weight: 800;
  color: #eee;
  text-transform: uppercase;
  letter-spacing: -0.02em;
}
.tile .coords {
  position: absolute;
  bottom: 4px;
  right: 6px;
  font-size: 0.65rem;
  color: #3a3a42;
  font-variant-numeric: tabular-nums;
}
.tile-hidden {
  background: #1c1c26;
}
.tile-hidden .glyph { color: #5a5a6e; }
.tile-revealed { background: #202028; }
.tile-hiding {
  background: #2a1f1a;
  outline: 2px dashed #6b4226;
  outline-offset: -4px;
}
.tile-door { background: #2b2b19; }
.tile-door .glyph { color: #e0c860; }
.tile-door-open { background: #2b3a1a; }
.tile-door-open .glyph { color: #9ce04c; }
.tile-player {
  background: #1a2a4a !important;
  box-shadow: inset 0 0 18px rgba(79, 142, 247, 0.45);
}
.tile-player .glyph {
  color: #fff;
  text-shadow: 0 0 10px #4f8ef7;
}

.tile-attack-flash {
  animation: attackFlash 0.9s ease-out;
}
@keyframes attackFlash {
  0% {
    background: #7a2b2b;
    box-shadow: inset 0 0 24px rgba(255, 140, 90, 0.9);
  }
  40% {
    background: #5a2424;
    box-shadow: inset 0 0 16px rgba(255, 140, 90, 0.55);
  }
  100% {
    background: inherit;
    box-shadow: none;
  }
}

.grid-empty {
  color: #555;
  font-style: italic;
}

@media (max-width: 1600px) {
  .live-board {
    grid-template-columns: 1fr;
  }
  .grid-area {
    border-left: none;
    border-top: 1px solid #1a1a1a;
    padding: 1rem;
  }
  .grid {
    transform: scale(0.7);
    transform-origin: top center;
  }
}
</style>
