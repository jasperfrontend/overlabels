<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import RekaToast from '@/components/RekaToast.vue';
import { PlayIcon, PauseIcon, RotateCcwIcon, SaveIcon, LockIcon, Search, ChevronRight, ChevronsUpDown, ChevronsDownUp } from 'lucide-vue-next';
import type { OverlayControl, OverlayTemplate } from '@/types';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

const props = defineProps<{
  template: OverlayTemplate;
  controls: OverlayControl[];
  isLive?: boolean;
}>();

/** Known external service sources and their display labels. */
const SERVICE_LABELS: Record<string, string> = {
  kofi: 'Ko-fi',
  streamlabs: 'Streamlabs',
  gpslogger: 'GPSLogger',
};

/** Build the template tag key: c:source:key for external, c:key for twitch/user. */
function tagKey(ctrl: OverlayControl): string {
  if (ctrl.source && ctrl.source !== 'twitch') {
    return `c:${ctrl.source}:${ctrl.key}`;
  }
  return `c:${ctrl.key}`;
}

function isTwitchOffline(ctrl: OverlayControl): boolean {
  return ctrl.source === 'twitch' && ctrl.source_managed && !props.isLive;
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
    if (ctrl.source && ctrl.source !== 'twitch' && SERVICE_LABELS[ctrl.source]) {
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
    expression: 'Expression',
    datetime: 'Date/Time',
  };

  const typeOrder = ['counter', 'timer', 'number', 'text', 'boolean', 'expression', 'datetime'];
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

async function counterAction(ctrl: OverlayControl, action: 'increment' | 'decrement' | 'reset') {
  await postValue(ctrl, { action });
}

async function timerAction(ctrl: OverlayControl, action: 'start' | 'stop' | 'reset') {
  await postValue(ctrl, { action });
}

const isTimerRunning = (ctrl: OverlayControl) => Boolean(ctrl.config?.running);

async function toggleBoolean(ctrl: OverlayControl) {
  const newValue = ctrl.value === '1' ? '0' : '1';
  await postValue(ctrl, { value: newValue });
  showMsg(`"${ctrl.label || ctrl.key}" ${newValue === '1' ? 'enabled' : 'disabled'}.`);
}
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
      <div class="h-7.5"></div>
    </div>

    <div v-if="controls.length === 0" class="bg-sidebar-accent p-8 text-center text-muted-foreground">No Controls for this Overlay.</div>

    <template v-if="controls.length > 0">
      <!-- Search and collapse/expand bar -->
      <div class="mb-4 flex items-center gap-3">
        <div class="relative flex-1 gap-2">
          <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
          <input
            v-model="searchQuery"
            placeholder="Filter controls..."
            class="input-border w-full pl-8 pr-2.5 py-1.5 text-sm"
          />
        </div>
      </div>

      <!-- Count and collapse/expand toggle -->
      <div class="mb-3 flex items-center text-xs text-muted-foreground">
        <span v-if="searchQuery">
          {{ totalVisibleControls }} control{{ totalVisibleControls !== 1 ? 's' : '' }} in {{ filteredGroupedControls.length }} group{{ filteredGroupedControls.length !== 1 ? 's' : '' }}
        </span>
        <span v-else>
          {{ controls.length }} controls across {{ groupedControls.length }} groups
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
            class="group flex w-full cursor-pointer bg-sidebar items-center gap-2 rounded-md px-2 py-4 text-left transition-colors hover:bg-sidebar-accent/50"
            :class="{ 'bg-sidebar-accent/50 rounded-b-none pb-0': isGroupExpanded(group.label) }"
          >
            <ChevronRight
              :size="14"
              class="shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-90"
            />
            <span class="text-sm font-medium">{{ group.label }}</span>
            <span class="ml-auto text-xs px-2.5 py-1.5 bg-card">{{ group.controls.length }}</span>
          </CollapsibleTrigger>

          <CollapsibleContent>
            <div class="grid grid-cols-1 bg-sidebar/50 lg:grid-cols-2 xl:grid-cols-3 gap-4 p-4">
          <div v-for="ctrl in group.controls" :key="ctrl.id" :class="[
            'p-6 transition-all duration-500 bg-sidebar',
            !ctrl.source_managed && ctrl.type === 'timer' && ctrl.config?.mode !== 'countto' && isTimerRunning(ctrl) && 'bg-linear-to-br from-green-500/15 to-background',
            !ctrl.source_managed && ctrl.type === 'timer' && ctrl.config?.mode !== 'countto' && !isTimerRunning(ctrl) && 'bg-linear-to-br from-red-500/15 to-background',
          ]">
            <div class="mb-2">
              <div class="flex items-center justify-between mb-4">
                <div class="flex flex-col gap-1 items-start">
                  <label :for="`cp-input-${ctrl.id}`"><span class="font-medium text-foreground">{{ ctrl.label || ctrl.key }}</span></label>
                  <span class="font-mono text-xs text-muted-foreground">{{ tagKey(ctrl) }}</span>
                </div>
                <div class="flex flex-col text-center gap-2">
                  <span class="text-xs text-foreground capitalize">{{ ctrl.type }}</span>
                  <span v-if="isTwitchOffline(ctrl)" title="This Control only works when you're streaming" class="rounded-full border border-muted-foreground/30 px-2 py-0.5 text-[10px] text-muted-foreground">Offline</span>
                  <span v-if="ctrl.source && ctrl.source_managed && ctrl.source !== 'twitch'" class="inline-flex items-center gap-1 bg-mauve-300/50 dark:bg-mauve-700/50 rounded-full border border-muted-foreground/30 px-2 py-0.5 text-[10px] text-muted-foreground" :title="`Managed by ${SERVICE_LABELS[ctrl.source]} - Updates automatically`">
                    <LockIcon class="h-2.5 w-2.5" />
                    {{ SERVICE_LABELS[ctrl.source] }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Source-managed: read-only value display -->
            <div v-if="ctrl.source_managed" class="flex items-center gap-3">
              <div class="min-w-0 flex-1 font-mono text-sm text-foreground truncate">
                {{ ctrl.value ?? '-' }}
              </div>
            </div>

            <!-- Text control -->
            <template v-else-if="ctrl.type === 'text'">
              <form @submit.prevent="saveTextValue(ctrl)" class="flex group gap-0">
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
                  class="btn btn-sm rounded-none bg-background rounded-r-none border border-l-0 border-border dark:border-violet-300/30 p-2 px-4 text-sm peer-focus:border-violet-400 peer-focus:bg-background hover:bg-violet-400/40 dark:peer-focus:border-violet-400 hover:ring-0"
                  :disabled="saving[ctrl.id]"
                  @click="saveTextValue(ctrl)"
                >
                  <SaveIcon class="h-3.5 w-3.5" />
                </button>
              </form>
            </template>

            <!-- Number control -->
            <template v-else-if="ctrl.type === 'number'">
              <div class="flex">
                <input
                  :value="getLocalValue(ctrl)"
                  :title="getLocalValue(ctrl) || 'Click to edit'"
                  :id="`cp-input-${ctrl.id}`"
                  :name="`cp-input-${ctrl.id}`"
                  @input="localValues[ctrl.id] = String(($event.target as HTMLInputElement).value)"
                  type="number"
                  :min="ctrl.config?.min"
                  :max="ctrl.config?.max"
                  :step="ctrl.config?.step ?? 1"
                  class="peer input-border flex-1"
                />
                <button type="submit" class="btn btn-sm rounded-none bg-background rounded-r-none border border-l-0 border-border dark:border-violet-300/30 p-2 px-4 text-sm peer-focus:border-violet-400 peer-focus:bg-background hover:bg-violet-400/40 dark:peer-focus:border-violet-400 hover:ring-0" :disabled="saving[ctrl.id]" @click="saveTextValue(ctrl)">
                  <SaveIcon class="h-3.5 w-3.5" />
                </button>
              </div>
            </template>

            <!-- Counter control -->
            <template v-else-if="ctrl.type === 'counter'">
              <div class="flex items-center gap-3">
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
                  <button class="btn btn-sm btn-primary px-3 text-lg" :disabled="saving[ctrl.id]" @click="counterAction(ctrl, 'increment')" title="Increment">
                    +
                  </button>
                  <button class="btn btn-sm btn-cancel px-3 text-xs" :disabled="saving[ctrl.id]" @click="counterAction(ctrl, 'reset')" title="Reset">
                    <RotateCcwIcon class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            </template>

            <!-- Timer control -->
            <template v-else-if="ctrl.type === 'timer'">
              <div class="flex items-center gap-3">
                <div class="min-w-22.5 text-center font-mono text-2xl font-bold tabular-nums">
                  <span v-if="isTimerRunning(ctrl) && ctrl.config?.mode !== 'countto'" class="size-2 mb-0.75 inline-block rounded-full bg-green-400"></span>
                  <span v-if="!isTimerRunning(ctrl) && ctrl.config?.mode !== 'countto'" class="size-2 mb-0.75 inline-block rounded-full bg-red-400"></span>
                  {{ timerDisplays[ctrl.id] ?? computeTimerDisplay(ctrl) }}
                </div>
                <template v-if="ctrl.config?.mode === 'countto'">
                  <span class="text-xs text-muted-foreground">Counting to {{ ctrl.config?.target_datetime ? new Date(ctrl.config.target_datetime).toLocaleString() : 'no target set' }}</span>
                </template>
                <div v-else class="flex gap-1.5">
                  <button class="btn btn-sm btn-primary px-3" :disabled="saving[ctrl.id]" @click="timerAction(ctrl, isTimerRunning(ctrl) ? 'stop' : 'start')">
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
            </template>

            <!-- Boolean control -->
            <template v-else-if="ctrl.type === 'boolean'">
              <div class="flex items-center gap-3">
                <button
                  type="button"
                  role="switch"
                  :aria-checked="ctrl.value === '1'"
                  :title="ctrl.value === '1' ? 'Enabled' : 'Disabled'"
                  :disabled="saving[ctrl.id]"
                  @click="toggleBoolean(ctrl)"
                  :class="[
                    'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                    ctrl.value === '1' ? 'bg-accent' : 'bg-input',
                  ]"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-4 w-4 rounded-full bg-accent-foreground shadow-sm ring-0 transition-transform dark:bg-white',
                      ctrl.value === '1' ? 'translate-x-4' : 'translate-x-0',
                    ]"
                  />
                </button>
                <span class="text-sm uppercase" :class="['text-sm', ctrl.value === '1' ? 'text-green-400' : 'text-muted-foreground']">
                  {{ctrl.value === '1' ? 'On' : 'Off'}}
                </span>
              </div>
            </template>

            <!-- Expression control (read-only, evaluated in overlay) -->
            <template v-else-if="ctrl.type === 'expression'">
              <div class="flex items-center gap-3">
                <pre class="text-xs bg-card w-full rounded-sm p-2 text-muted-foreground font-mono">{{ ctrl.config?.expression ?? '' }}</pre>
              </div>
            </template>

            <!-- Datetime control -->
            <template v-else-if="ctrl.type === 'datetime'">
              <div class="flex gap-0">
                <input
                  :value="getLocalValue(ctrl)"
                  @input="localValues[ctrl.id] = ($event.target as HTMLInputElement).value"
                  :id="`cp-input-${ctrl.id}`"
                  :name="`cp-input-${ctrl.id}`"
                  type="datetime-local"
                  class="peer input-border flex-1"
                />
                <button class="btn btn-sm rounded-none bg-background rounded-r-none border border-l-0 border-border dark:border-violet-300/30 p-2 px-4 text-sm peer-focus:border-violet-400 peer-focus:bg-background hover:bg-violet-400/40 dark:peer-focus:border-violet-400 hover:ring-0" :disabled="saving[ctrl.id]" @click="saveTextValue(ctrl)">
                  <SaveIcon class="h-3.5 w-3.5" />
                </button>
              </div>
            </template>
          </div>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </div>
    </template>
  </div>
</template>
