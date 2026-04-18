<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface GamePayload {
  id: number;
  status: 'waiting' | 'running' | 'won' | 'lost';
  current_round: number;
  player_hp: number;
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

interface Snapshot {
  game: GamePayload;
  joiners: JoinerPayload[];
}

const props = defineProps<{
  broadcasterId: string;
  broadcasterLogin: string;
  snapshot: Snapshot | null;
}>();

const game = ref<GamePayload | null>(props.snapshot?.game ?? null);
const joiners = ref<JoinerPayload[]>(props.snapshot?.joiners ?? []);
const connected = ref(false);
const lastUpdate = ref<number | null>(null);
const now = ref(Date.now());

let channel: any = null;
let tickInterval: ReturnType<typeof setInterval> | null = null;

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

function readableVote(vote: string | null): string {
  if (!vote) return '-';
  if (vote === 'h') return 'hide';
  if (vote === 'a') return 'attack';
  if (vote.startsWith('a:')) return `attack slot ${vote.slice(2)}`;
  if (vote.startsWith('p:')) {
    const arrows: Record<string, string> = { left: '<-', right: '->', up: '^', down: 'v' };
    const dir = vote.slice(2);
    return `${arrows[dir] ?? ''} ${dir}`.trim();
  }
  return vote;
}

const grouped = computed(() => ({
  pending: joiners.value.filter((j) => j.status === 'pending'),
  active: joiners.value.filter((j) => j.status === 'active'),
  inactive: joiners.value.filter((j) => j.status === 'inactive'),
}));

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
    lastUpdate.value = payload.updated_at;
  });
});

onUnmounted(() => {
  document.documentElement.classList.remove('gamejam-fullbleed');
  if (tickInterval) clearInterval(tickInterval);
  if (channel) channel.stopListening('.gamejam.state');
});
</script>

<template>
  <div class="live-board">
    <header class="board-header">
      <div class="title">
        <h1>Chat Castle</h1>
        <span class="login">@{{ broadcasterLogin }}</span>
      </div>
      <div class="stats" v-if="game">
        <div class="stat">
          <span class="label">Status</span>
          <span class="value status" :class="`status-${game.status}`">{{ game.status }}</span>
        </div>
        <div class="stat">
          <span class="label">Round</span>
          <span class="value">{{ game.current_round }}</span>
        </div>
        <div class="stat">
          <span class="label">HP Pool</span>
          <span class="value hp">{{ game.player_hp }}</span>
        </div>
        <div class="stat">
          <span class="label">Joiners</span>
          <span class="value">{{ joiners.length }}</span>
        </div>
      </div>
      <div v-else class="empty-state">No active game. Run <code>php artisan gamejam:start {{ broadcasterLogin }}</code>.</div>
      <div class="conn" :class="{ on: connected }">
        <span class="dot"></span>
        {{ connected ? 'live' : 'offline' }}
      </div>
    </header>

    <section v-if="game" class="resolver-row">
      <div class="resolver-card countdown">
        <span class="label">Next tick</span>
        <span class="value" :class="{ urgent: (secondsUntilNextTick ?? 99) < 5 }">
          {{ secondsUntilNextTick !== null ? `${secondsUntilNextTick}s` : '-' }}
        </span>
        <span class="sub">round of {{ game.round_duration_seconds }}s</span>
      </div>
      <div class="resolver-card resolved">
        <span class="label">Last round resolved</span>
        <span class="value">{{ game.last_resolved_action ? readableVote(game.last_resolved_action) : 'nothing yet' }}</span>
        <div v-if="lastResolvedTallyEntries.length" class="tally">
          <span v-for="[action, count] in lastResolvedTallyEntries" :key="action" class="tally-entry">
            {{ readableVote(action) }}: <b>{{ count }}</b>
          </span>
        </div>
        <span v-else class="sub">no votes were cast</span>
      </div>
    </section>

    <div v-if="game" class="columns">
      <section class="col">
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
          <li v-if="!grouped.active.length" class="placeholder">no active voters yet</li>
        </ul>
      </section>

      <section class="col">
        <h2>Pending <span class="count">{{ grouped.pending.length }}</span></h2>
        <ul>
          <li v-for="j in grouped.pending" :key="j.twitch_user_id" class="joiner">
            <div class="name">{{ j.username }}</div>
            <div class="vote dim">joined r{{ j.joined_round }} - votes next round</div>
          </li>
          <li v-if="!grouped.pending.length" class="placeholder">nobody waiting</li>
        </ul>
      </section>

      <section class="col">
        <h2>Inactive <span class="count">{{ grouped.inactive.length }}</span></h2>
        <ul>
          <li v-for="j in grouped.inactive" :key="j.twitch_user_id" class="joiner dim">
            <div class="name">{{ j.username }}</div>
            <div class="vote">left at r{{ j.last_vote_round ?? j.joined_round }}</div>
          </li>
          <li v-if="!grouped.inactive.length" class="placeholder">none</li>
        </ul>
      </section>
    </div>
  </div>
