<script setup lang="ts">
import { ref, watch, computed, nextTick, onBeforeUnmount } from 'vue';
import axios from 'axios';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ArrowLeft, Check, Copy, Lightbulb } from '@lucide/vue';
import ExpressionBuilder from '@/components/controls/ExpressionBuilder.vue';
import ControlTypePicker from '@/components/controls/ControlTypePicker.vue';
import ControlTypeCard from '@/components/controls/ControlTypeCard.vue';
import ProviderIcon from '@/components/ProviderIcon.vue';
import {
  controlTypeMeta,
  PRESET_GROUPS,
  SERVICE_ACCENT,
} from '@/components/controls/controlTypeCatalog';
import { getPresetsForSource, type ServicePreset } from '@/components/controls/controlPresets';
import { serviceLabel } from '@/utils/services';
import type { OverlayControl, OverlayTemplate } from '@/types';

interface UserList {
  id: number;
  slug: string;
  label?: string | null;
  items_count: number;
  disabled: boolean;
}

const props = defineProps<{
  open: boolean;
  template: OverlayTemplate;
  control?: OverlayControl | null;
  copyFrom?: OverlayControl | null;
  connectedServices?: string[];
  existingControls?: OverlayControl[];
  userScopedControls?: OverlayControl[];
  userLists?: UserList[];
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'saved', control: OverlayControl): void;
}>();

const isEditing = computed(() => !!props.control);
const isCopying = computed(() => !isEditing.value && !!props.copyFrom);
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const booleanValue = ref(false);
const manualInputRef = ref<HTMLInputElement | null>(null);
const labelInputRef = ref<HTMLInputElement | null>(null);
const pickerRef = ref<InstanceType<typeof ControlTypePicker> | null>(null);

/**
 * The modal is two screens, not one long scroll. `pick` sells the eight control
 * types and the ready-made service controls side by side; `configure` fills in
 * the one you chose. Editing and duplicating skip straight to `configure`,
 * because the type is already settled in both cases.
 */
type Step = 'pick' | 'configure';
const step = ref<Step>('pick');

// --- Service preset selection -----------------------------------------------
// Written only by selectPreset/clearPreset. It used to be a combobox v-model
// with a watcher that reset the form when it emptied, which made "clear the
// preset, then set a type" order-dependent: the watcher ran after the sync
// code and clobbered the type that had just been set.
const servicePresetKey = ref('');
const servicePresetSource = ref<string | null>(null);

const selectedServicePreset = computed(() => {
  if (!servicePresetKey.value || !servicePresetSource.value) return null;
  const presets = getPresetsForSource(servicePresetSource.value);
  const key = servicePresetKey.value.substring(servicePresetKey.value.indexOf(':') + 1);
  return presets.find((p) => p.key === key) ?? null;
});

/** Group label first ("Overlabels alerts"), service label as the fallback. */
function presetSourceLabel(source: string): string {
  return PRESET_GROUPS.find((g) => g.source === source)?.label ?? serviceLabel(source);
}

function selectPreset(source: string, preset: ServicePreset) {
  servicePresetKey.value = `${source}:${preset.key}`;
  servicePresetSource.value = source;
  form.value.key = preset.key;
  form.value.label = preset.label;
  form.value.type = preset.type;
  keyManuallyEdited.value = false;
  errors.value = {};
  step.value = 'configure';
}

function selectType(type: OverlayControl['type']) {
  // Coming from a preset, the label and key were the preset's, not the user's.
  if (servicePresetKey.value) {
    servicePresetKey.value = '';
    servicePresetSource.value = null;
    form.value.key = '';
    form.value.label = '';
    keyManuallyEdited.value = false;
  }
  form.value.type = type;
  errors.value = {};
  step.value = 'configure';
}

function backToPicker() {
  step.value = 'pick';
  errors.value = {};
  nextTick(() => pickerRef.value?.focusSearch());
}

// --- Sort order --------------------------------------------------------------
type SortMode = 'before' | 'after' | 'manual';
const sortMode = ref<SortMode>('after');

