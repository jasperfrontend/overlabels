<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { OverlayControl, OverlayTemplate } from '@/types';

interface ServicePreset {
  key: string;
  label: string;
  type: OverlayControl['type'];
}

const KOFI_PRESETS: ServicePreset[] = [
  { key: 'kofis_received', label: 'Ko-fi Donations Received', type: 'counter' },
  { key: 'latest_donor_name', label: 'Latest Donor Name', type: 'text' },
  { key: 'latest_donation_amount', label: 'Latest Donation Amount', type: 'number' },
  { key: 'latest_donation_message', label: 'Latest Donation Message', type: 'text' },
  { key: 'latest_donation_currency', label: 'Latest Currency', type: 'text' },
  { key: 'total_received', label: 'Total Received (session)', type: 'number' },
];

const GPS_PRESETS: ServicePreset[] = [
  { key: 'gps_speed', label: 'GPS Speed', type: 'number' },
  { key: 'gps_lat', label: 'GPS Latitude', type: 'text' },
  { key: 'gps_lng', label: 'GPS Longitude', type: 'text' },
  { key: 'gps_distance', label: 'GPS Distance (km)', type: 'number' },
];

const TWITCH_PRESETS: ServicePreset[] = [
  { key: 'follows_this_stream', label: 'Followers This Stream', type: 'counter' },
  { key: 'subs_this_stream', label: 'Subs This Stream', type: 'counter' },
  { key: 'gift_subs_this_stream', label: 'Gift Subs This Stream', type: 'counter' },
  { key: 'resubs_this_stream', label: 'Resubs This Stream', type: 'counter' },
  { key: 'raids_this_stream', label: 'Raids This Stream', type: 'counter' },
  { key: 'redemptions_this_stream', label: 'Redemptions This Stream', type: 'counter' },
];

const props = defineProps<{
  open: boolean;
  template: OverlayTemplate;
  control?: OverlayControl | null;
  connectedServices?: string[];
  existingControls?: OverlayControl[];
  userScopedControls?: OverlayControl[];
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'saved', control: OverlayControl): void;
}>();

const isEditing = computed(() => !!props.control);
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const booleanValue = ref(false);

// Service preset — driven by a single select value
const servicePresetKey = ref('');
const servicePresetSource = ref<string | null>(null);

const selectedServicePreset = computed(() => {
  if (!servicePresetKey.value) return null;
  return [...TWITCH_PRESETS, ...KOFI_PRESETS, ...GPS_PRESETS].find((p) => p.key === servicePresetKey.value) ?? null;
});

const showKofiPresets = computed(
  () =>
    !isEditing.value &&
    props.template?.type === 'static' &&
    (props.connectedServices ?? []).includes('kofi'),
);

const showGpsPresets = computed(
  () =>
    !isEditing.value &&
    props.template?.type === 'static' &&
    (props.connectedServices ?? []).includes('gpslogger'),
);

const showTwitchPresets = computed(
  () =>
    !isEditing.value &&
    props.template?.type === 'static',
);

watch(servicePresetKey, (key) => {
  const twitchPreset = TWITCH_PRESETS.find((p) => p.key === key);
  const kofiPreset = KOFI_PRESETS.find((p) => p.key === key);
  const gpsPreset = GPS_PRESETS.find((p) => p.key === key);
  const preset = twitchPreset ?? kofiPreset ?? gpsPreset ?? null;

  if (preset) {
    form.value.key = preset.key;
    form.value.label = preset.label;
    form.value.type = preset.type;
    servicePresetSource.value = twitchPreset ? 'twitch' : gpsPreset ? 'gpslogger' : 'kofi';
  } else {
    form.value.key = '';
    form.value.label = '';
    form.value.type = 'text';
    servicePresetSource.value = null;
  }
  errors.value = {};
});

// Sort order mode
type SortMode = 'before' | 'after' | 'manual';
const sortMode = ref<SortMode>('after');

function resolvedSortOrder(): number {
  const existing = props.existingControls ?? [];
  if (sortMode.value === 'before') {
    if (existing.length === 0) return 0;
    return Math.max(0, Math.min(...existing.map((c) => c.sort_order)) - 1);
  }
  if (sortMode.value === 'after') {
    if (existing.length === 0) return 0;
    return Math.max(...existing.map((c) => c.sort_order)) + 1;
  }
  return form.value.sort_order;
}

