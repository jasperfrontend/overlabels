<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import jsep from 'jsep';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { HelpCircle } from 'lucide-vue-next';
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
const helpOpen = ref(false);
const controlFilter = ref('');

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

// Group controls by source for visual clarity
interface ControlGroup {
  label: string;
  controls: OverlayControl[];
}

const filteredGroupedControls = computed((): ControlGroup[] => {
  const q = controlFilter.value.toLowerCase().trim();
  const filtered = q
    ? props.availableControls.filter((c) => {
        const ref = expressionRef(c).toLowerCase();
        const label = (c.label ?? '').toLowerCase();
        return ref.includes(q) || label.includes(q);
      })
    : props.availableControls;

  const groups: Record<string, OverlayControl[]> = {};
  for (const ctrl of filtered) {
    const groupKey = ctrl.source ?? 'custom';
    if (!groups[groupKey]) groups[groupKey] = [];
    groups[groupKey].push(ctrl);
  }

  const sourceLabels: Record<string, string> = {
    custom: 'Your controls',
    twitch: 'Twitch',
    kofi: 'Ko-fi',
    streamlabs: 'StreamLabs',
    gpslogger: 'GPSLogger',
  };

  // Custom first, then services alphabetically
  const order = ['custom', ...Object.keys(groups).filter((k) => k !== 'custom').sort()];
  return order
    .filter((k) => groups[k]?.length)
    .map((k) => ({ label: sourceLabels[k] ?? k, controls: groups[k] }));
});
</script>

<template>
  <div class="space-y-3 rounded-sm border border-violet-400/30 bg-violet-400/5 p-3">
    <div class="flex items-center justify-between">
      <p class="text-sm font-medium text-violet-500 dark:text-violet-400">Expression</p>
      <button
        type="button"
        class="flex items-center gap-1 rounded-sm px-2 py-0.5 text-xs text-muted-foreground hover:text-foreground hover:bg-background transition cursor-pointer"
        title="Expression syntax help"
        @click="helpOpen = true"
      >
        <HelpCircle class="size-3.5" />
        Help
      </button>
    </div>

    <!-- Formula -->
    <div class="space-y-1">
      <Label for="expression-text">Formula</Label>
      <textarea
        id="expression-text"
        v-model="expressionText"
        rows="3"
        class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 mt-1 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none font-mono text-sm resize-y"
        :class="{ 'border-destructive': expressionError }"
        placeholder="e.g. c.wins / (c.wins + c.losses) * 100"
      />
      <p v-if="expressionError" class="text-xs text-destructive">{{ expressionError }}</p>
      <p v-if="errors['config.expression']" class="text-xs text-destructive">{{ errors['config.expression'] }}</p>
    </div>

    <!-- Available controls -->
    <div v-if="availableControls.length" class="space-y-2">
      <div class="flex items-center justify-between">
        <Label>Available controls</Label>
        <span class="text-xs text-muted-foreground">click to insert</span>
      </div>
      <input
        type="text"
        v-model="controlFilter"
        placeholder="Filter controls..."
        class="h-7 w-full text-xs input-border"
      />
      <div class="max-h-50 overflow-y-auto space-y-2">
        <div v-for="group in filteredGroupedControls" :key="group.label">
          <p class="text-xs font-semibold text-muted-foreground mb-1 sticky top-0 bg-violet-400/5 py-0.5">{{ group.label }}</p>
          <div class="grid grid-cols-2 gap-1">
            <button
              v-for="ctrl in group.controls"
              :key="ctrl.id"
              type="button"
              :title="ctrl.label ? `${ctrl.label} - ${expressionRef(ctrl)}` : expressionRef(ctrl)"
              class="rounded-sm border border-sidebar bg-background overflow-hidden cursor-pointer px-2 py-1 font-mono text-left text-xs text-foreground/80 hover:text-violet-400 hover:border-violet-400/40 transition"
              @click="insertVariable(ctrl)"
            >
              {{ expressionRef(ctrl) }}
              <span v-if="ctrl.label" class="block text-[10px] text-muted-foreground font-sans truncate">{{ ctrl.label }}</span>
            </button>
          </div>
        </div>
        <p v-if="!filteredGroupedControls.length" class="text-xs text-muted-foreground py-2 text-center">No controls match your filter.</p>
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

  <!-- Help Dialog -->
  <Dialog v-model:open="helpOpen">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle>Expression Syntax</DialogTitle>
      </DialogHeader>
      <div class="space-y-4 text-sm">
        <div>
          <p class="font-semibold mb-2">Referencing controls</p>
          <div class="space-y-1.5 text-foreground">
            <p>Use <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.key</code> to reference a control's current value.</p>
            <p>For service controls, use <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.service.key</code> (e.g. <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.kofi.total_received</code>).</p>
            <p>Append <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">_at</code> to any control to get its last-updated timestamp (e.g. <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.kofi.latest_donor_name_at</code>).</p>
          </div>
        </div>

        <div>
          <p class="font-semibold mb-2">Supported operators</p>
          <div class="flex flex-wrap gap-1.5">
            <code v-for="op in ['+', '-', '*', '/', '==', '!=', '>', '<', '>=', '<=', '&&', '||', '? :', '()']" :key="op" class="rounded bg-sidebar px-2 py-0.5 font-mono text-xs">{{ op }}</code>
          </div>
        </div>

        <div>
          <p class="font-semibold mb-2">Examples</p>
          <div class="space-y-3">
            <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
              <p class="text-muted-foreground font-sans mb-1">Win rate percentage:</p>
              c.wins / (c.wins + c.losses) * 100
            </div>
            <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
              <p class="text-muted-foreground font-sans mb-1">Conditional text based on a value:</p>
              c.deaths > 10 ? "tilted" : "focused"
            </div>
            <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
              <p class="text-muted-foreground font-sans mb-1">Cross-service comparison (most recent donor):</p>
              <button
                type="button"
                class="text-violet-400 hover:text-violet-300 cursor-pointer font-sans underline float-right text-[10px] ml-2"
                @click="insertExample(); helpOpen = false"
              >
                insert this
              </button>
              c.streamlabs.latest_donor_name_at &gt; c.kofi.latest_donor_name_at<br />
              &nbsp;&nbsp;? c.streamlabs.latest_donor_name<br />
              &nbsp;&nbsp;: c.kofi.latest_donor_name
            </div>
            <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
              <p class="text-muted-foreground font-sans mb-1">Total donations across services:</p>
              c.streamlabs.total_received + c.kofi.total_received
            </div>
          </div>
        </div>

        <div>
          <p class="font-semibold mb-2">Strings</p>
          <p class="text-foreground">When working with text values, wrap them in quotes: <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.myname == "JasperDiscovers" ? "cyan" : "red"</code></p>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
