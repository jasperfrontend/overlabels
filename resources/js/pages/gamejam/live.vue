<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { floorFor, themeFor, type RoomTheme } from './themes';

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
  is_exit: boolean;
}

interface HidingSpotPayload {
  x: number;
  y: number;
}

interface BlockerPayload {
  x: number;
  y: number;
}

interface ZombiePayload {
  id: number;
  x: number;
  y: number;
  prev_x: number;
  prev_y: number;
  facing: 'up' | 'down' | 'left' | 'right';
  hp: number;
  max_hp: number;
  damage: number;
  kind: 'regular' | 'weakling' | 'boss';
  brain_state: 'drifting' | 'chasing';
}

interface WorldPayload {
  hidden_tiles: HiddenTilePayload[];
  doors: DoorPayload[];
  hiding_spots: HidingSpotPayload[];
  blockers: BlockerPayload[];
  zombies: ZombiePayload[];
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
  debugEnabled: boolean;
}>();

const emptyWorld: WorldPayload = { hidden_tiles: [], doors: [], hiding_spots: [], blockers: [], zombies: [] };

const game = ref<GamePayload | null>(props.snapshot?.game ?? null);
const joiners = ref<JoinerPayload[]>(props.snapshot?.joiners ?? []);
const world = ref<WorldPayload>(props.snapshot?.world ?? emptyWorld);

interface ZombieView {
  x: number;
  y: number;
  facing: ZombiePayload['facing'];
  animating: boolean;
  duration: number;
}

const zombieViews = ref<Record<number, ZombieView>>({});

function syncZombieViews(list: ZombiePayload[], duration: number) {
  // Snap each zombie to its prev position with no transition, then on the
  // next frame animate to the current position over the full round duration.
  // This keeps the crossing animation in lockstep with the round timer.
  const snap: Record<number, ZombieView> = {};
  for (const z of list) {
    snap[z.id] = {
      x: z.prev_x,
      y: z.prev_y,
      facing: z.facing,
      animating: false,
      duration,
    };
  }
  zombieViews.value = snap;

  requestAnimationFrame(() => {
    const anim: Record<number, ZombieView> = {};
    for (const z of list) {
      anim[z.id] = {
        x: z.x,
        y: z.y,
        facing: z.facing,
        animating: true,
        duration,
      };
    }
    zombieViews.value = anim;
  });
}

function zombieStyle(z: ZombiePayload) {
  const view = zombieViews.value[z.id];
  const x = view?.x ?? z.x;
  const y = view?.y ?? z.y;
  const transition = view?.animating
    ? `transform ${view.duration}s linear`
    : 'none';
  return {
    transform: `translate(calc(${x} * var(--tile)), calc(${y} * var(--tile)))`,
    transition,
  };
}
const debugEnabledLive = ref(props.debugEnabled);
const connected = ref(false);
const now = ref(Date.now());
const attackFlashTiles = ref<Set<string>>(new Set());
const needsAudioUnlock = ref(false);
const gamePeakHp = ref<number>(props.snapshot?.game?.player_hp ?? 0);
let audioCtx: AudioContext | null = null;

let channel: any = null;
let tickInterval: ReturnType<typeof setInterval> | null = null;
let attackFlashTimeout: ReturnType<typeof setTimeout> | null = null;
let lastFlashedResolvedAt: string | null = props.snapshot?.game?.last_resolved_at ?? null;

const ATTACK_FLASH_MS = 900;

const GRID_SIZE = 9;
const BORDER = 1;
const DISPLAY_SIZE = GRID_SIZE + BORDER * 2;
const rows = Array.from({ length: DISPLAY_SIZE }, (_, i) => i + 1);
const cols = rows;

function toGame(d: number): number | null {
  const g = d - BORDER;
  return g >= 1 && g <= GRID_SIZE ? g : null;
}

const debugInput = ref('');
const debugInspected = computed<{ x: number; y: number } | null>(() => {
  const match = debugInput.value.match(/^\s*(\d+)\s*,\s*(\d+)\s*$/);
  if (!match) return null;
  const x = parseInt(match[1], 10);
  const y = parseInt(match[2], 10);
  if (x < 1 || x > GRID_SIZE || y < 1 || y > GRID_SIZE) return null;
  return { x, y };
});