const form = ref({
  key: '',
  label: '',
  type: 'text' as OverlayControl['type'],
  value: '',
  config: {
    min: null as number | null,
    max: null as number | null,
    step: 1 as number | null,
    reset_value: 0 as number,
    mode: 'countup' as 'countup' | 'countdown',
    base_seconds: 0 as number,
  },
  sort_order: 0,
});

// Computed control formula state
const formula = ref({
  watch_key: '',
  watch_source: null as string | null,
  operator: '>=' as string,
  compare_value: '',
  then_value: '',
  else_value: '',
});

// Combined dropdown key for watch control: "source:key" or just "key"
const formulaWatchRef = ref('');

watch(formulaWatchRef, (val) => {
  if (!val) {
    formula.value.watch_key = '';
    formula.value.watch_source = null;
    return;
  }
  const parts = val.split(':');
  if (parts.length === 2) {
    formula.value.watch_source = parts[0];
    formula.value.watch_key = parts[1];
  } else {
    formula.value.watch_source = null;
    formula.value.watch_key = parts[0];
  }
});

// Controls available as watch targets for computed controls
const availableWatchControls = computed(() => {
  const templateControls = (props.existingControls ?? []).filter(
    (c) => !['timer', 'datetime'].includes(c.type) && c.id !== props.control?.id,
  );
  const userScoped = (props.userScopedControls ?? []).filter(
    (c) => !['timer', 'datetime'].includes(c.type),
  );
  return [...templateControls, ...userScoped];
});

function watchControlRef(ctrl: OverlayControl): string {
  return ctrl.source ? `${ctrl.source}:${ctrl.key}` : ctrl.key;
}

function watchControlLabel(ctrl: OverlayControl): string {
  const label = ctrl.label || ctrl.key;
  return ctrl.source ? `${label} (${ctrl.source}:${ctrl.key})` : `${label} (${ctrl.key})`;
}

watch(() => props.open, (open) => {
  if (open) {
    errors.value = {};
    servicePresetKey.value = '';
    servicePresetSource.value = null;
    if (props.control) {
      const c = props.control;
      const cfg = c.config ?? {};
      form.value = {
        key: c.key,
        label: c.label ?? '',
        type: c.type,
        value: c.value ?? '',
        config: {
          min: cfg.min ?? null,
          max: cfg.max ?? null,
          step: cfg.step ?? 1,
          reset_value: cfg.reset_value ?? 0,
          mode: cfg.mode ?? 'countup',
          base_seconds: cfg.base_seconds ?? 0,
        },
        sort_order: c.sort_order,
      };
      booleanValue.value = c.value === '1';
      sortMode.value = 'manual';
      // Populate formula state for computed controls
      if (c.type === 'computed' && cfg.formula) {
        formula.value = {
          watch_key: cfg.formula.watch_key ?? '',
          watch_source: cfg.formula.watch_source ?? null,
          operator: cfg.formula.operator ?? '>=',
          compare_value: cfg.formula.compare_value ?? '',
          then_value: cfg.formula.then_value ?? '',
          else_value: cfg.formula.else_value ?? '',
        };
        formulaWatchRef.value = cfg.formula.watch_source
          ? `${cfg.formula.watch_source}:${cfg.formula.watch_key}`
          : cfg.formula.watch_key ?? '';
      }
    } else {
      form.value = {
        key: '',
        label: '',
        type: 'text',
        value: '',
        config: { min: null, max: null, step: 1, reset_value: 0, mode: 'countup', base_seconds: 0 },
        sort_order: 0,
      };
      booleanValue.value = false;
      sortMode.value = 'after';
      formula.value = { watch_key: '', watch_source: null, operator: '>=', compare_value: '', then_value: '', else_value: '' };
      formulaWatchRef.value = '';
    }
  }
});

