<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { LMap, LTileLayer, LMarker, LPolyline, LPopup } from '@vue-leaflet/vue-leaflet';
import { useMapWebSocket } from './composables/useMapWebSocket';
import { Icon, type PointExpression } from 'leaflet';

const props = defineProps<{
  twitchId: string;
  streamerName: string;
  delay: number;
  speedUnit: string;
  isLive: boolean;
}>();

const zoom = ref(15);
const center = ref<[number, number]>([0, 0]);
const trail = ref<[number, number][]>([]);
const hasPosition = ref(false);
const loading = ref(true);
const isLive = ref(props.isLive);
const mapRef = ref<InstanceType<typeof LMap> | null>(null);

const MAX_TRAIL_POINTS = 200;

// Always subscribe to WebSocket. In delay mode we ignore the position values
// for display (polling handles that), but we still use WebSocket signals to
// flip live/offline state in realtime.
const { position: wsPosition, connected, trackingActive } = useMapWebSocket(props.twitchId);

// The position ref the map actually renders from.
const position = ref<{ lat: number; lng: number; speed: number; bearing: number } | null>(null);

// WebSocket lat/lng arrivals have two roles:
// 1) Trigger: any lat/lng means a location_update fired, so the session left
//    the safe zone (or was never in one) -> we're live.
// 2) Display: only when delay === 0. When delay > 0 the coords are shown via
//    the polling path to honor the configured delay.
watch(wsPosition, (pos) => {
  if (!pos) return;
  isLive.value = true;
  if (props.delay === 0) {
    position.value = pos;
  }
});

// session_end (gps_tracking = '0') flips back to offline and clears everything
// so the next session starts fresh.
watch(trackingActive, (active) => {
  if (active === false) {
    goOffline();
  }
});

function goOffline() {
  isLive.value = false;
  position.value = null;
  trail.value = [];
  hasPosition.value = false;
}

// Keep the tab title in sync with live state so a refreshed tab doesn't
// keep showing the streamer's name after they've gone offline, and updates
// to show it when a viewer is watching through a live transition.
watch(isLive, (live) => {
  document.title = live
    ? `${props.streamerName}'s live location - Overlabels`
    : 'Live location - Overlabels';
});

// Initial position fetch - only when the server says we're live.
onMounted(async () => {
  if (!isLive.value) {
    loading.value = false;
    return;
  }

  try {
    const res = await fetch(`/api/map/${props.twitchId}/position`);
    if (res.ok) {
      const data = await res.json();
      if (data.position) {
        center.value = [data.position.lat, data.position.lng];
        trail.value = [[data.position.lat, data.position.lng]];
        hasPosition.value = true;
      }
    }
  } catch {
    // Silently fail - WebSocket will catch us up.
  } finally {
    loading.value = false;
  }
});

// Polling for delayed position. Runs always; the callback early-exits when
// not live so we don't render stale positions from before session_start.
let pollInterval: ReturnType<typeof setInterval> | null = null;
if (props.delay > 0) {
  pollInterval = setInterval(async () => {
    if (!isLive.value) return;
    try {
      const res = await fetch(`/api/map/${props.twitchId}/position`);
      if (res.ok) {
        const data = await res.json();
        if (data.position) {
          position.value = {
            lat: data.position.lat,
            lng: data.position.lng,
            speed: data.position.speed,
            bearing: data.position.bearing,
          };
        }
      }
    } catch {
      // Silent
    }
  }, 5000);
}

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
});

watch(position, (pos) => {
  if (!pos) return;
  const latlng: [number, number] = [pos.lat, pos.lng];
  center.value = latlng;
  hasPosition.value = true;

  trail.value = [...trail.value, latlng].slice(-MAX_TRAIL_POINTS);

  // Pan map smoothly
  if (mapRef.value?.leafletObject) {
    mapRef.value.leafletObject.panTo(latlng, { animate: true, duration: 1 });
  }
});

function formatSpeed(ms: number): string {
  const converted = props.speedUnit === 'mph' ? (ms * 3.6) / 1.609344 : ms * 3.6;
  return converted.toFixed(1) + (props.speedUnit === 'mph' ? ' mph' : ' km/h');
}

