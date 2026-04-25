<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save, Eraser, Paintbrush, RefreshCw } from 'lucide-vue-next';

interface RoomCell {
  bg?: string;
  overlay?: string;
  trigger?: { sound?: string };
}

interface RoomFile {
  room: number;
  tileset: string;
  width: number;
  height: number;
  cells: (RoomCell | null)[][];
  filter?: string;
  overlayColor?: string;
  overlayOpacity?: number;
  version: 1;
}

interface AssetEntry {
  name: string;
  path: string;
  size: number;
}

interface AssetManifest {
  tiles: AssetEntry[];
  objects: AssetEntry[];
  sounds: AssetEntry[];
}

const props = defineProps<{
  room: number;
  roomFile: RoomFile | null;
  defaultWidth: number;
  defaultHeight: number;
}>();

const tileset = ref(props.roomFile?.tileset ?? `room-${props.room}`);
const width = ref(props.roomFile?.width ?? props.defaultWidth);
const height = ref(props.roomFile?.height ?? props.defaultHeight);
const filter = ref(props.roomFile?.filter ?? '');
const overlayColor = ref(props.roomFile?.overlayColor ?? '#000000');
const overlayOpacity = ref(props.roomFile?.overlayOpacity ?? 0);

function makeEmptyGrid(w: number, h: number): (RoomCell | null)[][] {
  return Array.from({ length: h }, () => Array.from({ length: w }, () => null));
}

const cells = ref<(RoomCell | null)[][]>(
  props.roomFile?.cells ?? makeEmptyGrid(width.value, height.value),
);

const manifest = ref<AssetManifest>({ tiles: [], objects: [], sounds: [] });
const manifestLoading = ref(false);

const selectedAsset = ref<string | null>(null);
const eraseMode = ref(false);
const isPainting = ref(false);
const tileSearch = ref('');

const dirty = ref(false);
const saving = ref(false);
const lastSavedAt = ref<string | null>(null);
const targetRoom = ref(props.room);