const SORT_MODES = [
  { value: 'after', label: 'Last', hint: 'After everything else' },
  { value: 'before', label: 'First', hint: 'Before everything else' },
  { value: 'manual', label: 'Custom', hint: 'Pick the number yourself' },
] as const;

const TIMER_MODES = [
  { value: 'countup', label: 'Count up', hint: 'From zero, upwards' },
  { value: 'countdown', label: 'Count down', hint: 'From a duration you set' },
  { value: 'countto', label: 'Count to', hint: 'Towards a date and time' },
] as const;

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
  description: '',
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

// --- Presentation ------------------------------------------------------------
const activeMeta = computed(() => controlTypeMeta(form.value.type));
const isPresetMode = computed(() => !!selectedServicePreset.value);
const accent = computed(() => (isPresetMode.value ? SERVICE_ACCENT : activeMeta.value.accent));

const dialogTitle = computed(() => {
  if (isEditing.value) return 'Edit control';
  if (isCopying.value) return 'Duplicate control';
  return 'Add a control';
});

const dialogDescription = computed(() => {
  if (isEditing.value) {
    return 'The name and description are yours to change any time. The key and the type are fixed once a control exists, because your templates already point at them.';
  }
  if (step.value === 'pick') {
    return 'Controls are the parts of your overlay you can change while you are live. Pick what you want to add.';
  }
  if (isPresetMode.value && servicePresetSource.value && selectedServicePreset.value) {
    return `${selectedServicePreset.value.label}, straight from ${presetSourceLabel(servicePresetSource.value)}. Give it a name you will recognise and choose where it sits.`;
  }
  return activeMeta.value.tagline;
});

/**
 * Placeholders follow the chosen type. A switch suggesting "Death counter"
 * teaches the wrong thing about what a switch is for, so the example comes from
 * the catalog entry and matches that type's demo.
 */
const labelPlaceholder = computed(() => {
  if (selectedServicePreset.value) return selectedServicePreset.value.label;
  return `e.g. ${activeMeta.value.exampleName}`;
});

// Slugified with the same function that derives the real key, so the two
// placeholders always demonstrate the actual name-to-key transformation.
const keyPlaceholder = computed(() => `e.g. ${slugifyLabel(activeMeta.value.exampleName)}`);

/** The tag the user will actually paste into their template. */
const tagPreview = computed(() => {
  if (selectedServicePreset.value && servicePresetSource.value) {
    return `[[[c:${servicePresetSource.value}:${selectedServicePreset.value.key}]]]`;
  }
  return form.value.key ? `[[[c:${form.value.key}]]]` : '';
});

const tagCopied = ref(false);
let tagCopyTimeout: ReturnType<typeof setTimeout> | null = null;

function copyTag() {
  if (!tagPreview.value || !navigator.clipboard) return;
  navigator.clipboard.writeText(tagPreview.value).then(() => {
    tagCopied.value = true;
    if (tagCopyTimeout) clearTimeout(tagCopyTimeout);
    tagCopyTimeout = setTimeout(() => {
      tagCopied.value = false;
    }, 1500);
  });
}

onBeforeUnmount(() => {
  if (tagCopyTimeout) clearTimeout(tagCopyTimeout);
});

// --- Key derivation ----------------------------------------------------------
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

// --- Expression / list writer state -----------------------------------------
const expressionText = ref('');

// Stored outside form.config so the picker bindings stay typed as IDs, not strings.
const listWriter = ref({
  source_control_id: '' as number | '',
  target_list_id: '' as number | '',
});

/**
 * Controls available as watch targets for expression and list-writer controls.
 * A service control can exist as both a user-scoped row (auto-provisioned) and
 * a template-scoped row created when the preset was added here. Both share the
 * same `c.source.key` reference, so dedupe by (source, key), preferring the
 * template-scoped one.
 */
const availableWatchControls = computed(() => {
  const templateControls = (props.existingControls ?? []).filter(
    (c) => c.id !== props.control?.id,
  );
  const seen = new Set<string>();
  for (const c of templateControls) seen.add(`${c.source ?? ''}:${c.key}`);
  const userScoped = (props.userScopedControls ?? []).filter(
    (c) => !seen.has(`${c.source ?? ''}:${c.key}`),
  );
  return [...templateControls, ...userScoped];
});

