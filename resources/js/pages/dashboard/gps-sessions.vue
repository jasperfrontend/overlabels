<script setup lang="ts">
import { computed, defineAsyncComponent, ref, toRef } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { MapPin, Clock, Gauge, Mountain, Battery, Radio, Map, Trash2, ExternalLink } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import { useSessionDataFormatter } from '@/composables/useSessionDataFormatter';


const SessionMapInline = defineAsyncComponent(() => import('@/components/SessionMapInline.vue'));

interface GpsSession {
  session_id: string;
  started_at: string;
  ended_at: string;
  completed: boolean;
  ping_count: number;
  max_speed_ms: number | null;
  avg_speed_ms: number | null;
  min_altitude: number | null;
  max_altitude: number | null;
  battery_start: number | null;
  battery_end: number | null;
  distance_km: number;
}

const props = defineProps<{
  sessions: GpsSession[];
  speedUnit: string;
  mapSharingEnabled: boolean;
  mapSlug: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'GPS Sessions', href: '/dashboard/gps-sessions' },
];

const {
  userLocale,
  formatDate,
  formatTime,
  formatDuration,
  formatSpeed,
  formatAltitude,
  formatDistance,
  batteryDelta,
  batteryColor,
} = useSessionDataFormatter({ speedUnit: toRef(props, 'speedUnit') });

const speedLabel = computed(() => props.speedUnit === 'mph' ? 'mph' : 'km/h');
const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const deleting = ref<string | null>(null);