async function loadManifest() {
  manifestLoading.value = true;
  try {
    const res = await fetch(`/dev/room-builder/${props.room}/assets`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (res.ok) {
      const payload = await res.json();
      manifest.value = payload.assets ?? { tiles: [], objects: [], sounds: [] };
    }
  } finally {
    manifestLoading.value = false;
  }
}

onMounted(loadManifest);

function resizeGrid(newW: number, newH: number) {
  const next = makeEmptyGrid(newW, newH);
  for (let y = 0; y < Math.min(newH, cells.value.length); y++) {
    for (let x = 0; x < Math.min(newW, cells.value[y]?.length ?? 0); x++) {
      next[y][x] = cells.value[y][x];
    }
  }
  cells.value = next;
  dirty.value = true;
}

watch(width, (w) => {
  if (w !== cells.value[0]?.length) resizeGrid(w, height.value);
});
watch(height, (h) => {
  if (h !== cells.value.length) resizeGrid(width.value, h);
});

function paintCell(x: number, y: number) {
  if (eraseMode.value) {
    cells.value[y][x] = null;
    dirty.value = true;
    return;
  }
  if (!selectedAsset.value) return;
  const current = cells.value[y][x] ?? {};
  cells.value[y][x] = { ...current, bg: selectedAsset.value };
  dirty.value = true;
}

function onCellMouseDown(e: MouseEvent, x: number, y: number) {
  e.preventDefault();
  isPainting.value = true;
  paintCell(x, y);
}

function onCellMouseEnter(x: number, y: number) {
  if (!isPainting.value) return;
  paintCell(x, y);
}

function stopPaint() {
  isPainting.value = false;
}

async function save() {
  if (saving.value) return;
  saving.value = true;
  try {
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
    const payload: Omit<RoomFile, 'room'> = {
      tileset: tileset.value,
      width: width.value,
      height: height.value,
      cells: cells.value,
      filter: filter.value || undefined,
      overlayColor: overlayOpacity.value > 0 ? overlayColor.value : undefined,
      overlayOpacity: overlayOpacity.value > 0 ? overlayOpacity.value : undefined,
      version: 1,
    };
    const res = await fetch(`/dev/room-builder/${props.room}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    if (!res.ok) {
      const body = await res.text();
      alert(`Save failed (${res.status}): ${body}`);
      return;
    }
    dirty.value = false;
    lastSavedAt.value = new Date().toLocaleTimeString();
  } finally {
    saving.value = false;
  }
}

function jumpToRoom() {
  const n = Number(targetRoom.value);
  if (!Number.isInteger(n) || n < 1) return;
  router.visit(`/dev/room-builder/${n}`);
}

const cellSize = computed(() => {
  const maxCanvasPx = 720;
  const longSide = Math.max(width.value, height.value);
  return Math.min(64, Math.floor(maxCanvasPx / longSide));
});

const paintedCount = computed(() => {
  let n = 0;
  for (const row of cells.value) for (const c of row) if (c?.bg) n++;
  return n;
});

const totalCells = computed(() => width.value * height.value);

const filteredTiles = computed(() => {
  const q = tileSearch.value.trim().toLowerCase();
  if (!q) return manifest.value.tiles;
  return manifest.value.tiles.filter((t) => t.name.toLowerCase().includes(q));
});

const usedTiles = computed(() => {
  const seen = new Map<string, AssetEntry>();
  for (const row of cells.value) {
    for (const cell of row) {
      if (!cell?.bg || seen.has(cell.bg)) continue;
      const known = manifest.value.tiles.find((t) => t.path === cell.bg);
      seen.set(cell.bg, known ?? {
        name: cell.bg.split('/').pop() ?? cell.bg,
        path: cell.bg,
        size: 0,
      });
    }
  }
  return Array.from(seen.values());
});

// Styles applied to every tile-floor <img> so the filter affects only the
// painted floor, not any item layer that will later sit above it. Kept as a
// computed style object rather than a scoped CSS var so the preview updates
// immediately while typing into the filter input.
const floorImgStyle = computed(() => ({
  filter: filter.value || 'none',
}));

const overlayLayerStyle = computed(() => ({
  backgroundColor: overlayColor.value,
  opacity: String(overlayOpacity.value),
}));
</script>

<template>
  <Head><title>Room Builder - Room {{ room }}</title></Head>

  <div class="h-screen bg-background text-foreground flex flex-col overflow-hidden">

    <div class="flex-1 min-h-0 grid grid-cols-[280px_1fr_240px] gap-0">
      <!-- Asset sidebar -->
      <aside class="border-r border-border flex flex-col overflow-y-auto min-h-0">
        <div class="flex items-center justify-between px-4 py-3 border-b border-border">
          <div>
            <div class="text-sm font-medium">Tiles</div>
            <div class="text-xs text-foreground">
              public/rooms/{{ room }}/tiles/
            </div>
          </div>
          <Button
            variant="ghost"
            size="sm"
            class="cursor-pointer"
            :disabled="manifestLoading"
            title="Reload asset folder"
            @click="loadManifest"
          >
            <RefreshCw class="size-4" :class="{ 'animate-spin': manifestLoading }" />
          </Button>
        </div>

        <div class="flex gap-2 px-4 py-2 border-b border-border">
          <Button
            variant="outline"
            size="sm"
            class="cursor-pointer flex-1"
            :class="{ 'ring-2 ring-primary': !eraseMode }"
            @click="eraseMode = false"
          >
            <Paintbrush class="size-4 mr-1" />
            Paint
          </Button>
          <Button
            variant="outline"
            size="sm"
            class="cursor-pointer flex-1"
            :class="{ 'ring-2 ring-destructive': eraseMode }"
            @click="eraseMode = true"
          >
            <Eraser class="size-4 mr-1" />
            Erase
          </Button>
        </div>

        <div class="flex flex-col gap-2 px-4 py-3 border-b border-border">
          <div class="flex flex-col gap-1">
            <Label for="filter-input" class="text-xs">CSS Filter</Label>
            <Input
              id="filter-input"
              v-model="filter"
              placeholder="hue-rotate(180deg)"
              class="text-xs"
              @update:model-value="dirty = true"
            />
          </div>
          <div class="flex items-end gap-2">
            <div class="flex flex-col gap-1">
              <Label for="overlay-color-input" class="text-xs">Overlay color</Label>
              <Input
                id="overlay-color-input"
                v-model="overlayColor"
                type="color"
                class="w-16 h-8 p-0.5 cursor-pointer"
                @update:model-value="dirty = true"
              />
            </div>
            <div class="flex flex-col gap-1 flex-1">
              <Label for="overlay-opacity-input" class="text-xs">Opacity ({{ overlayOpacity.toFixed(2) }})</Label>
              <input
                id="overlay-opacity-input"
                v-model.number="overlayOpacity"
                type="range"
                min="0"
                max="1"
                step="0.01"
                class="w-full cursor-pointer"
                @input="dirty = true"
              />
            </div>
          </div>
        </div>

        <div class="px-4 py-2 border-b border-border">
          <Label for="tile-search-input" class="text-xs">Search tiles</Label>
          <Input
            id="tile-search-input"
            v-model="tileSearch"
            placeholder="Filter by filename..."
            class="text-xs mt-1"
          />
        </div>

        <div class="flex-1 p-3">
          <div v-if="manifest.tiles.length === 0" class="text-xs text-foreground p-2 leading-relaxed">
            No tiles yet. Drop PNGs into
            <code class="bg-muted px-1 rounded">public/rooms/{{ room }}/tiles/</code>
            then hit the refresh button.
          </div>
          <div v-else-if="filteredTiles.length === 0" class="text-xs text-foreground p-2 leading-relaxed">
            No tiles match <code class="bg-muted px-1 rounded">{{ tileSearch }}</code>.
          </div>
          <div v-else class="grid grid-cols-3 gap-2">
            <button
              v-for="asset in filteredTiles"
              :key="asset.path"
              type="button"
              class="aspect-square rounded border border-border bg-muted overflow-hidden hover:border-primary cursor-pointer relative"
              :class="{ 'ring-2 ring-primary border-primary': selectedAsset === asset.path }"
              :title="asset.name"
              @click="selectedAsset = asset.path; eraseMode = false"
            >
              <img
                :src="asset.path"
                :alt="asset.name"
                class="w-full h-full object-cover pixelated"
                :style="floorImgStyle"
              />
              <div
                v-if="overlayOpacity > 0"
                class="pointer-events-none absolute inset-0"
                :style="overlayLayerStyle"
              ></div>
            </button>
          </div>
        </div>

        <div class="border-t border-border px-4 py-2 text-xs text-foreground flex items-center justify-between">
          <span v-if="tileSearch">{{ filteredTiles.length }} / {{ manifest.tiles.length }} tile{{ manifest.tiles.length === 1 ? '' : 's' }}</span>
          <span v-else>{{ manifest.tiles.length }} tile{{ manifest.tiles.length === 1 ? '' : 's' }}</span>
          <span>{{ paintedCount }} / {{ totalCells }} painted</span>
        </div>
      </aside>

      <!-- Canvas -->
      <main
        class="overflow-hidden min-h-0 p-6 flex flex-col items-start select-none"
        @mouseup="stopPaint"
        @mouseleave="stopPaint"
      >
        <!-- Top bar -->
        <header class="flex flex-wrap items-end gap-4 border-b border-border mb-8 px-6 py-4">
          <div>
            <div class="text-xs text-muted-foreground">Room Builder (dev)</div>
            <h1 class="text-xl font-semibold">Room {{ room }}</h1>
          </div>

          <div class="flex items-end gap-3 ml-auto flex-wrap">
            <div class="flex flex-col gap-1">
              <Label for="tileset-input" class="text-xs">Tileset</Label>
              <Input
                id="tileset-input"
                v-model="tileset"
                class="w-40"
                @update:model-value="dirty = true"
              />
            </div>
            <div class="flex flex-col gap-1">
              <Label for="width-input" class="text-xs">Width</Label>
              <Input id="width-input" v-model.number="width" type="number" min="1" max="64" class="w-20" />
            </div>
            <div class="flex flex-col gap-1">
              <Label for="height-input" class="text-xs">Height</Label>
              <Input id="height-input" v-model.number="height" type="number" min="1" max="64" class="w-20" />
            </div>
            <div class="flex flex-col gap-1">
              <Label for="room-jump-input" class="text-xs">Jump to room</Label>
              <div class="flex gap-2">
                <Input id="room-jump-input" v-model.number="targetRoom" type="number" min="1" class="w-20" />
                <Button variant="outline" class="cursor-pointer" @click="jumpToRoom">Go</Button>
              </div>
            </div>
            <Button
              :disabled="saving || !dirty"
              class="cursor-pointer"
              @click="save"
            >
              <Save class="size-4 mr-2" />
              {{ saving ? 'Saving...' : dirty ? 'Save' : 'Saved' }}
            </Button>
          </div>
        </header>
        <div class="relative">
          <div
            class="grid gap-0 border border-border shadow-sm"
            :style="{
              gridTemplateColumns: `repeat(${width}, ${cellSize}px)`,
              gridTemplateRows: `repeat(${height}, ${cellSize}px)`,
            }"
          >
            <div
              v-for="(row, y) in cells"
              :key="`r-${y}`"
              style="display: contents"
            >
              <div
                v-for="(cell, x) in row"
                :key="`c-${x}-${y}`"
                class="relative border border-border/40 bg-muted/40 hover:ring-1 hover:ring-primary cursor-pointer"
                :style="{ width: `${cellSize}px`, height: `${cellSize}px` }"
                :title="`(${x + 1}, ${y + 1})${cell?.bg ? ' ' + cell.bg : ''}`"
                @mousedown="onCellMouseDown($event, x, y)"
                @mouseenter="onCellMouseEnter(x, y)"
              >
                <img
                  v-if="cell?.bg"
                  :src="cell.bg"
                  alt=""
                  class="absolute inset-0 w-full h-full object-cover pixelated pointer-events-none"
                  :style="floorImgStyle"
                />
              </div>
            </div>
          </div>
          <!-- Overlay color layer sits above floor tiles but below any future
               item layer (sprites, zombies, etc.). Grid covers the full inner
               area, so inset-0 on the relative wrapper matches exactly. -->
          <div
            v-if="overlayOpacity > 0"
            class="pointer-events-none absolute inset-0"
            :style="overlayLayerStyle"
          ></div>
        </div>
      </main>

      <!-- Used tiles sidebar -->
      <aside class="border-l border-border flex flex-col overflow-y-auto min-h-0">
        <div class="px-4 py-3 border-b border-border">
          <div class="text-sm font-medium">Used tiles</div>
          <div class="text-xs text-foreground">
            Tiles already painted on the grid
          </div>
        </div>

        <div class="flex-1 p-3">
          <div v-if="usedTiles.length === 0" class="text-xs text-foreground p-2 leading-relaxed">
            No tiles painted yet. Pick a tile from the left and start painting.
          </div>
          <div v-else class="grid grid-cols-3 gap-2">
            <button
              v-for="asset in usedTiles"
              :key="asset.path"
              type="button"
              class="aspect-square rounded border border-border bg-muted overflow-hidden hover:border-primary cursor-pointer relative"
              :class="{ 'ring-2 ring-primary border-primary': selectedAsset === asset.path }"
              :title="asset.name"
              @click="selectedAsset = asset.path; eraseMode = false"
            >
              <img
                :src="asset.path"
                :alt="asset.name"
                class="w-full h-full object-cover pixelated"
                :style="floorImgStyle"
              />
              <div
                v-if="overlayOpacity > 0"
                class="pointer-events-none absolute inset-0"
                :style="overlayLayerStyle"
              ></div>
            </button>
          </div>
        </div>

        <div class="border-t border-border px-4 py-2 text-xs text-foreground flex items-center justify-between">
          <span>{{ usedTiles.length }} unique</span>
          <span>{{ paintedCount }} painted</span>
        </div>
      </aside>
    </div>

    <!-- Status footer -->
    <footer class="border-t border-border px-6 py-2 text-xs text-foreground flex items-center justify-between">
      <div>
        <span v-if="dirty" class="text-amber-500">Unsaved changes</span>
        <span v-else-if="lastSavedAt">Saved at {{ lastSavedAt }} - resources/js/rooms/{{ room }}.json</span>
        <span v-else>No changes since load</span>
      </div>
      <div class="flex gap-4">
        <span>Cell size: {{ cellSize }}px</span>
        <span v-if="selectedAsset && !eraseMode">Selected: <code class="bg-muted px-1 rounded">{{ selectedAsset.split('/').pop() }}</code></span>
        <span v-else-if="eraseMode" class="text-destructive">Erase mode</span>
        <span v-else class="text-muted-foreground">Pick a tile on the left to start painting</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.pixelated {
  image-rendering: pixelated;
}
</style>
