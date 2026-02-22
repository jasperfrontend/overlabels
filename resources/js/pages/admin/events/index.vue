<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { ref, watch } from 'vue';

interface TwitchEvent {
  id: number;
  event_type: string;
  processed: boolean;
  created_at: string;
  user: { id: number; name: string; twitch_id: string | null } | null;
}

interface Paginator {
  data: TwitchEvent[];
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  events: Paginator;
  eventTypes: string[];
  filters: {
    event_type?: string;
    processed?: boolean;
    user_id?: number;
    from?: string;
    to?: string;
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
    event_type: eventType.value || undefined,
    processed: processed.value === '' ? undefined : processed.value,
  }, { preserveState: true, replace: true });
}

watch([eventType, processed], () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 300);
});
</script>

<template>
  <Head><title>Admin — Events</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Twitch Events</h1>
        <span class="text-sm text-muted-foreground">{{ events.total }} total</span>
      </div>

      <div class="flex flex-wrap gap-2">
        <select v-model="eventType" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="">All event types</option>
          <option v-for="et in eventTypes" :key="et" :value="et">{{ et }}</option>
        </select>
        <select v-model="processed" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="">All</option>
          <option value="true">Processed</option>
          <option value="false">Pending</option>
        </select>
      </div>

      <div class="overflow-x-auto rounded border">
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
            <tr v-for="event in events.data" :key="event.id" class="border-t">
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
