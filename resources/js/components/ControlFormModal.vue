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

const props = defineProps<{
  open: boolean;
  template: OverlayTemplate;
  control?: OverlayControl | null;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'saved', control: OverlayControl): void;
}>();

const isEditing = computed(() => !!props.control);
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const booleanValue = ref(false);

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

watch(() => props.open, (open) => {
  if (open) {
    errors.value = {};
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
    }
  }
});

function buildPayload() {
  const payload: Record<string, any> = {
    label: form.value.label || null,
    sort_order: form.value.sort_order,
  };

  if (!isEditing.value) {
    payload.key = form.value.key;
    payload.type = form.value.type;
  }

  const t = form.value.type;

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
      errors.value = { general: 'An error occurred. Please try again.' };
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

        <!-- Label -->
        <div class="space-y-1">
          <Label for="ctrl-label">Label <span class="text-muted-foreground text-xs">(optional display name)</span></Label>
          <Input
            id="ctrl-label"
            v-model="form.label"
            placeholder="e.g. Death Counter"
            :class="{ 'border-destructive': errors.label }"
          />
          <p v-if="errors.label" class="text-xs text-destructive">{{ errors.label }}</p>
        </div>

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
          </select>
          <p v-if="errors.type" class="text-xs text-destructive">{{ errors.type }}</p>
        </div>

        <!-- Value (text/number/counter/datetime) -->
        <div v-if="form.type !== 'timer' && form.type !== 'boolean'" class="space-y-1">
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

        <!-- Sort order -->
        <div class="space-y-1">
          <Label for="ctrl-sort">Sort order</Label>
          <Input id="ctrl-sort" v-model.number="form.sort_order" type="number" min="0" />
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
