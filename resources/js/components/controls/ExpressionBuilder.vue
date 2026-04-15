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
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion';
import { HelpCircle } from 'lucide-vue-next';
import { buildContext, evaluate, ARG_FUNCTIONS, SUPPORTED_FUNCTIONS } from '@/composables/useExpressionEngine';
import type { OverlayControl } from '@/types';

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
const helpOpen = ref(false);
const HELP_TAB_KEY = 'expression-help-tab';
const helpTab = ref(localStorage.getItem(HELP_TAB_KEY) ?? 'basics');

watch(helpTab, (val) => {
  if (val) {
    localStorage.setItem(HELP_TAB_KEY, val);
  } else {
    localStorage.removeItem(HELP_TAB_KEY);
  }
});

const controlFilter = ref('');

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
      route('api.expression.tags'),
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
  return ctrl.source ? `c.${ctrl.source}.${ctrl.key}` : `c.${ctrl.key}`;
}

function insertVariable(ctrl: OverlayControl) {
  insertAtCursor(expressionRef(ctrl));
}

const exampleCopied = ref(false);

async function copyExampleCode() {
  const example = `latest(c.streamlabs.latest_donor_name_at, c.streamlabs.latest_donor_name, c.kofi.latest_donor_name_at, c.kofi.latest_donor_name)`;
  await navigator.clipboard.writeText(example);
  exampleCopied.value = true;
  setTimeout(() => { exampleCopied.value = false; }, 3000);
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

  <!-- Help Dialog -->
  <Dialog v-model:open="helpOpen">
    <DialogContent class="max-w-2xl max-h-[85vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle>Expression Syntax</DialogTitle>
      </DialogHeader>
      <Accordion type="single" collapsible v-model="helpTab" class="text-sm">
        <!-- Basics: controls, operators, strings -->
        <AccordionItem value="basics">
          <AccordionTrigger>Controls, operators and strings</AccordionTrigger>
          <AccordionContent>
            <div class="space-y-4 text-foreground">
              <div class="space-y-1.5">
                <p class="text-xs font-semibold text-muted-foreground">Referencing controls</p>
                <ul class="list-disc pl-4 text-sm">
                  <li>Use <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.key</code> to reference a control's current value.</li>
                  <li>For service controls, use <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.service.key</code> (e.g. <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.kofi.total_received</code>).</li>
                  <li>Append <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">_at</code> to any control to get its last-updated timestamp (e.g. <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.kofi.latest_donor_name_at</code>).</li>
                </ul>
              </div>
              <div class="space-y-1.5">
                <p class="text-xs font-semibold text-muted-foreground">Operators</p>
                <div class="flex flex-wrap gap-1.5">
                  <code v-for="op in ['+', '-', '*', '/', '==', '!=', '>', '<', '>=', '<=', '&&', '||', '? :']" :key="op" class="rounded bg-sidebar px-2 py-0.5 font-mono text-xs">{{ op }}</code>
                </div>
              </div>
              <div class="space-y-1.5">
                <p class="text-xs font-semibold text-muted-foreground">Strings</p>
                <p>When working with text values, wrap them in quotes: <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-xs">c.myname == "JasperDiscovers" ? "cyan" : "red"</code></p>
              </div>
            </div>
          </AccordionContent>
        </AccordionItem>

        <!-- Functions -->
        <AccordionItem value="functions">
          <AccordionTrigger>Functions</AccordionTrigger>
          <AccordionContent>
            <div class="space-y-3 text-foreground">
              <div>
                <div class="flex flex-wrap gap-1.5 mb-1.5">
                  <code v-for="fn in ['latest', 'oldest', 'argmax', 'argmin']" :key="fn" class="rounded bg-sidebar px-2 py-0.5 font-mono text-xs">{{ fn }}()</code>
                </div>
                <p class="text-xs text-muted-foreground">Accept pairs of <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">value, label</code> arguments. Return the label paired with the highest (latest/argmax) or lowest (oldest/argmin) value. Works with numbers and timestamps.</p>
              </div>
              <div>
                <div class="flex flex-wrap gap-1.5 mb-1.5">
                  <code v-for="fn in ['max', 'min', 'clamp', 'sum', 'avg', 'abs', 'round', 'floor', 'ceil']" :key="fn" class="rounded bg-sidebar px-2 py-0.5 font-mono text-xs">{{ fn }}()</code>
                </div>
                <p class="text-xs text-muted-foreground">Standard math functions. <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">max</code>, <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">min</code>, <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">sum</code>, and <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">avg</code> accept multiple arguments. <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">round</code> takes an optional decimals count: <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">round(0.1 + 0.2, 2)</code> returns <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">"0.30"</code> (with trailing zero). The 2-arg form returns a string, so put it last in the expression or use a <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">|round:2</code> pipe instead.</p>
              </div>
              <div>
                <div class="flex flex-wrap gap-1.5 mb-1.5">
                  <code v-for="fn in ['sin', 'cos', 'fract', 'mod']" :key="fn" class="rounded bg-sidebar px-2 py-0.5 font-mono text-xs">{{ fn }}()</code>
                  <code class="rounded bg-sidebar px-2 py-0.5 font-mono text-xs">PI</code>
                </div>
                <p class="text-xs text-muted-foreground">Animation-friendly helpers. <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">sin</code> and <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">cos</code> take radians. <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">fract(x)</code> returns the fractional part (<code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">x - floor(x)</code>). <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">mod(a, b)</code> is floor-based modulo (GLSL-style), so <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">mod(-1, 5) === 4</code>. Use the <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">%</code> operator if you want JS remainder (<code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">-1 % 5 === -1</code>). <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">PI</code> is a bare identifier - use <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">PI</code>, not <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">PI()</code>.</p>
                <p class="text-xs text-muted-foreground mt-1.5">Expect trailing float noise from <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">fract</code> / <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">sin</code> / <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">cos</code> (e.g. <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">fract(10.2)</code> -> <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">0.19999...93</code>). That's standard IEEE 754 - invisible for animations, easy to clean up for display with a <a href="/help/formatting" target="_blank" rel="noopener" class="text-violet-400 hover:underline">pipe formatter</a> like <code class="rounded bg-sidebar px-1 py-0.5 font-mono text-[10px]">|round:2</code>.</p>
              </div>
              <div>
                <div class="flex flex-wrap gap-1.5 mb-1.5">
                  <code class="rounded bg-sidebar px-2 py-0.5 font-mono text-xs">now()</code>
                </div>
                <p class="text-xs text-muted-foreground">Returns the current timestamp in seconds. Useful for calculating time since an event.</p>
              </div>
            </div>
          </AccordionContent>
        </AccordionItem>

        <!-- Examples -->
        <AccordionItem value="examples">
          <AccordionTrigger>Examples</AccordionTrigger>
          <AccordionContent>
            <div class="grid grid-cols-2 gap-2">
              <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
                <p class="text-muted-foreground font-sans mb-1">Win rate percentage:</p>
                c.wins / (c.wins + c.losses) * 100
              </div>
              <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
                <p class="text-muted-foreground font-sans mb-1">Conditional text based on a value:</p>
                c.deaths > 10 ? "tilted" : "focused"
              </div>
              <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
                <p class="text-muted-foreground font-sans mb-1">Most recent donor across services:</p>
                <button
                  type="button"
                  class="text-violet-400 hover:text-violet-300 cursor-pointer font-sans underline float-right text-[10px] ml-2"
                  @click="copyExampleCode()"
                >
                  {{ exampleCopied ? 'Copied!' : 'Copy' }}
                </button>
                latest(<br />
                &nbsp;&nbsp;c.streamlabs.latest_donor_name_at, c.streamlabs.latest_donor_name,<br />
                &nbsp;&nbsp;c.kofi.latest_donor_name_at, c.kofi.latest_donor_name<br />
                )
              </div>
              <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
                <p class="text-muted-foreground font-sans mb-1">Total donations across services:</p>
                c.streamlabs.total_received + c.kofi.total_received
              </div>
              <div class="rounded bg-sidebar p-3 font-mono text-xs leading-relaxed">
                <p class="text-muted-foreground font-sans mb-1">Highest single donation amount:</p>
                max(c.streamlabs.latest_donation_amount, c.kofi.latest_donation_amount)
              </div>
            </div>
          </AccordionContent>
        </AccordionItem>
      </Accordion>
    </DialogContent>
  </Dialog>
</template>
