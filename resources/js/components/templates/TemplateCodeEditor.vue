<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { ChevronDown, ChevronUp, FileCode2, Code, Maximize, Minimize, Palette, Wind, Video } from '@lucide/vue';
import AddToObsButton from '@/components/AddToObsButton.vue';
import { Badge } from '@/components/ui/badge';

const headValue = defineModel<string>('head', { required: true });
const htmlValue = defineModel<string>('body', { required: true });
const cssValue = defineModel<string>('css', { required: true });

const props = defineProps<{
  template?: { id: number; name: string; slug: string };
  templateType?: string;
}>();

// Detect dark mode reactively via MutationObserver on <html> class
const isDark = ref(document.documentElement.classList.contains('dark'));
let observer: MutationObserver | null = null;

onMounted(() => {
  observer = new MutationObserver(() => {
    isDark.value = document.documentElement.classList.contains('dark');
  });
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  window.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
  observer?.disconnect();
  window.removeEventListener('keydown', onEscape);
});

const editorTabs = [
  { key: 'head', label: 'HEAD', icon: FileCode2, color: 'text-pink-500 dark:text-pink-400' },
  { key: 'body', label: 'BODY', icon: Code, color: 'text-cyan-500 dark:text-cyan-400' },
  { key: 'css', label: 'CSS', icon: Palette, color: 'text-lime-500 dark:text-lime-400' },
  { key: 'tailwind', label: 'TW3', icon: Wind, color: 'text-sky-500 dark:text-sky-400' },
  { key: 'add-to-obs', label: 'OBS', icon: Video, color: 'text-violet-500 dark:text-violet-400' },
] as const;

type CodeTab = (typeof editorTabs)[number]['key'];

// OBS tab generates a browser-source URL, which only exists once the overlay is saved
const visibleTabs = computed(() => editorTabs.filter((tab) => tab.key !== 'add-to-obs' || props.template));

const codeTab = ref<CodeTab>('body');
const isExpanded = ref(false);
const isFullscreen = ref(false);

const { register } = useKeyboardShortcuts();

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape' && isFullscreen.value) {
    isFullscreen.value = false;
  }
}

onMounted(() => {
  register('fullscreen-editor', 'ctrl+shift+f', () => {
    isFullscreen.value = !isFullscreen.value;
  }, { description: 'Distraction-free editor' });
});

const baseTheme = EditorView.theme({
  '&': { height: '100%', fontSize: '14px' },
  '.cm-scroller': { overflow: 'auto' },
  '.cm-content': { padding: '5px 5px 3rem' },
  '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' },
});

// Key changes when dark mode toggles, forcing CodeMirror instances to remount with new extensions
const editorKey = computed(() => (isDark.value ? 'dark' : 'light'));
const headExtensions = computed(() => [html(), baseTheme, ...(isDark.value ? [oneDark] : [])]);
const htmlExtensions = computed(() => [html(), baseTheme, ...(isDark.value ? [oneDark] : [])]);
const cssExtensions = computed(() => [css(), baseTheme, ...(isDark.value ? [oneDark] : [])]);
</script>

