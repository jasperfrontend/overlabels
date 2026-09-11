<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
  ColorAreaArea,
  ColorAreaRoot,
  ColorAreaThumb,
  ColorSliderRoot,
  ColorSliderThumb,
  ColorSliderTrack,
  ColorSwatchPickerItem,
  ColorSwatchPickerItemIndicator,
  ColorSwatchPickerItemSwatch,
  ColorSwatchPickerRoot,
  colorToString,
  isValidColor,
  parseColor,
  type AcceptableValue,
  type Color,
  type ColorFormat,
} from 'reka-ui';
import { Check } from '@lucide/vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

/**
 * A color picker that never takes the value hostage.
 *
 * The string the caller holds is authoritative and is handed to CSS verbatim.
 * This component only ever OFFERS a value, because Reka's `parseColor` throws
 * on anything outside hex / rgb() / hsl() / hsb() with commas - which leaves
 * named colors, `rgb(0 0 0 / 50%)`, `color-mix()`, `var()` and `oklch()` all
 * perfectly valid CSS that it cannot read. Those have to keep working, so
 * every read of the incoming value goes through `isValidColor` first and an
 * unreadable one simply leaves the picker on its last position.
 *
 * The trigger swatch is painted with the raw string through `background`, not
 * through anything parsed, so it shows the truth: a color CSS understands
 * renders, and one it does not falls through to the transparency checkerboard.
 */
const props = withDefaults(
  defineProps<{
    /** Whatever the user has. Any string, parseable or not. */
    modelValue?: string | null;
    /** Where the picker starts when the current value cannot be read. */
    fallback?: string;
    disabled?: boolean;
    /** Accessible name for the trigger, e.g. the control's label. */
    label?: string;
  }>(),
  { modelValue: '', fallback: '#7c3aed', disabled: false, label: 'color' },
);

const emit = defineEmits<{
  /** Fires continuously while dragging. Cheap to handle: keep it local. */
  (e: 'update:modelValue', value: string): void;
  /**
   * Fires once the gesture is over, or immediately for a discrete act like
   * choosing a preset. This is the one to persist on - `update:modelValue`
   * fires on every pointer move, which down a save path would be dozens of
   * writes and broadcasts per second of dragging.
   */
  (e: 'commit', value: string): void;
}>();

/**
 * A short, neutral palette. Deliberately not the Tailwind ramp: these are
 * starting points for an overlay, so they are spread across the wheel rather
 * than clustered in one family, plus black and white for text and outlines.
 */
const PRESETS = [
  '#ffffff',
  '#a1a1aa',
  '#000000',
  '#ef4444',
  '#f97316',
  '#eab308',
  '#22c55e',
  '#14b8a6',
  '#3b82f6',
  '#7c3aed',
  '#ec4899',
  '#78350f',
];

const FORMATS: { value: ColorFormat; label: string }[] = [
  { value: 'hex', label: 'HEX' },
  { value: 'rgb', label: 'RGB' },
  { value: 'hsl', label: 'HSL' },
];

const open = ref(false);
const format = ref<ColorFormat>('hex');

/**
 * The picker's own position, always a readable hex. Seeded from the incoming
 * value when that can be read, and left where it is when it cannot.
 */
const working = ref(readable(props.modelValue) ?? props.fallback);

function readable(value: string | null | undefined): string | null {
  if (!value) return null;
  const trimmed = value.trim();
  return isValidColor(trimmed) ? trimmed : null;
}

watch(
  () => props.modelValue,
  (value) => {
    const next = readable(value);
    if (!next) return;
    // Compare through the parser so `#FFF`, `#ffffff` and `rgb(255, 255, 255)`
    // do not fight each other for the thumb position on every keystroke.
    if (sameColor(next, working.value)) return;
    working.value = next;
  },
);

function sameColor(a: string, b: string): boolean {
  try {
    return colorToString(parseColor(a), 'hex') === colorToString(parseColor(b), 'hex');
  } catch {
    return false;
  }
}

/** True when the caller is holding something the picker cannot read. */
const unreadable = computed(() => !!props.modelValue?.trim() && readable(props.modelValue) === null);

/**
 * Reka hands every sub-control's value back as hex, carrying alpha as the
 * seventh and eighth digits when it is below one, so re-formatting here is the
 * only place the chosen output format is applied.
 */
function formatted(next: string | Color): string {
  const hex = typeof next === 'string' ? next : colorToString(next, 'hex');
  working.value = hex;

  try {
    return colorToString(parseColor(hex), format.value);
  } catch {
    // Keep the hex. Reka produced it, so this cannot realistically happen.
    return hex;
  }
}

/** Mid-gesture. Live, local, not persisted. */
function preview(next: string | Color) {
  emit('update:modelValue', formatted(next));
}

/** Gesture finished, or a discrete choice. Worth writing down. */
function commit(next: string | Color) {
  const out = formatted(next);
  emit('update:modelValue', out);
  emit('commit', out);
}

