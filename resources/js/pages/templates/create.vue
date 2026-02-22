<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import Modal from '@/components/Modal.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import TemplateTagsList from '@/components/TemplateTagsList.vue';
import {
  Brackets,
  Code,
  InfoIcon,
  Palette,
  Save,
  ExternalLink,
  Keyboard,
  ChevronUp,
  ChevronDown,
  Zap,
  Layout,
  FileCode2,
} from 'lucide-vue-next';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { stripScriptsFromFields } from '@/utils/sanitize';

const form = useForm({
  name: '',
  description: '',
  head: '',
  html: '',
  css: '',
  type: 'static',
  is_public: true,
});

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Create New Overlay', href: '/templates/create' },
];

const isDark = ref(document.documentElement.classList.contains('dark'));
const showPreview = ref(false);
const previewHtml = ref('');
const tallEditor = ref(true);

const htmlExtensions = computed(() => [
  html(),
  EditorView.theme({
    '&': { fontSize: '14px' },
    '.cm-content': { padding: '16px' },
    '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' },
  }),
  ...(isDark.value ? [oneDark] : []),
]);

const cssExtensions = computed(() => [
  css(),
  EditorView.theme({
    '&': { fontSize: '14px' },
    '.cm-content': { padding: '16px' },
    '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' },
  }),
  ...(isDark.value ? [oneDark] : []),
]);

const mainTabs = [
  { key: 'meta', label: 'Meta', icon: InfoIcon },
  { key: 'code', label: 'Code', icon: Code },
  { key: 'tags', label: 'Tags', icon: Brackets },
] as const;

const editorTabs = [
  { key: 'head', label: 'HEAD', icon: FileCode2, color: 'text-pink-500 dark:text-pink-400' },
  { key: 'html', label: 'HTML', icon: Code, color: 'text-cyan-500 dark:text-cyan-400' },
  { key: 'css', label: 'CSS', icon: Palette, color: 'text-lime-500 dark:text-lime-400' },
] as const;

const mainTab = ref<'meta' | 'code' | 'tags'>('meta');
const codeTab = ref<'head' | 'html' | 'css'>('html');

const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

const showKeyboardShortcuts = ref(false);
const { register, getAllShortcuts } = useKeyboardShortcuts();

const submitForm = () => {
  const { sanitized, removed } = stripScriptsFromFields({
    name: form.name,
    description: form.description,
    head: form.head,
    html: form.html,
    css: form.css,
  });
  Object.assign(form, sanitized);

  if (removed > 0) {
    toastMessage.value = `Also removed ${removed} script tag${removed === 1 ? '' : 's'} — inline scripts aren't supported.`;
    toastType.value = 'warning';
    showToast.value = true;
  }

  form.post(route('templates.store'));
};

const previewTemplate = (): void => {
  const sampleData: Record<string, string> = {
    user_name: 'SampleStreamer',
    user_follower_count: '1,234',
    user_view_count: '45,678',
    stream_title: 'Playing an awesome game!',
    stream_game_name: 'Just Chatting',
    stream_viewer_count: '567',
    user_broadcaster_type: 'affiliate',
    channel_subscription_count: '123',
  };

  let htmlContent = form.html;
  let cssContent = form.css;
  Object.entries(sampleData).forEach(([tag, value]) => {
    const tagPattern = new RegExp(`\\[\\[\\[${tag}]]]`, 'g');
    htmlContent = htmlContent.replace(tagPattern, value);
    cssContent = cssContent.replace(tagPattern, value);
  });

  previewHtml.value = `<!DOCTYPE html>
<html lang="en">
  <head><style>${cssContent}</style>${form.head}</head>
  <body>${htmlContent}</body>
</html>`;
  showPreview.value = true;
};

onMounted(() => {
  register('save-overlay', 'ctrl+s', () => submitForm(), { description: 'Create overlay' });
  register('preview-overlay', 'ctrl+p', () => previewTemplate(), { description: 'Preview overlay' });
  register('toggle-shortcuts', 'ctrl+k', () => {
    showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
  }, { description: 'Show keyboard shortcuts' });
});

const keyboardShortcutsList = computed(() => getAllShortcuts());

watch(
  () => document.documentElement.classList.contains('dark'),
  (newDark) => { isDark.value = newDark; },
);
</script>

