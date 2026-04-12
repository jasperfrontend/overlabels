<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue';
import axios from 'axios';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import ExpressionBuilder from '@/components/controls/ExpressionBuilder.vue';
import {
  KOFI_PRESETS,
  GPS_PRESETS,
  STREAMLABS_PRESETS,
  STREAMELEMENTS_PRESETS,
  TWITCH_PRESETS,
  getPresetsForSource,
} from '@/components/controls/controlPresets';
import type { OverlayControl, OverlayTemplate } from '@/types';

const props = defineProps<{
  open: boolean;
  template: OverlayTemplate;
  control?: OverlayControl | null;
  copyFrom?: OverlayControl | null;
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
const manualInputRef = ref<HTMLInputElement | null>(null);

// Service preset selection
const servicePresetKey = ref('');
const servicePresetSource = ref<string | null>(null);

const selectedServicePreset = computed(() => {
  if (!servicePresetKey.value || !servicePresetSource.value) return null;
  const presets = getPresetsForSource(servicePresetSource.value);
  const key = servicePresetKey.value.substring(servicePresetKey.value.indexOf(':') + 1);
  return presets.find((p) => p.key === key) ?? null;
});

const isCopying = computed(() => !isEditing.value && !!props.copyFrom);
const showKofiPresets = computed(
  () => !isEditing.value && !isCopying.value && props.template?.type === 'static' && (props.connectedServices ?? []).includes('kofi'),
);
const showGpsPresets = computed(
  () => !isEditing.value && !isCopying.value && props.template?.type === 'static' && (props.connectedServices ?? []).includes('gpslogger'),
);
const showStreamLabsPresets = computed(
  () => !isEditing.value && !isCopying.value && props.template?.type === 'static' && (props.connectedServices ?? []).includes('streamlabs'),
);
const showStreamElementsPresets = computed(
  () => !isEditing.value && !isCopying.value && props.template?.type === 'static' && (props.connectedServices ?? []).includes('streamelements'),
);
const showTwitchPresets = computed(
  () => !isEditing.value && !isCopying.value && props.template?.type === 'static',
);

// Filter out presets that already exist as controls on this template
function isPresetAlreadyAdded(source: string, key: string): boolean {
  return (props.existingControls ?? []).some(
    (c) => c.source === source && c.key === key,
  );
}

const availableTwitchPresets = computed(() => TWITCH_PRESETS.filter((p) => !isPresetAlreadyAdded('twitch', p.key)));
const availableKofiPresets = computed(() => KOFI_PRESETS.filter((p) => !isPresetAlreadyAdded('kofi', p.key)));
const availableGpsPresets = computed(() => GPS_PRESETS.filter((p) => !isPresetAlreadyAdded('gpslogger', p.key)));
const availableStreamLabsPresets = computed(() => STREAMLABS_PRESETS.filter((p) => !isPresetAlreadyAdded('streamlabs', p.key)));
const availableStreamElementsPresets = computed(() => STREAMELEMENTS_PRESETS.filter((p) => !isPresetAlreadyAdded('streamelements', p.key)));

watch(servicePresetKey, (combinedKey) => {
  if (!combinedKey) {
    form.value.key = '';
    form.value.label = '';
    form.value.type = 'text';
    servicePresetSource.value = null;
    errors.value = {};
    return;
  }

  const separatorIndex = combinedKey.indexOf(':');
  const source = combinedKey.substring(0, separatorIndex);
  const key = combinedKey.substring(separatorIndex + 1);
  const preset = getPresetsForSource(source).find((p) => p.key === key) ?? null;

  if (preset) {
    form.value.key = preset.key;
    form.value.label = preset.label;
    form.value.type = preset.type;
    servicePresetSource.value = source;
  } else {
    form.value.key = '';
    form.value.label = '';
    form.value.type = 'text';
    servicePresetSource.value = null;
  }
  errors.value = {};
});

// Sort order
type SortMode = 'before' | 'after' | 'manual';
const sortMode = ref<SortMode>('after');

watch(sortMode, (newMode) => {
  if (newMode === 'manual') {
    nextTick(() => {
      manualInputRef.value?.focus();
    });
  }
});

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
    min: undefined as number | undefined,
    max: undefined as number | undefined,
    step: 1 as number | undefined,
    reset_value: 0 as number,
    random: false as boolean,
    random_interval: 1000 as number,
    mode: 'countup' as 'countup' | 'countdown' | 'countto',
    base_seconds: 0 as number,
    target_datetime: '' as string,
  },
  sort_order: 0,
});

