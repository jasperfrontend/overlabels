<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import RekaToast from '@/components/RekaToast.vue';
import ControlKnob from '@/components/controls/ControlKnob.vue';
import { PlayIcon, PauseIcon, RotateCcwIcon, SaveIcon, LockIcon, Search, ChevronRight, ChevronsUpDown, ChevronsDownUp } from '@lucide/vue';
import type { OverlayControl, OverlayTemplate } from '@/types';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SERVICE_LABELS } from '@/utils/services';
import { controlChoices } from '@/utils/controlChoices';

const props = defineProps<{
  template: OverlayTemplate;
  controls: OverlayControl[];
  isLive?: boolean;
  /** The font picker's shortlist and where to fetch the rest of the catalogue from. */
  fonts?: { url: string; suggested: { value: string; hint: string }[] };
}>();

/** Build the template tag key: c:source:key for external, c:key for twitch/user. */
function tagKey(ctrl: OverlayControl): string {
  if (ctrl.source) {
    return `c:${ctrl.source}:${ctrl.key}`;
  }
  return `c:${ctrl.key}`;
}

function isTwitchOffline(ctrl: OverlayControl): boolean {
  return ctrl.source === 'twitch' && ctrl.source_managed && !props.isLive;
}

/**
 * Whether a control is rendered by the shared knob (ControlKnob), which is
 * the same widget the product designer mounts for it: a number, a boolean, a
 * color, and a text control with a vocabulary or a webfont. Everything else -
 * counter, timer, expression, datetime, free text - is not a designer knob
 * and keeps its own widget below. A source-managed control is read-only
 * whatever its type.
 */
function isKnob(ctrl: OverlayControl): boolean {
  if (ctrl.source_managed) return false;
  if (ctrl.type === 'number' || ctrl.type === 'boolean' || ctrl.type === 'color') return true;
  return ctrl.type === 'text' && (controlChoices(ctrl.config).length > 0 || (ctrl.config?.webfont === true && !!props.fonts));
}

/** Group controls by category for organized display. */
interface ControlGroup {
  label: string;
  controls: OverlayControl[];
}

const groupedControls = computed<ControlGroup[]>(() => {
  const serviceGroups: Record<string, OverlayControl[]> = {};
  const userControls: Record<string, OverlayControl[]> = {};

  for (const ctrl of props.controls) {
    if (ctrl.source && SERVICE_LABELS[ctrl.source]) {
      if (!serviceGroups[ctrl.source]) serviceGroups[ctrl.source] = [];
      serviceGroups[ctrl.source].push(ctrl);
    } else {
      const type = ctrl.type;
      if (!userControls[type]) userControls[type] = [];
      userControls[type].push(ctrl);
    }
  }

  const groups: ControlGroup[] = [];

  const typeLabels: Record<string, string> = {
    text: 'Text',
    number: 'Number',
    counter: 'Counter',
    timer: 'Timer',
    boolean: 'Toggle',
    color: 'Color',
    expression: 'Expression',
    datetime: 'Date/Time',
  };

  // A type missing from this list is dropped from the panel entirely, so it
  // has to grow whenever OverlayControl::TYPES does.
  const typeOrder = ['counter', 'timer', 'number', 'text', 'color', 'boolean', 'expression', 'datetime'];
  for (const type of typeOrder) {
    if (userControls[type]?.length) {
      groups.push({ label: typeLabels[type] ?? type, controls: userControls[type] });
    }
  }

  for (const [source, ctrls] of Object.entries(serviceGroups)) {
    groups.push({ label: SERVICE_LABELS[source] ?? source, controls: ctrls });
  }

  return groups;
});

// Search and collapse state
const searchQuery = ref('');

const filteredGroupedControls = computed<ControlGroup[]>(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return groupedControls.value;

  return groupedControls.value
    .map((group) => ({
      label: group.label,
      controls: group.controls.filter((ctrl) => {
        const matchesLabel = (ctrl.label || '').toLowerCase().includes(query);
        const matchesKey = ctrl.key.toLowerCase().includes(query);
        const matchesTag = tagKey(ctrl).toLowerCase().includes(query);
        const matchesGroup = group.label.toLowerCase().includes(query);
        return matchesLabel || matchesKey || matchesTag || matchesGroup;
      }),
    }))
    .filter((group) => group.controls.length > 0);
});

