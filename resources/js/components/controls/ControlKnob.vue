<script setup lang="ts">
import { computed } from 'vue';
import FontPicker from '@/components/products/FontPicker.vue';
import { choiceHint, controlChoices } from '@/utils/controlChoices';

/**
 * One knob for one control: the widget a control's own row says it needs.
 *
 * This is the ONE place a designer-type control is rendered as an input. The
 * product designer and the overlay's Values tab both mount it, so the two
 * pages cannot drift into two ideas of what a number or a color looks like
 * to edit. The row decides the widget: a text control with `config.choices`
 * is a select with the held choice's hint under it, one with `config.webfont`
 * is a search over the Bunny Fonts catalogue, a boolean is a checkbox, a
 * color is a picker beside an editable field (anything CSS understands is a
 * valid value, and the picker is an offer rather than a gate), and a number
 * is a slider bounded by the row's own min/max with an exact box beside it,
 * because streamers type 96.
 *
 * Two events, because the two pages persist differently. `input` fires
 * continuously while a slider or picker is dragged and is for the caller
 * that wants to move optimistically and debounce (the designer). `commit`
 * fires once per finished change - a pick, a tick, a release, a typed value
 * left - and is the one to write on. A caller that only listens to `commit`
 * misses nothing but the drag.
 *
 * The knob renders its own label so the two pages read alike; `aside` is the
 * right-hand end of that label line, for whatever the page wants next to it
 * (the Values tab puts the tag key and its "Saved" receipt there).
 */
const props = defineProps<{
  id: string;
  control: { key: string; label: string | null; type: string; config: Record<string, unknown> | null };
  value: string;
  suggestedFonts?: { value: string; hint: string }[];
  fontsUrl?: string;
}>();

const emit = defineEmits<{
  (e: 'input', value: string): void;
  (e: 'commit', value: string): void;
}>();

const label = computed(() => props.control.label || props.control.key);
const choices = computed(() => controlChoices(props.control.config));
const hint = computed(() => choiceHint(choices.value, props.value));

/** Which widget the row asks for. Text is the fallback: a free string, committed when left. */
const kind = computed<'choices' | 'font' | 'boolean' | 'color' | 'number' | 'text'>(() => {
  const type = props.control.type;
  if (type === 'text' && choices.value.length) return 'choices';
  if (type === 'text' && props.control.config?.webfont === true && props.fontsUrl) return 'font';
  if (type === 'boolean') return 'boolean';
  if (type === 'color') return 'color';
  if (type === 'number') return 'number';
  return 'text';
});

function bound(name: 'min' | 'max' | 'step', fallback: number): number {
  const raw = props.control.config?.[name];
  const n = typeof raw === 'number' ? raw : Number(raw);
  return Number.isFinite(n) && raw !== null && raw !== undefined && raw !== '' ? n : fallback;
}

const min = computed(() => bound('min', 0));
const max = computed(() => bound('max', 100));
const step = computed(() => bound('step', 1));

function valueOf(event: Event): string {
  return (event.target as HTMLInputElement | HTMLSelectElement).value;
}
</script>

<template>
  <!-- On or off. The label is the checkbox's own. -->
  <div v-if="kind === 'boolean'" class="flex items-baseline justify-between gap-3">
    <label class="flex cursor-pointer items-center gap-2 text-sm text-foreground" :for="id">
      <input :id="id" type="checkbox" :checked="value === '1'" @change="emit('commit', ($event.target as HTMLInputElement).checked ? '1' : '0')" />
      {{ label }}
    </label>
    <slot name="aside" />
  </div>

  <template v-else>
    <div class="flex items-baseline justify-between gap-3">
      <label class="text-sm text-foreground" :for="id">{{ label }}</label>
      <slot name="aside" />
    </div>

    <!-- A closed vocabulary, declared on the row. -->
    <template v-if="kind === 'choices'">
      <select
        :id="id"
        class="w-full cursor-pointer rounded-sm border border-border bg-background px-2 py-1.5 text-sm text-foreground"
        :value="value"
        @change="emit('commit', valueOf($event))"
      >
        <!-- A held value that is not one of the choices stays selectable as
             itself, so the select never shows a value the row does not hold. -->
        <option v-if="!choices.some((choice) => choice.value === value)" :value="value">{{ value }}</option>
        <option v-for="choice in choices" :key="choice.value" :value="choice.value">{{ choice.label }}</option>
      </select>
      <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
    </template>

    <!-- An open vocabulary: every family Bunny Fonts serves. -->
    <FontPicker
      v-else-if="kind === 'font'"
      :id="id"
      :model-value="value"
      :suggested="suggestedFonts ?? []"
      :catalogue-url="fontsUrl ?? ''"
      @update:model-value="emit('commit', $event)"
    />

    <!-- A color. The field is the source of truth - anything CSS understands
         is valid, including colors the picker cannot read - and the picker
         beside it is an offer. -->
    <div v-else-if="kind === 'color'" class="flex items-center gap-2">
      <input
        :id="id"
        type="color"
        class="size-8 shrink-0 cursor-pointer rounded-sm border border-border bg-transparent p-0.5"
        :value="value"
        :aria-label="`${label} picker`"
        @input="emit('input', valueOf($event))"
        @change="emit('commit', valueOf($event))"
      />
      <input
        type="text"
        class="input-border min-w-0 flex-1 font-mono text-xs"
        :value="value"
        :aria-label="`${label} value`"
        @change="emit('commit', valueOf($event))"
      />
    </div>

    <!-- A number: a slider within the row's own bounds, and the exact value
         beside it for anyone who knows the number they want. -->
    <div v-else-if="kind === 'number'" class="flex items-center gap-3">
      <input
        :id="id"
        type="range"
        class="min-w-0 flex-1 cursor-pointer"
        :min="min"
        :max="max"
        :step="step"
        :value="value"
        @input="emit('input', valueOf($event))"
        @change="emit('commit', valueOf($event))"
      />
      <input
        type="number"
        class="input-border w-20 text-sm tabular-nums"
        :min="min"
        :max="max"
        :step="step"
        :value="value"
        :aria-label="`${label} exact value`"
        @change="emit('commit', valueOf($event))"
      />
    </div>

    <!-- A free string, written when the field is left. -->
    <input v-else :id="id" type="text" class="input-border w-full text-sm" :value="value" @change="emit('commit', valueOf($event))" />
  </template>
</template>
