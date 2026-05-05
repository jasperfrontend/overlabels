<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';
import jsep from 'jsep';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { ExternalLink, FunctionSquare } from 'lucide-vue-next';
import { buildContext, evaluate, ARG_FUNCTIONS, SUPPORTED_FUNCTIONS } from '@/composables/useExpressionEngine';
import type { OverlayControl } from '@/types';

interface FunctionGroup {
  label: string;
  functions: string[];
}

const FUNCTION_GROUPS: FunctionGroup[] = [
  { label: 'Label selectors', functions: ['argmax', 'argmin', 'latest', 'oldest'] },
  { label: 'Multi-argument', functions: ['max', 'min', 'clamp', 'sum', 'avg'] },
  { label: 'Rounding and utility', functions: ['abs', 'round', 'floor', 'ceil', 'sqrt'] },
  { label: 'Trig', functions: ['sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'atan2'] },
  { label: 'GLSL helpers', functions: ['fract', 'mod'] },
  { label: 'Time', functions: ['now', 'now_ms'] },
];

const CONSTANTS = ['PI'];

const props = defineProps<{
  modelValue: string;
  availableControls: OverlayControl[];
  errors: Record<string, string>;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

// Local ref avoids the prop round-trip that kills the browser's undo stack.
// With a computed get/set, every keystroke emits to parent → parent updates
// prop → Vue programmatically sets textarea.value → undo history is wiped.
const expressionText = ref(props.modelValue);
const textareaEl = ref<HTMLTextAreaElement | null>(null);

watch(() => props.modelValue, (val) => {
  if (val !== expressionText.value) expressionText.value = val;
});

watch(expressionText, (val) => {
  emit('update:modelValue', val);
});

const expressionError = ref('');
const expressionPreview = ref('');

/** Compute a representative preview value for a control, including timer types. */
function resolvePreviewValue(ctrl: OverlayControl): unknown {
  if (ctrl.type === 'timer') {
    const cfg = ctrl.config ?? {};
    const mode = cfg.mode ?? 'countup';
    const offset = Number(cfg.offset_seconds ?? 0);

    if (mode === 'countto') {
      const target = cfg.target_datetime ? new Date(cfg.target_datetime).getTime() : null;
      if (!target) return 0;
      return Math.max(0, Math.floor((target - Date.now()) / 1000));
    }

    let elapsed = offset;
    if (cfg.running && cfg.started_at) {
      elapsed = offset + Math.floor((Date.now() - new Date(cfg.started_at).getTime()) / 1000);
    }

    const base = Number(cfg.base_seconds ?? 0);
    return mode === 'countdown' ? Math.max(0, base - elapsed) : elapsed;
  }

  return ctrl.value ?? '';
}
const controlFilter = ref('');
const functionsOpen = ref(false);
const copiedSnippet = ref<string | null>(null);
let copyTimeout: ReturnType<typeof setTimeout> | null = null;

function copySnippet(snippet: string) {
  if (!navigator.clipboard) return;
  navigator.clipboard.writeText(snippet).then(() => {
    copiedSnippet.value = snippet;
    if (copyTimeout) clearTimeout(copyTimeout);
    copyTimeout = setTimeout(() => {
      copiedSnippet.value = null;
    }, 1500);
  });
}

/** Walk an AST and return a validation error for unsupported/misconfigured function calls, or null if valid. */
function validateFunctions(node: jsep.Expression): string | null {
  switch (node.type) {
    case 'CallExpression': {
      const ce = node as jsep.CallExpression;
      if (ce.callee.type !== 'Identifier') return 'Only simple function names are supported (no computed calls).';
      const name = (ce.callee as jsep.Identifier).name;
      if (!SUPPORTED_FUNCTIONS.has(name)) return `Unknown function "${name}". Supported: ${[...SUPPORTED_FUNCTIONS].join(', ')}.`;
      if (ARG_FUNCTIONS.has(name) && ce.arguments.length % 2 !== 0) {
        return `${name}() requires pairs of (value, label) arguments - got ${ce.arguments.length} (odd).`;
      }
      for (const arg of ce.arguments) {
        const err = validateFunctions(arg);
        if (err) return err;
      }
      return null;
    }
    case 'BinaryExpression': {
      const be = node as jsep.BinaryExpression;
      return validateFunctions(be.left) ?? validateFunctions(be.right);
    }
    case 'UnaryExpression':
      return validateFunctions((node as jsep.UnaryExpression).argument);
    case 'ConditionalExpression': {
      const ce = node as jsep.ConditionalExpression;
      return validateFunctions(ce.test) ?? validateFunctions(ce.consequent) ?? validateFunctions(ce.alternate);
    }
    case 'MemberExpression':
      return validateFunctions((node as jsep.MemberExpression).object);
    default:
      return null;
  }
}

// Live Twitch tag values fetched from the API on modal mount. Populated once
// per modal session; real values replace the fallback mocks below. Reactive
// so the preview watcher re-runs when the fetch resolves.
const liveTwitchTags = ref<Record<string, unknown> | null>(null);

onMounted(async () => {
  const csrf = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
  try {
    const res = await axios.get<{ tags?: Record<string, unknown> }>(
      route('expression.tags'),
      {
        withCredentials: true,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
      },
    );
    liveTwitchTags.value = res.data?.tags ?? {};
  } catch (err) {
    // Fall back to mocks; preview still works, just with placeholder values.
    const status = (err as { response?: { status?: number } })?.response?.status;
    console.warn(`[ExpressionBuilder] Live tag fetch failed${status ? ` (${status})` : ''}, falling back to mocks`, err);
    liveTwitchTags.value = {};
  }
});

/**
 * Produce a plausible mock value for a `t.<name>` reference in the preview
 * when the live fetch hasn't resolved or didn't include this tag. Real t.*
 * values come from `liveTwitchTags` whenever they're available.
 */
function mockTwitchTag(name: string): unknown {
  if (/_is_/.test(name)) return false;
  if (/(^|_)(total|count|points|bits|amount|peak|ms|viewers)(_|$)/.test(name)) return 42;
  if (/_at$/.test(name)) return Math.floor(Date.now() / 1000);
  if (/_(date|time|started_at)$/.test(name)) return Math.floor(Date.now() / 1000);
  return `(${name})`;
}

watch([expressionText, liveTwitchTags], ([text]) => {
  expressionError.value = '';
  expressionPreview.value = '';
  if (!text.trim()) return;

  if (text.length > 500) {
    expressionError.value = 'Expression must be 500 characters or less.';
    return;
  }

  try {
    const ast = jsep(text);

    const fnError = validateFunctions(ast);
    if (fnError) {
      expressionError.value = fnError;
      return;
    }

    const mockData: Record<string, unknown> = {};
    for (const ctrl of props.availableControls) {
      const key = ctrl.source ? `c:${ctrl.source}:${ctrl.key}` : `c:${ctrl.key}`;
      mockData[key] = resolvePreviewValue(ctrl);
    }

    // For every t.<name> reference, prefer the live server value; fall back
    // to a shape-aware mock (or the previous placeholder) if the fetch is
    // still pending or the tag is missing from the response.
    const live = liveTwitchTags.value ?? {};
    for (const match of text.matchAll(/\bt\.([a-z][a-z0-9_]*)/g)) {
      const tagName = match[1];
      const key = `t:${tagName}`;
      if (mockData[key] !== undefined) continue;
      mockData[key] = live[tagName] !== undefined ? live[tagName] : mockTwitchTag(tagName);
    }

    const ctx = buildContext(mockData);
    const result = evaluate(ast, ctx);
    expressionPreview.value = result === null || result === undefined ? '' : String(result);
  } catch {
    expressionError.value = 'Invalid expression syntax.';
  }
}, { immediate: true });

// Track cursor position so inserts land where the user last had focus
const lastCursor = ref<number | null>(null);

function saveCursor(e: Event) {
  const el = e.target as HTMLTextAreaElement;
  lastCursor.value = el.selectionStart;
}

function insertAtCursor(snippet: string) {
  const el = textareaEl.value;
  if (!el) {
    // Fallback: no textarea ref yet, just append
    expressionText.value = expressionText.value ? `${expressionText.value} ${snippet}` : snippet;
    return;
  }

  const text = expressionText.value;
  const pos = lastCursor.value ?? text.length;

  // Build the snippet with smart spacing
  const before = text.slice(0, pos);
  const after = text.slice(pos);
  const needsSpaceBefore = before.length > 0 && !before.endsWith(' ') && !before.endsWith('\n');
  const needsSpaceAfter = after.length > 0 && !after.startsWith(' ') && !after.startsWith('\n');
  const insertion = (needsSpaceBefore ? ' ' : '') + snippet + (needsSpaceAfter ? ' ' : '');

  // Use execCommand so the browser tracks this as an undoable action
  el.focus();
  el.setSelectionRange(pos, pos);
  document.execCommand('insertText', false, insertion);

  lastCursor.value = pos + insertion.length;
}

function expressionRef(ctrl: OverlayControl): string {
  if (!ctrl.source) return `c.${ctrl.key}`;
  // Hyphens in service names (e.g. overlabels-mobile) aren't valid in JS identifiers,
  // so jsep would parse `c.overlabels-mobile.gps_lat` as the subtraction
  // `c.overlabels - mobile.gps_lat`. Use bracket notation to preserve the source name.
  if (/[^a-zA-Z0-9_$]/.test(ctrl.source)) return `c["${ctrl.source}"].${ctrl.key}`;
  return `c.${ctrl.source}.${ctrl.key}`;
}

function insertVariable(ctrl: OverlayControl) {
  insertAtCursor(expressionRef(ctrl));
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
    gps: 'Overlabels GPS',
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
    <div class="flex items-center justify-between gap-2">
      <p class="text-sm font-medium text-violet-500 dark:text-violet-400">Expression</p>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="flex items-center gap-1 rounded-sm px-2 py-0.5 text-xs text-muted-foreground hover:text-foreground hover:bg-background transition cursor-pointer"
          title="Browse all available functions"
          @click="functionsOpen = true"
        >
          <FunctionSquare class="size-3.5" />
          Functions
        </button>
        <a
          href="/help/expressions"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-1 rounded-sm px-2 py-0.5 text-xs text-muted-foreground hover:text-foreground hover:bg-background transition cursor-pointer"
          title="Open the full Expression Controls reference in a new tab"
        >
          <ExternalLink class="size-3.5" />
          Full reference
        </a>
      </div>
    </div>

    <!-- Formula -->
    <div class="space-y-1">
      <Label for="expression-text">Formula</Label>
      <textarea
        id="expression-text"
        ref="textareaEl"
        v-model="expressionText"
        rows="3"
        class="w-full rounded-sm border border-sidebar bg-background px-3 py-2 mt-1 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none font-mono text-sm resize-y"
        :class="{ 'border-destructive': expressionError }"
        placeholder="e.g. c.wins / (c.wins + c.losses) * 100"
        @blur="saveCursor"
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

  <!-- Functions picker dialog -->
  <Dialog v-model:open="functionsOpen">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle>Available functions</DialogTitle>
      </DialogHeader>
      <p class="text-xs text-muted-foreground">
        Click any function to copy it to your clipboard, then paste it into your expression.
        For full descriptions, examples, and the Haversine walkthrough, see the
        <a href="/help/expressions" target="_blank" rel="noopener" class="text-violet-400 hover:underline">Expression Controls reference</a>.
      </p>
      <div class="space-y-3 max-h-[60vh] overflow-y-auto">
        <div v-for="group in FUNCTION_GROUPS" :key="group.label">
          <p class="text-xs font-semibold text-muted-foreground mb-1">{{ group.label }}</p>
          <div class="flex flex-wrap gap-1">
            <button
              v-for="fn in group.functions"
              :key="fn"
              type="button"
              :title="copiedSnippet === `${fn}()` ? 'Copied!' : `Copy ${fn}() to clipboard`"
              class="rounded-sm border border-sidebar bg-background px-2 py-0.5 font-mono text-xs text-foreground/80 hover:text-violet-400 hover:border-violet-400/40 transition cursor-pointer"
              @click="copySnippet(`${fn}()`)"
            >
              {{ copiedSnippet === `${fn}()` ? 'Copied!' : `${fn}()` }}
            </button>
          </div>
        </div>
        <div>
          <p class="text-xs font-semibold text-muted-foreground mb-1">Constants</p>
          <div class="flex flex-wrap gap-1">
            <button
              v-for="c in CONSTANTS"
              :key="c"
              type="button"
              :title="copiedSnippet === c ? 'Copied!' : `Copy ${c} to clipboard`"
              class="rounded-sm border border-sidebar bg-background px-2 py-0.5 font-mono text-xs text-foreground/80 hover:text-violet-400 hover:border-violet-400/40 transition cursor-pointer"
              @click="copySnippet(c)"
            >
              {{ copiedSnippet === c ? 'Copied!' : c }}
            </button>
          </div>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