const markerAnchor: PointExpression = [12, 41];
const markerIcon = new Icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: markerAnchor,
});
</script>

<template>
  <div class="map-container">
    <!-- Header -->
    <div class="map-header">
      <span class="map-logo">Overlabels</span>
      <span class="map-title">{{ isLive ? streamerName + "'s live location" : 'Live location' }}</span>
      <span v-if="delay > 0 && isLive" class="map-delay">{{ delay }}s delay</span>
      <span v-if="isLive && connected" class="map-status live">LIVE</span>
      <span v-else-if="isLive && hasPosition" class="map-status">Connected</span>
      <span v-else class="map-status offline">OFFLINE</span>
    </div>

    <!-- Offline panel: shown when no active session with location data.
         Flips to the live map automatically when the first location_update
         arrives over WebSocket. -->
    <div v-if="!isLive" class="map-offline">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 12.55a11 11 0 0 1 14.08 0" />
        <path d="M1.42 9a16 16 0 0 1 21.16 0" />
        <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
        <line x1="12" y1="20" x2="12.01" y2="20" />
        <line x1="2" y1="2" x2="22" y2="22" />
      </svg>
      <h1>Nothing to show right now</h1>
      <p>This map will come to life as soon as a live stream begins broadcasting GPS.</p>
      <p class="map-offline-hint">
        <small>Waiting for a signal. The page will update automatically when it arrives.</small>
      </p>
    </div>

    <div v-else-if="loading" class="map-loading">Loading map...</div>

    <l-map
      v-else
      ref="mapRef"
      :zoom="zoom"
      :center="center"
      :use-global-leaflet="false"
      class="map-leaflet"
    >
      <l-tile-layer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        attribution="&copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a>"
        :max-zoom="19"
      />

      <!-- Trail polyline -->
      <l-polyline
        v-if="trail.length > 1"
        :lat-lngs="trail"
        :color="'#7c3aed'"
        :weight="4"
        :opacity="0.8"
      />

      <!-- Current position marker -->
      <l-marker
        v-if="hasPosition"
        :lat-lng="center"
        :icon="markerIcon"
      >
        <l-popup>
          <div style="font-family: system-ui; font-size: 13px;">
            <strong>{{ streamerName }}</strong><br>
            <span v-if="position">{{ formatSpeed(position.speed) }}</span>
          </div>
        </l-popup>
      </l-marker>
    </l-map>
  </div>
</template>

<style scoped>
.map-container {
  display: flex;
  flex-direction: column;
  height: 100vh;
  width: 100vw;
  background: #0a0a0a;
}
.map-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  background: #171717;
  border-bottom: 1px solid #262626;
  font-family: system-ui, -apple-system, sans-serif;
  color: #e5e5e5;
  font-size: 14px;
  flex-shrink: 0;
}
.map-logo {
  font-weight: 600;
  color: #a78bfa;
}
.map-title {
  flex: 1;
}
.map-delay {
  background: #422006;
  color: #fbbf24;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}
.map-status {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #737373;
}
.map-status.live {
  color: #22c55e;
  animation: pulse 2s ease-in-out infinite;
}
.map-status.offline {
  color: #737373;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
.map-loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #737373;
  font-family: system-ui;
}
.map-leaflet {
  flex: 1;
  z-index: 0;
}
.map-offline {
  flex: 1;
  display: grid;
  place-content: center;
  text-align: center;
  padding: 1.5rem;
  background: #2f2e42;
  color: #eae9f6;
  font-family: system-ui, -apple-system, sans-serif;
}
.map-offline svg {
  width: 64px;
  height: 64px;
  display: block;
  margin: 0 auto 1.25rem;
  color: #b599f1;
  stroke: #b599f1;
  opacity: 0.85;
}
.map-offline h1 {
  font-size: 28px;
  margin: 0 0 12px;
  font-weight: 600;
}
.map-offline p {
  font-size: 16px;
  margin: 0 0 8px;
  max-width: 560px;
  line-height: 1.5;
}
.map-offline-hint {
  margin-top: 16px !important;
  color: #a5a3c4;
}
@media (max-width: 640px) {
  .map-offline h1 { font-size: 22px; }
  .map-offline p { font-size: 14px; }
}
</style>
