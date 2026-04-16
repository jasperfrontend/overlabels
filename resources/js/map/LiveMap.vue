<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { LMap, LTileLayer, LMarker, LPolyline, LPopup } from '@vue-leaflet/vue-leaflet';
import { useMapWebSocket } from './composables/useMapWebSocket';
import type { PointExpression } from 'leaflet';

const props = defineProps<{
  twitchId: string;
  streamerName: string;
  delay: number;
  speedUnit: string;
}>();

const zoom = ref(15);
const center = ref<[number, number]>([0, 0]);
const trail = ref<[number, number][]>([]);
const hasPosition = ref(false);
const loading = ref(true);
const mapRef = ref<InstanceType<typeof LMap> | null>(null);

const MAX_TRAIL_POINTS = 200;

// Fetch initial position from the API
onMounted(async () => {
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
    // Silently fail - will pick up from WebSocket
  } finally {
    loading.value = false;
  }
});

// WebSocket for real-time updates (only when delay is 0)
const { position, connected } = props.delay === 0
  ? useMapWebSocket(props.twitchId)
  : { position: ref(null), connected: ref(false) };

// Polling for delayed position
let pollInterval: ReturnType<typeof setInterval> | null = null;
if (props.delay > 0) {
  pollInterval = setInterval(async () => {
    try {
      const res = await fetch(`/api/map/${props.twitchId}/position`);
      if (res.ok) {
        const data = await res.json();
        if (data.position) {
          const pos = { lat: data.position.lat, lng: data.position.lng, speed: data.position.speed, bearing: data.position.bearing };
          position.value = pos;
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
const markerIcon = {
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41] as PointExpression,
  iconAnchor: markerAnchor,
};
</script>

<template>
  <div class="map-container">
    <!-- Header -->
    <div class="map-header">
      <span class="map-logo">Overlabels</span>
      <span class="map-title">{{ streamerName }}'s live location</span>
      <span v-if="delay > 0" class="map-delay">{{ delay }}s delay</span>
      <span v-if="connected" class="map-status live">LIVE</span>
      <span v-else-if="hasPosition" class="map-status">Connected</span>
    </div>

    <div v-if="loading" class="map-loading">Loading map...</div>

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
</style>