function buildPayload() {
  const payload: Record<string, any> = {
    label: form.value.label || null,
    sort_order: resolvedSortOrder(),
  };

  if (!isEditing.value) {
    payload.key = form.value.key;
    payload.type = form.value.type;

    if (servicePresetSource.value) {
      payload.source = servicePresetSource.value;
    }
  }

  // Service preset: don't send config/value (service handles it)
  if (selectedServicePreset.value) {
    return payload;
  }

  const t = form.value.type;

  // Computed control: send formula config, no value
  if (t === 'computed') {
    payload.config = {
      formula: {
        watch_key: formula.value.watch_key,
        watch_source: formula.value.watch_source || null,
        operator: formula.value.operator,
        compare_value: formula.value.compare_value,
        then_value: formula.value.then_value,
        else_value: formula.value.else_value,
      },
    };
    return payload;
  }

  if (t === 'number' || t === 'counter') {
    payload.config = {
      min: form.value.config.min,
      max: form.value.config.max,
      step: form.value.config.step,
      reset_value: form.value.config.reset_value,
    };
  } else if (t === 'timer') {
    payload.config = {
      mode: form.value.config.mode,
      base_seconds: form.value.config.base_seconds,
      offset_seconds: 0,
      running: false,
      started_at: null,
    };
  } else {
    payload.config = null;
  }

  if (t !== 'timer' && t !== 'datetime') {
    if (t === 'boolean') {
      payload.value = booleanValue.value ? '1' : '0';
    } else {
      const raw = form.value.value;
      payload.value = raw !== '' && raw != null ? String(raw) : null;
    }
  }

  return payload;
}

