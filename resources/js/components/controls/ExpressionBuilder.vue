<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import jsep from 'jsep';
import { Label } from '@/components/ui/label';
import { buildContext, evaluate } from '@/composables/useExpressionEngine';
import type { OverlayControl } from '@/types';

const props = defineProps<{
  modelValue: string;
  availableControls: OverlayControl[];
  errors: Record<string, string>;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

const expressionText = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

const expressionError = ref('');
const expressionPreview = ref('');

watch(expressionText, (text) => {
  expressionError.value = '';
  expressionPreview.value = '';
  if (!text.trim()) return;

  if (text.length > 500) {
    expressionError.value = 'Expression must be 500 characters or less.';
    return;
  }

  try {
    const ast = jsep(text);

    const mockData: Record<string, unknown> = {};
    for (const ctrl of props.availableControls) {
      const key = ctrl.source ? `c:${ctrl.source}:${ctrl.key}` : `c:${ctrl.key}`;
      mockData[key] = ctrl.value ?? '';
    }

    const ctx = buildContext(mockData);
    const result = evaluate(ast, ctx);
    expressionPreview.value = result === null || result === undefined ? '' : String(result);
  } catch {
    expressionError.value = 'Invalid expression syntax.';
  }
});

function expressionRef(ctrl: OverlayControl): string {
  return ctrl.source ? `c.${ctrl.source}.${ctrl.key}` : `c.${ctrl.key}`;
}

function insertVariable(ctrl: OverlayControl) {
  const ref = expressionRef(ctrl);
  expressionText.value = expressionText.value ? `${expressionText.value} ${ref}` : ref;
}

function insertExample() {
  const example = `c.streamlabs.latest_donor_name_at > c.kofi.latest_donor_name_at
    ? c.streamlabs.latest_donor_name
    : c.kofi.latest_donor_name`;

  expressionText.value = expressionText.value
    ? `${expressionText.value}\n${example}`
    : example;
}
</script>

<template>
  <div class="space-y-1 rounded-sm border border-violet-400/30 bg-violet-400/5 p-3">
    <p class="text-sm font-medium text-violet-500 dark:text-violet-400">Expression</p>

    <div class="space-y-1">
      <p class="text-xs text-muted-foreground mb-2">
        Supports
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">+</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">-</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">*</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">/</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">==</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">!=</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">&gt;</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">&lt;</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">&gt;=</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">&lt;=</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">&amp;&amp;</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">||</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">? :</code>
        <code class="rounded bg-black/10 px-1 ml-0.5 dark:bg-white/10">()</code>
      </p>
      <Label for="expression-text">Formula</Label>

      <textarea
        id="expression-text"
        v-model="expressionText"
        rows="3"
        class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 mt-1 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none font-mono text-sm resize-y"
        :class="{ 'border-destructive': expressionError }"
        placeholder="c.streamlabs.latest_donor_name_at > c.kofi.latest_donor_name_at
    ? c.streamlabs.latest_donor_name
    : c.kofi.latest_donor_name"
      />
      <p v-if="expressionError" class="text-xs text-destructive">{{ expressionError }}</p>
      <p v-if="errors['config.expression']" class="text-xs text-destructive">{{ errors['config.expression'] }}</p>
      <p class="text-xs text-muted-foreground mb-4">
        Use <code class="rounded bg-black/10 px-1 dark:bg-white/10">c.key</code> to reference controls,
        <code class="rounded bg-black/10 px-1 dark:bg-white/10">c.service.key</code> for service controls.
        You can add <code class="rounded bg-black/10 px-1 dark:bg-white/10">_at</code> to any control to get when it was last updated.
        The placeholder text shows the last user who donated through either Ko-fi or Streamlabs.
        <button
          type="button"
          class="ml-1 underline text-violet-300 hover:text-violet-400 cursor-pointer"
          @click="insertExample"
          title="Insert the placeholder code as an example"
        >
          insert example
        </button>
      </p>
    </div>

    <!-- Available variables (click to insert) -->
    <div v-if="availableControls.length" class="space-y-1">
      <Label>Available controls <span class="text-xs text-muted-foreground">(click to insert)</span></Label>
      <div class="grid grid-cols-2 max-h-50 flex-col gap-1.5 overflow-y-auto">
        <button
          v-for="ctrl in availableControls"
          :key="ctrl.id"
          type="button"
          :title="expressionRef(ctrl)"
          class="rounded-sm border border-dashed border-sidebar overflow-hidden cursor-pointer px-2 py-0.5 font-mono text-left text-xs text-muted-foreground hover:text-foreground hover:border-foreground/30 transition"
          @click="insertVariable(ctrl)"
        >
          {{ expressionRef(ctrl) }}
        </button>
      </div>
    </div>

    <!-- Live preview -->
    <div v-if="expressionText.trim() && !expressionError" class="space-y-1">
      <Label>Preview</Label>
      <div class="rounded-sm bg-black/5 dark:bg-white/5 px-3 py-1.5 font-mono text-sm">
        {{ expressionPreview !== '' ? expressionPreview : '(empty)' }}
      </div>
    </div>
  </div>
</template>