const totalVisibleControls = computed(() => {
  return filteredGroupedControls.value.reduce((sum, g) => sum + g.controls.length, 0);
});

const EXPANDED_KEY = 'control_panel_expanded';

function loadExpandedState(): Record<string, boolean> {
  try {
    const stored = localStorage.getItem(EXPANDED_KEY);
    if (stored) return JSON.parse(stored);
  } catch {
    // ignore
  }
  return {};
}

function saveExpandedState(): void {
  try {
    localStorage.setItem(EXPANDED_KEY, JSON.stringify(expandedGroups.value));
  } catch {
    // ignore
  }
}

const expandedGroups = ref<Record<string, boolean>>(loadExpandedState());

function isGroupExpanded(label: string): boolean {
  return expandedGroups.value[label] ?? true;
}

function toggleGroup(label: string): void {
  expandedGroups.value[label] = !isGroupExpanded(label);
  saveExpandedState();
}

const allExpanded = computed(() => {
  return filteredGroupedControls.value.every((g) => isGroupExpanded(g.label));
});

function toggleAll(): void {
  const newState = !allExpanded.value;
  filteredGroupedControls.value.forEach((g) => {
    expandedGroups.value[g.label] = newState;
  });
  saveExpandedState();
}

const toastMessage = ref('');
const toastType = ref<'success' | 'error'>('success');
const showToast = ref(false);

const localValues = ref<Record<number, string>>({});
const saving = ref<Record<number, boolean>>({});
const timerIntervals = ref<Record<number, number>>({});
const timerDisplays = ref<Record<number, string>>({});

function showMsg(msg: string, type: 'success' | 'error' = 'success') {
  toastMessage.value = msg;
  toastType.value = type;
  showToast.value = false;
  setTimeout(() => {
    showToast.value = true;
  }, 10);
}

function getLocalValue(ctrl: OverlayControl): string {
  if (ctrl.id in localValues.value) return localValues.value[ctrl.id];
  return ctrl.value ?? '';
}

function formatSeconds(secs: number): string {
  const s = Math.max(0, Math.floor(secs));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
  return `${m}:${String(sec).padStart(2, '0')}`;
}

function computeTimerDisplay(ctrl: OverlayControl): string {
  const cfg = ctrl.config ?? {};
  const mode = cfg.mode ?? 'countup';
  const base = Number(cfg.base_seconds ?? 0);
  const offset = Number(cfg.offset_seconds ?? 0);
  const running = Boolean(cfg.running ?? false);
  const startedAt = cfg.started_at ? new Date(cfg.started_at).getTime() : null;

  if (mode === 'countto') {
    const target = cfg.target_datetime ? new Date(cfg.target_datetime).getTime() : null;
    if (!target) return '0:00';
    return formatSeconds(Math.max(0, Math.floor((target - Date.now()) / 1000)));
  }

  let elapsed = offset;
  if (running && startedAt) {
    elapsed = offset + Math.floor((Date.now() - startedAt) / 1000);
  }

  const displaySecs = mode === 'countdown' ? Math.max(0, base - elapsed) : elapsed;
  return formatSeconds(displaySecs);
}

function startTimerTick(ctrl: OverlayControl) {
  stopTimerTick(ctrl.id);
  timerDisplays.value[ctrl.id] = computeTimerDisplay(ctrl);
  const cfg = ctrl.config ?? {};
  const isCountto = cfg.mode === 'countto';
  if (!cfg.running && !isCountto) return;

  timerIntervals.value[ctrl.id] = window.setInterval(() => {
    timerDisplays.value[ctrl.id] = computeTimerDisplay(ctrl);
  }, 500);
}

function stopTimerTick(id: number) {
  if (timerIntervals.value[id]) {
    clearInterval(timerIntervals.value[id]);
    delete timerIntervals.value[id];
  }
}

props.controls.forEach((ctrl) => {
  if (ctrl.type === 'timer') {
    startTimerTick(ctrl);
  }
});

// Listen for external control updates (bot, Ko-fi, Streamlabs, etc.) so the
// panel reflects changes without a page reload.
const page = usePage();
const twitchId = computed<string | null>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.twitch_id ? String(user.twitch_id) : null;
});
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.locale || undefined;
});