</template>

<style scoped>
.live-board {
  min-height: 100%;
  padding: 1.5rem;
  color: #eee;
  background: #0e0e10;
  font-family: system-ui, sans-serif;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
.board-header {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 1rem;
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
.stats {
  display: flex;
  gap: 1.5rem;
}
.stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 60px;
}
.stat .label {
  font-size: 0.7rem;
  text-transform: uppercase;
  color: #888;
  letter-spacing: 0.05em;
}
.stat .value {
  font-size: 1.5rem;
  font-weight: 700;
}
.stat .value.hp {
  color: #ff5a5a;
}
.stat .status {
  font-size: 1rem;
  padding: 0.1rem 0.5rem;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.status-running {
  background: #2a9d90;
  color: #fff;
}
.status-waiting {
  background: #b0823d;
  color: #fff;
}
.status-won {
  background: #4f8ef7;
  color: #fff;
}
.status-lost {
  background: #7a2b2b;
  color: #fff;
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
.empty-state {
  color: #888;
  font-size: 0.9rem;
}
.empty-state code {
  background: #1a1a1a;
  padding: 0.1rem 0.4rem;
  border-radius: 3px;
  color: #ccc;
}
.resolver-row {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 1rem;
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
  font-size: 1.5rem;
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
  gap: 0.5rem 1rem;
  margin-top: 0.25rem;
  font-size: 0.85rem;
  color: #bbb;
}
.tally-entry b {
  color: #2a9d90;
}
.columns {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  flex: 1;
  min-height: 0;
}
.col {
  background: #1a1a1a;
  border-radius: 6px;
  padding: 1rem;
  overflow-y: auto;
}
.col h2 {
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #aaa;
  margin: 0 0 0.75rem;
  display: flex;
  justify-content: space-between;
}
.col h2 .count {
  color: #2a9d90;
  font-weight: 700;
}
.col ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.joiner {
  background: #242424;
  padding: 0.6rem 0.75rem;
  border-radius: 4px;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.joiner.dim {
  opacity: 0.5;
}
.joiner .name {
  font-weight: 600;
  font-size: 0.95rem;
}
.joiner .vote {
  font-size: 0.9rem;
  color: #2a9d90;
}
.joiner .vote.dim {
  color: #888;
}
.joiner .meta {
  display: flex;
  gap: 0.75rem;
  font-size: 0.75rem;
  color: #888;
}
.placeholder {
  color: #555;
  font-style: italic;
  font-size: 0.85rem;
}
@media (max-width: 900px) {
  .columns {
    grid-template-columns: 1fr;
  }
  .board-header {
    grid-template-columns: 1fr;
  }
  .resolver-row {
    grid-template-columns: 1fr;
  }
  .stats {
    flex-wrap: wrap;
  }
}
</style>
