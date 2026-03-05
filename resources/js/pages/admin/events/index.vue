<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { ref, watch } from 'vue';

interface TwitchEvent {
  id: number;
  event_type: string;
  processed: boolean;
  created_at: string;
  user: { id: number; name: string; twitch_id: string | null } | null;
}

interface ExternalEvent {
  id: number;
  service: string;
  event_type: string;
  controls_updated: boolean;
  alert_dispatched: boolean;
  created_at: string;
  user: { id: number; name: string; twitch_id: string | null } | null;
}

interface Paginator {
  data: (TwitchEvent | ExternalEvent)[];
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  events: Paginator;
  eventTypes: string[];
  source: 'twitch' | 'external';
  filters: {
    event_type?: string;
    processed?: boolean;
    user_id?: number;
    from?: string;
    to?: string;
    source?: string;
  };
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Events', href: route('admin.events.index') },
];

const eventType = ref(props.filters.event_type ?? '');
const processed = ref(props.filters.processed ?? '');

let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
  router.get(route('admin.events.index'), {
    source: props.source,
    event_type: eventType.value || undefined,
    processed: props.source === 'twitch' && processed.value !== '' ? processed.value : undefined,
  }, { preserveState: true, replace: true });
}

watch([eventType, processed], () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 300);
});

const page = usePage<{ flash?: { message?: string } }>();

const prunePeriod = ref('90');
const showPruneConfirm = ref(false);

function submitPrune() {
  router.delete(route('admin.events.prune'), {
    data: { period: prunePeriod.value, source: props.source },
    onSuccess: () => { showPruneConfirm.value = false; },
  });
}

watch([prunePeriod, () => props.source], () => { showPruneConfirm.value = false; });
</script>

<template>
  <Head><title>Admin — Events</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Events" title-class="text-2xl font-bold">
        <template #actions>
          <span class="text-sm text-muted-foreground">{{ events.total }} total</span>
        </template>
      </PageHeader>

      <div v-if="page.props.flash?.message" class="rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-700 dark:bg-green-950 dark:text-green-300">
        {{ page.props.flash.message }}
      </div>

      <!-- Source tabs -->
      <div class="flex gap-1">
        <a :href="route('admin.events.index', { source: 'twitch' })"
           :class="source === 'twitch' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
           class="rounded border px-3 py-1 text-sm">Twitch</a>
        <a :href="route('admin.events.index', { source: 'external' })"
           :class="source === 'external' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
           class="rounded border px-3 py-1 text-sm">External</a>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <select v-model="eventType" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="">All event types</option>
          <option v-for="et in eventTypes" :key="et" :value="et">{{ et }}</option>
        </select>
        <select v-if="source === 'twitch'" v-model="processed" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="">All</option>
          <option value="true">Processed</option>
          <option value="false">Pending</option>
        </select>
      </div>

      <!-- Prune bar -->
      <div class="flex items-center gap-2 rounded border border-destructive/30 bg-destructive/5 px-3 py-2">
        <span class="text-sm text-muted-foreground">Prune {{ source }} events older than</span>
        <select v-model="prunePeriod" class="rounded border px-2 py-1 text-sm bg-background">
          <option value="30">30 days</option>
          <option value="60">60 days</option>
          <option value="90">90 days</option>
          <option value="all">All records</option>
        </select>
        <template v-if="!showPruneConfirm">
          <button
            class="rounded border border-destructive px-3 py-1 text-sm text-destructive hover:bg-destructive hover:text-destructive-foreground"
            @click="showPruneConfirm = true"
          >
            Prune
          </button>
        </template>
        <template v-else>
          <span class="text-sm font-medium text-destructive">
            {{ prunePeriod === 'all' ? `Delete ALL ${source} event records?` : `Delete all ${source} events older than ${prunePeriod} days?` }}
          </span>
          <button class="rounded border border-destructive bg-destructive px-3 py-1 text-sm text-destructive-foreground hover:bg-destructive/90" @click="submitPrune">
            Yes, prune
          </button>
          <button class="rounded border px-3 py-1 text-sm hover:bg-muted" @click="showPruneConfirm = false">
            Cancel
          </button>
        </template>
      </div>

      <!-- Twitch events table -->
      <div v-if="source === 'twitch'" class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Type</th>
              <th class="px-3 py-2">User</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Created</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in (events.data as TwitchEvent[])" :key="event.id" class="border-t">
              <td class="px-3 py-2 font-mono text-xs">{{ event.event_type }}</td>
              <td class="px-3 py-2">
                <a v-if="event.user" :href="route('admin.users.show', event.user.id)" class="hover:underline">{{ event.user.name }}</a>
                <span v-else class="text-muted-foreground">—</span>
              </td>
              <td class="px-3 py-2">
                <Badge :variant="event.processed ? 'default' : 'secondary'">{{ event.processed ? 'processed' : 'pending' }}</Badge>
              </td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ event.created_at }}</td>
              <td class="px-3 py-2">
                <a :href="route('admin.events.show', event.id)" class="text-primary text-xs hover:underline">View</a>
              </td>
            </tr>
            <tr v-if="events.data.length === 0">
              <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">No events found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- External events table -->
      <div v-else class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Service</th>
              <th class="px-3 py-2">Type</th>
              <th class="px-3 py-2">User</th>
              <th class="px-3 py-2">Controls</th>
              <th class="px-3 py-2">Alert</th>
              <th class="px-3 py-2">Created</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in (events.data as ExternalEvent[])" :key="event.id" class="border-t">
              <td class="px-3 py-2">
                <Badge variant="outline" class="font-mono text-xs">{{ event.service }}</Badge>
              </td>
              <td class="px-3 py-2 font-mono text-xs">{{ event.event_type }}</td>
              <td class="px-3 py-2">
                <a v-if="event.user" :href="route('admin.users.show', event.user.id)" class="hover:underline">{{ event.user.name }}</a>
                <span v-else class="text-muted-foreground">—</span>
              </td>
              <td class="px-3 py-2">
                <Badge :variant="event.controls_updated ? 'default' : 'secondary'">{{ event.controls_updated ? '✓' : '✗' }}</Badge>
              </td>
              <td class="px-3 py-2">
                <Badge :variant="event.alert_dispatched ? 'default' : 'secondary'">{{ event.alert_dispatched ? '✓' : '✗' }}</Badge>
              </td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ event.created_at }}</td>
              <td class="px-3 py-2">
                <a :href="route('admin.events.external.show', event.id)" class="text-primary text-xs hover:underline">View</a>
              </td>
            </tr>
            <tr v-if="events.data.length === 0">
              <td colspan="7" class="px-3 py-6 text-center text-muted-foreground">No external events found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex gap-1">
        <template v-for="link in events.links" :key="link.label">
          <a v-if="link.url" :href="link.url" class="rounded border px-3 py-1 text-sm"
            :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'" v-html="link.label" />
          <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