function formatCountToTarget(iso: string): string {
  return new Date(iso).toLocaleString(userLocale.value);
}

let echoChannel: any = null;

function broadcastKeyFor(ctrl: OverlayControl): string {
  return ctrl.source ? `${ctrl.source}:${ctrl.key}` : ctrl.key;
}

function handleControlUpdated(event: any) {
  // User-scoped broadcasts arrive with empty overlay_slug - apply to all. Template-scoped must match this template.
  if (event.overlay_slug && event.overlay_slug !== props.template.slug) return;

  const match = props.controls.find((c) => broadcastKeyFor(c) === event.key);
  if (!match) return;

  if (event.type === 'timer' && event.timer_state) {
    match.config = { ...(match.config ?? {}), ...event.timer_state };
    startTimerTick(match);
  } else {
    match.value = event.value == null ? match.value : String(event.value);
  }
}

const controlsCounter = computed(() => props.controls.length);

onMounted(() => {
  const echo = (window as any).Echo;
  if (!echo || !twitchId.value) return;
  // ControlValueUpdated broadcasts on PrivateChannel('alerts.{id}'), i.e. the
  // wire channel is private-alerts.{id}. echo.channel() would subscribe to the
  // public alerts.{id}, which nothing ever publishes on.
  echoChannel = echo.private(`alerts.${twitchId.value}`);
  echoChannel.listen('.control.updated', handleControlUpdated);
  // Service-fed controls (GPS) arrive batched, one event per tick carrying
  // every changed key. Without this the panel only moved for single updates.
  echoChannel.listen('.control.batch', handleControlBatch);
});

onBeforeUnmount(() => {
  if (echoChannel) {
    echoChannel.stopListening('.control.updated', handleControlUpdated);
    echoChannel.stopListening('.control.batch', handleControlBatch);
    echoChannel = null;
  }
  for (const timer of Object.values(receiptTimers)) clearTimeout(timer);
});

function handleControlBatch(event: any) {
  if (!event || !Array.isArray(event.updates)) return;
  for (const u of event.updates) {
    handleControlUpdated({ ...u, updated_at: u.updated_at ?? event.updated_at });
  }
}

async function postValue(ctrl: OverlayControl, payload: Record<string, any>) {
  saving.value[ctrl.id] = true;
  try {
    const { data } = await axios.post(`/templates/${props.template.id}/controls/${ctrl.id}/value`, payload);
    if (data.control) {
      Object.assign(ctrl, data.control);
    }
    if (ctrl.type === 'timer') {
      startTimerTick(ctrl);
    }
    return data;
  } catch (err: any) {
    const msg = err.response?.data?.message ?? 'Failed to update control.';
    showMsg(msg, 'error');
    throw err;
  } finally {
    saving.value[ctrl.id] = false;
  }
}

async function saveTextValue(ctrl: OverlayControl) {
  const val = localValues.value[ctrl.id] ?? ctrl.value ?? '';
  await postValue(ctrl, { value: val });
  showMsg(`"${ctrl.label || ctrl.key}" updated.`);
}

/* ------------------------------------------------------------------ knobs */

/*
 * A knob writes on `commit` - a pick, a tick, a slider released, a typed value
 * left - through the same endpoint as everything else here. This tab is NOT
 * the overlay: nothing on it shows a value landing, so every write gets a
 * receipt where the hand was, a "Saved" that names what landed and holds for
 * two seconds beside the row's label. (The designer has no receipt because
 * the preview frame is one.) The row shows the server's answer, not what was
 * typed: a number is clamped to the control's own bounds there.
 */
const receipts = ref<Record<number, string>>({});
const receiptTimers: Record<number, ReturnType<typeof setTimeout>> = {};

function receipt(ctrl: OverlayControl, saved: string): string {
  if (ctrl.type === 'boolean') return saved === '1' ? 'Saved: on' : 'Saved: off';
  const picked = controlChoices(ctrl.config).find((choice) => choice.value === saved);
  return `Saved: ${picked ? picked.label : saved}`;
}