// Auto-derive key from label
const keyManuallyEdited = ref(false);
const keyWarning = ref('');

function slugifyLabel(label: string): string {
  return label
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s_]/g, '')
    .replace(/\s+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '');
}

function validateKey(key: string): string {
  if (!key) return '';
  if (/\s/.test(key)) return 'Keys cannot contain spaces. Use underscores instead.';
  if (/[A-Z]/.test(key)) return 'Keys must be lowercase.';
  if (/^_|_$/.test(key)) return 'Keys cannot start or end with an underscore.';
  if (/^[0-9]/.test(key)) return 'Keys cannot start with a number.';
  if (!/^[a-z0-9_]+$/.test(key)) return 'Only lowercase letters, numbers and underscores allowed.';
  return '';
}

watch(() => form.value.label, (label) => {
  if (!selectedServicePreset.value && !isEditing.value && !keyManuallyEdited.value) {
    form.value.key = slugifyLabel(label);
  }
});

watch(() => form.value.key, (key) => {
  keyWarning.value = validateKey(key);
});

// Expression control state
const expressionText = ref('');

// Controls available as watch targets for expression controls
const availableWatchControls = computed(() => {
  const templateControls = (props.existingControls ?? []).filter(
    (c) => c.id !== props.control?.id,
  );
  const userScoped = (props.userScopedControls ?? []);
  return [...templateControls, ...userScoped];
});