async function deleteSession(sessionId: string) {
  if (!confirm('Delete this session and all its GPS data? This cannot be undone.')) return;
  deleting.value = sessionId;
  try {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const res = await fetch(`/dashboard/gps-sessions/${sessionId}`, {
      method: 'DELETE',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (res.ok) {
      toastMessage.value = `Session deleted (${data.deleted} events removed).`;
      toastType.value = 'success';
      router.reload({ only: ['sessions'] });
    } else {
      toastMessage.value = data.error ?? 'Failed to delete session.';
      toastType.value = 'error';
    }
  } catch {
    toastMessage.value = 'Failed to delete session.';
    toastType.value = 'error';
  } finally {
    deleting.value = null;
  }
}

const expandedSessions = ref<Set<string>>(new Set());

function toggleMap(sessionId: string) {
  if (expandedSessions.value.has(sessionId)) {
    expandedSessions.value.delete(sessionId);
  } else {
    expandedSessions.value.add(sessionId);
  }
  // Force reactivity
  expandedSessions.value = new Set(expandedSessions.value);
}

</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="GPS Sessions" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
      <div class="flex items-center gap-3">
        <MapPin class="h-6 w-6" />
        <Heading title="GPS Sessions" />
      </div>

      <div v-if="sessions.length === 0" class="text-foreground flex flex-col gap-2 text-sm max-w-2xl">
        <p>No GPS sessions yet. Connect the Overlabels GPS app and start tracking to see your sessions here.</p>
        <h3 class="mt-4 text-xl font-bold">How to connect the app</h3>
        <ul>
          <li>Step 1: you can't yet.</li>
        </ul>

        <h3 class="mt-4 text-xl font-bold">Why? What's wrong?</h3>
        <p>The app is great, it's stable and works well. It's pretty efficient on your battery and has all kinds of
        stuff built in to keep your map logging as efficient and perfect as we possibly can. But&hellip;</p>
        <p>I can't submit the app to the Google Play Store without either verifying my business through a US-based
        third party or have my full home address leaked on the app page in the Play Store - And neither of those
        two things are going to happen anytime soon.</p>
        <p>So yeah, you're out of luck until Google changes their store policies - which they won't.</p>
      </div>

      <div class="space-y-4">
        <div
          v-for="session in sessions"
          :key="session.session_id"
          class="border border-sidebar-border bg-card p-4 space-y-3"
        >
          <!-- Header: date + status -->
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
              <span class="font-medium text-foreground">{{ formatDate(session.started_at) }}</span>
              <span class="text-sm text-muted-foreground">{{ formatTime(session.started_at) }}</span>
              <span class="text-sm text-muted-foreground">-</span>
              <span class="text-sm text-muted-foreground">{{ formatTime(session.ended_at) }}</span>
            </div>
            <div class="flex items-center gap-2">
              <Badge v-if="session.completed" variant="default" class="bg-green-400 hover:bg-green-400">Completed</Badge>
              <Badge v-else variant="secondary" class="bg-amber-400 hover:bg-amber-400 text-primary-foreground">In progress</Badge>
            </div>
          </div>

          <!-- Stats grid -->
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <!-- Duration -->
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Clock class="h-3 w-3" /> Duration
              </div>
              <p class="text-sm font-medium text-foreground">{{ formatDuration(session.started_at, session.ended_at) }}</p>
            </div>

            <!-- Distance -->
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <MapPin class="h-3 w-3" /> Distance
              </div>
              <p class="text-sm font-medium text-foreground">{{ formatDistance(session.distance_km) }}</p>
            </div>

            <!-- Speed -->
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Gauge class="h-3 w-3" /> Speed
              </div>
              <p class="text-sm font-medium text-foreground">
                <span class="text-muted-foreground text-xs">avg</span> {{ formatSpeed(session.avg_speed_ms) }}
                <span class="text-muted-foreground text-xs ml-1">max</span> {{ formatSpeed(session.max_speed_ms) }}
                <span class="text-xs text-muted-foreground ml-0.5">{{ speedLabel }}</span>
              </p>
            </div>

            <!-- Elevation -->
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Mountain class="h-3 w-3" /> Elevation
              </div>
              <p class="text-sm font-medium text-foreground">
                {{ formatAltitude(session.min_altitude) }}
                <span v-if="session.min_altitude !== session.max_altitude" class="text-muted-foreground text-xs mx-0.5">-</span>
                <span v-if="session.min_altitude !== session.max_altitude">{{ formatAltitude(session.max_altitude) }}</span>
              </p>
            </div>

            <!-- Battery -->
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Battery class="h-3 w-3" /> Battery
              </div>
              <p v-if="session.battery_start !== null" class="text-sm font-medium">
                <span :class="batteryColor(session.battery_start)">{{ session.battery_start }}%</span>
                <span class="text-muted-foreground mx-1">&rarr;</span>
                <span :class="batteryColor(session.battery_end)">{{ session.battery_end }}%</span>
                <span class="text-xs text-muted-foreground ml-1">({{ batteryDelta(session.battery_start, session.battery_end) }})</span>
              </p>
              <p v-else class="text-sm text-muted-foreground">-</p>
            </div>

            <!-- Pings -->
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Radio class="h-3 w-3" /> Pings
              </div>
              <p class="text-sm font-medium text-foreground">{{ session.ping_count.toLocaleString(userLocale) }}</p>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="toggleMap(session.session_id)">
              <Map class="h-3.5 w-3.5 mr-1.5" />
              {{ expandedSessions.has(session.session_id) ? 'Hide map' : 'View map' }}
            </Button>
            <Button
              v-if="mapSharingEnabled"
              as="a"
              :href="`/map/${mapSlug}/${session.session_id}`"
              target="_blank"
              rel="noopener"
              variant="outline"
              size="sm"
              title="Open this session on the public full-view map in a new tab"
            >
              <ExternalLink class="h-3.5 w-3.5 mr-1.5" />
              Open full view
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="text-destructive hover:text-destructive"
              :disabled="deleting === session.session_id"
              @click="deleteSession(session.session_id)"
            >
              <Trash2 class="h-3.5 w-3.5 mr-1.5" />
              {{ deleting === session.session_id ? 'Deleting...' : 'Delete' }}
            </Button>
          </div>

          <!-- Inline map -->
          <SessionMapInline
            v-if="expandedSessions.has(session.session_id)"
            :session-id="session.session_id"
          />
        </div>
      </div>
    </div>

    <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