async function save() {
  saving.value = true;
  errors.value = {};

  try {
    let response;
    if (isEditing.value) {
      response = await axios.put(
        `/templates/${props.template.id}/controls/${props.control!.id}`,
        buildPayload()
      );
    } else {
      response = await axios.post(
        `/templates/${props.template.id}/controls`,
        buildPayload()
      );
    }

    emit('saved', isEditing.value ? response.data.control : response.data.control);
    emit('update:open', false);
  } catch (err: any) {
    if (err.response?.status === 422) {
      const errs = err.response.data.errors ?? {};
      const flat: Record<string, string> = {};
      for (const [k, v] of Object.entries(errs)) {
        flat[k] = Array.isArray(v) ? (v as string[])[0] : (v as string);
      }
      errors.value = flat;
    } else {
      errors.value = { general: err.response?.data?.message || 'An error occurred. Please try again.' };
    }
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle>{{ isEditing ? 'Edit Control' : 'Add Control' }}</DialogTitle>
      </DialogHeader>

      <div class="space-y-4 py-2">
        <p v-if="errors.general" class="text-sm text-destructive">{{ errors.general }}</p>

        <!-- Service Presets (Twitch / Ko-fi / GPSLogger) -->
        <div v-if="showTwitchPresets || showKofiPresets || showGpsPresets" class="space-y-2 rounded-sm border border-violet-400/30 bg-violet-400/5 p-3">
          <p class="text-sm font-medium text-violet-500 dark:text-violet-400">Stream Controls</p>
          <select
            v-model="servicePresetKey"
            class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
          >
            <option value="">— Select a preset control —</option>
            <optgroup v-if="showTwitchPresets" label="Twitch — Per-Stream Counters">
              <option v-for="preset in TWITCH_PRESETS" :key="preset.key" :value="preset.key">
                {{ preset.label }} ({{ preset.type }})
              </option>
            </optgroup>
            <optgroup v-if="showKofiPresets" label="Ko-fi">
              <option v-for="preset in KOFI_PRESETS" :key="preset.key" :value="preset.key">
                {{ preset.label }} ({{ preset.type }})
              </option>
            </optgroup>
            <optgroup v-if="showGpsPresets" label="GPSLogger">
              <option v-for="preset in GPS_PRESETS" :key="preset.key" :value="preset.key">
                {{ preset.label }} ({{ preset.type }})
              </option>
            </optgroup>
          </select>
          <p v-if="selectedServicePreset && servicePresetSource" class="text-xs text-muted-foreground">
            Use <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:{{ servicePresetSource }}:{{ selectedServicePreset.key }}]]]</code>
            in your template. Value is managed automatically{{ servicePresetSource === 'twitch' ? ' — resets when you go live' : '' }}.
          </p>
        </div>

        <!-- Only show manual fields if no service preset selected -->
        <template v-if="!selectedServicePreset">
          <!-- Key (immutable after creation) -->
          <div class="space-y-1">
            <Label for="ctrl-key">Key <span class="text-muted-foreground text-xs">(used in template as <code>[[[c:key]]]</code>)</span></Label>
            <Input
              id="ctrl-key"
              v-model="form.key"
              :disabled="isEditing"
              placeholder="e.g. deaths"
              :class="{ 'border-destructive': errors.key }"
            />
            <p v-if="errors.key" class="text-xs text-destructive">{{ errors.key }}</p>
            <p v-else class="text-xs text-muted-foreground">Lowercase letters, numbers, underscores only. Cannot be changed after creation.</p>
          </div>
        </template>

        <!-- Label (always shown) -->
        <div class="space-y-1">
          <Label for="ctrl-label">Label <span class="text-muted-foreground text-xs">(optional display name)</span></Label>
          <Input
            id="ctrl-label"
            v-model="form.label"
            :placeholder="selectedServicePreset ? selectedServicePreset.label : 'e.g. Death Counter'"
            :class="{ 'border-destructive': errors.label }"
          />
          <p v-if="errors.label" class="text-xs text-destructive">{{ errors.label }}</p>
        </div>

        <!-- Only show type/value/config if no Ko-fi preset selected -->
        <template v-if="!selectedServicePreset">
          <!-- Type -->
          <div v-if="!isEditing" class="space-y-1">
            <Label for="ctrl-type">Type</Label>
            <select
              id="ctrl-type"
              v-model="form.type"
              class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none"
            >
              <option value="text">Text</option>
              <option value="number">Number</option>
              <option value="counter">Counter</option>
              <option value="timer">Timer</option>
              <option value="datetime">Date/Time</option>
              <option value="boolean">Boolean (on/off switch)</option>
              <option value="computed">Computed (auto-calculated)</option>
            </select>
            <p v-if="errors.type" class="text-xs text-destructive">{{ errors.type }}</p>
          </div>

          <!-- Computed formula builder -->
          <div v-if="form.type === 'computed'" class="space-y-3 rounded-sm border border-violet-400/30 bg-violet-400/5 p-3">
            <p class="text-sm font-medium text-violet-500 dark:text-violet-400">Formula</p>

            <div class="space-y-1">
              <Label for="formula-watch">Watch control</Label>
              <select
                id="formula-watch"
                v-model="formulaWatchRef"
                class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
              >
                <option value="">-- Select a control --</option>
                <option v-for="ctrl in availableWatchControls" :key="ctrl.id" :value="watchControlRef(ctrl)">
                  {{ watchControlLabel(ctrl) }}
                </option>
              </select>
              <p v-if="errors['config.formula.watch_key']" class="text-xs text-destructive">{{ errors['config.formula.watch_key'] }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <Label for="formula-op">Operator</Label>
                <select
                  id="formula-op"
                  v-model="formula.operator"
                  class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
                >
                  <option value="==">== (equals)</option>
                  <option value="!=">!= (not equals)</option>
                  <option value=">">&gt; (greater than)</option>
                  <option value="<">&lt; (less than)</option>
                  <option value=">=">&gt;= (greater or equal)</option>
                  <option value="<=">&lt;= (less or equal)</option>
                </select>
              </div>
              <div class="space-y-1">
                <Label for="formula-compare">Compare value</Label>
                <Input id="formula-compare" v-model="formula.compare_value" placeholder="e.g. 5" />
                <p v-if="errors['config.formula.compare_value']" class="text-xs text-destructive">{{ errors['config.formula.compare_value'] }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <Label for="formula-then">Then value <span class="text-muted-foreground text-xs">(condition true)</span></Label>
                <Input id="formula-then" v-model="formula.then_value" placeholder="e.g. 50" />
                <p v-if="errors['config.formula.then_value']" class="text-xs text-destructive">{{ errors['config.formula.then_value'] }}</p>
              </div>
              <div class="space-y-1">
                <Label for="formula-else">Else value <span class="text-muted-foreground text-xs">(condition false)</span></Label>
                <Input id="formula-else" v-model="formula.else_value" placeholder="e.g. 10" />
                <p v-if="errors['config.formula.else_value']" class="text-xs text-destructive">{{ errors['config.formula.else_value'] }}</p>
              </div>
            </div>

            <p v-if="formulaWatchRef && formula.compare_value" class="text-xs text-muted-foreground">
              WHEN <code class="rounded bg-black/10 px-1 dark:bg-white/10">{{ formulaWatchRef }}</code>
              {{ formula.operator }} {{ formula.compare_value }}
              THEN <code class="rounded bg-black/10 px-1 dark:bg-white/10">{{ formula.then_value || '(empty)' }}</code>
              ELSE <code class="rounded bg-black/10 px-1 dark:bg-white/10">{{ formula.else_value || '(empty)' }}</code>
            </p>
          </div>

          <!-- Value (text/number/counter/datetime) -->
          <div v-if="form.type !== 'timer' && form.type !== 'boolean' && form.type !== 'computed'" class="space-y-1">
            <Label for="ctrl-value">{{ isEditing ? 'Value' : 'Initial Value' }} <span class="text-muted-foreground text-xs">(optional)</span></Label>
            <Input
              id="ctrl-value"
              v-model="form.value"
              :type="form.type === 'number' || form.type === 'counter' ? 'number' : form.type === 'datetime' ? 'datetime-local' : 'text'"
              placeholder="Leave blank to start empty"
            />
            <p v-if="errors.value" class="text-xs text-destructive">{{ errors.value }}</p>
          </div>

          <!-- Value (boolean) -->
          <div v-if="form.type === 'boolean'" class="space-y-1">
            <Label>{{ isEditing ? 'Value' : 'Initial Value' }}</Label>
            <div class="flex items-center gap-3 pt-1">
              <Switch v-model:checked="booleanValue" />
              <span class="text-sm text-muted-foreground">{{ booleanValue ? 'On (true)' : 'Off (false)' }}</span>
            </div>
            <p v-if="errors.value" class="text-xs text-destructive">{{ errors.value }}</p>
          </div>

          <!-- Number/Counter config -->
          <div v-if="form.type === 'number' || form.type === 'counter'" class="space-y-3 rounded-sm border border-sidebar p-3">
            <p class="text-sm font-medium">Numeric settings</p>
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <Label for="ctrl-min">Min</Label>
                <Input id="ctrl-min" v-model.number="form.config.min" type="number" placeholder="No limit" />
              </div>
              <div class="space-y-1">
                <Label for="ctrl-max">Max</Label>
                <Input id="ctrl-max" v-model.number="form.config.max" type="number" placeholder="No limit" />
              </div>
              <div class="space-y-1">
                <Label for="ctrl-step">Step</Label>
                <Input id="ctrl-step" v-model.number="form.config.step" type="number" min="0" step="any" />
              </div>
              <div class="space-y-1">
                <Label for="ctrl-reset">Reset value</Label>
                <Input id="ctrl-reset" v-model.number="form.config.reset_value" type="number" step="any" />
              </div>
            </div>
          </div>

          <!-- Timer config -->
          <div v-if="form.type === 'timer'" class="space-y-3 rounded-sm border border-sidebar p-3">
            <p class="text-sm font-medium">Timer settings</p>
            <div class="space-y-2">
              <Label>Mode</Label>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="form.config.mode" value="countup" />
                  <span class="text-sm">Count up</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="form.config.mode" value="countdown" />
                  <span class="text-sm">Count down</span>
                </label>
              </div>
            </div>
            <div v-if="form.config.mode === 'countdown'" class="space-y-1">
              <Label for="ctrl-base">Base duration (seconds)</Label>
              <Input id="ctrl-base" v-model.number="form.config.base_seconds" type="number" min="0" />
            </div>
          </div>
        </template>

        <!-- Sort order -->
        <div class="space-y-1">
          <Label for="ctrl-sort">Position</Label>
          <select
            id="ctrl-sort"
            v-model="sortMode"
            class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
          >
            <option value="after">After existing (last)</option>
            <option value="before">Before existing (first)</option>
            <option value="manual">Enter sort order manually</option>
          </select>
          <Input
            v-if="sortMode === 'manual'"
            v-model.number="form.sort_order"
            type="number"
            min="0"
            placeholder="0"
            class="mt-1.5"
          />
          <p v-if="errors.sort_order" class="text-xs text-destructive">{{ errors.sort_order }}</p>
        </div>
      </div>

      <DialogFooter>
        <button class="btn btn-cancel" @click="emit('update:open', false)">Cancel</button>
        <button class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Saving...' : isEditing ? 'Save changes' : 'Add control' }}
        </button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
