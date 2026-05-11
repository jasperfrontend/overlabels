<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { VisuallyHidden } from 'reka-ui';
import {
  Check,
  ChevronDown,
  CodeIcon,
  Eye,
  FileCode2Icon,
  GitFork,
  ImageOff,
  PaletteIcon,
} from 'lucide-vue-next';
import type { AppPageProps } from '@/types';

interface OwnerInfo {
  name: string;
  avatar: string | null;
}

interface PreviewTemplate {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: 'static' | 'alert';
  head: string | null;
  html: string | null;
  css: string | null;
  screenshot_url: string | null;
  view_count: number;
  fork_count: number;
  created_at: string;
  owner: OwnerInfo | null;
}

const props = defineProps<{
  template: PreviewTemplate;
}>();

const page = usePage<AppPageProps>();
const isAuthed = computed(() => !!page.props.auth?.user);

const csrf = computed(() => {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
});

type CopyKind = 'head' | 'body' | 'css' | 'full';

const copyFeedback = ref<string>('');
let feedbackTimer: ReturnType<typeof setTimeout> | null = null;

function confirmCopy(event: Event) {
  if (!window.confirm('Copy this overlay to your account?')) {
    event.preventDefault();
  }
}

function buildCompleteTemplate(): string {
  const head = props.template.head ?? '';
  const html = props.template.html ?? '';
  const css = props.template.css ?? '';
  const title = props.template.name ?? 'Overlay';

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${title}</title>
  <style>
${css}
  </style>
${head}
</head>
<body>
${html}
</body>
</html>
`;
}

const copyLabels: Record<CopyKind, string> = {
  head: 'HEAD',
  body: 'BODY',
  css: 'CSS',
  full: 'Complete template',
};

function copy(kind: CopyKind) {
  let value = '';
  switch (kind) {
    case 'head':
      value = props.template.head ?? '';
      break;
    case 'body':
      value = props.template.html ?? '';
      break;
    case 'css':
      value = props.template.css ?? '';
      break;
    case 'full':
      value = buildCompleteTemplate();
      break;
  }

  navigator.clipboard.writeText(value).then(() => {
    copyFeedback.value = `Copied ${copyLabels[kind]}!`;
    if (feedbackTimer) clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => {
      copyFeedback.value = '';
    }, 1500);
  });
}

const showScreenshot = ref(false);
function openScreenshot() {
  if (!props.template.screenshot_url) return;
  showScreenshot.value = true;
}

type SourceTab = 'head' | 'html' | 'css';

const sourceTabs: Array<{ key: SourceTab; label: string; icon: typeof FileCode2Icon; color: string }> = [
  { key: 'head', label: 'HEAD', icon: FileCode2Icon, color: 'text-pink-500 dark:text-pink-400' },
  { key: 'html', label: 'BODY', icon: CodeIcon, color: 'text-cyan-500 dark:text-cyan-400' },
  { key: 'css', label: 'CSS', icon: PaletteIcon, color: 'text-lime-500 dark:text-lime-400' },
];

const activeSourceTab = ref<SourceTab>('html');

const activeSource = computed(() => {
  const v = props.template[activeSourceTab.value];
  return typeof v === 'string' && v.length > 0 ? v : '';
});
</script>

<template>
  <Head :title="`${template.name} - Public Preview`" />
  <div class="min-h-screen bg-background text-foreground">
    <div class="mx-auto max-w-450 p-4 lg:p-6">
      <!-- Slim brand strip -->
      <div class="mb-4 flex items-center justify-between">
        <Link href="/" class="flex items-center gap-2 text-sm font-bold tracking-tight text-foreground hover:text-violet-400">
          <img src="/favicon-light.svg" alt="" class="h-6 w-6 dark:hidden" />
          <img src="/favicon.png" alt="" class="hidden h-6 w-6 dark:block" />
          Overlabels
        </Link>
        <Link
          v-if="isAuthed"
          :href="route('dashboard.index')"
          class="text-sm text-violet-400 hover:underline"
        >
          Dashboard
        </Link>
      </div>

      <!-- Top header strip -->
      <div class="flex flex-wrap items-center justify-between gap-3 border border-sidebar-border bg-sidebar px-4 py-3">
        <div class="flex items-center gap-3 min-w-0">
          <img
            v-if="template.owner?.avatar"
            :src="template.owner.avatar"
            alt=""
            class="h-9 w-9 shrink-0 object-cover"
          />
          <div class="min-w-0">
            <div class="truncate text-base font-medium text-foreground">{{ template.name }}</div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-foreground">
              <span>
                by <span class="text-violet-400">{{ template.owner?.name ?? 'Anonymous' }}</span>
              </span>
              <span class="inline-flex items-center gap-1">
                <Eye class="h-3.5 w-3.5 text-violet-400" />
                {{ template.view_count }}
              </span>
              <span class="inline-flex items-center gap-1">
                <GitFork class="h-3.5 w-3.5 text-violet-400" />
                {{ template.fork_count }}
              </span>
              <span class="border border-sidebar-border bg-card px-1.5 py-0.5 text-xs uppercase text-violet-400">
                {{ template.type }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button type="button" class="ovl-btn cursor-pointer">
                <Check v-if="copyFeedback" class="h-3.5 w-3.5 text-violet-400" />
                <span>{{ copyFeedback || 'Copy...' }}</span>
                <ChevronDown v-if="!copyFeedback" class="h-3.5 w-3.5" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56 rounded-none border-sidebar-border bg-card">
              <DropdownMenuLabel class="text-xs uppercase tracking-wider text-violet-400">
                Copy snippet
              </DropdownMenuLabel>
              <DropdownMenuItem class="cursor-pointer rounded-none focus:bg-sidebar focus:text-violet-400" @click="copy('head')">
                HEAD
                <span class="ml-auto text-xs text-foreground">&lt;head&gt;</span>
              </DropdownMenuItem>
              <DropdownMenuItem class="cursor-pointer rounded-none focus:bg-sidebar focus:text-violet-400" @click="copy('body')">
                BODY
                <span class="ml-auto text-xs text-foreground">&lt;body&gt;</span>
              </DropdownMenuItem>
              <DropdownMenuItem class="cursor-pointer rounded-none focus:bg-sidebar focus:text-violet-400" @click="copy('css')">
                CSS
                <span class="ml-auto text-xs text-foreground">&lt;style&gt;</span>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem class="cursor-pointer rounded-none focus:bg-sidebar focus:text-violet-400" @click="copy('full')">
                Complete template
                <span class="ml-auto text-xs text-foreground">.html</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <button
            v-if="template.screenshot_url"
            type="button"
            class="ovl-btn cursor-pointer"
            @click="openScreenshot"
          >
            Screenshot
          </button>

          <form
            v-if="isAuthed"
            :action="route('templates.fork', template.id)"
            method="POST"
            class="inline"
            @submit="confirmCopy"
          >
            <input type="hidden" name="_token" :value="csrf" />
            <button type="submit" class="ovl-btn-copy cursor-pointer">Copy</button>
          </form>
          <a v-else href="/auth/redirect/twitch" class="ovl-btn-copy cursor-pointer">Log in to copy</a>
        </div>
      </div>

      <!-- 2-column body -->
      <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <!-- Left: screenshot in 16/9 -->
        <div class="border border-sidebar-border bg-card">
          <div class="aspect-video w-full overflow-hidden bg-sidebar">
            <img
              v-if="template.screenshot_url"
              :src="template.screenshot_url"
              :alt="`${template.name} screenshot`"
              class="h-full w-full cursor-pointer object-cover transition-opacity hover:opacity-90"
              @click="openScreenshot"
            />
            <div v-else class="flex h-full w-full flex-col items-center justify-center px-6 text-center">
              <ImageOff class="mb-3 h-10 w-10 text-violet-400" />
              <p class="text-sm text-foreground">No screenshot yet</p>
              <p class="mt-1 text-xs text-foreground">
                The owner hasn't added a screenshot for this overlay.
              </p>
            </div>
          </div>
          <div class="flex items-center justify-between border-t border-sidebar-border px-4 py-2.5 text-sm text-foreground">
            <span class="text-violet-400">Screenshot</span>
          </div>
        </div>

        <!-- Right: raw source viewer with HEAD / BODY / CSS tabs -->
        <div class="border border-sidebar-border bg-card">
          <div class="flex aspect-video w-full overflow-hidden">
            <!-- Vertical tab strip -->
            <div class="flex flex-col bg-sidebar text-sidebar-foreground">
              <button
                v-for="tab in sourceTabs"
                :key="tab.key"
                type="button"
                @click="activeSourceTab = tab.key"
                :class="[
                  'flex cursor-pointer items-center gap-1.5 px-5 py-3 text-left text-xs uppercase tracking-wider transition-colors',
                  activeSourceTab === tab.key
                    ? 'bg-[#f8f8f8] dark:bg-[#160e21] text-foreground'
                    : 'text-foreground hover:bg-background/40 hover:text-violet-400',
                ]"
              >
                <component :is="tab.icon" :class="tab.color" class="h-3.5 w-3.5" />
                <span>{{ tab.label }}</span>
              </button>
              <div class="flex-1 bg-sidebar" />
            </div>
            <!-- Source panel -->
            <div class="relative flex-1 overflow-hidden">
              <pre
                class="h-full w-full overflow-auto bg-white p-4 text-xs leading-relaxed text-gray-700 dark:bg-[#160e21] dark:text-accent-foreground"
              ><code v-if="activeSource">{{ activeSource }}</code><code v-else class="text-foreground">// no {{ activeSourceTab.toUpperCase() }} content</code></pre>
            </div>
          </div>
          <div class="flex items-center justify-between border-t border-sidebar-border px-4 py-2.5 text-sm text-foreground">
            <span class="text-violet-400">Source</span>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div
        v-if="template.description"
        class="mt-4 border border-sidebar-border bg-card p-4 text-sm text-foreground whitespace-pre-wrap"
      >
        {{ template.description }}
      </div>

      <!-- Fullscreen screenshot dialog -->
      <Dialog :open="showScreenshot" @update:open="showScreenshot = $event">
        <DialogContent class="max-w-[95vw] max-h-[95vh] w-auto p-2 sm:max-w-[95vw]">
          <VisuallyHidden>
            <DialogTitle>Screenshot preview</DialogTitle>
          </VisuallyHidden>
          <img
            v-if="template.screenshot_url"
            :src="template.screenshot_url"
            :alt="`${template.name} screenshot`"
            class="max-h-[80vh] max-w-[90vw] object-contain"
          />
          <DialogFooter>
            <div class="flex w-full items-center justify-between gap-2">
              <div class="text-sm text-foreground">
                Screenshot: <span class="text-violet-400">{{ template.name }}</span>
              </div>
              <button type="button" class="ovl-btn cursor-pointer" @click="showScreenshot = false">Close</button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>

<style scoped>
.ovl-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  border: 1px solid var(--sidebar-border);
  background: var(--card);
  color: var(--foreground);
  transition: color 120ms ease, background 120ms ease, border-color 120ms ease;
}

.ovl-btn:hover {
  color: var(--color-violet-400, #a78bfa);
  border-color: var(--color-violet-400, #a78bfa);
}

.ovl-btn-copy {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.375rem 0.85rem;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  border: 1px solid var(--color-violet-400, #a78bfa);
  background: var(--color-violet-400, #a78bfa);
  color: #000;
  transition: background 120ms ease, border-color 120ms ease;
}

.ovl-btn-copy:hover {
  background: var(--color-violet-300, #c4b5fd);
  border-color: var(--color-violet-300, #c4b5fd);
}
</style>