watch(() => props.open, (open) => {
  if (open) {
    errors.value = {};
    keyManuallyEdited.value = false;
    keyWarning.value = '';
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
          min: cfg.min ?? undefined,
          max: cfg.max ?? undefined,
          step: cfg.step ?? 1,
          reset_value: cfg.reset_value ?? 0,
          random: cfg.random ?? false,
          random_interval: cfg.random_interval ?? 1000,
          mode: cfg.mode ?? 'countup',
          base_seconds: cfg.base_seconds ?? 0,
          target_datetime: cfg.target_datetime ?? '',
        },
        sort_order: c.sort_order,
      };
      booleanValue.value = c.value === '1';
      sortMode.value = 'manual';
      expressionText.value = c.type === 'expression' ? (cfg.expression ?? '') : '';
    } else if (props.copyFrom) {
      const c = props.copyFrom;
      const cfg = c.config ?? {};
      form.value = {
        key: '',
        label: `${c.label || c.key} (copy)`,
        type: c.type,
        value: c.value ?? '',
        config: {
          min: cfg.min ?? undefined,
          max: cfg.max ?? undefined,
          step: cfg.step ?? 1,
          reset_value: cfg.reset_value ?? 0,
          random: cfg.random ?? false,
          random_interval: cfg.random_interval ?? 1000,
          mode: cfg.mode ?? 'countup',
          base_seconds: cfg.base_seconds ?? 0,
          target_datetime: cfg.target_datetime ?? '',
        },
        sort_order: 0,
      };
      booleanValue.value = c.value === '1';
      sortMode.value = 'after';
      expressionText.value = c.type === 'expression' ? (cfg.expression ?? '') : '';
    } else {
      form.value = {
        key: '',
        label: '',
        type: 'text',
        value: '',
        config: { min: undefined, max: undefined, step: 1, reset_value: 0, random: false, random_interval: 1000, mode: 'countup', base_seconds: 0, target_datetime: '' },
        sort_order: 0,
      };
      booleanValue.value = false;
      sortMode.value = 'after';
      expressionText.value = '';
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

  if (selectedServicePreset.value) return payload;

  const t = form.value.type;

  if (t === 'expression') {
    payload.config = { expression: expressionText.value };
    return payload;
  }

  if (t === 'number' || t === 'counter') {
    payload.config = {
      min: form.value.config.min ?? null,
      max: form.value.config.max ?? null,
      step: form.value.config.step ?? null,
      reset_value: form.value.config.reset_value,
      random: form.value.config.random || false,
      random_interval: form.value.config.random ? (form.value.config.random_interval || 1000) : null,
    };
  } else if (t === 'timer') {
    payload.config = {
      mode: form.value.config.mode,
      base_seconds: form.value.config.mode === 'countdown' ? form.value.config.base_seconds : 0,
      target_datetime: form.value.config.mode === 'countto' ? form.value.config.target_datetime : null,
      offset_seconds: 0,
      running: false,
      started_at: null,
    };
  } else {
    payload.config = null;
  }

  if (t !== 'timer') {
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

    emit('saved', response.data.control);
    emit('update:open', false);
  } catch (err: any) {
    if (err.response?.status === 422) {
      const errs = err.response.data.errors ?? {};
      const flat: Record<string, string> = {};
      for (const [k, v] of Object.entries(errs)) {
        flat[k] = Array.isArray(v) ? (v as string[])[0] : (v as string);
      }
      // abort(422, message) returns message without errors object
      if (Object.keys(flat).length === 0 && err.response.data.message) {
        flat.general = err.response.data.message;
      }
      // Surface key errors as general when the key field is hidden (preset mode)
      if (flat.key && selectedServicePreset.value) {
        flat.general = flat.key;
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
    <DialogContent :class="form.type === 'expression' ? 'max-w-300' : 'max-w-lg'">
      <DialogHeader>
        <DialogTitle>{{ isEditing ? 'Edit Control' : copyFrom ? 'Duplicate Control' : 'Add Control' }}</DialogTitle>
      </DialogHeader>

      <div :class="form.type === 'expression' ? 'grid grid-cols-1 gap-6 py-2 md:grid-cols-2' : 'space-y-4 py-2'">
        <!-- Left column (or single column for non-expression types) -->
        <div class="space-y-4">
          <p v-if="errors.general" class="text-sm text-destructive">{{ errors.general }}</p>

          <!-- Service Presets -->
          <div v-if="showTwitchPresets || showKofiPresets || showGpsPresets || showStreamLabsPresets || showStreamElementsPresets" class="space-y-2 border border-violet-400/30 bg-violet-400/5 p-3">
            <p class="text-sm font-medium text-violet-500 dark:text-violet-400">Stream Controls</p>
            <select
              v-model="servicePresetKey"
              class="w-full input-border"
            >
              <option value="">- Select a preset control -</option>
              <optgroup v-if="showTwitchPresets && availableTwitchPresets.length" label="Twitch - Per-Stream Counters">
                <option v-for="preset in availableTwitchPresets" :key="'twitch:' + preset.key" :value="'twitch:' + preset.key">
                  {{ preset.label }} ({{ preset.type }})
                </option>
              </optgroup>
              <optgroup v-if="showKofiPresets && availableKofiPresets.length" label="Ko-fi">
                <option v-for="preset in availableKofiPresets" :key="'kofi:' + preset.key" :value="'kofi:' + preset.key">
                  {{ preset.label }} ({{ preset.type }})
                </option>
              </optgroup>
              <optgroup v-if="showGpsPresets && availableGpsPresets.length" label="GPSLogger">
                <option v-for="preset in availableGpsPresets" :key="'gpslogger:' + preset.key" :value="'gpslogger:' + preset.key">
                  {{ preset.label }} ({{ preset.type }})
                </option>
              </optgroup>
              <optgroup v-if="showStreamLabsPresets && availableStreamLabsPresets.length" label="StreamLabs">
                <option v-for="preset in availableStreamLabsPresets" :key="'streamlabs:' + preset.key" :value="'streamlabs:' + preset.key">
                  {{ preset.label }} ({{ preset.type }})
                </option>
              </optgroup>
              <optgroup v-if="showStreamElementsPresets && availableStreamElementsPresets.length" label="StreamElements">
                <option v-for="preset in availableStreamElementsPresets" :key="'streamelements:' + preset.key" :value="'streamelements:' + preset.key">
                  {{ preset.label }} ({{ preset.type }})
                </option>
              </optgroup>
            </select>
            <p v-if="selectedServicePreset && servicePresetSource" class="text-xs text-muted-foreground">
              Use <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:{{ servicePresetSource }}:{{ selectedServicePreset.key }}]]]</code>
              in your template. Value is managed automatically{{ servicePresetSource === 'twitch' ? ' - resets when you go live' : '' }}.
            </p>
          </div>

          <!-- Label (always shown) -->
          <div class="space-y-2">
            <Label for="ctrl-label">Give your control a name <span class="text-muted-foreground text-xs">(be descriptive)</span></Label>
            <input
              id="ctrl-label"
              v-model="form.label"
              :placeholder="selectedServicePreset ? selectedServicePreset.label : 'e.g. Death Counter'"
              class="input-border w-full"
              :class="{ 'border-destructive': errors.label }"
            />
            <p v-if="errors.label" class="text-xs text-destructive">{{ errors.label }}</p>
          </div>

          <!-- Only show manual fields if no service preset selected -->
          <template v-if="!selectedServicePreset">
            <!-- Key (immutable after creation) -->
            <div class="space-y-2">
              <Label for="ctrl-key">Key <span class="text-muted-foreground text-xs">(auto-generated from name)</span></Label>
              <input
                id="ctrl-key"
                v-model="form.key"
                :disabled="isEditing"
                placeholder="e.g. death_counter"
                class="input-border w-full"
                :class="{ 'border-destructive': errors.key, 'border-amber-500': !errors.key && keyWarning }"
                @input="keyManuallyEdited = true"
              />
              <p v-if="errors.key" class="text-xs text-destructive">{{ errors.key }}</p>
              <p v-else-if="keyWarning" class="text-xs text-amber-500">{{ keyWarning }}</p>
              <p v-if="form.key && !errors.key && !keyWarning" class="text-xs text-muted-foreground">
                Template tag: <code class="rounded bg-black/10 px-1 dark:bg-white/10">[[[c:{{ form.key }}]]]</code>
              </p>
              <p v-if="!isEditing && !form.key" class="text-xs text-muted-foreground">Cannot be changed after creation.</p>
            </div>
          </template>


          <!-- Only show type/value/config if no service preset selected -->
          <template v-if="!selectedServicePreset">
            <!-- Type -->
            <div v-if="!isEditing" class="space-y-2">
              <Label for="ctrl-type">Type</Label>
              <select
                id="ctrl-type"
                v-model="form.type"
                class="w-full input-border"
              >
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="counter">Counter</option>
                <option value="timer">Timer</option>
                <option value="datetime">Date/Time</option>
                <option value="boolean">Boolean (on/off switch)</option>
                <option value="expression">Expression (formula)</option>
              </select>
              <p v-if="errors.type" class="text-xs text-destructive">{{ errors.type }}</p>
            </div>

            <!-- Value (text/number/counter) -->
            <div v-if="['text', 'number', 'counter'].includes(form.type)" class="space-y-2">
              <Label for="ctrl-value">{{ isEditing ? 'Value' : 'Initial Value' }} <span class="text-muted-foreground text-xs">(optional)</span></Label>
              <input
                id="ctrl-value"
                v-model="form.value"
                :type="form.type === 'number' || form.type === 'counter' ? 'number' : 'text'"
                placeholder="Leave blank to start empty"
                class="input-border w-full"
              />
              <p v-if="errors.value" class="text-xs text-destructive">{{ errors.value }}</p>
            </div>

            <!-- Value (datetime) -->
            <div v-if="form.type === 'datetime'" class="space-y-2">
              <Label for="ctrl-value-dt">{{ isEditing ? 'Value' : 'Initial Value' }} <span class="text-muted-foreground text-xs">(optional)</span></Label>
              <input
                id="ctrl-value-dt"
                v-model="form.value"
                type="datetime-local"
                class="input-border w-full"
              />
              <p v-if="errors.value" class="text-xs text-destructive">{{ errors.value }}</p>
            </div>

            <!-- Value (boolean) -->
            <div v-if="form.type === 'boolean'" class="space-y-2">
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
                <div class="space-y-2">
                  <Label for="ctrl-min">Min</Label>
                  <input id="ctrl-min" class="input-border" v-model.number="form.config.min" type="number" placeholder="No limit" />
                </div>
                <div class="space-y-2">
                  <Label for="ctrl-max">Max</Label>
                  <input id="ctrl-max" class="input-border" v-model.number="form.config.max" type="number" placeholder="No limit" />
                </div>
                <div class="space-y-2">
                  <Label for="ctrl-step">Step</Label>
                  <input id="ctrl-step" class="input-border" v-model.number="form.config.step" type="number" min="0" step="any" />
                </div>
                <div class="space-y-2">
                  <Label for="ctrl-reset">Reset value</Label>
                  <input id="ctrl-reset" class="input-border" v-model.number="form.config.reset_value" type="number" step="any" />
                </div>
              </div>
              <div class="flex items-center gap-2">
                <input
                  id="ctrl-random"
                  type="checkbox"
                  v-model="form.config.random"
                  class="size-4 rounded border-input"
                />
                <Label for="ctrl-random" class="cursor-pointer">Random mode</Label>
              </div>
              <div v-if="form.config.random" class="space-y-2">
                <Label for="ctrl-random-interval">Update interval (ms)</Label>
                <input
                  id="ctrl-random-interval"
                  class="w-full input-border"
                  v-model.number="form.config.random_interval"
                  type="number" min="100" step="100" placeholder="1000"
                />
                <p class="text-xs text-muted-foreground">
                  How often to generate a new random value. Default: 1000ms (1 second).
                </p>
              </div>
            </div>

            <!-- Timer config -->
            <div v-if="form.type === 'timer'" class="space-y-3 rounded-sm border border-sidebar p-3">
              <p class="text-sm font-medium">Timer settings</p>
              <div class="space-y-2">
                <Label>Mode</Label>
                <div class="flex flex-wrap gap-4">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.config.mode" value="countup" />
                    <span class="text-sm">Count up</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.config.mode" value="countdown" />
                    <span class="text-sm">Count down</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.config.mode" value="countto" />
                    <span class="text-sm">Count to date/time</span>
                  </label>
                </div>
              </div>
              <div v-if="form.config.mode === 'countdown'" class="space-y-2">
                <Label for="ctrl-base">Base duration (seconds)</Label>
                <input id="ctrl-base" class="w-full input-border" v-model.number="form.config.base_seconds" type="number" min="0" />
              </div>
              <div v-if="form.config.mode === 'countto'" class="space-y-2">
                <Label for="ctrl-target">Target date/time</Label>
                <input
                  id="ctrl-target"
                  v-model="form.config.target_datetime"
                  type="datetime-local"
                  class="w-full input-border"
                />
                <p class="text-xs text-muted-foreground">The timer will count down the seconds remaining until this date and time.</p>
              </div>
            </div>
          </template>

          <!-- Sort order -->
          <div class="space-y-2">
            <Label for="ctrl-sort">Position</Label>
            <select
              id="ctrl-sort"
              v-model="sortMode"
              class="w-full input-border"
            >
              <option value="after">After existing (last)</option>
              <option value="before">Before existing (first)</option>
              <option value="manual">Enter sort order manually</option>
            </select>
            <label
              v-if="sortMode === 'manual'"
              for="position-manual-input" class="mt-1.5 block text-sm font-medium text-foreground">Enter manual sort order</label>

            <input
              v-if="sortMode === 'manual'"
              id="position-manual-input"
              v-model.number="form.sort_order"
              ref="manualInputRef"
              type="number"
              min="0"
              placeholder="0"
              class="w-full input-border"
            />
            <p v-if="errors.sort_order" class="text-xs text-destructive">{{ errors.sort_order }}</p>
          </div>
        </div>

        <!-- Right column: Expression builder (only when type is expression) -->
        <ExpressionBuilder
          v-if="form.type === 'expression' && !selectedServicePreset"
          v-model="expressionText"
          :available-controls="availableWatchControls"
          :errors="errors"
        />
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
