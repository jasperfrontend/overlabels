<script setup lang="ts">
import { ref, toRef, onMounted } from 'vue';
import { LMap, LTileLayer, LPolyline, LCircleMarker, LPopup } from '@vue-leaflet/vue-leaflet';
import type { LatLngBoundsExpression } from 'leaflet';
import { Clock, MapPin, Gauge, Mountain, Battery, Radio } from 'lucide-vue-next';
import { useSessionDataFormatter } from '@/composables/useSessionDataFormatter';

const props = defineProps<{
  slug: string;
  sessionId: string;
  streamerName: string;
  speedUnit: string;
}>();

interface SessionMeta {
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

const zoom = ref(14);
const center = ref<[number, number]>([0, 0]);
const route = ref<[number, number][]>([]);
const startPoint = ref<[number, number] | null>(null);
const endPoint = ref<[number, number] | null>(null);
const bounds = ref<LatLngBoundsExpression | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const pointInfo = ref<{ original: number; simplified: number } | null>(null);
const mapRef = ref<InstanceType<typeof LMap> | null>(null);
const meta = ref<SessionMeta | null>(null);
const metaLocale = ref<string | null>(null);

const speedUnitRef = toRef(props, 'speedUnit');
const {
  formatDate,
  formatTime,
  formatDuration,
  formatSpeed,
  formatAltitude,
  formatDistance,
  batteryDelta,
  batteryColor,
} = useSessionDataFormatter({ speedUnit: speedUnitRef, localeOverride: metaLocale });

const speedLabel = props.speedUnit === 'mph' ? 'mph' : 'km/h';

onMounted(async () => {
  // Fire both fetches in parallel - they're independent and the meta call is
  // cheap enough that we don't want it gated on the geojson roundtrip.
  const [geoRes, metaRes] = await Promise.all([
    fetch(`/api/map/${props.slug}/${props.sessionId}/geojson`).catch(() => null),
    fetch(`/api/map/${props.slug}/${props.sessionId}/meta`).catch(() => null),
  ]);

  if (metaRes?.ok) {
    try {
      const data = await metaRes.json();
      meta.value = data.session;
      metaLocale.value = data.locale ?? null;
    } catch {
      // Meta is decorative - if it fails we still want the map.
    }
  }

  try {
    if (!geoRes?.ok) {
      error.value = geoRes?.status === 403 ? 'Map sharing is not enabled.' : 'Session not found.';
      return;
    }

    const geojson = await geoRes.json();

    if (!geojson.features?.length) {
      error.value = 'No GPS data for this session.';
      return;
    }

    for (const feature of geojson.features) {
      if (feature.geometry.type === 'LineString') {
        // GeoJSON is [lng, lat], Leaflet wants [lat, lng]
        route.value = feature.geometry.coordinates.map((c: number[]) => [c[1], c[0]]);
        pointInfo.value = {
          original: feature.properties.original_points,
          simplified: feature.properties.simplified_points,
        };
      } else if (feature.geometry.type === 'Point') {
        const coord: [number, number] = [feature.geometry.coordinates[1], feature.geometry.coordinates[0]];
        if (feature.properties.marker === 'start') {
          startPoint.value = coord;
        } else if (feature.properties.marker === 'end') {
          endPoint.value = coord;
        }
      }
    }

    if (route.value.length > 0) {
      // Compute bounding box
      const lats = route.value.map(p => p[0]);
      const lngs = route.value.map(p => p[1]);
      const padding = 0.002; // ~200m padding
      bounds.value = [
        [Math.min(...lats) - padding, Math.min(...lngs) - padding],
        [Math.max(...lats) + padding, Math.max(...lngs) + padding],
      ];
      center.value = [
        (Math.min(...lats) + Math.max(...lats)) / 2,
        (Math.min(...lngs) + Math.max(...lngs)) / 2,
      ];
    }
  } catch {
    error.value = 'Failed to load session data.';
  } finally {
    loading.value = false;
  }
});

function onMapReady() {
  if (bounds.value && mapRef.value?.leafletObject) {
    mapRef.value.leafletObject.fitBounds(bounds.value);
  }
}
</script>

<template>
  <div class="map-container">
    <!-- Header -->
    <div class="map-header">
      <span class="map-logo">Overlabels</span>
      <span class="map-title">{{ streamerName }}'s session</span>
      <span v-if="pointInfo" class="map-points">{{ pointInfo.original }} points</span>
    </div>

    <div v-if="loading" class="map-loading">Loading session...</div>
    <div v-else-if="error" class="map-loading">{{ error }}</div>