function debugTileState(x: number, y: number) {
  const t = tileAt(x, y);
  return {
    player: t.player ? { hp: t.player.player_hp, hiding: t.player.player_hiding_this_round } : null,
    blocker: !!t.blocker,
    hidingSpot: !!t.hidingSpot,
    door: t.door
      ? { state: t.door.state, is_exit: t.door.is_exit, turns_remaining: t.door.turns_remaining }
      : null,
    hiddenTile: t.hiddenTile
      ? { content: t.hiddenTile.content, revealed_at_round: t.hiddenTile.revealed_at_round }
      : null,
    glyph: tileGlyph(x, y) || '(none)',
    floor: floorFor(theme.value, x, y),
    sprite: spriteFor(x, y) ?? '(none)',
  };
}

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

const blocks = computed(() => {
  return (blocksRemaining: number) => {
    const MAX_BLOCKS = 3;
    return Array.from({ length: MAX_BLOCKS }, (_, i) => i < blocksRemaining ? 'filled' : 'empty');
  };
});

function readableVote(vote: string | null): string {
  if (!vote) return '-';
  if (vote === 'h') return 'hide';
  if (vote === 's') return 'stay';
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

const theme = computed<RoomTheme>(() => themeFor(game.value?.current_room ?? 1));

function spriteFor(dx: number, dy: number): string | null {
  if (toGame(dx) === null || toGame(dy) === null) return null;
  const t = tileAt(dx, dy);
  const th = theme.value;
  if (t.player) return th.player;
  if (t.blocker) return th.blocker;
  if (t.door) {
    if (t.door.is_exit) return th.door.exit;
    return th.door[t.door.state];
  }
  if (t.hiddenTile) {
    if (t.hiddenTile.revealed_at_round === null) return th.hidden;
    const content = t.hiddenTile.content as keyof RoomTheme['pickups'] | null;
    return content ? th.pickups[content] ?? null : null;
  }
  if (t.hidingSpot) return th.hidingSpot;
  return null;
}

function tileAt(dx: number, dy: number) {
  const x = toGame(dx);
  const y = toGame(dy);
  if (x === null || y === null) {
    return { player: null, door: null, hidingSpot: null, hiddenTile: null, blocker: null };
  }
  const player =
    game.value && game.value.player_x === x && game.value.player_y === y ? game.value : null;
  const door = world.value.doors.find((d) => d.x === x && d.y === y) ?? null;
  const hidingSpot = world.value.hiding_spots.find((s) => s.x === x && s.y === y) ?? null;
  const hiddenTile = world.value.hidden_tiles.find((t) => t.x === x && t.y === y) ?? null;
  const blocker = world.value.blockers.find((b) => b.x === x && b.y === y) ?? null;
  return { player, door, hidingSpot, hiddenTile, blocker };
}

function tileGlyph(x: number, y: number): string {
  const { player, door, hiddenTile, blocker } = tileAt(x, y);
  if (player) return 'P';
  if (blocker) return '#';
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

const PICKUP_CLASSES: Record<string, string> = {
  regular_sword: 'pickup-sword',
  de_sword: 'pickup-de-sword',
  iron_fists: 'pickup-iron-fists',
  bomb: 'pickup-bomb',
  hp_restore: 'pickup-hp',
  zombie_spawn: 'pickup-zombie',
};

const LOW_HP_THRESHOLD = 1;

function tileClasses(dx: number, dy: number): string[] {
  const x = toGame(dx);
  const y = toGame(dy);
  if (x === null || y === null) return ['is-border'];

  const { player, door, hidingSpot, hiddenTile, blocker } = tileAt(dx, dy);
  const classes: string[] = [];

  if (player) classes.push('has-player');
  if (blocker) classes.push('has-blocker');
  if (hidingSpot) classes.push('has-hiding');
  if (door) {
    classes.push('has-door', `door-${door.state}`);
    if (door.is_exit) classes.push('door-exit');
  }
  if (hiddenTile) {
    if (hiddenTile.revealed_at_round === null) {
      classes.push('has-hidden', 'reveal-hidden');
    } else {
      classes.push('has-pickup');
      const pickup = hiddenTile.content ? PICKUP_CLASSES[hiddenTile.content] : null;
      if (pickup) classes.push(pickup);
    }
  }

  if (player && game.value) {
    if (game.value.player_hiding_this_round) classes.push('player-hiding');
    if (game.value.player_hp <= LOW_HP_THRESHOLD) classes.push('player-low-hp');
  }

  if (attackFlashTiles.value.has(`${x},${y}`)) classes.push('fx-attack');

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

function checkAutoplayPolicy() {
  const policy = (document as Document & { autoplayPolicy?: string }).autoplayPolicy;
  if (policy === undefined) {
    needsAudioUnlock.value = true;
    return;
  }
  needsAudioUnlock.value = policy === 'disallowed' || policy === 'allowed-muted';
}

async function unlockAudio() {
  try {
    const Ctor =
      (window as unknown as { AudioContext?: typeof AudioContext }).AudioContext ||
      (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!audioCtx && Ctor) audioCtx = new Ctor();
    if (audioCtx && audioCtx.state === 'suspended') {
      await audioCtx.resume();
    }
    if (audioCtx) {
      const buffer = audioCtx.createBuffer(1, 1, 22050);
      const source = audioCtx.createBufferSource();
      source.buffer = buffer;
      source.connect(audioCtx.destination);
      source.start(0);
    }
  } catch (err) {
    console.warn('[gamejam.audio.unlock]', err);
  } finally {
    needsAudioUnlock.value = false;
  }
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
  checkAutoplayPolicy();
  tickInterval = setInterval(() => (now.value = Date.now()), 250);

  if (world.value.zombies.length && game.value) {
    syncZombieViews(world.value.zombies, game.value.round_duration_seconds);
  }

  const echo = (window as any).Echo;
  if (!echo) return;

  const conn = echo.connector?.pusher?.connection;
  if (conn) {
    connected.value = conn.state === 'connected';
    conn.bind('connected', () => (connected.value = true));
    conn.bind('disconnected', () => (connected.value = false));
  }

  channel = echo.channel(`gamejam.${props.broadcasterId}`);
  channel.listen('.gamejam.debug', (payload: { enabled: boolean }) => {
    debugEnabledLive.value = payload.enabled;
  });
  channel.listen(
    '.gamejam.state',
    (
      payload: Snapshot & {
        updated_at: number;
        dispatched_at_ms?: number;
        broadcast_start_ms?: number;
      },
    ) => {
      const receivedAtMs = Date.now();
      const applyStartMs = receivedAtMs;

      const incoming = payload.game;
      gamePeakHp.value = Math.max(gamePeakHp.value, incoming.player_hp);

      game.value = incoming;
      joiners.value = payload.joiners;
      world.value = payload.world ?? emptyWorld;
      syncZombieViews(world.value.zombies, incoming.round_duration_seconds);
      maybeFlashAttack(incoming);

      const applyEndMs = Date.now();

      console.info('[gamejam.snapshot.received]', {
        round: payload.game.current_round,
        status: payload.game.status,
        dispatched_at_ms: payload.dispatched_at_ms ?? null,
        broadcast_start_ms: payload.broadcast_start_ms ?? null,
        received_at_ms: receivedAtMs,
        handler_to_broadcast_ms:
          payload.dispatched_at_ms && payload.broadcast_start_ms
            ? payload.broadcast_start_ms - payload.dispatched_at_ms
            : null,
        broadcast_to_client_ms: payload.broadcast_start_ms
          ? receivedAtMs - payload.broadcast_start_ms
          : null,
        end_to_end_ms: payload.dispatched_at_ms ? receivedAtMs - payload.dispatched_at_ms : null,
        apply_duration_ms: applyEndMs - applyStartMs,
      });
    },
  );
});

onUnmounted(() => {
  document.documentElement.classList.remove('gamejam-fullbleed');
  if (tickInterval) clearInterval(tickInterval);
  if (attackFlashTimeout) clearTimeout(attackFlashTimeout);
  if (channel) {
    channel.stopListening('.gamejam.state');
    channel.stopListening('.gamejam.debug');
  }
});
</script>

<template>
  <div class="live-board">
    <div v-if="!needsAudioUnlock" class="audio-unlock-overlay">
      <!-- @todo: remove ! above -->
      <div class="audio-unlock-panel">
        <h2>Audio is blocked</h2>
        <p>Your browser is preventing this overlay from playing sound until you interact with the page.</p>
        <button type="button" class="audio-unlock-button" @click="unlockAudio">
          Click to enable audio
        </button>
      </div>
    </div>
    <aside class="sidebar">
      <header class="flex justify-between gap-2">
        <div class="flex flex-col gap-0">
          <h1 class="text-3xl font-bold anthon-sc">Chat Castle</h1>
          <span class="text-muted-foreground text-[15px]">@{{ broadcasterLogin }}</span>
        </div>
        <div>

          <!-- Game Status -->
          <section v-if="game" class="flex text-center gap-10 anthon-sc">
            <div class="flex flex-col gap-0">
              <span class="text-muted-foreground uppercase">Status</span>
              <span class="py-0.5 px-2 rounded-sm uppercase font-bold" :class="`status-${game.status}`">{{ game.status }}</span>
            </div>
            <div class="flex flex-col gap-0">
              <span class="text-muted-foreground uppercase">Round</span>
              <span class="py-0.5 px-2 rounded-sm uppercase font-bold">{{ game.current_round }}</span>
            </div>
            <div class="flex flex-col gap-0">
              <span class="text-muted-foreground uppercase">Players</span>
              <span class="py-0.5 px-2 rounded-sm uppercase font-bold">{{ joiners.length }}</span>
            </div>
          </section>

        </div>
        <div class="conn bg-sidebar-accent py-0.5 px-3 rounded-sm" :class="{ on: connected }">
          <span class="dot size-3"></span>
          <span class="text-lg">{{ connected ? 'live' : 'offline' }}</span>
        </div>
      </header>
      <section class="stats-row" v-if="debugEnabledLive">
        <pre>{{ joiners }}</pre>
      </section>

      <!-- Weapons -->
      <section v-if="game" class="bg-[url(/tile-icons/Tile/ui/bg_tile_1.png)] p-4 rounded-sm flex justify-around items-center">
        <div class="weapon p-2">
          <span class="anthon-sc">Slot 1</span>
          <span class="value">
            {{ game.weapon_slot_1 }}
            <small v-if="game.weapon_slot_1_uses !== null">({{ game.weapon_slot_1_uses }})</small>
          </span>
        </div>
        <div class="weapon p-2">
          <span class="anthon-sc">Slot 2</span>
          <span class="value">{{ game.weapon_slot_2 ?? '-' }}</span>
        </div>
        <div class="weapon p-2">
          <span class="anthon-sc">Iron Fists</span>
          <span class="value">{{ game.wears_iron_fists ? 'yes' : 'no' }}</span>
        </div>
      </section>

      <!-- Health Bar -->
      <section v-if="game">
        <div
          class="relative py-2 w-full overflow-hidden rounded bg-[url(/tile-icons/Tile/progress/bl.png)] bg-auto bg-repeat"
          role="progressbar"
          :aria-valuenow="game.player_hp"
          aria-valuemin="0"
          :aria-valuemax="gamePeakHp"
        >
          <span
            class="absolute inset-y-0 left-0 transition-[width] duration-200 ease-out bg-[url(/tile-icons/Tile/progress/g.png)] bg-auto bg-repeat"
            :style="{ width: gamePeakHp > 0 ? `${Math.min(100, (game.player_hp / gamePeakHp) * 100)}%` : '0%' }"
          ></span>
          <span class="macondo-sc relative z-10 flex h-full items-center justify-center text-3xl font-bold tracking-wide text-white">
            {{ game.player_hp }} / {{ gamePeakHp }}
          </span>
        </div>
      </section>


      <section v-if="game" class="resolver-row">
        <div class="resolver-card countdown">
          <span class="anthon-sc">Next round in</span>
          <span class="value anthon-sc" :class="{ urgent: (secondsUntilNextTick ?? 99) < 5 }">
            {{ secondsUntilNextTick !== null ? `${secondsUntilNextTick}s` : '-' }}
          </span>
          <span class="text-sm text-muted-foreground">Rounds are {{ game.round_duration_seconds }} seconds</span>
        </div>
        <div class="resolver-card resolved">
          <span class="anthon-sc">Last Twitch chat vote</span>
          <span class="value anthon-sc">{{game.last_resolved_action ? readableVote(game.last_resolved_action) : 'nothing yet'}}</span>
          <div v-if="lastResolvedTallyEntries.length" class="tally">
            <span
              v-for="[action, count] in lastResolvedTallyEntries"
              :key="action"
              class="tally-entry text-sm text-muted-foreground"
            >
              {{ readableVote(action) }}: <b>{{ count }}</b>
            </span>
          </div>
          <span v-else class="text-sm text-muted-foreground">no votes cast</span>
        </div>
      </section>

      <section v-if="game" class="joiners-col">
        <div class="">
          <h2 class="anthon-sc text-lg text-white">Active: <span class="count">{{ grouped.active.length }} player{{ grouped.active.length !== 1 ? 's' : '' }}</span></h2>
          <ul>
            <li
              v-for="j in grouped.active"
              :key="j.twitch_user_id"
              class="joiner"
              :title="`Joined r${j.joined_round}${j.last_vote_round ? `, last vote r${j.last_vote_round}` : ''}`"
            >
              <span class="name">{{ j.username }}</span>
              <span class="vote">{{ readableVote(j.current_vote) }}</span>
              <div class="flex ml-auto gap-2" :aria-label="`${j.blocks_remaining} of 3 energy`">
                <span class="-mt-0.5 anthon-sc">Energy:</span>
                <span
                  v-for="(state, i) in blocks(j.blocks_remaining)"
                  :key="i"
                  class="pip"
                  :class="state"
                ></span>
              </div>
            </li>
            <li v-if="!grouped.active.length" class="placeholder">no active players right now</li>
          </ul>
        </div>

        <div class="">
          <h2 class="anthon-sc text-lg text-white">Pending: <span class="count">{{ grouped.pending.length }} players</span></h2>
          <ul>
            <li v-for="j in grouped.pending" :key="j.twitch_user_id" class="joiner">
              <div class="name">{{ j.username }} <span class="dim">joined r{{ j.joined_round }}</span></div>
            </li>
            <li v-if="!grouped.pending.length" class="text-sm text-muted-foreground">no players waiting right now</li>
          </ul>
        </div>

        <div class="anthon-sc text-sm">
          <h2 class="anthon-sc text-lg text-white">Inactive: <span class="count">{{ grouped.inactive.length }} players</span></h2>
          <div v-for="j in grouped.inactive" :key="j.twitch_user_id" class="flex flex-wrap bg-card p-2 rounded-sm mb-0.5">
            <div class="max-w-[75%] overflow-hidden whitespace-nowrap text-ellipsis tracking-wide">{{ j.username }}</div>
            <div class="ml-auto text-muted-foreground tracking-wide">left at round {{ j.last_vote_round ?? j.joined_round }}</div>
          </div>
          <div v-if="!grouped.inactive.length" class="text-sm text-muted-foreground">no inactive players right now</div>
        </div>


        <div v-if="debugEnabledLive" class="debug-panel">
          <h2>Debug: player tile <span class="debug-tag">temp</span></h2>
          <div v-if="game.player_x !== null && game.player_y !== null" class="debug-block">
            <div class="debug-coords">({{ game.player_x }}, {{ game.player_y }})</div>
            <div class="debug-classes">
              <span
                v-for="c in tileClasses(game.player_x, game.player_y)"
                :key="c"
                class="debug-class"
              >{{ c }}</span>
              <span v-if="!tileClasses(game.player_x, game.player_y).length" class="debug-empty">
                (no classes)
              </span>
            </div>
            <pre class="debug-state">{{ debugTileState(game.player_x, game.player_y) }}</pre>
          </div>
          <div v-else class="debug-empty">player not on board</div>

          <h2>Debug: inspect any tile</h2>
          <input
            v-model="debugInput"
            class="debug-input"
            type="text"
            placeholder="x,y (e.g. 5,9)"
            inputmode="numeric"
            autocomplete="off"
          />
          <div v-if="debugInspected" class="debug-block">
            <div class="debug-coords">({{ debugInspected.x }}, {{ debugInspected.y }})</div>
            <div class="debug-classes">
              <span
                v-for="c in tileClasses(debugInspected.x, debugInspected.y)"
                :key="c"
                class="debug-class"
              >{{ c }}</span>
              <span
                v-if="!tileClasses(debugInspected.x, debugInspected.y).length"
                class="debug-empty"
              >(no classes)</span>
            </div>
            <pre class="debug-state">{{ debugTileState(debugInspected.x, debugInspected.y) }}</pre>
          </div>
          <div v-else-if="debugInput" class="debug-empty">
            invalid coords (use x,y with 1-{{ GRID_SIZE }})
          </div>
        </div>
      </section>

      <div v-else class="empty-state">
        No active game. Run
        <code>php artisan gamejam:start {{ broadcasterLogin }}</code>
      </div>
    </aside>

    <main class="grid-area">
      <div v-if="game" class="grid" :style="{ '--tile': 'calc(1080px / 11)' }">
        <div v-for="y in rows" :key="`row-${y}`" class="grid-row">
          <div
            v-for="x in cols"
            :key="`${x}-${y}`"
            class="tile"
            :class="tileClasses(x, y)"
            :style="{ backgroundImage: `url('${floorFor(theme, x, y)}')` }"
            :data-x="x"
            :data-y="y"
          >
            <img v-if="spriteFor(x, y)" :src="spriteFor(x, y)!" class="sprite" alt="" />
            <span class="glyph">{{ tileGlyph(x, y) }}</span>
            <span class="coords">{{ x }},{{ y }}</span>
          </div>
        </div>
        <div class="zombies-layer" aria-hidden="true">
          <div
            v-for="z in world.zombies"
            :key="z.id"
            class="zombie"
            :class="[`zombie-${z.kind}`, `zombie-${z.brain_state}`, `facing-${zombieViews[z.id]?.facing ?? z.facing}`]"
            :style="zombieStyle(z)"
          >
            <span class="zombie-body"></span>
            <span class="zombie-hp">{{ z.hp }}/{{ z.max_hp }}</span>
          </div>
        </div>
      </div>
      <div v-else class="grid-empty">Waiting for a game to start...</div>
    </main>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Anton+SC&family=Macondo+Swash+Caps&display=swap');

.macondo-sc {
  font-family: 'Macondo Swash Caps', cursive;
  font-weight: 400;
  font-style: normal;
}
.anthon-sc {
  font-family: 'Anton SC', sans-serif;
  font-weight: 400;
}
.live-board {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1080px;
  background: #0e0e10;
  color: #eee;
  font-size: 25px;
  line-height: 1.5;
  font-weight: 400;
  font-synthesis: none;
  text-rendering: optimizeLegibility;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  -webkit-text-size-adjust: 100%;
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
  border-radius: 50%;
  background: #555;
}
.conn.on .dot {
  background: #e8db0b;
  box-shadow: 0 0 8px #e8db0b;
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
  font-size: 1rem;
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
  font-size: 1rem;
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
  font-size: 1rem;
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
  gap: 0.2rem;
}
.joiner {
  background: #242424;
  padding: 0.3rem 0.55rem;
  border-radius: 3px;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  font-size: 1rem;
  line-height: 1.2;
}
.joiner.dim { opacity: 0.5; }
.joiner .name {
  font-weight: 600;
  font-size: 1.5rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
  flex: 0 1 auto;
}
.joiner .vote {
  background: #000;
  color: #2a9d90;
  white-space: nowrap;
  font-size: 1.5rem;
  min-width: 0;
  padding: .2rem;
  border-radius: 5px;
  overflow: hidden;
  font-weight: bold;
  text-overflow: ellipsis;
}
.joiner .vote.dim,
.joiner .dim { color: #888; }
.joiner .energy {
  display: inline-flex;
  gap: 3px;
  margin-left: auto;
  flex-shrink: 0;
}
.joiner .pip {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  display: inline-block;
  transition: background 0.2s, box-shadow 0.2s;
}
.joiner .pip.filled {
  background: #c69217;
  box-shadow: 0 0 4px rgba(198, 146, 23, .15);
}
.joiner .pip.empty {
  background: transparent;
  border: 1px solid #3a3a3a;
}


.placeholder {
  color: #555;
  font-style: italic;
  font-size: 0.8rem;
}

.debug-panel {
  background: #1a1410;
  border: 1px dashed #b0823d;
  border-radius: 6px;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.debug-panel h2 {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #e0a060;
  margin: 0.25rem 0 0.1rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.debug-panel h2:first-child { margin-top: 0; }
.debug-tag {
  font-size: 0.6rem;
  padding: 0.05rem 0.35rem;
  background: #b0823d;
  color: #1a1410;
  border-radius: 3px;
  letter-spacing: 0.05em;
}
.debug-block {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.debug-coords {
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
  color: #e0a060;
  font-weight: 700;
}
.debug-classes {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}
.debug-class {
  background: #2a2018;
  color: #ffd9a8;
  font-family: ui-monospace, monospace;
  font-size: 0.72rem;
  padding: 0.1rem 0.4rem;
  border-radius: 3px;
  border: 1px solid #3a2a1a;
}
.debug-empty {
  color: #666;
  font-style: italic;
  font-size: 0.75rem;
}
.debug-state {
  margin: 0;
  background: #0f0a08;
  border-radius: 4px;
  padding: 0.5rem 0.6rem;
  font-family: ui-monospace, monospace;
  font-size: 0.7rem;
  color: #c8c0b8;
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 180px;
  overflow-y: auto;
}
.debug-input {
  background: #0f0a08;
  border: 1px solid #3a2a1a;
  border-radius: 4px;
  padding: 0.4rem 0.6rem;
  color: #ffd9a8;
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
  width: 100%;
  box-sizing: border-box;
}
.debug-input:focus {
  outline: none;
  border-color: #b0823d;
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
  border-left: 0;
}
.grid {
  display: grid;
  grid-template-rows: repeat(11, var(--tile));
  gap: 0;
  border: none;
  position: relative;
}
.zombies-layer {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 5;
}
.zombie {
  position: absolute;
  top: 0;
  left: 0;
  width: var(--tile);
  height: var(--tile);
  display: flex;
  align-items: center;
  justify-content: center;
  will-change: transform;
  transform: translate(0, 0);
}
.zombie .zombie-body {
  width: 70%;
  height: 70%;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #7abb63 0%, #3e7a2e 60%, #1d3a16 100%);
  box-shadow: 0 0 14px rgba(122, 187, 99, 0.55), inset 0 0 10px rgba(0, 0, 0, 0.4);
  border: 2px solid rgba(0, 0, 0, 0.45);
  position: relative;
}
.zombie .zombie-body::after {
  content: '';
  position: absolute;
  width: 22%;
  height: 22%;
  top: 15%;
  left: 50%;
  transform: translateX(-50%);
  background: #ffcf66;
  border-radius: 50%;
  box-shadow: 0 0 6px #ffcf66;
}
.zombie-chasing .zombie-body {
  background: radial-gradient(circle at 35% 30%, #e06a4c 0%, #9a2e18 60%, #3c0d05 100%);
  box-shadow: 0 0 18px rgba(224, 106, 76, 0.75), inset 0 0 10px rgba(0, 0, 0, 0.4);
  animation: zombiePulse 0.9s ease-in-out infinite alternate;
}
@keyframes zombiePulse {
  from { transform: scale(1); }
  to { transform: scale(1.08); }
}
.zombie-boss {
  width: calc(var(--tile) * 1.3);
  height: calc(var(--tile) * 1.3);
  margin-top: calc(var(--tile) * -0.15);
  margin-left: calc(var(--tile) * -0.15);
}
.zombie-boss .zombie-body {
  background: radial-gradient(circle at 35% 30%, #a058e0 0%, #4a1c6a 60%, #1d0a2c 100%);
  box-shadow: 0 0 22px rgba(160, 88, 224, 0.8), inset 0 0 14px rgba(0, 0, 0, 0.5);
  border-color: #e0d04e;
}
.zombie-weakling {
  width: calc(var(--tile) * 0.72);
  height: calc(var(--tile) * 0.72);
  margin-top: calc(var(--tile) * 0.14);
  margin-left: calc(var(--tile) * 0.14);
}
.zombie-weakling .zombie-body {
  background: radial-gradient(circle at 35% 30%, #cbd67a 0%, #6b7526 60%, #2c3010 100%);
  opacity: 0.85;
}
.zombie .zombie-hp {
  position: absolute;
  bottom: 4px;
  right: 4px;
  font-size: 0.65rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #fff;
  background: rgba(0, 0, 0, 0.7);
  border-radius: 3px;
  padding: 0 4px;
  line-height: 1.3;
  pointer-events: none;
}
.zombie.facing-up .zombie-body::after { top: 15%; left: 50%; transform: translateX(-50%); }
.zombie.facing-down .zombie-body::after { top: auto; bottom: 15%; left: 50%; transform: translateX(-50%); }
.zombie.facing-left .zombie-body::after { top: 50%; left: 15%; transform: translateY(-50%); }
.zombie.facing-right .zombie-body::after { top: 50%; left: auto; right: 15%; transform: translateY(-50%); }
.grid-row {
  display: grid;
  grid-template-columns: repeat(11, var(--tile));
}
.tile.is-border {
  pointer-events: none;
}
.tile.is-border .glyph,
.tile.is-border .coords {
  display: none;
}
.tile {
  width: var(--tile);
  height: var(--tile);
  box-sizing: border-box;
  border: 1px solid #15151a;
  background: #15151a center / cover no-repeat;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
  @apply: rounded-sm;
}
.tile .sprite {
  position: absolute;
  inset: 4px;
  width: calc(100% - 8px);
  height: calc(100% - 8px);
  object-fit: contain;
  pointer-events: none;
  image-rendering: pixelated;
  z-index: 1;
}
.tile .glyph {
  font-size: 1rem;
  font-weight: 700;
  color: rgba(238, 238, 238, 0.55);
  text-transform: uppercase;
  letter-spacing: -0.02em;
  position: absolute;
  top: 4px;
  left: 6px;
  z-index: 2;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
}
.tile .coords {
  position: absolute;
  bottom: 4px;
  right: 6px;
  font-size: 0.65rem;
  color: #3a3a42;
  font-variant-numeric: tabular-nums;
}
/* ---- has-* axis: what's on the tile ---- */
.has-hidden { background-color: #1c1c26; }
.has-hidden .glyph { color: #5a5a6e; }
.has-pickup { background-color: #202028; }
.has-hiding {
  background-color: #2a1f1a;
  outline: 2px dashed #6b4226;
  outline-offset: -4px;
}
.has-blocker {
  background: repeating-linear-gradient(45deg, #3a3a3a 0 8px, #555 8px 16px);
  box-shadow: inset 0 0 0 2px #222;
}
.has-blocker .glyph {
  color: #ddd;
  text-shadow: 0 1px 2px #000;
}
.has-door { background-color: #2b2b19; }
.has-door .glyph { color: #e0c860; }
.has-player {
  background-color: #1a2a4a !important;
  box-shadow: inset 0 0 18px rgba(79, 142, 247, 0.45);
}
.has-player .glyph {
  color: #fff;
  text-shadow: 0 0 10px #4f8ef7;
}

/* ---- door-* substate ---- */
.door-closed { /* hook: add shake/glow anticipation here */ }
.door-opening { /* hook: mid-open visuals */ }
.door-open {
  background-color: #2b3a1a;
}
.door-open .glyph { color: #9ce04c; }
.door-exit { /* hook: mark the room-ending door differently (e.g. outlined gold) */ }

/* ---- pickup-* (revealed tile contents) ---- */
.pickup-sword {
  border-color: blue;
  color: blue;
}
.pickup-de-sword {
  border-color: yellow;
  color: yellow;
}
.pickup-iron-fists {
  border-color: chartreuse;
  color: chartreuse;
}
.pickup-bomb {
  border-color: red;
  color: red;
}
.pickup-hp {
  border-color: purple;
  color: purple;
}
.pickup-zombie {
  border-color: orange;
  color: orange;
}

/* ---- reveal-* (hidden -> shown FX) ---- */
.reveal-hidden { /* hook: subtle idle pulse on unrevealed tiles */ }
.reveal-shown { /* hook: short-lived class on the frame a tile reveals */ }

/* ---- player-* modifiers ---- */
.player-hiding { /* hook: dampen the blue glow, add concealment effect */ }
.player-low-hp { /* hook: red pulse on the player tile when HP <= 1 */ }

/* ---- vote-* (not wired yet; add when we thread votes into tiles) ---- */
.vote-target-attack { /* hook: tile is inside a pending attack AoE */ }
.vote-target-move { /* hook: tile the winning move vote points at */ }
.vote-heat-1 { /* hook: low interest */ }
.vote-heat-2 { /* hook */ }
.vote-heat-3 { /* hook */ }
.vote-heat-4 { /* hook */ }
.vote-heat-5 { /* hook: high interest */ }

/* ---- fx-* short-lived animations ---- */
.fx-attack { animation: fxAttack 0.9s ease-out; }
@keyframes fxAttack {
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

.audio-unlock-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(10, 10, 14, 0.85);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}
.audio-unlock-panel {
  background: #15151a;
  border: 1px solid #2a2a32;
  border-radius: 10px;
  padding: 2rem 2.5rem;
  max-width: 420px;
  text-align: center;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}
.audio-unlock-panel h2 {
  margin: 0 0 0.5rem;
  font-size: 1.4rem;
  color: #eee;
}
.audio-unlock-panel p {
  margin: 0 0 1.25rem;
  color: #aaa;
  font-size: 0.95rem;
  line-height: 1.4;
}
.audio-unlock-button {
  cursor: pointer;
  background: #2a9d90;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 0.75rem 1.5rem;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  transition: background 0.15s;
}
.audio-unlock-button:hover {
  background: #36bfb0;
}

progress {
  height: 30px;
  background: red;
  box-shadow: 1px 1px 4px rgba( 0, 0, 0, 0.2 );
}
progress::-webkit-progress-bar {
  background-color: yellow;
  border-radius: 70px;
}
progress::-webkit-progress-value {
  background-color: blue;
  border-radius: 7px;
  box-shadow: 1px 1px 5px 3px rgba( 255, 0, 0, 0.8 );
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
