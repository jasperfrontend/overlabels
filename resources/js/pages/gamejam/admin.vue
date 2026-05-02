<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItem } from '@/types';

interface GameSnapshot {
  id: number;
  status: 'waiting' | 'running' | 'won' | 'lost';
  current_round: number;
  player_hp: number;
  round_duration_seconds: number;
  round_started_at: string | null;
}

interface RateLimitEvent {
  scope: string;
  login: string | null;
  ip: string;
  at: string;
}

const props = defineProps<{
  game: GameSnapshot | null;
  debugEnabled: boolean;
  broadcasterLogin: string;
  recentRateLimits: RateLimitEvent[];
}>();

const page = usePage();
const flash = computed(() => (page.props.flash ?? {}) as { message?: string; type?: 'success' | 'error' });

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Chat Castle Admin', href: '/gamejam/admin' },
];

const hp = ref<number>(10);
const roundDuration = ref<number>(30);
const endStatus = ref<'won' | 'lost'>('won');
const working = ref(false);

const isActive = computed(() => !!props.game && props.game.status === 'running');

function start() {
  if (working.value) return;
  working.value = true;
  router.post(
    '/gamejam/admin/start',
    { hp: hp.value, round_duration: roundDuration.value },
    { preserveScroll: true, onFinish: () => (working.value = false) }
  );
}

function end() {
  if (working.value) return;
  working.value = true;
  router.post(
    '/gamejam/admin/end',
    { status: endStatus.value },
    { preserveScroll: true, onFinish: () => (working.value = false) }
  );
}

function toggleDebug() {
  if (working.value) return;
  working.value = true;
  router.post(
    '/gamejam/admin/debug/toggle',
    {},
    { preserveScroll: true, onFinish: () => (working.value = false) }
  );
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Chat Castle Admin" />

    <div class="space-y-8 p-6 max-w-3xl">
      <HeadingSmall
        title="Chat Castle control panel"
        :description="`Controls for ${broadcasterLogin}'s game. Mirrors the gamejam:* artisan commands.`"
      />

      <div
        v-if="flash.message"
        class="rounded-sm border p-3 text-sm"
        :class="flash.type === 'error'
          ? 'border-red-500/30 bg-red-500/5 text-red-500'
          : 'border-green-500/30 bg-green-500/5 text-green-500'"
      >
        {{ flash.message }}
      </div>

      <!-- Current state -->
      <section class="rounded-sm border border-border p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-foreground font-medium">Current game</h3>
          <Badge v-if="isActive" class="bg-green-400 hover:bg-green-400">Running</Badge>
          <Badge v-else-if="game" variant="secondary">{{ game.status }}</Badge>
          <Badge v-else variant="secondary">No game</Badge>
        </div>
        <dl v-if="game" class="grid grid-cols-2 gap-y-2 text-sm text-foreground">
          <dt class="text-muted-foreground">Game ID</dt>
          <dd>#{{ game.id }}</dd>
          <dt class="text-muted-foreground">Round</dt>
          <dd>{{ game.current_round }}</dd>
          <dt class="text-muted-foreground">Player HP</dt>
          <dd>{{ game.player_hp }}</dd>
          <dt class="text-muted-foreground">Round duration</dt>
          <dd>{{ game.round_duration_seconds }}s</dd>
        </dl>
        <p v-else class="text-sm text-muted-foreground">No active game. Start one below.</p>
      </section>

      <!-- Start game -->
      <section class="rounded-sm border border-border p-4 space-y-4">
        <h3 class="text-foreground font-medium">Start a new game</h3>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label for="hp">Player HP</Label>
            <Input id="hp" v-model.number="hp" type="number" min="1" max="100" :disabled="isActive" />
          </div>
          <div class="space-y-2">
            <Label for="round_duration">Round duration (seconds)</Label>
            <Input id="round_duration" v-model.number="roundDuration" type="number" min="5" max="600" :disabled="isActive" />
          </div>
        </div>
        <Button class="cursor-pointer" :disabled="isActive || working" @click="start">
          Start game
        </Button>
        <p v-if="isActive" class="text-xs text-muted-foreground">End the current game before starting a new one.</p>
      </section>

      <!-- End game -->
      <section class="rounded-sm border border-border p-4 space-y-4">
        <h3 class="text-foreground font-medium">End current game</h3>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 cursor-pointer text-foreground">
            <input type="radio" value="won" v-model="endStatus" /> Won
          </label>
          <label class="flex items-center gap-2 cursor-pointer text-foreground">
            <input type="radio" value="lost" v-model="endStatus" /> Lost
          </label>
        </div>
        <Button class="cursor-pointer" variant="destructive" :disabled="!isActive || working" @click="end">
          End game as {{ endStatus }}
        </Button>
      </section>

      <!-- Rate-limit events -->
      <section class="rounded-sm border border-border p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-foreground font-medium">Recent rate-limited (429) requests</h3>
          <Badge v-if="recentRateLimits.length" variant="destructive">{{ recentRateLimits.length }}</Badge>
          <Badge v-else variant="secondary">None</Badge>
        </div>
        <p class="text-xs text-muted-foreground">
          Each entry is one bot request the backend rejected with 429. Chat sees "something went wrong" for these. If this list fills up during a stream, raise the per-minute caps in <code>AppServiceProvider</code>.
        </p>
        <ul v-if="recentRateLimits.length" class="text-xs text-foreground space-y-1 max-h-64 overflow-y-auto font-mono">
          <li v-for="(ev, i) in recentRateLimits" :key="i">
            <span class="text-muted-foreground">{{ ev.at }}</span>
            <span class="ml-2">{{ ev.scope }}</span>
            <span v-if="ev.login" class="ml-2 text-muted-foreground">@{{ ev.login }}</span>
          </li>
        </ul>
      </section>

      <!-- Debug toggle -->
      <section class="rounded-sm border border-border p-4 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-foreground font-medium">Live-board debug panel</h3>
            <p class="text-sm text-muted-foreground">Shows the on-overlay debug panel for this broadcaster.</p>
          </div>
          <Badge v-if="debugEnabled" class="bg-green-400 hover:bg-green-400">On</Badge>
          <Badge v-else variant="secondary">Off</Badge>
        </div>
        <Button class="cursor-pointer" variant="secondary" :disabled="working" @click="toggleDebug">
          Toggle debug panel
        </Button>
      </section>
    </div>
  </AppLayout>
</template>