    <!-- Leaflet wraps the map and the overlay card together so the card can
         absolute-position over the tiles without escaping the flex column. -->
    <div v-else class="map-stage">
      <!-- Stats card (top-left). Mirrors the per-session row from the GPS
           Sessions dashboard so a viewer of the public route gets the same
           context the streamer sees in their own admin view. -->
      <aside v-if="meta" class="map-stats-card">
        <header class="map-stats-card-header">
          <div class="map-stats-card-title">{{ formatDate(meta.started_at) }}</div>
          <div class="map-stats-card-time">
            {{ formatTime(meta.started_at) }} - {{ formatTime(meta.ended_at) }}
          </div>
        </header>
        <dl class="map-stats-grid">
          <div class="map-stat">
            <dt><Clock class="map-stat-icon" /> Duration</dt>
            <dd>{{ formatDuration(meta.started_at, meta.ended_at) }}</dd>
          </div>
          <div class="map-stat">
            <dt><MapPin class="map-stat-icon" /> Distance</dt>
            <dd>{{ formatDistance(meta.distance_km) }}</dd>
          </div>
          <div class="map-stat">
            <dt><Gauge class="map-stat-icon" /> Speed</dt>
            <dd>
              <span class="map-stat-mini">avg</span> {{ formatSpeed(meta.avg_speed_ms) ?? '-' }}
              <span class="map-stat-mini map-stat-spaced">max</span> {{ formatSpeed(meta.max_speed_ms) ?? '-' }}
              <span class="map-stat-mini">{{ speedLabel }}</span>
            </dd>
          </div>
          <div class="map-stat">
            <dt><Mountain class="map-stat-icon" /> Elevation</dt>
            <dd v-if="meta.min_altitude !== null && meta.max_altitude !== null">
              {{ formatAltitude(meta.min_altitude) }}
              <template v-if="meta.min_altitude !== meta.max_altitude">
                <span class="map-stat-mini map-stat-spaced">-</span>
                {{ formatAltitude(meta.max_altitude) }}
              </template>
            </dd>
            <dd v-else class="map-stat-muted">-</dd>
          </div>
          <div class="map-stat">
            <dt><Battery class="map-stat-icon" /> Battery</dt>
            <dd v-if="meta.battery_start !== null && meta.battery_end !== null">
              <span :class="batteryColor(meta.battery_start)">{{ meta.battery_start }}%</span>
              <span class="map-stat-mini map-stat-spaced">&rarr;</span>
              <span :class="batteryColor(meta.battery_end)">{{ meta.battery_end }}%</span>
              <span class="map-stat-mini">({{ batteryDelta(meta.battery_start, meta.battery_end) }})</span>
            </dd>
            <dd v-else class="map-stat-muted">-</dd>
          </div>
          <div class="map-stat">
            <dt><Radio class="map-stat-icon" /> Pings</dt>
            <dd>{{ meta.ping_count.toLocaleString(metaLocale ?? undefined) }}</dd>
          </div>
        </dl>
      </aside>

      <l-map
        ref="mapRef"
        :zoom="zoom"
        :center="center"
        :use-global-leaflet="false"
        class="map-leaflet"
        @ready="onMapReady"
      >
      <l-tile-layer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        attribution="&copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a>"
        :max-zoom="19"
      />

      <!-- Route polyline -->
      <l-polyline
        v-if="route.length > 1"
        :lat-lngs="route"
        :color="'#7c3aed'"
        :weight="4"
        :opacity="0.8"
      />

      <!-- Start marker (green) -->
      <l-circle-marker
        v-if="startPoint"
        :lat-lng="startPoint"
        :radius="8"
        :color="'#16a34a'"
        :fill-color="'#22c55e'"
        :fill-opacity="1"
        :weight="2"
      >
        <l-popup>
          <div style="font-family: system-ui; font-size: 13px;">
            <strong>Start</strong>
          </div>
        </l-popup>
      </l-circle-marker>

      <!-- End marker (red) -->
      <l-circle-marker
        v-if="endPoint"
        :lat-lng="endPoint"
        :radius="8"
        :color="'#dc2626'"
        :fill-color="'#ef4444'"
        :fill-opacity="1"
        :weight="2"
      >
        <l-popup>
          <div style="font-family: system-ui; font-size: 13px;">
            <strong>End</strong>
          </div>
        </l-popup>
      </l-circle-marker>
      </l-map>
    </div>
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
.map-points {
  font-size: 12px;
  color: #737373;
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
.map-stage {
  position: relative;
  flex: 1;
  display: flex;
}
.map-stats-card {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 500; /* above leaflet panes (default ~400) */
  background: rgba(23, 23, 23, 0.92);
  backdrop-filter: blur(6px);
  border: 1px solid #262626;
  border-radius: 8px;
  padding: 12px 14px;
  font-family: system-ui, -apple-system, sans-serif;
  color: #e5e5e5;
  font-size: 13px;
  min-width: 280px;
  max-width: calc(100vw - 24px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
  pointer-events: auto;
}
.map-stats-card-header {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid #262626;
}
.map-stats-card-title {
  font-weight: 600;
  font-size: 14px;
}
.map-stats-card-time {
  font-size: 12px;
  color: #a3a3a3;
}
.map-stats-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 14px;
  margin: 0;
}
.map-stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.map-stat dt {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: #a3a3a3;
}
.map-stat dd {
  margin: 0;
  font-size: 13px;
  font-weight: 500;
  color: #e5e5e5;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.map-stat-icon {
  width: 12px;
  height: 12px;
}
.map-stat-mini {
  font-size: 11px;
  color: #a3a3a3;
}
.map-stat-spaced {
  margin: 0 2px;
}
.map-stat-muted {
  color: #737373;
}
@media (max-width: 640px) {
  .map-stats-card {
    min-width: 0;
    width: calc(100vw - 24px);
    padding: 10px 12px;
  }
  .map-stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 10px;
  }
}
</style>
