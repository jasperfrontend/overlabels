<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, type Component } from 'vue';
import { ArrowLeft, ArrowRight, ArrowUp, ArrowDown, CircleDot, CircleDashed } from '@lucide/vue';
import { floorFor, themeFor, type RoomTheme } from './themes';
import GameResultBanner from '@/components/gamejam/GameResultBanner.vue';
import GameStatusCard from '@/components/gamejam/GameStatusCard.vue';
import GameWeaponCard from '@/components/gamejam/GameWeaponCard.vue';
import { gameCommands } from '@/lib/game-commands';

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
  active: boolean;
  lunged_this_turn: boolean;
}

interface WorldPayload {
  hidden_tiles: HiddenTilePayload[];
  doors: DoorPayload[];
  hiding_spots: HidingSpotPayload[];
  blockers: BlockerPayload[];
  zombies: ZombiePayload[];
}

interface GameLogEntry {
  id: string;
  at: number;
  type: string;
  data: Record<string, unknown>;
}

interface Snapshot {
  game: GamePayload;
  joiners: JoinerPayload[];
  world: WorldPayload;
  log: GameLogEntry[];
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
const log = ref<GameLogEntry[]>(props.snapshot?.log ?? []);
const tickerEntry = ref<GameLogEntry | null>(null);
let tickerTimeout: ReturnType<typeof setTimeout> | null = null;
let lastSeenLogId: string | null = props.snapshot?.log?.length ? props.snapshot.log[props.snapshot.log.length - 1].id : null;
const TICKER_VISIBLE_MS = 4000;
const logScrollRef = ref<HTMLElement | null>(null);

function syncTicker(entries: GameLogEntry[]) {
  if (!entries.length) return;
  const latest = entries[entries.length - 1];
  if (latest.id === lastSeenLogId) return;
  lastSeenLogId = latest.id;
  tickerEntry.value = latest;
  if (tickerTimeout) clearTimeout(tickerTimeout);
  tickerTimeout = setTimeout(() => {
    tickerEntry.value = null;
    tickerTimeout = null;
  }, TICKER_VISIBLE_MS);
}

function scrollLogToBottom() {
  const el = logScrollRef.value;
  if (!el) return;
  requestAnimationFrame(() => {
    el.scrollTop = el.scrollHeight;
  });
}

const activeScrollRef = ref<HTMLElement | null>(null);
let activeScrollDir: 1 | -1 = 1;
let activeScrollPausedUntil = 0;
let activeScrollAnimId: number | null = null;
let activeScrollLastTs = 0;
const ACTIVE_SCROLL_PX_PER_MS = 0.04; // ~40px/sec
const ACTIVE_SCROLL_PAUSE_MS = 1500;

function autoScrollActiveStep(ts: number) {
  const el = activeScrollRef.value;
  if (!el) {
    activeScrollAnimId = requestAnimationFrame(autoScrollActiveStep);
    return;
  }
  if (activeScrollLastTs === 0) activeScrollLastTs = ts;
  const dt = ts - activeScrollLastTs;
  activeScrollLastTs = ts;

  const max = el.scrollHeight - el.clientHeight;
  if (max <= 0) {
    el.scrollTop = 0;
    activeScrollAnimId = requestAnimationFrame(autoScrollActiveStep);
    return;
  }

  if (ts >= activeScrollPausedUntil) {
    const next = el.scrollTop + activeScrollDir * ACTIVE_SCROLL_PX_PER_MS * dt;
    if (next >= max) {
      el.scrollTop = max;
      activeScrollDir = -1;
      activeScrollPausedUntil = ts + ACTIVE_SCROLL_PAUSE_MS;
    } else if (next <= 0) {
      el.scrollTop = 0;
      activeScrollDir = 1;
      activeScrollPausedUntil = ts + ACTIVE_SCROLL_PAUSE_MS;
    } else {
      el.scrollTop = next;
    }
  }

  activeScrollAnimId = requestAnimationFrame(autoScrollActiveStep);
}

function zombieLabel(kind: unknown): string {
  if (kind === 'boss') return 'the boss';
  if (kind === 'weakling') return 'a weakling zombie';
  return 'a zombie';
}

function weaponLabel(weapon: unknown, ironFists: boolean): string {
  if (ironFists) return 'Iron Fists';
  if (weapon === 'de_sword') return 'the DE Sword';
  if (weapon === 'regular_sword') return 'a Sword';
  return 'fists';
}

function contentLabel(content: unknown): string {
  if (content === 'bomb') return 'a bomb';
  if (content === 'hp_restore') return 'an HP restore';
  if (content === 'regular_sword') return 'a Sword';
  if (content === 'de_sword') return 'the DE Sword';
  if (content === 'iron_fists') return 'Iron Fists';
  if (content === 'zombie_spawn') return 'a zombie spawn';
  return 'something';
}

function logEntryClass(entry: GameLogEntry): string {
  switch (entry.type) {
    case 'zombie_killed':
      return 'text-lime-300';
    case 'zombie_attack':
      return 'text-red-300';
    case 'game_won':
      return 'text-blue-300 font-bold';
    case 'game_lost':
      return 'text-red-400 font-bold';
    case 'hp_pickup':
      return 'text-emerald-300';
    case 'weapon_pickup':
      return 'text-yellow-300';
    case 'door_opened':
      return 'text-lime-200';
    case 'room_entered':
      return 'text-blue-200 font-bold';
    default:
      return 'text-olive-200';
  }
}

function formatLogEntry(entry: GameLogEntry): string {
  const d = entry.data;
  switch (entry.type) {
    case 'hide':
      return 'You went into hiding';
    case 'hidden_reveal':
      return `You found ${contentLabel(d.content)} in a hidden chest`;
    case 'hp_pickup':
      return `You picked up +${d.amount} HP (${d.player_hp} hp)`;
    case 'weapon_pickup':
      return `You picked up ${weaponLabel(d.weapon, d.iron_fists === true)}`;
    case 'player_attack':
      return `You hit ${zombieLabel(d.kind)} for ${d.damage} (${d.target_hp}/${d.target_max_hp})`;
    case 'zombie_killed':
      return `You killed ${zombieLabel(d.kind)}`;
    case 'zombie_attack':
      return `${zombieLabel(d.kind)[0].toUpperCase() + zombieLabel(d.kind).slice(1)} hit you for ${d.damage} (${d.player_hp} hp)`;
    case 'door_damage':
      return d.is_exit
        ? `You hit the exit door for ${d.damage} (${d.door_hp} left)`
        : `You hit a door for ${d.damage} (${d.door_hp} left)`;
    case 'door_opened':
      return d.is_exit ? 'The exit door is open!' : 'A door is open';
    case 'room_entered':
      return `You entered room ${d.to_room}`;
    case 'game_won':
      return 'Castle saved!';
    case 'game_lost':
      if (d.cause === 'fists') return 'You punched yourself to death...';
      if (d.cause === 'bomb') return 'A bomb finished you off...';
      return 'The zombies got you...';
    default:
      return entry.type;
  }
}

type LungeMode = 'none' | 'moving' | 'stationary';

interface ZombieView {
  x: number;
  y: number;
  facing: ZombiePayload['facing'];
  animating: boolean;
  duration: number;
  lungeMode: LungeMode;
}

const zombieViews = ref<Record<number, ZombieView>>({});

const LUNGE_DURATION_S = 0.18;
const LUNGE_EASING = 'cubic-bezier(0.2, 0.8, 0.3, 1)';

function lungeModeFor(z: ZombiePayload): LungeMode {
  if (!z.lunged_this_turn) return 'none';
  const moved = z.prev_x !== z.x || z.prev_y !== z.y;
  return moved ? 'moving' : 'stationary';
}

function syncZombieViews(list: ZombiePayload[], duration: number) {
  // Snap each zombie to its prev position with no transition, then on the
  // next frame animate to the current position over the full round duration.
  // This keeps the crossing animation in lockstep with the round timer.
  // Lunging zombies that moved get a short ease-out tween instead of the
  // slow drift; stationary lunging zombies keep their tile position but
  // get a wind-up + shoot-over-edge keyframe animation on the inner body.
  // The snap-then-rAF pattern also retriggers the CSS keyframe animation
  // each turn (class is removed on snap, re-added on anim). The keyframe
  // itself is bounded by animation-iteration-count: 1 in CSS, so each
  // retrigger plays exactly one bounce regardless of tick cadence.
  const snap: Record<number, ZombieView> = {};
  for (const z of list) {
    snap[z.id] = {
      x: z.prev_x,
      y: z.prev_y,
      facing: z.facing,
      animating: false,
      duration,
      lungeMode: 'none',
    };
  }
  zombieViews.value = snap;

  requestAnimationFrame(() => {
    const anim: Record<number, ZombieView> = {};
    for (const z of list) {
      const mode = lungeModeFor(z);
      anim[z.id] = {
        x: z.x,
        y: z.y,
        facing: z.facing,
        animating: true,
        duration: mode === 'moving' ? LUNGE_DURATION_S : duration,
        lungeMode: mode,
      };
    }
    zombieViews.value = anim;
  });
}

function zombieStyle(z: ZombiePayload) {
  const view = zombieViews.value[z.id];
  const x = view?.x ?? z.x;
  const y = view?.y ?? z.y;
  const easing = view?.lungeMode === 'moving' ? LUNGE_EASING : 'linear';
  const transition = view?.animating
    ? `transform ${view.duration}s ${easing}`
    : 'none';
  return {
    transform: `translate(calc(${x} * var(--tile)), calc(${y} * var(--tile)))`,
    transition,
  };
}

function zombieLungeClass(z: ZombiePayload): string {
  return zombieViews.value[z.id]?.lungeMode === 'stationary' ? 'zombie-lunge-stationary' : '';
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

// DEV: visualize 50 active + 50 inactive. Flip to false (or delete) when done.
const FAKE_PREVIEW = false;
const FAKE_NAMES_A = [
  'mossy_owl', 'pixelpilot', 'banana_storm', 'silentgryph', 'kaiser_42',
  'zombiehugger99', 'thunderfist', 'lavender_warden', 'oolong_dev', 'crispmango',
  'nyx_the_great_and_powerful_ruler', 'foxbyte', 'glimmerpath', 'rusticgoose',
  'inverse_chimera', 'pebble_throw', 'thatonebot', 'sunset_runner', 'binary_bard',
  'orchidwhisper', 'hexcrafter', 'velvetkey', 'mocha_thunder', 'pinegale',
  'longusernamethatdefinitelywillnotfit', 'syrupsoul', 'amber_drift', 'voidcricket',
  'pretzel_logic', 'midnightmoth', 'cobblepunk', 'mintycheese', 'arcanetangerine',
  'rocketpotato', 'tidewind', 'glassyowl', 'lonesomelantern', 'pawsandeffect',
  'caramelnova', 'snickerthorn', 'lichenstein', 'verdantwolf', 'hexagonsong',
  'plumbeard', 'sodalitestream', 'jadewhisker', 'cinder_paw', 'twilight_clover',
  'opalveil', 'misterquibble',
];
const FAKE_NAMES_I = [
  'driftless_haze', 'wickerbeam', 'rustyriverlong_username_check', 'pumpkin_kite', 'sable_chime',
  'kelpfortune', 'amaranthia', 'creakycog', 'goldfinchnova', 'tinker_grove',
  'mauveroamer', 'ferncipher', 'duskwalker', 'tinypost', 'ironclove',
  'porchlight7', 'ribbonchaser', 'salt_and_clover', 'bramblepudding', 'lozengevortex',
  'turnipgazer', 'shrubcadet', 'meadowtrickle', 'thistlepine', 'hemlockharbour',
  'cellargrin', 'frostedfollow', 'maplelore', 'glintwood', 'baronpeach',
  'orchard_static', 'pebbleflute', 'softprism', 'twigfox', 'apricotmechanic',
  'cardamomglow', 'snug_lantern', 'velourmoss', 'larkspurnine', 'moltenchime',
  'sundaystation', 'bluffwicker', 'cherubcrane', 'puddingmoth', 'ginger_oracle',
  'lookoutdaisy', 'plumeria_void', 'bristlefable', 'lichenpost', 'antiquefen',
];
const FAKE_VOTES = ['p:up', 'p:down', 'p:left', 'p:right', 'p:up:2', 'p:right:3', 'a', 'a:2', 'h', 's', null];
const fakeActive: JoinerPayload[] = FAKE_PREVIEW
  ? FAKE_NAMES_A.map((name, i) => ({
      twitch_user_id: `fake-a-${i}`,
      username: name,
      status: 'active',
      joined_round: 1,
      current_vote: FAKE_VOTES[i % FAKE_VOTES.length],
      last_vote_round: 1 + (i % 12),
      blocks_remaining: i % 4,
    }))
  : [];
const fakeInactive: JoinerPayload[] = FAKE_PREVIEW
  ? FAKE_NAMES_I.map((name, i) => ({
      twitch_user_id: `fake-i-${i}`,
      username: name,
      status: 'inactive',
      joined_round: 1,
      current_vote: null,
      last_vote_round: 1 + (i % 12),
      blocks_remaining: 0,
    }))
  : [];

const grouped = computed(() => ({
  active: [
    ...joiners.value.filter((j) => j.status === 'active'),
    ...fakeActive,
  ],
  pending: joiners.value.filter((j) => j.status === 'pending'),
  inactive: [
    ...joiners.value.filter((j) => j.status === 'inactive'),
    ...fakeInactive,
  ],
}));

const blocks = computed(() => {
  return (blocksRemaining: number) => {
    const MAX_BLOCKS = 3;
    return Array.from({ length: MAX_BLOCKS }, (_, i) => i < blocksRemaining ? 'filled' : 'empty');
  };
});

const voteArrowIcons: Record<string, Component> = {
  left: ArrowLeft,
  right: ArrowRight,
  up: ArrowUp,
  down: ArrowDown,
};

function voteIcon(vote: string | null): Component | null {
  if (!vote || !vote.startsWith('p:')) return null;
  const dir = vote.slice(2).split(':')[0];
  return voteArrowIcons[dir] ?? null;
}

function voteIconCount(vote: string | null): number {
  if (!vote || !vote.startsWith('p:')) return 0;
  return parseInt(vote.slice(2).split(':')[1] ?? '1', 10);
}

function voteLabel(vote: string | null): string {
  let weaponchoice = '';
  if (!vote) return '-';
  if (vote === 'h') return 'hide';
  if (vote === 's') return 'stay';
  if (vote === 'a') return 'attack';
  if (vote.slice(2) === '1') weaponchoice = 'I';
  if (vote.slice(2) === '2') weaponchoice = 'II';
  if (vote.startsWith('a:')) return `wpn ${weaponchoice}`;
  if (vote.startsWith('p:')) return '';
  return vote;
}

const theme = computed<RoomTheme>(() => themeFor(game.value?.current_room ?? 1));

// Room-level visual layer (CSS filter + coloured overlay) authored in the
// builder. Exposed to CSS as custom properties on .grid so .tile::before
// (floor + filter) and .tile::after (overlay) can read them. Items that sit
// above these layers (sprites, glyphs, zombies) are unaffected because they
// live in children/siblings with higher z-index.
const gridLayerStyle = computed(() => {
  const layout = theme.value.layout;
  return {
    '--tile': 'calc(1080px / 11)',
    '--room-filter': layout?.filter || 'none',
    '--room-overlay-color': layout?.overlayColor ?? 'transparent',
    '--room-overlay-opacity': String(layout?.overlayOpacity ?? 0),
  } as Record<string, string>;
});

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

const LOW_HP_THRESHOLD = 5;

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

function triggerAttackFlash(px: number, py: number, reach: number) {
  const tiles = new Set<string>();
  for (let dx = -reach; dx <= reach; dx++) {
    for (let dy = -reach; dy <= reach; dy++) {
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

  const slot = action.startsWith('a:') ? action.slice(2) : '1';
  const weapon = slot === '2' ? g.weapon_slot_2 : g.weapon_slot_1;
  const reach = weapon === 'de_sword' ? 2 : 1;

  triggerAttackFlash(g.player_x, g.player_y, reach);
}

onMounted(() => {
  document.documentElement.classList.add('gamejam-fullbleed');
  checkAutoplayPolicy();
  tickInterval = setInterval(() => (now.value = Date.now()), 250);

  if (world.value.zombies.length && game.value) {
    syncZombieViews(world.value.zombies, game.value.round_duration_seconds);
  }

  if (log.value.length) {
    scrollLogToBottom();
  }

  activeScrollAnimId = requestAnimationFrame(autoScrollActiveStep);

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
      log.value = payload.log ?? [];
      syncZombieViews(world.value.zombies, incoming.round_duration_seconds);
      syncTicker(log.value);
      scrollLogToBottom();
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
  if (tickerTimeout) clearTimeout(tickerTimeout);
  if (activeScrollAnimId !== null) cancelAnimationFrame(activeScrollAnimId);
  if (channel) {
    channel.stopListening('.gamejam.state');
    channel.stopListening('.gamejam.debug');
  }
});
</script>

<template>

  <Teleport to="body" v-if="game?.status !== 'running' && game?.status === 'won' || game?.status === 'lost'">
    <div
      v-if="!debugEnabledLive"
      class="fixed inset-0 z-9999 flex items-center justify-center bg-black/80 backdrop-blur-sm"
    >
      <GameResultBanner
        v-if="game?.status === 'won'"
        :status="game.status"
        title="Congratulations"
        description="You have defeated the zombie infestation and the castle is saved"
        footnote="This game is over now. Thank you for playing"
      />

      <GameResultBanner
        v-if="game?.status === 'lost'"
        :status="game.status"
        title="Well that sucks"
        description="The zombies have eaten you alive&hellip;"
        footnote="This game is over now. Thank you for playing"
      />
    </div>
  </Teleport>

  <div
    class="live-board"
    :class="game ? 'live' : ''"
  >
    <div v-if="!needsAudioUnlock" class="audio-unlock-overlay">
      <!-- @todo: Remove ! above after you're done here. -->
      <div class="audio-unlock-panel">
        <h2>Audio is blocked</h2>
        <p>Your browser is preventing this overlay from playing sound until you interact with the page.</p>
        <button type="button" class="audio-unlock-button" @click="unlockAudio">
          Click to enable audio
        </button>
      </div>
    </div>

    <aside class="sidebar" v-if="game">

      <!-- Game Inventory -->
      <section v-if="game" class="flex justify-between medievalsharp-regular">
        <div class="flex shrink grow-0 gap-1">
          <GameWeaponCard
            v-if="game.weapon_slot_1 === 'fists'"
            title="Weapon I"
            image-url="/tile-icons/pixel/128x128/fist.png"
            image-url-alt="fists"
            description="Fists &bull; !a"
          />
          <GameWeaponCard
            v-if="game.weapon_slot_1 === 'regular_sword'"
            title="Weapon 3x3"
            image-url="/tile-icons/pixel/128x128/sword-default.png"
            image-url-alt="sword-default"
            :weapon-uses="game.weapon_slot_1_uses"
          />
          <GameWeaponCard
            v-if="game.weapon_slot_2"
            title="Weapon 5x5"
            image-url="/tile-icons/pixel/128x128/sword-de.png"
            image-url-alt="sword-de"
            description="Infinite &bull; !a 2"
          />
          <GameWeaponCard
            v-if="game.wears_iron_fists"
            title="Iron Fists"
            image-url="/tile-icons/pixel/128x128/iron-fist.png"
            image-url-alt="iron-fist"
            description="Infinite &bull; auto"
          />
        </div>
        <!-- Game Status -->
        <div class="flex">
          <section v-if="game" class="flex text-center medievalsharp-regular gap-1">
            <GameStatusCard
              title="Round"
              :description="game.current_round"
            />
            <GameStatusCard
            title="Room"
            :description="game.current_room"
            />
            <GameStatusCard
            :title="`Player${joiners.length !== 1 ? 's' : ''}`"
            :description="joiners.length"
            />
          </section>
        </div>
      </section>

      <!-- Health Bar -->
      <section v-if="game">
        <div
          class="relative w-full overflow-hidden bg-red-400/50 border border-olive-500/50"
          role="progressbar"
          :aria-valuenow="game.player_hp"
          aria-valuemin="0"
          :aria-valuemax="gamePeakHp"
        >
          <span
            class="absolute inset-y-0 left-0 transition-[width] duration-200 ease-out bg-green-400/50"
            :style="{ width: gamePeakHp > 0 ? `${Math.min(100, (game.player_hp / gamePeakHp) * 100)}%` : '0%' }"
          ></span>
          <span class="medievalsharp-regular relative z-10 flex pt-0.5 h-full items-center justify-center text-2xl font-bold tracking-wide text-white">
            {{ game.player_hp }} / {{ gamePeakHp }}
          </span>
        </div>
      </section>

      <div class="flex">
        <div class="w-[40%]">
          <section v-if="game" class="flex flex-col gap-2.5">

            <div class="bg-olive-800 border border-olive-500/50 p-4 text-center medievalsharp-regular">
              <span class="text-olive-400">Next round in</span>
              <div class="text-8xl mt-1.5 text-olive-400" :class="{ 'text-red-400': (secondsUntilNextTick ?? 99) < 5 }">{{ secondsUntilNextTick !== null ? `${secondsUntilNextTick}` : '-' }}</div>
              <div class="text-sm text-olive-400">{{ game.round_duration_seconds }} seconds per round</div>
            </div>

            <div class="bg-olive-800 border border-olive-500/50 p-4 pb-0 flex flex-col resolved medievalsharp-regular">
              <span class="text-olive-400 text-center">Last Twitch chat vote</span>
              <div class="text-teal-400 text-8xl my-2 flex items-center justify-center gap-2">
                <template v-if="game.last_resolved_action">
                  <component
                    v-for="i in voteIconCount(game.last_resolved_action)"
                    :is="voteIcon(game.last_resolved_action)"
                    :key="i"
                    class="h-24 w-24"
                  />
                  <span v-if="voteLabel(game.last_resolved_action)">{{ voteLabel(game.last_resolved_action) }}</span>
                </template>
                <template v-else>-</template>
              </div>
              <div v-if="lastResolvedTallyEntries.length" class="mb-2 text-lg">
                <span
                  v-for="[action, count] in lastResolvedTallyEntries"
                  :key="action"
                  class="tally-entry medievalsharp-regular text-olive-400 text-sm inline-flex items-center gap-1"
                >
                  <component
                    v-for="i in voteIconCount(action)"
                    :is="voteIcon(action)"
                    :key="i"
                    class="h-4 w-4"
                  />
                  <span v-if="voteLabel(action)">{{ voteLabel(action) }}</span>
                  <span>: <strong class="text-teal-400">{{ count }}</strong></span>
                </span>
              </div>
            </div>

            <div class="bg-olive-800 border border-olive-500/50 medievalsharp-regular flex flex-col">
              <div class="text-olive-400 text-center px-3 py-2 border-b border-olive-500/40">Game log</div>
              <div ref="logScrollRef" class="h-100 overflow-y-auto flex flex-col gap-0.5 px-3 py-2">
                <div
                  v-for="entry in log"
                  :key="entry.id"
                  class="text-sm leading-tight py-0.5 border-b border-olive-500/10 last:border-b-0"
                  :class="logEntryClass(entry)"
                >
                  {{ formatLogEntry(entry) }}
                </div>
                <div v-if="!log.length" class="text-olive-400 italic text-sm">No events yet</div>
              </div>
            </div>

          </section>
        </div> <!-- grid col 1 -->

        <div class="w-[60%] min-w-0 overflow-hidden h-209.5 flex flex-col">
          <section v-if="game" class="pt-0 gap-2 ml-2 flex flex-col min-h-0 flex-1 overflow-hidden">
            <div class="medievalsharp-regular flex flex-col min-h-0 flex-1 overflow-hidden">
              <ul ref="activeScrollRef" class="overflow-hidden flex-1 min-h-0">
                <li
                  v-for="j in grouped.active"
                  :key="j.twitch_user_id"
                  class="joiner flex items-center gap-2 pl-1 w-full min-w-0 bg-olive-800 border border-olive-500/50 medievalsharp-regular"
                >
                  <div class="text-teal-400 bg-card p-1 w-25 shrink-0 fade-in-5 h-7 overflow-hidden px-3 flex items-center justify-center gap-1">
                    <component
                      v-for="i in voteIconCount(j.current_vote)"
                      :is="voteIcon(j.current_vote)"
                      :key="i"
                      class="h-4 w-4 fade-in-5"
                    />
                    <span v-if="voteLabel(j.current_vote)" class="whitespace-nowrap text-left">{{ voteLabel(j.current_vote) }}</span>
                  </div>
                  <div class="name flex-1 min-w-0 overflow-hidden whitespace-nowrap text-ellipsis">{{ j.username }}</div>
                  <div class="flex shrink-0 items-center mr-2 gap-1">
                    <span
                      v-for="(state, i) in blocks(j.blocks_remaining)"
                      :key="i"
                      :class="state"
                    >
                      <CircleDot class="fill-teal-600 size-3" v-if="state === 'filled'" />
                      <CircleDashed class="size-3" v-else />
                    </span>
                  </div>
                </li>
                <li v-if="!grouped.active.length" class="text-xl text-olive-400">no active players right now. Type <span class="text-yellow-400">!join</span> in chat.</li>
              </ul>

              <div
                v-if="grouped.inactive.length"
                class="grid grid-cols-2 gap-1 mt-2 opacity-60 max-h-60 overflow-hidden shrink-0"
              >
                <div
                  v-for="j in grouped.inactive"
                  :key="j.twitch_user_id"
                  class="flex items-center gap-2 px-2 py-0.5 bg-olive-800/60 border border-olive-500/30 text-sm min-w-0"
                >
                  <div class="flex-1 min-w-0 overflow-hidden whitespace-nowrap text-ellipsis text-foreground/70">{{ j.username }}</div>
                  <div class="text-yellow-400/40 shrink-0">r{{ j.last_vote_round ?? j.joined_round }}</div>
                </div>
              </div>
            </div>

            <div class="medievalsharp-regular" v-if="grouped.pending.length > 0">
              <h2 class="medievalsharp-regular text-lg text-white">Pending: <span class="count">{{ grouped.pending.length }} players</span></h2>
              <ul>
                <li v-for="j in grouped.pending" :key="j.twitch_user_id" class="joiner medievalsharp-regular">
                  <div class="name">{{ j.username }} <span class="dim">joined r{{ j.joined_round }}</span></div>
                </li>
                <li v-if="!grouped.pending.length" class="text-sm text-muted-foreground">no players waiting right now</li>
              </ul>
            </div>


            <div v-if="debugEnabledLive" class="bg-[#1a1410] border border-dashed border-[#b0823d] rounded-md px-4 py-3 flex flex-col gap-2">

              <h2 class="text-[0.75rem] uppercase tracking-[0.05em] text-[#e0a060] mt-1 mb-[0.1rem] first:mt-0 flex items-center gap-[0.4rem]">
                Debug: player tile
                <span class="text-[0.6rem] py-[0.05rem] px-[0.35rem] bg-[#b0823d] text-[#1a1410] rounded-[3px] tracking-[0.05em]">temp</span>
              </h2>

              <div v-if="game.player_x !== null && game.player_y !== null" class="flex flex-col gap-[0.4rem]">
                <div class="font-mono text-[0.85rem] text-[#e0a060] font-bold">({{ game.player_x }}, {{ game.player_y }})</div>
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="c in tileClasses(game.player_x, game.player_y)"
                    :key="c"
                    class="bg-[#2a2018] text-[#ffd9a8] font-mono text-[0.72rem] py-[0.1rem] px-[0.4rem] rounded-[3px] border border-[#3a2a1a]"
                  >{{ c }}</span>
                              <span v-if="!tileClasses(game.player_x, game.player_y).length" class="text-[#666] italic text-[0.75rem]">
                    (no classes)
                  </span>
                </div>
                <pre class="m-0 bg-[#0f0a08] rounded px-[0.6rem] py-2 font-mono text-[0.7rem] text-[#c8c0b8] whitespace-pre-wrap break-all max-h-45 overflow-y-auto">{{ debugTileState(game.player_x, game.player_y) }}</pre>
              </div>
              <div v-else class="text-[#666] italic text-[0.75rem]">player not on board</div>

              <h2 class="text-[0.75rem] uppercase tracking-[0.05em] text-[#e0a060] mt-1 mb-[0.1rem] first:mt-0 flex items-center gap-[0.4rem]">
                Debug: inspect any tile
              </h2>
              <input
                v-model="debugInput"
                class="bg-[#0f0a08] border border-[#3a2a1a] rounded px-[0.6rem] py-[0.4rem] text-[#ffd9a8] font-mono text-[0.85rem] w-full focus:outline-none focus:border-[#b0823d]"
                type="text"
                placeholder="x,y (e.g. 5,9)"
                inputmode="numeric"
                autocomplete="off"
              />

              <div v-if="debugInspected" class="flex flex-col gap-[0.4rem]">
                <div class="font-mono text-[0.85rem] text-[#e0a060] font-bold">({{ debugInspected.x }}, {{ debugInspected.y }})</div>
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="c in tileClasses(debugInspected.x, debugInspected.y)"
                    :key="c"
                    class="bg-[#2a2018] text-[#ffd9a8] font-mono text-[0.72rem] py-[0.1rem] px-[0.4rem] rounded-[3px] border border-[#3a2a1a]"
                  >{{ c }}</span>
                  <span
                    v-if="!tileClasses(debugInspected.x, debugInspected.y).length"
                    class="text-[#666] italic text-[0.75rem]"
                  >(no classes)</span>
                </div>
                <pre class="m-0 bg-[#0f0a08] rounded px-[0.6rem] py-2 font-mono text-[0.7rem] text-[#c8c0b8] whitespace-pre-wrap break-all max-h-45 overflow-y-auto">{{ debugTileState(debugInspected.x, debugInspected.y) }}</pre>
              </div>
              <div v-else-if="debugInput" class="text-[#666] italic text-[0.75rem]">
                invalid coords (use x,y with 1-{{ GRID_SIZE }})
              </div>

            </div>
          </section>
        </div> <!-- grid col 2 -->
      </div>
    </aside>

    <main class="grid-area relative">
      <div v-if="game" class="medievalsharp-regular bg-olive-700/90 border-t border-r border-olive-500 text-sm p-1 absolute bottom-0 left-0 z-9999">
        !join - join the game<br>
        !p up {3} - move player 1-3 blocks up/down/left/right.<br>
        !a or !a2 - attack with weapon 1 or 2<br>
        !h - teleport to hiding<br>
        !s - stay. do nothing.
      </div>

      <div v-if="game" class="ticker medievalsharp-regular">
        <Transition name="ticker">
          <div
            v-if="tickerEntry"
            :key="tickerEntry.id"
            class="ticker-entry"
            :data-type="tickerEntry.type"
          >
            {{ formatLogEntry(tickerEntry) }}
          </div>
        </Transition>
      </div>
      <div v-if="game" class="grid" :style="gridLayerStyle">
        <div v-for="y in rows" :key="`row-${y}`" class="grid-row">
          <div
            v-for="x in cols"
            :key="`${x}-${y}`"
            class="tile"
            :class="tileClasses(x, y)"
            :style="{ '--tile-floor': `url('${floorFor(theme, x, y)}')` }"
            :data-x="x"
            :data-y="y"
          >
            <img v-if="spriteFor(x, y)" :src="spriteFor(x, y)!" class="sprite pixelated" alt="" />
            <span class="glyph">{{ tileGlyph(x, y) }}</span>
            <span class="coords">{{ x }},{{ y }}</span>
          </div>
        </div>
        <div class="zombies-layer" aria-hidden="true">
          <div
            v-for="z in world.zombies"
            :key="z.id"
            class="zombie"
            :class="[
              `zombie-${z.kind}`,
              z.active ? `zombie-${z.brain_state}` : 'zombie-dead',
              `facing-${zombieViews[z.id]?.facing ?? z.facing}`,
              zombieLungeClass(z),
            ]"
            :style="zombieStyle(z)"
          >
            <span class="zombie-body"></span>
            <span v-if="z.active" class="zombie-hp">{{ z.hp }}/{{ z.max_hp }}</span>
          </div>
        </div>
      </div>
      <div v-else class="grid-empty">
        <div class="flex flex-col gap-2 p-4">
          <div>
            <h1 class="medievalsharp-regular text-6xl text-yellow-400">Welcome to Chat Castle</h1>
          </div>
          <div>Type <span class="text-yellow-400 tracking-wide">!castlehelp</span> in chat to see the commands again.</div>
          <div class="mb-10 space-y-3">
            <div
              v-for="cmd in gameCommands"
              :key="cmd.command"
              class="bg-olive-800/60 border border-olive-500/30 p-5"
            >
              <div class="mb-2 flex flex-wrap items-center gap-3">
                <code class="bg-olive-600 px-2 py-0.5 font-bold text-foreground inline-block shadow-[2px_2px_0_rgba(0,0,0,0.5)] inset-shadow-2xs inset-shadow-yellow-300/50">
                  {{ cmd.example }}
                </code>
                <code v-if="cmd.example2" class="bg-olive-600 px-2 py-0.5 font-bold text-foreground inline-block shadow-[2px_2px_0_rgba(0,0,0,0.5)] inset-shadow-2xs inset-shadow-yellow-300/50">
                  {{ cmd.example2 }}
                </code>
              </div>
              <p class="text-xl text-foreground">{{ cmd.summary }}</p>
            </div>
            <div class="pt-4 text-sm">Streamer: go to /gamejam/admin to start a new game.</div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=MedievalSharp&display=swap');

.medievalsharp-regular {
  font-family: "MedievalSharp", cursive;
  font-weight: 400;
  font-style: normal;
}

.live-board {
  width: 100vw;
  height: 100vh;
  max-width: 100vw;
  max-height: 100vh;
  overflow: hidden !important;
  display: grid;
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
.live-board.live {
  grid-template-columns: 1fr 1080px;
}

:global(html.gamejam-fullbleed),
:global(html.gamejam-fullbleed body) {
  overflow: hidden !important;
  width: 100vw;
  height: 100vh;
  max-width: 100vw;
  max-height: 100vh;
}

.sidebar {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow-y: auto;
  max-height: 100vh;
}

.title h1 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
}

.status-running { background: #2a9d90; color: #fff; }
.status-waiting { background: #b0823d; color: #fff; }
.status-won { background: #4f8ef7; color: #fff; }
.status-lost { background: #7a2b2b; color: #fff; }

.resolver-card.countdown .value {
  font-variant-numeric: tabular-nums;
}
.resolver-card.countdown .value.urgent {
  color: #ff5a5a;
}

.tally-entry b { color: #2a9d90; }

.grid-area {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  background: rgba(0, 0, 0, 0.88);
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
.zombie-dead {
  z-index: 3;
}
.zombie-dead .zombie-body {
  background: radial-gradient(circle at 35% 30%, #4a4a4a 0%, #2a2a2a 60%, #111 100%);
  box-shadow: 0 0 6px rgba(0, 0, 0, 0.6), inset 0 0 6px rgba(0, 0, 0, 0.6);
  border-color: rgba(0, 0, 0, 0.6);
  opacity: 0.55;
  transform: rotate(80deg);
  animation: none;
}
.zombie-dead .zombie-body::after {
  background: #3a1a1a;
  box-shadow: none;
  opacity: 0.7;
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

/* Stationary lunge: zombie was already adjacent and attacked without moving.
   Winds up by pulling back, shoots forward past the tile edge, then bounces
   back to rest. Per-facing keyframes so the body translates toward the
   player. Duration is short (~450ms) so it never overlaps into the next tick. */
.zombie.zombie-lunge-stationary .zombie-body {
  animation-duration: 0.45s;
  animation-timing-function: cubic-bezier(0.2, 0.8, 0.3, 1);
  animation-fill-mode: both;
  animation-iteration-count: 1;
}
.zombie.zombie-lunge-stationary.facing-up .zombie-body { animation-name: zombieLungeUp; }
.zombie.zombie-lunge-stationary.facing-down .zombie-body { animation-name: zombieLungeDown; }
.zombie.zombie-lunge-stationary.facing-left .zombie-body { animation-name: zombieLungeLeft; }
.zombie.zombie-lunge-stationary.facing-right .zombie-body { animation-name: zombieLungeRight; }

@keyframes zombieLungeUp {
  0%   { transform: translateY(0); }
  25%  { transform: translateY(12%); }
  60%  { transform: translateY(-45%); }
  100% { transform: translateY(0); }
}
@keyframes zombieLungeDown {
  0%   { transform: translateY(0); }
  25%  { transform: translateY(-12%); }
  60%  { transform: translateY(45%); }
  100% { transform: translateY(0); }
}
@keyframes zombieLungeLeft {
  0%   { transform: translateX(0); }
  25%  { transform: translateX(12%); }
  60%  { transform: translateX(-45%); }
  100% { transform: translateX(0); }
}
@keyframes zombieLungeRight {
  0%   { transform: translateX(0); }
  25%  { transform: translateX(-12%); }
  60%  { transform: translateX(45%); }
  100% { transform: translateX(0); }
}
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
  background-color: #15151a;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
}
/* Floor layer: painted tile image with the room-level CSS filter applied.
   Lives on ::before so sprites, glyphs, zombies and everything else inside
   .tile sit visually above it and are not affected by the filter. */
.tile::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: var(--tile-floor, none);
  background-position: center;
  background-size: cover;
  background-repeat: no-repeat;
  filter: var(--room-filter, none);
  z-index: 0;
  pointer-events: none;
}
/* Overlay color layer: sits above the floor but below any item. Opacity is
   driven by the room-level --room-overlay-opacity var; when 0 (the default)
   this layer is fully transparent and free for the compositor to skip. */
.tile::after {
  content: '';
  position: absolute;
  inset: 0;
  background-color: var(--room-overlay-color, transparent);
  opacity: var(--room-overlay-opacity, 0);
  z-index: 1;
  pointer-events: none;
}
.tile .sprite {
  position: absolute;
  inset: 4px;
  width: calc(100% - 8px);
  height: calc(100% - 8px);
  object-fit: contain;
  pointer-events: none;
  image-rendering: pixelated;
  z-index: 2;
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
  z-index: 3;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
}
.tile .coords {
  position: absolute;
  bottom: 4px;
  right: 6px;
  font-size: 0.65rem;
  color: #3a3a42;
  font-variant-numeric: tabular-nums;
  z-index: 3;
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
.has-door::before { background-color: #2b2b19; }
.has-door .glyph { color: #e0c860; }
.has-player::before {
  background-color: #1a2a4a !important;
  box-shadow: inset 0 0 18px rgba(79, 142, 247, 0.45);
}
.has-player .glyph {
  color: #fff;
  text-shadow: 0 0 10px #4f8ef7;
}

/* ---- door-* substate ---- */
.door-closed {
  border-color: red;
}
.door-opening {
  border-color: yellow;
}
.door-open {
  border-color: green;
  background-color: #2b3a1a;
}
.door-open .glyph { color: #9ce04c; }
.door-exit { /* hook: mark the room-ending door differently (e.g. outlined gold) */ }

/* ---- pickup-* (revealed tile contents) ---- */
.pickup-sword {
  border-color: blue;
  color: blue;
  box-shadow: inset 0 0 30px #00f, 0 0 30px #00f;
}
.pickup-de-sword {
  border-color: rgba(0,255,255, .75);
  box-shadow: inset 0 0 30px rgba(0,255,255, .75), 0 0 30px rgba(0,255,255, .75);
}
.pickup-iron-fists {
  border-color: chartreuse;
  color: chartreuse;
  box-shadow: inset 0 0 30px #0f0, 0 0 30px #0f0;
}
.pickup-bomb {
  border-color: red;
  color: red;
  box-shadow: inset 0 0 30px #f00, 0 0 30px #f00;
}
.pickup-hp {
  border-color: green;
  color: green;
  box-shadow: inset 0 0 30px greenyellow, 0 0 30px greenyellow;
}
.pickup-zombie {
  border-color: orange;
  color: orange;
  box-shadow: inset 0 0 30px #ffa500, 0 0 30px #ffa500;
}

/* ---- reveal-* (hidden -> shown FX) ---- */
.reveal-hidden {
  /* hook: subtle idle pulse on unrevealed tiles */
  animation: unrevealedIdlePulse 5s ease-in-out infinite alternate;
}
.reveal-shown {
  /* hook: short-lived class on the frame a tile reveals */
  border-color: #4f8ef7;
  color: #4f8ef7;
  box-shadow: inset 0 0 30px #4f8ef7, 0 0 30px #4f8ef7;
}

/* ---- player-* modifiers ---- */
.player-hiding::before {
  background: radial-gradient(circle at 50% 50%, green 0%, rgba(0, 0, 0, 0.25) 100%);
  z-index: 2;
}
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
.fx-attack { animation: fxAttack 1s ease-out; }
@keyframes fxAttack {
  0%   { zoom: 1;    rotate: 0deg;   opacity: 1;   filter: brightness(1); }
  5%   { zoom: 1.15;                 opacity: 1;   filter: brightness(4) saturate(0%); }
  15%  { zoom: 0.9;  rotate: 12deg;  opacity: 0.8; filter: brightness(0.5) saturate(200%) hue-rotate(30deg); }
  35%  { zoom: 0.7;  rotate: -8deg;  opacity: 0.4; filter: brightness(0.3) saturate(300%); }
  60%  { zoom: 0.75; rotate: 5deg;   opacity: 0.6; }
  100% { zoom: 1;    rotate: 0deg;   opacity: 1;   filter: brightness(1) saturate(100%); }
}

@keyframes unrevealedIdlePulse {
  0% {
    border-color: rgba(247,143,79, 1);
    box-shadow: inset 0 0 10px rgba(247,143,79, .15), 0 0 30px rgba(247,143,79,.15);
  }
  100% {
    border-color: rgba(247,143,79, .5);
    box-shadow: inset 0 0 30px rgba(247,143,79, .5), 0 0 30px rgba(247,143,79,.5);
  }
}

/* ---- Live event ticker ---- */
.ticker {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 50;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  pointer-events: none;
  max-width: 360px;
}
.ticker-entry {
  background: rgba(15, 10, 8, 0.78);
  border: 1px solid rgba(224, 200, 96, 0.35);
  color: #f0e4c8;
  padding: 6px 10px;
  font-size: 0.95rem;
  line-height: 1.2;
  border-radius: 3px;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
}
.ticker-entry[data-type="zombie_killed"] { border-color: rgba(156, 224, 76, 0.6); }
.ticker-entry[data-type="zombie_attack"] { border-color: rgba(255, 90, 90, 0.55); color: #ffd9d9; }
.ticker-entry[data-type="game_won"] { border-color: rgba(79, 142, 247, 0.7); color: #c8dbff; }
.ticker-entry[data-type="game_lost"] { border-color: rgba(122, 43, 43, 0.8); color: #ffcccc; }
.ticker-entry[data-type="hp_pickup"] { border-color: rgba(76, 224, 156, 0.6); color: #d6f5e1; }
.ticker-entry[data-type="weapon_pickup"] { border-color: rgba(224, 200, 96, 0.7); color: #fff2c8; }
.ticker-entry[data-type="door_opened"] { border-color: rgba(156, 224, 76, 0.7); color: #e8ffd0; }
.ticker-entry[data-type="room_entered"] { border-color: rgba(79, 142, 247, 0.55); color: #d4e3ff; }

.ticker-enter-active,
.ticker-leave-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}
.ticker-enter-from {
  opacity: 0;
  transform: translateX(20px);
}
.ticker-leave-to {
  opacity: 0;
  transform: translateX(20px);
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

.pixelated {
  image-rendering: pixelated;
}
</style>