async function commitKnob(ctrl: OverlayControl, value: string) {
  localValues.value[ctrl.id] = value;
  const data = await postValue(ctrl, { value });
  delete localValues.value[ctrl.id];

  const saved = typeof data?.value === 'string' ? data.value : (ctrl.value ?? '');
  receipts.value[ctrl.id] = receipt(ctrl, saved);
  clearTimeout(receiptTimers[ctrl.id]);
  receiptTimers[ctrl.id] = setTimeout(() => delete receipts.value[ctrl.id], 2000);
}

async function counterAction(ctrl: OverlayControl, action: 'increment' | 'decrement' | 'reset') {
  await postValue(ctrl, { action });
}

async function timerAction(ctrl: OverlayControl, action: 'start' | 'stop' | 'reset') {
  await postValue(ctrl, { action });
}

const isTimerRunning = (ctrl: OverlayControl) => Boolean(ctrl.config?.running);
</script>

<template>
  <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />

  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <p class="text-sm text-foreground">
        Manage the values of the Controls created in this Overlay. Check
        <a class="text-violet-400 hover:underline" href="/help/controls" target="_blank">the guide</a> to see how to implement Controls in your
        Overlays.
      </p>
      <div class="h-7" />
    </div>

    <div v-if="controls.length === 0" class="bg-sidebar-accent p-8 text-center text-muted-foreground">No Controls for this Overlay.</div>

    <template v-if="controls.length > 0">
      <!-- Search and collapse/expand bar -->
      <div class="mb-4 flex items-center gap-3">
        <div class="relative flex-1 gap-2">
          <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
          <input v-model="searchQuery" placeholder="Filter controls..." class="input-border w-full py-1.5 pr-2.5 pl-8 text-sm" />
        </div>
      </div>

      <!-- Count and collapse/expand toggle -->
      <div class="mb-3 flex items-center text-xs text-muted-foreground">
        <span v-if="searchQuery">
          {{ totalVisibleControls }} control{{ totalVisibleControls !== 1 ? 's' : '' }} in {{ filteredGroupedControls.length }} group{{
            filteredGroupedControls.length !== 1 ? 's' : ''
          }}
        </span>
        <span v-else>
          {{ controls.length }} control{{ controls.length !== 1 ? 's' : null }} across {{ groupedControls.length }} group{{
            groupedControls.length !== 1 ? 's' : ''
          }}
        </span>
        <button
          v-if="filteredGroupedControls.length > 0"
          class="ml-auto flex cursor-pointer items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
          @click.prevent="toggleAll"
        >
          <ChevronsDownUp v-if="allExpanded" :size="13" />
          <ChevronsUpDown v-else :size="13" />
          {{ allExpanded ? 'Collapse all' : 'Expand all' }}
        </button>
      </div>

      <!-- No results -->
      <div v-if="searchQuery && filteredGroupedControls.length === 0" class="py-8 text-center">
        <p class="text-sm text-muted-foreground">No controls match "{{ searchQuery }}"</p>
      </div>

      <!-- Collapsible groups -->
      <div class="space-y-1.5">
        <Collapsible
          v-for="group in filteredGroupedControls"
          :key="group.label"
          :open="isGroupExpanded(group.label)"
          @update:open="toggleGroup(group.label)"
        >
          <CollapsibleTrigger
            class="group flex w-full cursor-pointer items-center gap-2 rounded-md bg-sidebar px-2 py-4 text-left transition-colors hover:bg-sidebar-accent/50"
            :class="{ 'rounded-b-none bg-sidebar-accent/50 pb-0': isGroupExpanded(group.label) }"
          >
            <ChevronRight :size="14" class="shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-90" />
            <span class="text-sm font-medium">{{ group.label }}</span>
            <span class="ml-auto bg-card px-2.5 py-1.5 text-xs">{{ group.controls.length }}</span>
          </CollapsibleTrigger>

          <CollapsibleContent>
            <!-- Rows, in the shape the product designer's column gives a
                 control: the label, the widget, a line under it. The value is
                 the biggest thing on a row; the tag key and the description
                 sit behind the label in small type. -->
            <div class="grid grid-cols-1 gap-x-10 bg-sidebar/50 px-4 py-2 md:grid-cols-2 xl:grid-cols-3">
              <div v-for="ctrl in group.controls" :key="ctrl.id" class="flex flex-col gap-1.5 border-b border-border/60 py-3">
                <!-- The shared knob, for the five designer types. -->
                <ControlKnob
                  v-if="isKnob(ctrl)"
                  :id="`cp-input-${ctrl.id}`"
                  :control="ctrl"
                  :value="getLocalValue(ctrl)"
                  :suggested-fonts="fonts?.suggested"
                  :fonts-url="fonts?.url"
                  @input="localValues[ctrl.id] = $event"
                  @commit="commitKnob(ctrl, $event)"
                >
                  <template #aside>
                    <span class="flex shrink-0 items-baseline gap-2 text-xs">
                      <span v-if="receipts[ctrl.id]" class="text-green-600 dark:text-green-400">{{ receipts[ctrl.id] }}</span>
                      <span class="font-mono text-muted-foreground">{{ tagKey(ctrl) }}</span>
                    </span>
                  </template>
                </ControlKnob>

                <!-- Everything else keeps its own widget under the same label line. -->
                <template v-else>
                  <div class="flex items-baseline justify-between gap-3">
                    <label :for="`cp-input-${ctrl.id}`" class="text-sm text-foreground">{{ ctrl.label || ctrl.key }}</label>
                    <span class="flex shrink-0 items-center gap-2 text-xs">
                      <span
                        v-if="isTwitchOffline(ctrl)"
                        title="This Control only works when you're streaming"
                        class="rounded-full border border-muted-foreground/30 px-2 py-0.5 text-[10px] text-muted-foreground"
                        >Offline</span
                      >
                      <span
                        v-if="ctrl.source && ctrl.source_managed && ctrl.source !== 'twitch'"
                        class="inline-flex items-center gap-1 rounded-full border border-muted-foreground/30 bg-mauve-300/50 px-2 py-0.5 text-[10px] text-muted-foreground dark:bg-mauve-700/50"
                        :title="`Managed by ${SERVICE_LABELS[ctrl.source]} - Updates automatically`"
                      >
                        <LockIcon class="h-2.5 w-2.5" />
                        {{ SERVICE_LABELS[ctrl.source] }}
                      </span>
                      <span class="font-mono text-muted-foreground">{{ tagKey(ctrl) }}</span>
                    </span>
                  </div>

                  <!-- Source-managed: read-only value display -->
                  <div v-if="ctrl.source_managed" class="min-w-0 truncate font-mono text-sm text-foreground">
                    {{ ctrl.value ?? '-' }}
                  </div>

                  <!-- Free text: typed, so it waits for the button. -->
                  <form v-else-if="ctrl.type === 'text'" @submit.prevent="saveTextValue(ctrl)" @keydown.enter.stop class="group flex gap-0">
                    <input
                      type="text"
                      :id="`cp-input-${ctrl.id}`"
                      :name="`cp-input-${ctrl.id}`"
                      :value="getLocalValue(ctrl)"
                      :title="getLocalValue(ctrl) || 'Click to edit'"
                      @input="localValues[ctrl.id] = String(($event.target as HTMLInputElement).value)"
                      class="peer input-border flex-1"
                      placeholder="Enter text..."
                    />
                    <button
                      type="submit"
                      class="btn btn-sm rounded-none rounded-r-none border border-l-0 border-border bg-background p-2 px-4 text-sm peer-focus:border-violet-400 peer-focus:bg-background hover:bg-violet-400/40 hover:ring-0 dark:border-violet-300/30 dark:peer-focus:border-violet-400"
                      :disabled="saving[ctrl.id]"
                    >
                      <SaveIcon class="h-3.5 w-3.5" />
                    </button>
                  </form>

                  <!-- Counter control -->
                  <div v-else-if="ctrl.type === 'counter'" class="flex items-center gap-3">
                    <div class="min-w-15 text-center text-2xl font-bold tabular-nums">
                      {{ ctrl.value ?? '0' }}
                    </div>
                    <div class="flex gap-1.5">
                      <button
                        class="btn btn-sm btn-secondary px-3 text-lg"
                        :disabled="saving[ctrl.id]"
                        @click="counterAction(ctrl, 'decrement')"
                        title="Decrement"
                      >
                        −
                      </button>
                      <button
                        class="btn btn-sm btn-primary px-3 text-lg"
                        :disabled="saving[ctrl.id]"
                        @click="counterAction(ctrl, 'increment')"
                        title="Increment"
                      >
                        +
                      </button>
                      <button
                        class="btn btn-sm btn-cancel px-3 text-xs"
                        :disabled="saving[ctrl.id]"
                        @click="counterAction(ctrl, 'reset')"
                        title="Reset"
                      >
                        <RotateCcwIcon class="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>

                  <!-- Timer control -->
                  <div v-else-if="ctrl.type === 'timer'" class="flex items-center gap-3">
                    <div class="min-w-22.5 text-center font-mono text-2xl font-bold tabular-nums">
                      <span
                        v-if="isTimerRunning(ctrl) && ctrl.config?.mode !== 'countto'"
                        class="mb-0.75 inline-block size-2 rounded-full bg-green-400"
                      ></span>
                      <span
                        v-if="!isTimerRunning(ctrl) && ctrl.config?.mode !== 'countto'"
                        class="mb-0.75 inline-block size-2 rounded-full bg-red-400"
                      ></span>
                      {{ timerDisplays[ctrl.id] ?? computeTimerDisplay(ctrl) }}
                    </div>
                    <template v-if="ctrl.config?.mode === 'countto'">
                      <span class="text-xs text-muted-foreground"
                        >Counting to {{ ctrl.config?.target_datetime ? formatCountToTarget(ctrl.config.target_datetime) : 'no target set' }}</span
                      >
                    </template>
                    <div v-else class="flex gap-1.5">
                      <button
                        class="btn btn-sm btn-primary px-3"
                        :disabled="saving[ctrl.id]"
                        @click="timerAction(ctrl, isTimerRunning(ctrl) ? 'stop' : 'start')"
                      >
                        <PauseIcon v-if="isTimerRunning(ctrl)" class="h-3.5 w-3.5" />
                        <PlayIcon v-else class="h-3.5 w-3.5" />
                        <span class="ml-1">{{ isTimerRunning(ctrl) ? 'Stop' : 'Start' }}</span>
                      </button>
                      <button class="btn btn-sm btn-cancel px-3" :disabled="saving[ctrl.id]" @click="timerAction(ctrl, 'reset')">
                        <RotateCcwIcon class="h-3.5 w-3.5" />
                        <span class="ml-1">Reset</span>
                      </button>
                    </div>
                  </div>

                  <!-- Expression control (read-only, evaluated in overlay) -->
                  <pre
                    v-else-if="ctrl.type === 'expression'"
                    class="w-full overflow-hidden rounded-sm bg-card p-2 font-mono text-xs text-muted-foreground"
                    >{{ ctrl.config?.expression ?? '' }}</pre>

                  <!-- Datetime control -->
                  <form v-else-if="ctrl.type === 'datetime'" @submit.prevent="saveTextValue(ctrl)" @keydown.enter.stop class="flex gap-0">
                    <input
                      :value="getLocalValue(ctrl)"
                      @input="localValues[ctrl.id] = ($event.target as HTMLInputElement).value"
                      :id="`cp-input-${ctrl.id}`"
                      :name="`cp-input-${ctrl.id}`"
                      type="datetime-local"
                      class="peer input-border flex-1"
                    />
                    <button
                      type="submit"
                      class="btn btn-sm rounded-none rounded-r-none border border-l-0 border-border bg-background p-2 px-4 text-sm peer-focus:border-violet-400 peer-focus:bg-background hover:bg-violet-400/40 hover:ring-0 dark:border-violet-300/30 dark:peer-focus:border-violet-400"
                      :disabled="saving[ctrl.id]"
                    >
                      <SaveIcon class="h-3.5 w-3.5" />
                    </button>
                  </form>
                </template>

                <p v-if="ctrl.description" class="text-xs whitespace-pre-line text-muted-foreground">{{ ctrl.description }}</p>
              </div>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </div>
    </template>

    <div class="flex items-center justify-between gap-3 text-xs">
      <span class="text-foreground">Controls with a lock icon are managed by their source and cannot be manually changed.</span>
      <span class="ml-auto shrink-0">{{ controlsCounter }}/50 Controls in use.</span>
    </div>
  </div>
</template>
