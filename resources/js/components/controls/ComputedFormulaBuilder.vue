<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { OverlayControl } from '@/types';

export interface FormulaState {
  watch_key: string;
  watch_source: string | null;
  operator: string;
  compare_value: string;
  then_value: string;
  else_value: string;
}

const props = defineProps<{
  modelValue: FormulaState;
  availableControls: OverlayControl[];
  errors: Record<string, string>;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: FormulaState): void;
}>();

const formula = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

// Combined dropdown key for watch control: "source:key" or just "key"
const formulaWatchRef = ref('');

// Initialize formulaWatchRef from formula state
if (formula.value.watch_key) {
  formulaWatchRef.value = formula.value.watch_source
    ? `${formula.value.watch_source}:${formula.value.watch_key}`
    : formula.value.watch_key;
}

watch(formulaWatchRef, (val) => {
  if (!val) {
    emit('update:modelValue', { ...formula.value, watch_key: '', watch_source: null });
    return;
  }
  const parts = val.split(':');
  if (parts.length === 2) {
    emit('update:modelValue', { ...formula.value, watch_source: parts[0], watch_key: parts[1] });
  } else {
    emit('update:modelValue', { ...formula.value, watch_source: null, watch_key: parts[0] });
  }
});

// Sync formulaWatchRef when modelValue changes externally (e.g. on edit open)
watch(() => props.modelValue, (val) => {
  const expected = val.watch_source
    ? `${val.watch_source}:${val.watch_key}`
    : val.watch_key;
  if (formulaWatchRef.value !== expected) {
    formulaWatchRef.value = expected;
  }
}, { deep: true });

function updateField(field: keyof FormulaState, value: string) {
  emit('update:modelValue', { ...formula.value, [field]: value });
}

function watchControlRef(ctrl: OverlayControl): string {
  return ctrl.source ? `${ctrl.source}:${ctrl.key}` : ctrl.key;
}

function watchControlLabel(ctrl: OverlayControl): string {
  const label = ctrl.label || ctrl.key;
  return ctrl.source ? `${label} (${ctrl.source}:${ctrl.key})` : `${label} (${ctrl.key})`;
}
</script>

<template>
  <div class="space-y-3 rounded-sm border border-violet-400/30 bg-violet-400/5 p-3">
    <p class="text-sm font-medium text-violet-500 dark:text-violet-400">Formula</p>

    <div class="space-y-1">
      <Label for="formula-watch">Watch control</Label>
      <select
        id="formula-watch"
        v-model="formulaWatchRef"
        class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
      >
        <option value="">-- Select a control --</option>
        <option v-for="ctrl in availableControls" :key="ctrl.id" :value="watchControlRef(ctrl)">
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
          :value="formula.operator"
          class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none text-sm"
          @change="updateField('operator', ($event.target as HTMLSelectElement).value)"
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
        <Input id="formula-compare" :model-value="formula.compare_value" placeholder="e.g. 5" @update:model-value="updateField('compare_value', $event)" />
        <p v-if="errors['config.formula.compare_value']" class="text-xs text-destructive">{{ errors['config.formula.compare_value'] }}</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div class="space-y-1">
        <Label for="formula-then">Then value <span class="text-muted-foreground text-xs">(condition true)</span></Label>
        <Input id="formula-then" :model-value="formula.then_value" placeholder="e.g. 50" @update:model-value="updateField('then_value', $event)" />
        <p v-if="errors['config.formula.then_value']" class="text-xs text-destructive">{{ errors['config.formula.then_value'] }}</p>
      </div>
      <div class="space-y-1">
        <Label for="formula-else">Else value <span class="text-muted-foreground text-xs">(condition false)</span></Label>
        <Input id="formula-else" :model-value="formula.else_value" placeholder="e.g. 10" @update:model-value="updateField('else_value', $event)" />
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
</template>