/**
 * The swatch grid is a Listbox underneath, so it can emit null when an item is
 * toggled off. Nothing sensible to do with "no color" here, so ignore it and
 * leave the current pick standing.
 */
function commitSwatch(next: AcceptableValue) {
  if (typeof next !== 'string' || next === '') return;
  commit(next);
}

function setFormat(next: ColorFormat) {
  format.value = next;
  commit(working.value);
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger
      :disabled="disabled"
      class="ol-color-swatch relative size-9 shrink-0 cursor-pointer border border-border transition hover:border-violet-500/60 focus-visible:ring-2 focus-visible:ring-violet-400/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:hover:border-violet-400/50"
      :title="`Pick a color for ${label}`"
      :aria-label="`Pick a color for ${label}`"
    >
      <!-- Painted with the raw string, so an unreadable value shows the
           checkerboard underneath instead of a color that is not real. -->
      <span class="absolute inset-0" :style="{ background: modelValue || 'transparent' }" />
    </PopoverTrigger>

    <PopoverContent class="w-72 border border-border shadow-lg" align="start">
      <div class="space-y-3">
        <ColorAreaRoot
          v-slot="{ style }"
          :model-value="working"
          color-space="hsb"
          x-channel="saturation"
          y-channel="brightness"
          class="block"
          @update:model-value="preview"
          @change-end="commit"
        >
          <ColorAreaArea :style="style" class="relative h-32 w-full cursor-crosshair border border-border">
            <ColorAreaThumb
              class="size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(0,0,0,0.45)] focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:outline-none"
            />
          </ColorAreaArea>
        </ColorAreaRoot>

        <ColorSliderRoot :model-value="working" channel="hue" class="relative flex h-4 w-full items-center" @update:model-value="preview" @change-end="commit">
          <ColorSliderTrack class="h-3 w-full border border-border">
            <ColorSliderThumb
              class="size-4 -translate-x-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(0,0,0,0.45)] focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:outline-none"
            />
          </ColorSliderTrack>
        </ColorSliderRoot>

        <ColorSliderRoot :model-value="working" channel="alpha" class="relative flex h-4 w-full items-center" @update:model-value="preview" @change-end="commit">
          <ColorSliderTrack class="h-3 w-full border border-border">
            <ColorSliderThumb
              class="size-4 -translate-x-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(0,0,0,0.45)] focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:outline-none"
            />
          </ColorSliderTrack>
        </ColorSliderRoot>

        <ColorSwatchPickerRoot :model-value="working" class="grid grid-cols-6 gap-1.5" @update:model-value="commitSwatch">
          <ColorSwatchPickerItem
            v-for="preset in PRESETS"
            :key="preset"
            :value="preset"
            class="ol-color-swatch relative flex size-8 cursor-pointer items-center justify-center border border-border transition hover:border-violet-500/60 focus-visible:ring-2 focus-visible:ring-violet-400/40 focus-visible:outline-none dark:hover:border-violet-400/50"
          >
            <ColorSwatchPickerItemSwatch class="ol-color-preset absolute inset-0" />
            <ColorSwatchPickerItemIndicator class="relative">
              <Check class="size-4 text-white mix-blend-difference" />
            </ColorSwatchPickerItemIndicator>
          </ColorSwatchPickerItem>
        </ColorSwatchPickerRoot>

        <div class="flex items-center gap-1">
          <button
            v-for="option in FORMATS"
            :key="option.value"
            type="button"
            class="flex-1 cursor-pointer border px-2 py-1 text-[10px] font-semibold tracking-wide transition"
            :class="
              format === option.value
                ? 'border-violet-500/60 bg-violet-500/10 text-violet-600 dark:border-violet-400/50 dark:text-violet-300'
                : 'border-border/60 text-muted-foreground hover:border-violet-500/40 dark:hover:border-violet-400/40'
            "
            @click="setFormat(option.value)"
          >
            {{ option.label }}
          </button>
        </div>

        <p v-if="unreadable" class="text-[11px] leading-snug text-muted-foreground">
          The picker cannot read this value, so it is showing its last position. Your value is kept exactly as you typed it. Pick a color here to
          replace it.
        </p>
      </div>
    </PopoverContent>
  </Popover>
</template>

<style scoped>
/* ColorSwatch is headless: it paints NOTHING and only publishes the color as
   `--reka-color-swatch-color` for the consumer to use. Without this rule the
   preset grid renders twelve empty boxes that still select the right color
   when clicked, which is exactly as confusing as it sounds. */
.ol-color-preset {
  background: var(--reka-color-swatch-color);
}

/* Transparency checkerboard, so a value with alpha - or one CSS cannot parse -
   reads as see-through rather than as a solid theme-colored square. */
.ol-color-swatch {
  background-image:
    linear-gradient(45deg, var(--color-border) 25%, transparent 25%),
    linear-gradient(-45deg, var(--color-border) 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, var(--color-border) 75%),
    linear-gradient(-45deg, transparent 75%, var(--color-border) 75%);
  background-size: 8px 8px;
  background-position:
    0 0,
    0 4px,
    4px -4px,
    -4px 0;
}
</style>
