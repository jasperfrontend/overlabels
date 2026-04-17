<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { html as htmlLang } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import { Badge } from '@/components/ui/badge';
import { replaceTagsWithFormatting } from '@/utils/tagParser';
import { MousePointerClick, Sparkles, Eye } from 'lucide-vue-next';

type SampleData = Record<string, string | number | boolean>;

const page = usePage();
const sampleData = computed<SampleData>(() => (page.props.sampleData as SampleData | undefined) ?? {});

const STARTER = `<style>
  .card {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 1rem;
    border-radius: 0.5rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.25);
  }
  .card img {
    width: 64px;
    height: 64px;
    border-radius: 9999px;
    object-fit: cover;
    border: 2px solid rgba(16, 185, 129, 0.5);
  }
  .card h2 { font-size: 1.25rem; font-weight: 700; margin: 0 0 0.25rem; }
  .card p { margin: 0 0 0.25rem; opacity: 0.85; font-size: 0.9rem; }
  .card strong { color: rgb(16, 185, 129); }
</style>
<div class="card">
  <img src="[[[user_avatar]]]" alt="avatar" />
  <div>
    <h2>[[[channel_name]]]</h2>
    <p>Playing [[[channel_game]]]</p>
    <p>[[[channel_title]]]</p>
    <p>
      <strong>[[[followers_total]]]</strong> followers
      &middot; <strong>[[[subscribers_total]]]</strong> subs
    </p>
  </div>
</div>`;

const PRESETS: { label: string; snippet: string; blurb: string }[] = [
  {
    label: 'Channel card',
    blurb: 'The basics: name, avatar, game, follower count. You write the CSS.',
    snippet: STARTER,
  },
  {
    label: 'Goal bar',
    blurb: 'Pipe formatters turn raw numbers into locale-aware output.',
    snippet: `<style>
  .goal {
    padding: 1rem;
    border-radius: 0.5rem;
    background: rgba(14, 165, 233, 0.08);
    border: 1px solid rgba(14, 165, 233, 0.3);
  }
  .goal h3 { margin: 0 0 0.5rem; font-size: 1rem; font-weight: 600; }
  .goal .bar {
    height: 10px;
    border-radius: 9999px;
    background: rgba(14, 165, 233, 0.15);
    overflow: hidden;
    margin: 0.5rem 0;
  }
  .goal .fill { height: 100%; background: rgb(14, 165, 233); width: 62%; }
  .goal p { opacity: 0.75; font-size: 0.9rem; margin: 0; }
  .goal strong { color: rgb(14, 165, 233); }
</style>
<div class="goal">
  <h3>[[[goals_latest_description]]]</h3>
  <div class="bar"><div class="fill"></div></div>
  <p>
    <strong>[[[goals_latest_current|number]]]</strong>
    of <strong>[[[goals_latest_target|number]]]</strong> followers
  </p>
</div>`,
  },
  {
    label: 'Latest follower',
    blurb: 'Any tag works anywhere in your HTML. No special syntax.',
    snippet: `<style>
  .thanks {
    text-align: center;
    padding: 2rem 1rem;
    border-radius: 0.5rem;
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.12), rgba(236, 72, 153, 0.12));
    border: 1px solid rgba(168, 85, 247, 0.3);
  }
  .thanks h3 { margin: 0 0 0.25rem; font-size: 1rem; font-weight: 500; opacity: 0.7; }
  .thanks h1 {
    margin: 0 0 0.5rem;
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #a855f7, #ec4899);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }
  .thanks p { margin: 0; opacity: 0.7; font-size: 0.9rem; }
</style>
<div class="thanks">
  <h3>Thanks for the follow,</h3>
  <h1>[[[followers_latest_name]]]!</h1>
  <p>Follower #[[[followers_total]]]</p>
</div>`,
  },
];

const TAG_CHIPS = [
  { key: 'channel_name', label: 'channel name' },
  { key: 'channel_game', label: 'game' },
  { key: 'channel_title', label: 'stream title' },
  { key: 'followers_total', label: 'follower count' },
  { key: 'subscribers_total', label: 'sub count' },
  { key: 'followers_latest_name', label: 'latest follower' },
  { key: 'user_avatar', label: 'avatar URL' },
];

const source = ref<string>(STARTER);
const view = ref<EditorView | null>(null);

function onEditorReady(payload: { view: EditorView }) {
  view.value = payload.view;
}

function insertTag(key: string) {
  const snippet = `[[[${key}]]]`;
  const v = view.value;
  if (v) {
    const { from, to } = v.state.selection.main;
    v.dispatch({
      changes: { from, to, insert: snippet },
      selection: { anchor: from + snippet.length },
    });
    v.focus();
    return;
  }
  source.value += snippet;
}

function loadPreset(snippet: string) {
  source.value = snippet;
  const v = view.value;
  if (v) {
    v.dispatch({
      changes: { from: 0, to: v.state.doc.length, insert: snippet },
      selection: { anchor: snippet.length },
    });
  }
}

const rendered = computed(() => replaceTagsWithFormatting(source.value, sampleData.value, 'en-US', true));

const tagCount = computed(() => {
  const matches = source.value.match(/\[\[\[[\w.:\-]+(?:\|[\w.:\- ]+)?]]]/g);
  return matches ? matches.length : 0;
});

const isDark = ref(false);
let observer: MutationObserver | null = null;

