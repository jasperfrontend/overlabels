<script setup lang="ts">
import { ref, onMounted } from 'vue';
import 'leaflet/dist/leaflet.css';
import { LMap, LTileLayer, LPolyline, LCircleMarker } from '@vue-leaflet/vue-leaflet';
import type { LatLngBoundsExpression } from 'leaflet';

const props = defineProps<{
  sessionId: string;
}>();

const zoom = ref(14);
const center = ref<[number, number]>([0, 0]);
const route = ref<[number, number][]>([]);
const startPoint = ref<[number, number] | null>(null);
const endPoint = ref<[number, number] | null>(null);
const bounds = ref<LatLngBoundsExpression | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const mapRef = ref<InstanceType<typeof LMap> | null>(null);

onMounted(async () => {
  try {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const res = await fetch(`/api/gps-sessions/${props.sessionId}/geojson`, {
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      credentials: 'same-origin',
    });

    if (!res.ok) {
      error.value = 'Failed to load route data.';
      return;
    }

    const geojson = await res.json();

    if (!geojson.features?.length) {
      error.value = 'No GPS data.';
      return;
    }

    for (const feature of geojson.features) {
      if (feature.geometry.type === 'LineString') {
        route.value = feature.geometry.coordinates.map((c: number[]) => [c[1], c[0]]);
      } else if (feature.geometry.type === 'Point') {
        const coord: [number, number] = [feature.geometry.coordinates[1], feature.geometry.coordinates[0]];
        if (feature.properties.marker === 'start') startPoint.value = coord;
        else if (feature.properties.marker === 'end') endPoint.value = coord;
      }
    }

    if (route.value.length > 0) {
      const lats = route.value.map(p => p[0]);
      const lngs = route.value.map(p => p[1]);
      const padding = 0.002;
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
    error.value = 'Failed to load route data.';
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
  <div class="rounded-lg border overflow-hidden" style="height: 300px;">
    <div v-if="loading" class="flex items-center justify-center h-full text-muted-foreground text-sm bg-muted/30">
      Loading map...
    </div>
    <div v-else-if="error" class="flex items-center justify-center h-full text-muted-foreground text-sm bg-muted/30">
      {{ error }}
    </div>
    <l-map
      v-else
      ref="mapRef"
      :zoom="zoom"
      :center="center"
      :use-global-leaflet="false"
      style="height: 100%; width: 100%;"
      @ready="onMapReady"
    >
      <l-tile-layer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        attribution="&copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a>"
        :max-zoom="19"
      />
      <l-polyline
        v-if="route.length > 1"
        :lat-lngs="route"
        :color="'#7c3aed'"
        :weight="4"
        :opacity="0.8"
      />
      <l-circle-marker
        v-if="startPoint"
        :lat-lng="startPoint"
        :radius="7"
        :color="'#16a34a'"
        :fill-color="'#22c55e'"
        :fill-opacity="1"
        :weight="2"
      />
      <l-circle-marker
        v-if="endPoint"
        :lat-lng="endPoint"
        :radius="7"
        :color="'#dc2626'"
        :fill-color="'#ef4444'"
        :fill-opacity="1"
        :weight="2"
      />
    </l-map>
  </div>
</template>
