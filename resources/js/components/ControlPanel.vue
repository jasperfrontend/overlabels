<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import RekaToast from '@/components/RekaToast.vue';
import { PlayIcon, PauseIcon, RotateCcwIcon, SaveIcon } from 'lucide-vue-next';
import type { OverlayControl, OverlayTemplate } from '@/types';
import RefreshIcon from '@/components/RefreshIcon.vue';

const props = defineProps<{
  template: OverlayTemplate;
  controls: OverlayControl[];
}>();

const toastMessage = ref('');
const toastType = ref<'success' | 'error'>('success');
const showToast = ref(false);

// Per-control local input values for text/number/datetime
const localValues = ref<Record<number, string>>({});
// Per-control saving state
const saving = ref<Record<number, boolean>>({});
// Timer display intervals
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
  if (!cfg.running) return;

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

// Initialize timer displays
props.controls.forEach((ctrl) => {
  if (ctrl.type === 'timer') {
    startTimerTick(ctrl);
  }
});

async function postValue(ctrl: OverlayControl, payload: Record<string, any>) {
  saving.value[ctrl.id] = true;
  try {
    const { data } = await axios.post(`/templates/${props.template.id}/controls/${ctrl.id}/value`, payload);

    // Update local control state
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
      <p class="text-sm text-muted-foreground">
        Manage the values of the Controls created in this Overlay. Check
        <a class="text-violet-400 hover:underline" href="/help/controls" target="_blank">the guide</a> to see how to implement Controls in your
        Overlays.
      </p>
      <button class="btn btn-primary btn-sm btn-dead opacity-0">
        <RefreshIcon class="mr-1.5 h-3.5 w-3.5" />
        Refresh
      </button>
    </div>

    <div v-if="controls.length === 0" class="bg-sidebar-accent p-8 text-center text-muted-foreground">No Controls for this Overlay.</div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div v-for="ctrl in controls" :key="ctrl.id" class="border border-border bg-background p-3 pt-2 pb-4">
        <div class="mb-2 flex items-center justify-between">
          <div>
            <span class="font-medium">{{ ctrl.label || ctrl.key }}</span>
            <span class="ml-2 font-mono text-xs text-muted-foreground">c:{{ ctrl.key }}</span>
          </div>
          <span class="text-xs text-muted-foreground capitalize">{{ ctrl.type }}</span>
        </div>

        <!-- Text control -->
        <div v-if="ctrl.type === 'text'" class="flex gap-0">
          <input
            type="text"
            :value="getLocalValue(ctrl)"
            :title="getLocalValue(ctrl) || 'Click to edit'"
            @input="localValues[ctrl.id] = String(($event.target as HTMLInputElement).value)"
            class="peer input-border flex-1"
            placeholder="Enter text..."
          />
          <button
            class="btn btn-sm rounded-none rounded-r-none border border-l-0 border-border p-2 px-4 text-sm peer-focus:border-gray-400 peer-focus:bg-gray-400/20 hover:bg-gray-400/40 hover:ring-0"
            :disabled="saving[ctrl.id]"
            @click="saveTextValue(ctrl)"
          >
            <SaveIcon class="h-3.5 w-3.5" />
            <span class="ml-1">Save</span>
          </button>
        </div>

        <!-- Number control -->
        <div v-else-if="ctrl.type === 'number'" class="flex gap-2">
          <input
            :value="getLocalValue(ctrl)"
            :title="getLocalValue(ctrl) || 'Click to edit'"
            @input="localValues[ctrl.id] = String(($event.target as HTMLInputElement).value)"
            type="number"
            :min="ctrl.config?.min"
            :max="ctrl.config?.max"
            :step="ctrl.config?.step ?? 1"
            class="input-border flex-1"
          />
          <button class="btn btn-primary btn-sm" :disabled="saving[ctrl.id]" @click="saveTextValue(ctrl)">
            <SaveIcon class="h-3.5 w-3.5" />
            <span class="ml-1">Save</span>
          </button>
        </div>

        <!-- Counter control -->
        <div v-else-if="ctrl.type === 'counter'" class="flex items-center gap-3">
          <div class="min-w-[60px] text-center text-2xl font-bold tabular-nums">
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

        <!-- Timer control -->
        <div v-else-if="ctrl.type === 'timer'" class="flex items-center gap-3">
          <div class="min-w-[90px] text-center font-mono text-2xl font-bold tabular-nums">
            {{ timerDisplays[ctrl.id] ?? computeTimerDisplay(ctrl) }}
          </div>
          <div class="flex gap-1.5">
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

        <!-- Boolean control -->
        <div v-else-if="ctrl.type === 'boolean'" class="flex items-center gap-3">
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
          <span class="text-sm uppercase" :class="['text-sm', ctrl.value === '1' ? 'text-green-400' : 'text-muted-foreground']">{{
            ctrl.value === '1' ? 'On' : 'Off'
          }}</span>
        </div>

        <!-- Datetime control -->
        <div v-else-if="ctrl.type === 'datetime'" class="flex gap-2">
          <input
            :value="getLocalValue(ctrl)"
            @input="localValues[ctrl.id] = ($event.target as HTMLInputElement).value"
            type="datetime-local"
            class="flex-1 rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:outline-none"
          />
          <button class="btn btn-primary btn-sm" :disabled="saving[ctrl.id]" @click="saveTextValue(ctrl)">
            <SaveIcon class="h-3.5 w-3.5" />
            <span class="ml-1">Save</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
