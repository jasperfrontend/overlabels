<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { LMap, LTileLayer, LPolyline, LCircleMarker, LPopup } from '@vue-leaflet/vue-leaflet';
import type { LatLngBoundsExpression } from 'leaflet';

const props = defineProps<{
  twitchId: string;
  sessionId: string;
  streamerName: string;
  speedUnit: string;
}>();

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

onMounted(async () => {
  try {
    const res = await fetch(`/api/map/${props.twitchId}/${props.sessionId}/geojson`);
    if (!res.ok) {
      error.value = res.status === 403 ? 'Map sharing is not enabled.' : 'Session not found.';
      return;
    }

    const geojson = await res.json();

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

    <l-map
      v-else
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
</style>