<template>
  <div>
    <div
      class="overflow-hidden"
      :class="isFullscreen ? 'fixed inset-0 z-50 rounded-none' : 'rounded-none'"
      :style="isFullscreen ? undefined : { height: isExpanded ? '800px' : '500px' }"
    >
      <div class="flex h-full">
        <!-- Vertical file tabs -->
        <div class="flex flex-col bg-sidebar text-sidebar-foreground">
          <button
            v-for="tab in visibleTabs"
            :key="tab.key"
            type="button"
            @click="codeTab = tab.key"
            :class="[
              'flex cursor-pointer items-center gap-1.5 px-6 py-3 text-left text-xs uppercase transition-colors',
              codeTab === tab.key
                ? 'bg-[#f8f8f8] dark:bg-[#160e21] text-accent-foreground'
                : 'text-sidebar-foreground hover:bg-background/40 hover:text-foreground',
            ]"
          >
            <component :is="tab.icon" :class="tab.color" class="size-3.5" />
            {{ tab.label }}
          </button>
          <div class="mt-auto">
            <button
              type="button"
              @click="isFullscreen = !isFullscreen"
              class="flex w-full cursor-pointer items-center gap-1.5 px-5 py-3 text-xs text-sidebar-foreground/40 transition-colors hover:text-sidebar-foreground"
              :title="isFullscreen ? 'Exit distraction-free editor mode' : 'Enter distraction-free editor mode'"
            >
              <Minimize v-if="isFullscreen" class="size-3.5" />
              <Maximize v-else class="size-3.5" />
              {{ isFullscreen ? 'Exit' : 'Focus' }}
            </button>
          </div>
        </div>

        <!-- Editor panel -->
        <div class="relative flex-1 bg-background">
          <div v-show="codeTab === 'head'" class="absolute inset-0 flex flex-col">
            <div class="flex-none select-none border-b border-sidebar-border bg-sidebar px-3 py-1 font-mono text-xs text-muted-foreground">
              &lt;head&gt;
            </div>
            <div class="relative min-h-0 flex-1">
              <Codemirror
                :key="'head-' + editorKey"
                v-model="headValue"
                class="absolute inset-0"
                :indent-with-tab="true"
                :tab-size="2"
                :extensions="headExtensions"
                placeholder="Enter <head> content here… e.g. <link> tags for fonts or icon libraries."
              />
            </div>
            <div class="flex-none select-none border-t border-sidebar-border bg-sidebar px-3 py-1 font-mono text-xs text-muted-foreground">
              &lt;/head&gt;
            </div>
          </div>
          <div v-show="codeTab === 'body'" class="absolute inset-0 flex flex-col">
            <div class="flex-none select-none border-b border-sidebar-border bg-sidebar px-3 py-1 font-mono text-xs text-muted-foreground">
              &lt;body&gt;
            </div>
            <div class="relative min-h-0 flex-1">
              <Codemirror
                :key="'body-' + editorKey"
                v-model="htmlValue"
                class="absolute inset-0"
                :autofocus="true"
                :indent-with-tab="true"
                :tab-size="2"
                :extensions="htmlExtensions"
                placeholder="Enter your <body> contents here. No need to add the body tags, your overlay will render directly under <body>. Tailwind 3 tags are supported."
              />
            </div>
            <div class="flex-none select-none border-t border-sidebar-border bg-sidebar px-3 py-1 font-mono text-xs text-muted-foreground">
              &lt;/body&gt;
            </div>
          </div>
          <div v-show="codeTab === 'css'" class="absolute inset-0 flex flex-col">
            <div class="flex-none select-none border-b border-sidebar-border bg-sidebar font-mono text-xs">
              <div class="px-3 pt-1 text-muted-foreground">&lt;style&gt;</div>
              <div class="px-3 pb-1 italic text-muted-foreground/70">/* Your overlay renders directly as a child of &lt;body&gt; - flex and grid on body flow right through to your top-level elements. No need to dig up 3 wrappers. */</div>
            </div>
            <div class="relative min-h-0 flex-1">
              <Codemirror
                :key="'css-' + editorKey"
                v-model="cssValue"
                class="absolute inset-0"
                :indent-with-tab="true"
                :tab-size="2"
                :extensions="cssExtensions"
                placeholder="Enter your CSS styles here. Note: your overlay will render directly as a child element of <body>, which makes flexbox styling a breeze"
              />
            </div>
            <div class="flex-none select-none border-t border-sidebar-border bg-sidebar px-3 py-1 font-mono text-xs text-muted-foreground">
              &lt;/style&gt;
            </div>
          </div>
          <!-- Tailwind info panel -->
          <div
            v-show="codeTab === 'tailwind'"
            class="absolute inset-0 overflow-auto p-6 text-sm text-foreground"
          >
            <div class="mx-auto max-w-2xl space-y-4">
              <div class="flex items-center gap-3">
                <Wind class="size-6 text-sky-500 dark:text-sky-400" />
                <h3 class="text-lg font-semibold">Overlabels supports Tailwind 3 CSS classes</h3>
              </div>

              <p>
                Overlabels compiles your template's utility classes into a minimal stylesheet when you save.
                Drop Tailwind 3 classes directly into your BODY HTML and they'll just work on the live overlay.
              </p>

              <div class="rounded border border-sidebar-border bg-sidebar p-3 font-mono text-xs">
                &lt;div class="flex items-center gap-3 bg-purple-600 text-white p-4 rounded-lg"&gt;...&lt;/div&gt;
              </div>
              <h3 class="font-semibold text-lg mb-1 mt-4">How it works</h3>
              <p>
                The compiler scans your overlay code at save time, emits only the rules your classes actually
                use, and stores the result with the template. The overlay renderer injects this compiled CSS
                <em>before</em> your own CSS tab - so anything you write by hand in CSS wins on conflicts.
              </p>

              <div class="rounded border-l-4 border-yellow-500 bg-yellow-500/10 p-3 my-6">
                <p class="font-semibold text-yellow-700 dark:text-yellow-300">Preview caveat</p>
                <p class="mt-1 text-yellow-800 dark:text-yellow-200">
                  Tailwind classes do <strong>not</strong> render in the Ctrl+P preview modal or at
                  <code class="rounded bg-sidebar px-1">/preview/{slug}</code>. Those previews only inline the
                  CSS tab and sample-substituted HTML - the compile step runs on save, so you only see the
                  utility classes paint on an authenticated overlay URL
                  (<code class="rounded bg-sidebar px-1">/overlay/{slug}#token</code>).
                </p>
              </div>

              <div class="my-4">
                <h3 class="font-semibold text-lg">Dynamic class names</h3>
                <p class="mt-1">
                  Classes computed at render time via tags
                  (e.g. <code class="rounded bg-sidebar px-1">class="text-[[[c:color]]]"</code>) won't get CSS
                  generated, because the compiler only sees the pre-substitution template source. For those,
                  hand-write the rules in the CSS tab.
                </p>
              </div>

              <div class="h-px w-full bg-sidebar-border" />

              <p class="text-muted-foreground">
                Powered by <a href="https://unocss.dev/" target="_blank" rel="noopener" class="cursor-pointer underline hover:text-foreground">UnoCSS</a>
                with <code class="rounded bg-sidebar px-1">presetWind3</code>. Most Tailwind v3 utilities,
                arbitrary values, and gradient helpers compile identically. Found a Tailwind v3 class that doesn't parse? Let me know! Drop an email
                to <a href="mailto:jasper@emailjasper.com" class="cursor-pointer underline hover:text-foreground">jasper@emailjasper.com</a>. Thanks!
              </p>
            </div>
          </div>

          <!-- Add to OBS panel -->
          <div
            v-show="codeTab === 'add-to-obs' && props.templateType === 'static'"
            v-if="props.template"
            class="absolute inset-0 overflow-auto p-6 text-sm text-foreground"
          >
            <div class="mx-auto max-w-2xl space-y-4">
              <div class="flex items-center gap-3">
                <Video class="size-6 text-violet-500 dark:text-violet-400" />
                <h3 class="text-lg font-semibold">Add this overlay to OBS</h3>
              </div>
              <p v-if="props.templateType === 'static'">
                Add this overlay to OBS by clicking the button below and copying the exact URL to your OBS as a browser source.
              </p>

              <div>
                <AddToObsButton ref="obsButton" :template="props.template" />
              </div>
            </div>
          </div>


          <!-- Fullscreen hint bar -->
          <div v-if="isFullscreen" class="absolute bottom-0 left-0 right-0 flex items-center justify-center border-t border-border bg-sidebar/80 py-1.5 text-[11px] text-muted-foreground">
            <kbd class="border rounded px-1 py-0.5 text-[10px]">Ctrl</kbd>+<kbd class="border rounded px-1 py-0.5 text-[10px]">Shift</kbd>+<kbd class="border rounded px-1 py-0.5 text-[10px]">F</kbd> or <kbd class="border rounded px-1 py-0.5 text-[10px] ml-1">Esc</kbd> to exit
          </div>
        </div>
      </div>
    </div>

    <!-- Expand / shortcuts row (hidden in fullscreen) -->
    <div v-if="!isFullscreen" class="flex justify-between">
      <button
        type="button"
        @click="isExpanded = !isExpanded"
        class="flex cursor-pointer items-center gap-1 p-2 text-sm text-muted-foreground hover:text-accent-foreground"
      >
        <ChevronUp v-if="isExpanded" class="h-4 w-4" />
        <ChevronDown v-else class="h-4 w-4" />
        {{ isExpanded ? 'Collapse editor' : 'Expand editor' }}
      </button>
    </div>
  </div>
</template>