// Shadow-DOM preview: user <style> blocks are isolated from the rest of the page.
const previewRoot = ref<HTMLDivElement | null>(null);
let shadowMount: HTMLElement | null = null;

const SHADOW_RESET = `:host { display: block; color: inherit; font-family: inherit; line-height: 1.5; }
* { box-sizing: border-box; }`;

function syncPreview() {
  if (shadowMount) shadowMount.innerHTML = rendered.value;
}

onMounted(() => {
  isDark.value = document.documentElement.classList.contains('dark');
  observer = new MutationObserver(() => {
    isDark.value = document.documentElement.classList.contains('dark');
  });
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

  if (previewRoot.value) {
    const root = previewRoot.value.attachShadow({ mode: 'open' });
    const styleEl = document.createElement('style');
    styleEl.textContent = SHADOW_RESET;
    root.appendChild(styleEl);
    shadowMount = document.createElement('div');
    root.appendChild(shadowMount);
    syncPreview();
  }
});

watch(rendered, syncPreview);

onUnmounted(() => {
  observer?.disconnect();
});

const editorKey = computed(() => (isDark.value ? 'dark' : 'light'));

const baseTheme = EditorView.theme({
  '&': { height: '100%', fontSize: '13px' },
  '.cm-scroller': { overflow: 'auto' },
  '.cm-content': { padding: '10px 12px' },
  '.cm-focused': { outline: 'none' },
});

const extensions = computed(() => [htmlLang(), baseTheme, ...(isDark.value ? [oneDark] : [])]);
</script>

<template>
  <section id="playground" class="scroll-mt-16 border-b border-sidebar-accent py-24">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-5xl">
        <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Playground</Badge>
        <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Try it, right here.</h2>
        <p class="mb-3 max-w-3xl text-lg text-foreground">
          This is an editor with a live preview. Type plain HTML on the left. Anywhere you write
          <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">[[[channel_name]]]</code>,
          the preview on the right will show the actual value (<code
            class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-emerald-600 dark:text-emerald-400">wilko_dj</code>
          in this demo). That is the entire idea. Your overlay is HTML; tags pull live data in.
        </p>
        <p class="mb-8 max-w-3xl text-sm text-muted-foreground">
          New here? Click a tag chip below to drop it into the editor, or pick a preset to load a fuller example. No
          account needed - this all runs in your browser.
        </p>

        <!-- Presets -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <span class="mr-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
            <Sparkles class="h-3.5 w-3.5" /> Presets
          </span>
          <button
            v-for="preset in PRESETS"
            :key="preset.label"
            type="button"
            @click="loadPreset(preset.snippet)"
            class="cursor-pointer rounded-sm border border-sidebar-accent bg-card px-3 py-1.5 text-sm transition-colors hover:border-sky-500/60 hover:text-sky-500"
            :title="preset.blurb"
          >
            {{ preset.label }}
          </button>
        </div>

        <!-- Tag chips -->
        <div class="mb-6 flex flex-wrap items-center gap-2">
          <span class="mr-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
            <MousePointerClick class="h-3.5 w-3.5" /> Insert a tag
          </span>
          <button
            v-for="chip in TAG_CHIPS"
            :key="chip.key"
            type="button"
            @click="insertTag(chip.key)"
            class="cursor-pointer rounded-sm bg-accent px-2.5 py-1 font-mono text-xs text-amber-700 transition-colors hover:bg-amber-500/10 hover:text-amber-600 dark:text-amber-400 dark:hover:text-amber-300"
            :title="`Insert [[[${chip.key}]]]`"
          >
            [[[{{ chip.key }}]]]
          </button>
        </div>

        <!-- Editor + Preview -->
        <div class="grid gap-4 lg:grid-cols-2">
          <!-- Editor -->
          <div class="overflow-hidden rounded-sm border border-sidebar-accent bg-card">
            <div class="flex items-center justify-between border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
              <span class="font-mono text-xs text-muted-foreground">your_overlay.html</span>
              <span class="font-mono text-xs text-muted-foreground">
                {{ tagCount }} tag{{ tagCount === 1 ? '' : 's' }} detected
              </span>
            </div>
            <div class="h-[360px] bg-background">
              <Codemirror
                :key="editorKey"
                v-model="source"
                class="h-full"
                :indent-with-tab="true"
                :tab-size="2"
                :extensions="extensions"
                placeholder="Type HTML with [[[tag_name]]] anywhere..."
                @ready="onEditorReady"
              />
            </div>
          </div>

          <!-- Preview -->
          <div class="overflow-hidden rounded-sm border border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-950/20">
            <div class="flex items-center gap-2 border-b border-emerald-500/20 px-4 py-2.5">
              <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
              <Eye class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
              <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">live preview - sample data</span>
            </div>
            <div ref="previewRoot" class="h-[360px] overflow-auto p-5 text-foreground" />
          </div>
        </div>

        <p class="mt-4 max-w-3xl text-sm text-muted-foreground">
          Under the hood: every tag you type is matched against a flat data object. In this playground, the values are
          static sample data. On your real overlay, those same tag names are wired to live Twitch data, so
          <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 font-mono text-xs text-amber-700 dark:text-amber-400">[[[followers_total]]]</code>
          ticks up the moment someone follows you.
        </p>
      </div>
    </div>
  </section>
</template>