<template>
  <Head title="Create Overlay" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">

      <!-- Header -->
      <div class="mb-6 flex items-start justify-between">
        <Heading title="New Overlay" description="Build your overlay with HTML, CSS, and Tags." description-class="text-sm text-muted-foreground" />
        <div class="flex shrink-0 items-center gap-2">
          <button type="button" @click="previewTemplate" class="btn btn-cancel">
            Preview <ExternalLink class="ml-2 h-4 w-4" />
          </button>
          <button @click="submitForm" :disabled="form.processing" class="btn btn-primary">
            <Save class="mr-2 h-4 w-4" />
            Create Overlay
          </button>
        </div>
      </div>

      <form @submit.prevent="submitForm">

        <!-- Tab bar -->
        <div class="rounded-sm rounded-b-none border border-b-0 border-sidebar bg-sidebar-accent">
          <div class="flex border-b border-violet-600 dark:border-violet-400">
            <button
              v-for="(tab, index) in mainTabs"
              :key="tab.key"
              type="button"
              @click="mainTab = tab.key"
              :class="[
                'flex cursor-pointer items-center gap-1.5 px-5 py-2.5 text-sm font-medium transition-colors hover:bg-background',
                index === 0 && 'rounded-tl-sm',
                mainTab === tab.key ? 'bg-violet-600 text-accent dark:bg-violet-400' : 'text-accent-foreground',
              ]"
            >
              <component :is="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Content box -->
        <div class="rounded-b-sm border border-t-0 border-sidebar bg-sidebar-accent p-4">

          <!-- Meta Tab -->
          <div v-if="mainTab === 'meta'" class="max-w-2xl space-y-5">
            <div>
              <label for="name" class="mb-1 block text-sm font-medium text-accent-foreground/50">Overlay Name *</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                class="w-full rounded border border-sidebar p-2 transition hover:shadow-sm"
                placeholder="My Awesome Overlay"
                required
                autofocus
                data-1p-ignore
              />
              <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
            </div>

            <div>
              <label for="description" class="mb-1 block text-sm font-medium text-accent-foreground/50">Description</label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="w-full rounded border border-sidebar p-2 transition hover:shadow-sm"
                placeholder="Describe what your overlay does…"
              />
              <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
            </div>

            <!-- Overlay Type -->
            <div>
              <label class="mb-2 block text-sm font-medium text-accent-foreground/50">Overlay Type *</label>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label
                  class="relative flex cursor-pointer items-start rounded-sm border p-4 transition-all hover:bg-background"
                  :class="form.type === 'static' ? 'border-violet-400 bg-violet-400/10 dark:bg-violet-400/5' : 'border-sidebar'"
                >
                  <input v-model="form.type" type="radio" value="static" class="sr-only" required />
                  <div class="flex items-start">
                    <div
                      class="mr-3 mt-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2"
                      :class="form.type === 'static' ? 'border-violet-500 bg-violet-500' : 'border-gray-400'"
                    >
                      <div v-if="form.type === 'static'" class="h-2 w-2 rounded-full bg-white" />
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <Layout class="h-4 w-4" />
                        <span class="text-sm font-medium">Static Overlay</span>
                      </div>
                      <p class="mt-1 text-sm text-muted-foreground">
                        Persistent content with live Twitch data (follower count, stream title, etc.)
                      </p>
                    </div>
                  </div>
                </label>

                <label
                  class="relative flex cursor-pointer items-start rounded-sm border p-4 transition-all hover:bg-background"
                  :class="form.type === 'alert' ? 'border-violet-500 bg-violet-500/10 dark:bg-violet-500/5' : 'border-sidebar'"
                >
                  <input v-model="form.type" type="radio" value="alert" class="sr-only" required />
                  <div class="flex items-start">
                    <div
                      class="mr-3 mt-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2"
                      :class="form.type === 'alert' ? 'border-violet-500 bg-violet-500' : 'border-gray-400'"
                    >
                      <div v-if="form.type === 'alert'" class="h-2 w-2 rounded-full bg-white" />
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <Zap class="h-4 w-4" />
                        <span class="text-sm font-medium">Event Alert</span>
                      </div>
                      <p class="mt-1 text-sm text-muted-foreground">
                        Shows temporarily when events occur (new follower, subscription, raid, etc.)
                      </p>
                    </div>
                  </div>
                </label>
              </div>
              <div v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</div>
            </div>

            <!-- Event alert tips -->
            <div v-if="form.type === 'alert'" class="rounded-sm bg-sidebar p-4 text-sm">
              <strong class="text-accent-foreground/70">Event Alert tips:</strong>
              <ul class="mt-2 list-inside list-disc space-y-1 text-muted-foreground">
                <li>Visit the <a class="text-violet-400 hover:underline" href="/help" target="_blank">Help docs</a> for all event-based tags.</li>
                <li>Only <code class="rounded bg-sidebar-accent px-1">channel.raid</code> exposes <code class="rounded bg-sidebar-accent px-1">event.from_broadcaster_user_name</code>.</li>
                <li>Mix event tags with regular tags like <code class="rounded bg-sidebar-accent px-1">[[[followers_total]]]</code>.</li>
                <li>Keep alert overlays simple — they display briefly on screen.</li>
              </ul>
            </div>

            <div>
              <label class="flex items-center gap-2">
                <input
                  v-model="form.is_public"
                  type="checkbox"
                  class="rounded border-gray-300 text-violet-600 shadow-sm focus:border-violet-500 focus:ring-violet-500"
                />
                <span class="text-sm">Make this overlay public (others can view and fork it)</span>
              </label>
            </div>
          </div>

          <!-- Code Tab -->
          <div v-if="mainTab === 'code'">
            <div class="overflow-hidden rounded-sm border border-border" :style="{ height: tallEditor ? '500px' : '800px' }">
              <div class="flex h-full">
                <!-- Vertical file tabs -->
                <div class="flex flex-col border-r border-border bg-sidebar text-sidebar-foreground">
                  <button
                    v-for="tab in editorTabs"
                    :key="tab.key"
                    type="button"
                    @click="codeTab = tab.key"
                    :class="[
                      'flex cursor-pointer items-center gap-1.5 px-5 py-3 text-left text-xs uppercase transition-colors',
                      codeTab === tab.key
                        ? 'bg-background text-accent-foreground'
                        : 'text-sidebar-foreground/60 hover:bg-background/40 hover:text-sidebar-foreground',
                    ]"
                  >
                    <component :is="tab.icon" :class="tab.color" class="size-3.5" />
                    {{ tab.label }}
                  </button>
                </div>
                <!-- Editor panel -->
                <div class="relative flex-1 overflow-hidden bg-background">
                  <textarea
                    v-show="codeTab === 'head'"
                    v-model="form.head"
                    class="font-mono h-full w-full resize-none bg-background p-4 text-sm text-foreground outline-none"
                    placeholder="Enter <head> content here… e.g. <link> tags for fonts or icon libraries."
                  />
                  <Codemirror
                    v-show="codeTab === 'html'"
                    v-model="form.html"
                    class="h-full"
                    :autofocus="true"
                    :indent-with-tab="true"
                    :tab-size="2"
                    :extensions="htmlExtensions"
                    placeholder="Enter your HTML here… Use [[[tag_name]]] for dynamic content"
                  />
                  <Codemirror
                    v-show="codeTab === 'css'"
                    v-model="form.css"
                    class="h-full"
                    :indent-with-tab="true"
                    :tab-size="2"
                    :extensions="cssExtensions"
                    placeholder="Enter your CSS styles here…"
                  />
                </div>
              </div>
            </div>

            <!-- Toggle + shortcuts -->
            <div class="mt-3 flex justify-between">
              <button
                type="button"
                @click="tallEditor = !tallEditor"
                class="flex cursor-pointer items-center gap-1 text-sm text-muted-foreground hover:text-accent-foreground"
              >
                <ChevronDown v-if="tallEditor" class="h-4 w-4" />
                <ChevronUp v-else class="h-4 w-4" />
                {{ tallEditor ? 'Expand editor' : 'Collapse editor' }}
              </button>
              <a
                @click.prevent="showKeyboardShortcuts = !showKeyboardShortcuts"
                href="#"
                class="flex cursor-pointer items-center text-sm text-muted-foreground hover:text-accent-foreground"
              >
                <Keyboard class="mr-2 h-4 w-4" />
                Keyboard Shortcuts
              </a>
            </div>
          </div>

          <!-- Tags Tab -->
          <div v-if="mainTab === 'tags'">
            <TemplateTagsList />
          </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-6 flex justify-between">
          <Link :href="route('dashboard.index')" class="btn btn-cancel">← Back to Dashboard</Link>
          <button type="submit" :disabled="form.processing" class="btn btn-primary">Create Overlay</button>
        </div>
      </form>
    </div>

    <!-- Preview Modal -->
    <Modal :show="showPreview" @close="showPreview = false" max-width="4xl">
      <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-foreground">Overlay Preview</h3>
          <button @click="showPreview = false" class="rounded-full p-1 hover:bg-sidebar-accent">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" style="fill: currentColor">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
        <div class="rounded-sm border border-border bg-muted" style="height: 400px;">
          <iframe v-if="previewHtml" :srcdoc="previewHtml" class="h-full w-full border-0" sandbox="allow-scripts" />
        </div>
        <p class="mt-4 text-sm text-muted-foreground">Tags are shown with sample data in preview.</p>
      </div>
    </Modal>

    <!-- Keyboard Shortcuts Dialog -->
    <div
      v-if="showKeyboardShortcuts"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="showKeyboardShortcuts = false"
    >
      <div class="w-full max-w-md overflow-hidden rounded-lg border border-sidebar bg-sidebar-accent p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-medium">Keyboard Shortcuts</h3>
          <button @click.prevent="showKeyboardShortcuts = false" class="rounded-full p-1 hover:bg-background">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" style="fill: currentColor">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
        <div class="space-y-2">
          <div v-for="shortcut in keyboardShortcutsList" :key="shortcut.id" class="flex items-center justify-between rounded-md border p-2 text-sm">
            <span>{{ shortcut.description }}</span>
            <kbd class="rounded bg-sidebar px-2 py-1 font-mono text-xs">{{ shortcut.keys }}</kbd>
          </div>
          <p class="mt-4 text-xs text-muted-foreground">
            Press <kbd class="rounded bg-sidebar px-1">Ctrl+K</kbd> to toggle this dialog.<br /><br />
            Shortcuts don't work when focused inside the code editor — click outside first.
          </p>
        </div>
      </div>
    </div>

    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />
  </AppLayout>
</template>
