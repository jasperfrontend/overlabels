<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { MapPin, Clock, Gauge, Mountain, Battery, Radio, Map } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

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
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'GPS Sessions', href: '/dashboard/gps-sessions' },
];

const page = usePage();
const userLocale = computed<string>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.locale || 'en-US';
});

function formatSpeed(ms: number | null): string {
  if (ms === null) return '-';
  const converted = props.speedUnit === 'mph'
    ? (ms * 3.6) / 1.609344
    : ms * 3.6;
  return new Intl.NumberFormat(userLocale.value, { maximumFractionDigits: 1 }).format(converted);
}

const speedLabel = computed(() => props.speedUnit === 'mph' ? 'mph' : 'km/h');

function formatDistance(km: number): string {
  if (props.speedUnit === 'mph') {
    const miles = km / 1.609344;
    return new Intl.NumberFormat(userLocale.value, { maximumFractionDigits: 2 }).format(miles) + ' mi';
  }
  return new Intl.NumberFormat(userLocale.value, { maximumFractionDigits: 2 }).format(km) + ' km';
}

function formatAltitude(m: number | null): string {
  if (m === null) return '-';
  return new Intl.NumberFormat(userLocale.value, { maximumFractionDigits: 1 }).format(m) + ' m';
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(userLocale.value, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString(userLocale.value, {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function formatDuration(startIso: string, endIso: string): string {
  const ms = new Date(endIso).getTime() - new Date(startIso).getTime();
  const totalSeconds = Math.floor(ms / 1000);
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (hours > 0) {
    return `${hours}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
  }
  return `${minutes}m ${String(seconds).padStart(2, '0')}s`;
}

function batteryDelta(start: number | null, end: number | null): string {
  if (start === null || end === null) return '';
  const diff = end - start;
  if (diff > 0) return `+${diff}%`;
  if (diff < 0) return `${diff}%`;
  return '0%';
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

function batteryColor(pct: number | null): string {
  if (pct === null) return 'text-muted-foreground';
  if (pct <= 15) return 'text-red-500';
  if (pct <= 30) return 'text-amber-500';
  return 'text-green-500';
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

      <p v-if="sessions.length === 0" class="text-muted-foreground text-sm">
        No GPS sessions yet. Connect the Overlabels GPS app and start tracking to see your sessions here.
      </p>

      <div class="space-y-4">
        <div
          v-for="session in sessions"
          :key="session.session_id"
          class="rounded-lg border bg-card p-4 space-y-3"
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

          <!-- Map toggle -->
          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="toggleMap(session.session_id)">
              <Map class="h-3.5 w-3.5 mr-1.5" />
              {{ expandedSessions.has(session.session_id) ? 'Hide map' : 'View map' }}
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
  </AppLayout>
</template>