watch(() => props.open, (open) => {
  if (!open) return;

  errors.value = {};
  keyManuallyEdited.value = false;
  keyWarning.value = '';
  servicePresetKey.value = '';
  servicePresetSource.value = null;
  tagCopied.value = false;

  if (props.control) {
    const c = props.control;
    const cfg = c.config ?? {};
    form.value = {
      key: c.key,
      label: c.label ?? '',
      description: c.description ?? '',
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
    // An existing service control keeps its service identity in the rail.
    if (c.source && getPresetsForSource(c.source).some((p) => p.key === c.key)) {
      servicePresetKey.value = `${c.source}:${c.key}`;
      servicePresetSource.value = c.source;
    }
    booleanValue.value = c.value === '1';
    sortMode.value = 'manual';
    expressionText.value = c.type === 'expression' ? (cfg.expression ?? '') : '';
    listWriter.value = c.type === 'list_writer'
      ? {
          source_control_id: (cfg.source_control_id ?? '') as number | '',
          target_list_id: (cfg.target_list_id ?? '') as number | '',
        }
      : { source_control_id: '', target_list_id: '' };
    step.value = 'configure';
  } else if (props.copyFrom) {
    const c = props.copyFrom;
    const cfg = c.config ?? {};
    form.value = {
      key: '',
      label: `${c.label || c.key} (copy)`,
      description: c.description ?? '',
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
    listWriter.value = c.type === 'list_writer'
      ? {
          source_control_id: (cfg.source_control_id ?? '') as number | '',
          target_list_id: (cfg.target_list_id ?? '') as number | '',
        }
      : { source_control_id: '', target_list_id: '' };
    step.value = 'configure';
  } else {
    form.value = {
      key: '',
      label: '',
      description: '',
      type: 'text',
      value: '',
      config: { min: undefined, max: undefined, step: 1, reset_value: 0, random: false, random_interval: 1000, mode: 'countup', base_seconds: 0, target_datetime: '' },
      sort_order: 0,
    };
    booleanValue.value = false;
    sortMode.value = 'after';
    expressionText.value = '';
    listWriter.value = { source_control_id: '', target_list_id: '' };
    step.value = 'pick';
  }
});

/** Land focus where the user is about to type, on open and on every step change. */
function focusStep() {
  nextTick(() => {
    if (step.value === 'pick') pickerRef.value?.focusSearch();
    else labelInputRef.value?.focus();
  });
}

function onOpenAutoFocus(event: Event) {
  event.preventDefault();
  focusStep();
}

watch(step, (value) => {
  if (value === 'configure') {
    nextTick(() => labelInputRef.value?.focus());
  }
});

function buildPayload() {
  const payload: Record<string, any> = {
    label: form.value.label || null,
    description: form.value.description || null,
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

  if (t === 'list_writer') {
    payload.config = {
      source_control_id: Number(listWriter.value.source_control_id),
      target_list_id: Number(listWriter.value.target_list_id),
    };
    payload.value = null;
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
    <DialogContent
      class="grid h-[92vh] max-h-[92vh] w-[95vw] max-w-[1500px] gap-0 overflow-hidden p-0"
      style="grid-template-rows: auto minmax(0, 1fr) auto"
      @open-auto-focus="onOpenAutoFocus"
    >
      <div class="border-b border-sidebar-border px-6 py-5 sm:px-8">
        <DialogTitle class="text-2xl font-semibold tracking-tight text-foreground">{{ dialogTitle }}</DialogTitle>
        <DialogDescription class="mt-1.5 max-w-4xl text-sm text-foreground/75">
          {{ dialogDescription }}
        </DialogDescription>
      </div>

      <!-- Body: the only scroll container, so the footer is always reachable. -->
      <div class="overflow-y-auto px-6 py-6 sm:px-8">
        <!-- v-show, not v-if: the search survives a trip into configure and back.
             display:none also keeps these controls out of the tab order. -->
        <ControlTypePicker
          v-show="step === 'pick'"
          ref="pickerRef"
          :template="template"
          :connected-services="connectedServices"
          :existing-controls="existingControls"
          @select-type="selectType"
          @select-preset="selectPreset"
        />

        <div
          v-if="step === 'configure'"
          class="grid gap-8 lg:grid-cols-3"
          :class="form.type === 'expression' && !isPresetMode ? 'xl:grid-cols-4' : ''"
        >
          <!-- Rail: what you picked, why it is good, and the tag you will paste. -->
          <aside class="space-y-5 lg:col-span-1">
            <ControlTypeCard v-if="!isPresetMode" :meta="activeMeta" selected />

            <div v-else class="border bg-background p-4" :class="SERVICE_ACCENT.ring">
              <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center" :class="SERVICE_ACCENT.icon">
                  <ProviderIcon :source="servicePresetSource ?? ''" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                  <h3 class="text-base leading-tight font-semibold text-foreground">
                    {{ selectedServicePreset?.label }}
                  </h3>
                  <p class="mt-1 text-sm leading-snug text-foreground/75">
                    Managed by {{ presetSourceLabel(servicePresetSource ?? '') }}. The value keeps itself up to date,
                    you never set it by hand.
                  </p>
                </div>
              </div>
            </div>

            <!-- Good for -->
            <div v-if="!isPresetMode" class="space-y-2">
              <h4 class="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">Good for</h4>
              <ul class="space-y-1.5">
                <li v-for="use in activeMeta.goodFor" :key="use" class="flex items-start gap-2 text-sm text-foreground">
                  <!-- Accent comes from a catalog literal. Never build a class
                       string at runtime here: Tailwind only generates what it
                       can see spelled out in source. -->
                  <Check class="mt-0.5 size-3.5 shrink-0" :class="accent.text" />
                  <span>{{ use }}</span>
                </li>
              </ul>
            </div>

            <p v-if="!isPresetMode" class="text-sm text-foreground/75">{{ activeMeta.blurb }}</p>

            <p
              v-if="isPresetMode && servicePresetSource === 'twitch'"
              class="flex items-start gap-2 border-l-2 border-amber-400/50 pl-3 text-xs text-foreground/75"
            >
              <Lightbulb class="mt-0.5 size-3.5 shrink-0 text-amber-500 dark:text-amber-400" />
              <span>Per-stream counters reset themselves the moment you go live. The "latest" values do not, they
                carry over between streams.</span>
            </p>

            <!-- The tag you paste -->
            <div class="space-y-2">
              <h4 class="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                Paste this in your template
              </h4>
              <button
                v-if="tagPreview"
                type="button"
                class="flex w-full cursor-pointer items-center justify-between gap-2 border border-border/60 bg-foreground/3 px-3 py-2 text-left transition hover:border-violet-500/50 dark:bg-black/25 dark:hover:border-violet-400/50"
                :title="tagCopied ? 'Copied' : 'Copy to clipboard'"
                @click="copyTag"
              >
                <code class="truncate font-mono text-xs text-foreground">{{ tagPreview }}</code>
                <Check v-if="tagCopied" class="size-3.5 shrink-0 text-green-500" />
                <Copy v-else class="size-3.5 shrink-0 text-muted-foreground" />
              </button>
              <p v-else class="border border-dashed border-border px-3 py-2 text-xs text-muted-foreground">
                Give it a name and your tag appears here.
              </p>
            </div>

            <button
              v-if="!isEditing"
              type="button"
              class="flex cursor-pointer items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
              @click="backToPicker"
            >
              <ArrowLeft class="size-3.5" />
              Pick a different control
            </button>
            <p v-else class="text-xs text-muted-foreground">
              The type cannot be changed after a control is created.
            </p>
          </aside>

          <!-- The form itself -->
          <div
            class="space-y-8 lg:col-span-2"
            :class="form.type === 'expression' && !isPresetMode ? 'xl:col-span-1' : ''"
          >
            <p v-if="errors.general" class="border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
              {{ errors.general }}
            </p>

            <!-- Name it -->
            <section class="space-y-4">
              <h3 class="border-b border-border/60 pb-2 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                Name it
              </h3>

              <div class="grid gap-4" :class="isPresetMode ? '' : 'sm:grid-cols-2'">
                <div class="space-y-2">
                  <Label for="ctrl-label">Name</Label>
                  <input
                    id="ctrl-label"
                    ref="labelInputRef"
                    v-model="form.label"
                    :placeholder="labelPlaceholder"
                    class="input-border w-full"
                    :class="{ 'border-destructive': errors.label }"
                  />
                  <p v-if="errors.label" class="text-xs text-destructive">{{ errors.label }}</p>
                  <p v-else class="text-xs text-muted-foreground">
                    This is what you will see in your dashboard while you are live. Be descriptive.
                  </p>
                </div>

                <div v-if="!isPresetMode" class="space-y-2">
                  <Label for="ctrl-key">Key</Label>
                  <input
                    id="ctrl-key"
                    v-model="form.key"
                    :disabled="isEditing"
                    :placeholder="keyPlaceholder"
                    class="input-border w-full font-mono disabled:cursor-not-allowed disabled:opacity-60"
                    :class="{ 'border-destructive': errors.key, 'border-amber-500': !errors.key && keyWarning }"
                    @input="keyManuallyEdited = true"
                  />
                  <p v-if="errors.key" class="text-xs text-destructive">{{ errors.key }}</p>
                  <p v-else-if="keyWarning" class="text-xs text-amber-500">{{ keyWarning }}</p>
                  <p v-else-if="isEditing" class="text-xs text-muted-foreground">
                    Fixed. Your templates already point at this.
                  </p>
                  <p v-else class="text-xs text-muted-foreground">
                    Written for you from the name. Cannot be changed after you save.
                  </p>
                </div>
              </div>

              <div class="space-y-2">
                <Label for="ctrl-description">Description <span class="text-xs text-muted-foreground">(optional)</span></Label>
                <textarea
                  id="ctrl-description"
                  v-model="form.description"
                  rows="2"
                  maxlength="1000"
                  placeholder="What does this control do? Notes for your future self."
                  class="input-border w-full"
                  :class="{ 'border-destructive': errors.description }"
                />
                <p v-if="errors.description" class="text-xs text-destructive">{{ errors.description }}</p>
              </div>
            </section>

            <template v-if="!isPresetMode">
              <!-- Starting value -->
              <section
                v-if="['text', 'number', 'counter', 'datetime', 'boolean'].includes(form.type)"
                class="space-y-4"
              >
                <h3 class="border-b border-border/60 pb-2 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                  {{ isEditing ? 'Value' : 'Starting value' }}
                </h3>

                <div v-if="['text', 'number', 'counter'].includes(form.type)" class="space-y-2">
                  <input
                    id="ctrl-value"
                    v-model="form.value"
                    :type="form.type === 'number' || form.type === 'counter' ? 'number' : 'text'"
                    placeholder="Leave blank to start empty"
                    class="input-border w-full"
                  />
                  <p v-if="errors.value" class="text-xs text-destructive">{{ errors.value }}</p>
                </div>

                <div v-if="form.type === 'datetime'" class="space-y-2">
                  <input id="ctrl-value-dt" v-model="form.value" type="datetime-local" class="input-border w-full" />
                  <p v-if="errors.value" class="text-xs text-destructive">{{ errors.value }}</p>
                </div>

                <div v-if="form.type === 'boolean'" class="space-y-2">
                  <div class="flex items-center gap-3">
                    <label class="relative inline-flex cursor-pointer items-center">
                      <input v-model="booleanValue" type="checkbox" class="peer sr-only" />
                      <span
                        class="peer h-6 w-10 rounded-full bg-gray-300 after:absolute after:inset-s-0.5 after:top-0.5
                        after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-['']
                        peer-checked:bg-green-400 peer-checked:after:translate-x-4 peer-focus:outline-none
                        dark:bg-gray-600 dark:after:bg-gray-100 dark:peer-checked:bg-green-800"
                      ></span>
                    </label>
                    <span class="text-sm text-foreground">{{ booleanValue ? 'On (true)' : 'Off (false)' }}</span>
                  </div>
                  <p v-if="errors.value" class="text-xs text-destructive">{{ errors.value }}</p>
                </div>
              </section>

              <!-- Number / counter settings -->
              <section v-if="form.type === 'number' || form.type === 'counter'" class="space-y-4">
                <h3 class="border-b border-border/60 pb-2 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                  Limits and steps
                </h3>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                  <div class="space-y-2">
                    <Label for="ctrl-min">Min</Label>
                    <input id="ctrl-min" v-model.number="form.config.min" class="input-border w-full" type="number" placeholder="No limit" />
                  </div>
                  <div class="space-y-2">
                    <Label for="ctrl-max">Max</Label>
                    <input id="ctrl-max" v-model.number="form.config.max" class="input-border w-full" type="number" placeholder="No limit" />
                  </div>
                  <div class="space-y-2">
                    <Label for="ctrl-step">Step</Label>
                    <input id="ctrl-step" v-model.number="form.config.step" class="input-border w-full" type="number" min="0" step="any" />
                  </div>
                  <div class="space-y-2">
                    <Label for="ctrl-reset">Reset to</Label>
                    <input id="ctrl-reset" v-model.number="form.config.reset_value" class="input-border w-full" type="number" step="any" />
                  </div>
                </div>
                <p class="text-xs text-muted-foreground">
                  Step is how far one click of plus or minus moves it. Reset is where the reset button puts it back to.
                </p>

                <div class="flex items-center gap-2 pt-1">
                  <input id="ctrl-random" v-model="form.config.random" type="checkbox" class="size-4 cursor-pointer rounded border-input" />
                  <Label for="ctrl-random" class="cursor-pointer">Roll a random value on a timer instead</Label>
                </div>
                <div v-if="form.config.random" class="max-w-xs space-y-2">
                  <Label for="ctrl-random-interval">How often (milliseconds)</Label>
                  <input
                    id="ctrl-random-interval"
                    v-model.number="form.config.random_interval"
                    class="input-border w-full"
                    type="number"
                    min="100"
                    step="100"
                    placeholder="1000"
                  />
                  <p class="text-xs text-muted-foreground">Default is 1000, which is once a second.</p>
                </div>
              </section>

              <!-- Timer settings -->
              <section v-if="form.type === 'timer'" class="space-y-4">
                <h3 class="border-b border-border/60 pb-2 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                  How it counts
                </h3>

                <div class="grid gap-2 sm:grid-cols-3">
                  <button
                    v-for="mode in TIMER_MODES"
                    :key="mode.value"
                    type="button"
                    class="cursor-pointer border px-3 py-2 text-left transition duration-150"
                    :class="
                      form.config.mode === mode.value
                        ? 'border-amber-500/60 bg-amber-500/8 dark:border-amber-400/50 dark:bg-amber-400/8'
                        : 'border-border/60 hover:border-amber-500/40 dark:hover:border-amber-400/40'
                    "
                    @click="form.config.mode = mode.value"
                  >
                    <span class="block text-sm font-medium text-foreground">{{ mode.label }}</span>
                    <span class="block text-xs text-muted-foreground">{{ mode.hint }}</span>
                  </button>
                </div>

                <div v-if="form.config.mode === 'countdown'" class="max-w-xs space-y-2">
                  <Label for="ctrl-base">Starting duration (seconds)</Label>
                  <input id="ctrl-base" v-model.number="form.config.base_seconds" class="input-border w-full" type="number" min="0" />
                  <p class="text-xs text-muted-foreground">3600 is one hour. Format it as hh:mm:ss in your template.</p>
                </div>

                <div v-if="form.config.mode === 'countto'" class="max-w-sm space-y-2">
                  <Label for="ctrl-target">Target date and time</Label>
                  <input id="ctrl-target" v-model="form.config.target_datetime" type="datetime-local" class="input-border w-full" />
                  <p class="text-xs text-muted-foreground">Counts down the seconds remaining until this moment.</p>
                </div>
              </section>

              <!-- List writer settings -->
              <section v-if="form.type === 'list_writer'" class="space-y-4">
                <h3 class="border-b border-border/60 pb-2 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                  What it watches, where it writes
                </h3>

                <div class="grid gap-4 sm:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="ctrl-lw-source">Watch this control</Label>
                    <select id="ctrl-lw-source" v-model="listWriter.source_control_id" class="input-border w-full cursor-pointer">
                      <option value="">Pick a control</option>
                      <option v-for="c in availableWatchControls" :key="c.id" :value="c.id">
                        {{ c.label || c.key }} ({{ c.type }}{{ c.source ? `, ${c.source}` : '' }})
                      </option>
                    </select>
                    <p v-if="errors['config.source_control_id']" class="text-xs text-destructive">
                      {{ errors['config.source_control_id'] }}
                    </p>
                  </div>

                  <div class="space-y-2">
                    <Label for="ctrl-lw-target">Append to this list</Label>
                    <select id="ctrl-lw-target" v-model="listWriter.target_list_id" class="input-border w-full cursor-pointer">
                      <option value="">Pick a list</option>
                      <option v-for="l in (props.userLists ?? [])" :key="l.id" :value="l.id">
                        {{ l.label || l.slug }} ({{ l.items_count }} item{{ l.items_count === 1 ? '' : 's' }}{{ l.disabled ? ', disabled' : '' }})
                      </option>
                    </select>
                    <p v-if="errors['config.target_list_id']" class="text-xs text-destructive">
                      {{ errors['config.target_list_id'] }}
                    </p>
                    <p v-if="(props.userLists ?? []).length === 0" class="text-xs text-amber-500">
                      You do not have any Lists yet. Create one from the Lists dashboard first.
                    </p>
                  </div>
                </div>
                <p class="text-xs text-muted-foreground">
                  Every time the watched control changes, its new value is appended to the list. Works with any control
                  type, expressions included.
                </p>
              </section>
            </template>

            <!-- Position -->
            <section class="space-y-4">
              <h3 class="border-b border-border/60 pb-2 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                Where it sits in your dashboard
              </h3>

              <div class="grid gap-2 sm:grid-cols-3">
                <button
                  v-for="mode in SORT_MODES"
                  :key="mode.value"
                  type="button"
                  class="cursor-pointer border px-3 py-2 text-left transition duration-150"
                  :class="
                    sortMode === mode.value
                      ? 'border-violet-500/60 bg-violet-500/8 dark:border-violet-400/50 dark:bg-violet-400/8'
                      : 'border-border/60 hover:border-violet-500/40 dark:hover:border-violet-400/40'
                  "
                  @click="sortMode = mode.value"
                >
                  <span class="block text-sm font-medium text-foreground">{{ mode.label }}</span>
                  <span class="block text-xs text-muted-foreground">{{ mode.hint }}</span>
                </button>
              </div>

              <div v-if="sortMode === 'manual'" class="max-w-xs space-y-2">
                <Label for="position-manual-input">Sort order</Label>
                <input
                  id="position-manual-input"
                  ref="manualInputRef"
                  v-model.number="form.sort_order"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="input-border w-full"
                />
              </div>
              <p v-if="errors.sort_order" class="text-xs text-destructive">{{ errors.sort_order }}</p>
            </section>
          </div>

          <!-- Expression builder gets its own column, because a formula needs room. -->
          <div
            v-if="form.type === 'expression' && !isPresetMode"
            class="lg:col-span-3 xl:col-span-2"
          >
            <ExpressionBuilder
              v-model="expressionText"
              :available-controls="availableWatchControls"
              :errors="errors"
            />
          </div>
        </div>
      </div>

      <!-- Footer: outside the scroll area, so Save never runs off the bottom. -->
      <div class="flex items-center justify-between gap-3 border-t border-sidebar-border bg-sidebar-accent/40 px-6 py-4 sm:px-8">
        <p v-if="step === 'pick'" class="text-sm text-muted-foreground">
          Pick a control to carry on. You can change your mind on the next screen.
        </p>
        <button
          v-else-if="!isEditing"
          type="button"
          class="flex cursor-pointer items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
          @click="backToPicker"
        >
          <ArrowLeft class="size-3.5" />
          Back
        </button>
        <span v-else />

        <div class="flex items-center gap-3">
          <button class="btn btn-cancel" @click="emit('update:open', false)">Cancel</button>
          <button v-if="step === 'configure'" class="btn btn-primary" :disabled="saving" @click="save">
            {{ saving ? 'Saving...' : isEditing ? 'Save changes' : 'Add control' }}
          </button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
