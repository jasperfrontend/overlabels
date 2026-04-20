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
</script>

<template>
  <Head><title>Room Builder - Room {{ room }}</title></Head>

  <div class="min-h-screen bg-background text-foreground flex flex-col">
    <!-- Top bar -->
    <header class="flex flex-wrap items-end gap-4 border-b border-border px-6 py-4">
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

    <div class="flex-1 grid grid-cols-[280px_1fr] gap-0">
      <!-- Asset sidebar -->
      <aside class="border-r border-border flex flex-col">
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

        <div class="flex-1 overflow-y-auto p-3">
          <div v-if="manifest.tiles.length === 0" class="text-xs text-foreground p-2 leading-relaxed">
            No tiles yet. Drop PNGs into
            <code class="bg-muted px-1 rounded">public/rooms/{{ room }}/tiles/</code>
            then hit the refresh button.
          </div>
          <div v-else class="grid grid-cols-3 gap-2">
            <button
              v-for="asset in manifest.tiles"
              :key="asset.path"
              type="button"
              class="aspect-square rounded border border-border bg-muted overflow-hidden hover:border-primary cursor-pointer relative"
              :class="{ 'ring-2 ring-primary border-primary': selectedAsset === asset.path }"
              :title="asset.name"
              @click="selectedAsset = asset.path; eraseMode = false"
            >
              <img :src="asset.path" :alt="asset.name" class="w-full h-full object-cover pixelated" />
            </button>
          </div>
        </div>

        <div class="border-t border-border px-4 py-2 text-xs text-foreground flex items-center justify-between">
          <span>{{ manifest.tiles.length }} tile{{ manifest.tiles.length === 1 ? '' : 's' }}</span>
          <span>{{ paintedCount }} / {{ totalCells }} painted</span>
        </div>
      </aside>

      <!-- Canvas -->
      <main
        class="overflow-auto p-6 flex items-start justify-center select-none"
        @mouseup="stopPaint"
        @mouseleave="stopPaint"
      >
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
              />
            </div>
          </div>
        </div>
      </main>
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
  image-rendering: crisp-edges;
}
</style>
