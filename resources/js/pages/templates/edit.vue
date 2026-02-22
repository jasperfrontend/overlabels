<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import type { OverlayControl } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
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
  RefreshCcwDot,
  Save,
  ExternalLink,
  Split,
  Trash,
  CircleAlert,
  Keyboard,
  ChevronUp,
  ChevronDown,
  FileCode2,
  SlidersHorizontal,
  CopyIcon,
} from 'lucide-vue-next';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { stripScriptsFromFields } from '@/utils/sanitize';
import { useLinkWarning } from '@/composables/useLinkWarning';
import { useTemplateActions } from '@/composables/useTemplateActions';
import TooltipBase from '@/components/TooltipBase.vue';

interface TemplateTag {
  display_tag: string;
  description: string;
  category?: string;
}

interface Props {
  existingTemplate: { head: string; html: string; css: string };
  template: {
    id: number;
    name: string;
    description: string;
    head: string;
    html: string;
    css: string;
    is_public: boolean;
    slug: string;
    created_at: string;
    updated_at: string;
    view_count: number;
    fork_count: number;
    template_tags: TemplateTag[] | null;
  };
  availableTags: Array<{
    tag_name: string;
    display_name: string;
    description: string;
    data_type: string;
    category: string;
    sample_data?: string;
  }>;
  controls?: OverlayControl[];
}

const props = withDefaults(defineProps<Props>(), {
  existingTemplate: () => ({ head: '', html: '', css: '' }),
  availableTags: () => [],
  template: Object,
});

const isDark = ref(document.documentElement.classList.contains('dark'));
const { triggerLinkWarning } = useLinkWarning();

const {
  previewTemplate,
  forkTemplate,
  deleteTemplate,
  toastMessage: templateToastMessage,
  toastType: templateToastType,
  showToast: showTemplateToast,
} = useTemplateActions(props.template);

const form = useForm({
  name: props?.template?.name,
  description: props?.template?.description || '',
  head: props?.template?.head || '',
  html: props?.template?.html || '',
  css: props?.template?.css || '',
  is_public: props?.template?.is_public,
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Editing: ' + props.template.name,
    href: route('templates.edit', props.template),
  },
];

const mainTabs = [
  { key: 'code', label: 'Code', icon: Code },
  { key: 'meta', label: 'Meta', icon: InfoIcon },
  { key: 'tags', label: 'Tags', icon: Brackets },
  { key: 'controls', label: 'Controls', icon: SlidersHorizontal },
] as const;

const editorTabs = [
  { key: 'head', label: 'HEAD', icon: FileCode2, color: 'text-pink-500 dark:text-pink-400' },
  { key: 'html', label: 'HTML', icon: Code, color: 'text-cyan-500 dark:text-cyan-400' },
  { key: 'css', label: 'CSS', icon: Palette, color: 'text-lime-500 dark:text-lime-400' },
] as const;

const mainTab = ref<'code' | 'meta' | 'tags' | 'controls'>('code');
const codeTab = ref<'head' | 'html' | 'css'>('html');
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

const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

const openExternalLink = (link: any, target: string) => window.open(link, target);

const copySnippet = (snippet: string) => {
  navigator.clipboard.writeText(snippet);
  showToast.value = false;
  toastMessage.value = `${snippet} copied to clipboard!`;
  toastType.value = 'success';
  showToast.value = true;
};

const submitForm = () => {
  const { sanitized, removed } = stripScriptsFromFields({
    name: form.name,
    description: form.description,
    head: form.head,
    html: form.html,
    css: form.css,
  });
  Object.assign(form, sanitized);

  form.put(route('templates.update', props.template), {
    preserveScroll: true,
    onSuccess: () => {
      showToast.value = false;
      toastMessage.value = removed > 0
        ? `Saved! Also removed ${removed} script tag${removed === 1 ? '' : 's'} — inline scripts aren't supported.`
        : 'Overlay saved successfully!';
      toastType.value = removed > 0 ? 'warning' : 'success';
      showToast.value = true;
    },
    onError: () => {
      showToast.value = false;
      toastMessage.value = 'Failed to save overlay.';
      toastType.value = 'error';
      showToast.value = true;
    },
  });
};

watch(
  () => document.documentElement.classList.contains('dark'),
  (newDark) => { isDark.value = newDark; },
);

const { register, getAllShortcuts } = useKeyboardShortcuts();
const showKeyboardShortcuts = ref(false);

onMounted(() => {
  register('save-template', 'ctrl+s', () => submitForm(), { description: 'Save overlay' });
  register('preview-live', 'ctrl+p', () => {
    triggerLinkWarning(
      () => openExternalLink(`/overlay/${props.template?.slug}/public`, '_blank'),
      'Remember: DO NOT EVER show the overlay link with your personal access #hash in the URL on stream! Treat it like a password.',
    );
  }, { description: 'Preview in new tab' });
  register('toggle-shortcuts', 'ctrl+k', () => {
    showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
  }, { description: 'Show keyboard shortcuts' });
});

const keyboardShortcutsList = computed(() => getAllShortcuts());
</script>

<template>
  <Head :title="`Editing: ${template.name}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">

      <!-- Header -->
      <div class="mb-6 flex items-start justify-between">
        <Heading :title="template.name" :description="template.description || 'No description set.'" description-class="text-sm text-muted-foreground" />
        <div class="flex shrink-0 items-center gap-2">
          <a v-if="template?.is_public" @click.prevent="previewTemplate" href="#" class="btn btn-cancel">
            Preview <ExternalLink class="ml-2 h-4 w-4" />
          </a>
          <TooltipBase v-else tt-content-class="tooltip-base tooltip-content" align="start" side="left">
            <template #trigger>
              <a @click.prevent="previewTemplate" href="#" class="btn btn-private">
                Preview <ExternalLink class="ml-2 h-4 w-4" />
              </a>
            </template>
            <template #content>
              <div class="space-y-1 text-sm">
                <div class="flex items-center space-x-2">
                  <CircleAlert class="mr-2 h-6 w-6 text-violet-400" />
                  <h3 class="text-xl font-bold">Don't forget</h3>
                </div>
                Add your token to the end of the URL like this:<br />
                <code class="text-violet-400/80">/overlay/your-template-slug/#YOUR_TOKEN_HERE</code>
              </div>
            </template>
          </TooltipBase>
          <button @click="deleteTemplate" class="btn btn-danger">Delete <Trash class="ml-2 h-4 w-4" /></button>
          <button @click="forkTemplate" class="btn btn-warning">Fork <Split class="ml-2 h-4 w-4" /></button>
          <button @click="submitForm" :disabled="form.processing || !form.isDirty" class="btn btn-primary">
            <RefreshCcwDot v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <Save v-else class="mr-2 h-4 w-4" />
            Save
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
                  <p class="mt-auto px-3 py-2 text-xs text-sidebar-foreground/30 uppercase">HEAD</p>
                  <p class="px-3 pb-2 text-[10px] text-sidebar-foreground/20 leading-tight">Use &lt;link&gt; tags only.<br />Scripts are stripped.</p>
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

            <!-- Toggle + shortcuts row -->
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

          <!-- Meta Tab -->
          <div v-if="mainTab === 'meta'" class="max-w-2xl space-y-4">
            <div>
              <label for="name" class="mb-1 block text-sm font-medium text-accent-foreground/50">Title *</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                class="w-full rounded border border-sidebar p-2 transition hover:shadow-sm"
                required
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
              />
              <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
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

            <div class="grid grid-cols-2 gap-4 rounded-sm bg-sidebar p-4 text-sm">
              <div>
                <span class="text-muted-foreground">Created:</span>
                <span class="ml-2">{{ new Date(template?.created_at).toLocaleDateString() }}</span>
              </div>
              <div>
                <span class="text-muted-foreground">Last updated:</span>
                <span class="ml-2">{{ new Date(template?.updated_at).toLocaleDateString() }}</span>
              </div>
              <div>
                <span class="text-muted-foreground">Views:</span>
                <span class="ml-2">{{ template?.view_count }}</span>
              </div>
              <div>
                <span class="text-muted-foreground">Forks:</span>
                <span class="ml-2">{{ template?.fork_count }}</span>
              </div>
            </div>

            <div v-if="template?.template_tags && template.template_tags.length > 0" class="rounded-sm bg-sidebar p-4 text-sm">
              <p class="mb-2 text-muted-foreground">Tags in use:</p>
              <div class="flex flex-wrap gap-1">
                <code v-for="tag in template.template_tags" :key="String(tag)" class="rounded bg-sidebar-accent px-2 py-1 text-xs">
                  [[[{{ tag }}]]]
                </code>
              </div>
            </div>
          </div>

          <!-- Tags Tab -->
          <div v-if="mainTab === 'tags'">
            <TemplateTagsList />
          </div>

          <!-- Controls Tab -->
          <div v-if="mainTab === 'controls'">
            <div v-if="!controls || controls.length === 0" class="py-8 text-center text-sm text-muted-foreground">
              No controls defined for this overlay yet.
              <a :href="route('templates.show', template)" class="ml-1 text-violet-400 hover:underline">Go to the overlay page</a>
              to add some.
            </div>
            <template v-else>
              <p class="mb-3 text-sm text-muted-foreground">
                Reference-only. Use these snippets in your HTML or CSS — click any snippet to copy it.
              </p>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead class="w-32">Key</TableHead>
                    <TableHead>Label</TableHead>
                    <TableHead class="w-28">Type</TableHead>
                    <TableHead>Snippet</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="control in controls" :key="control.id">
                    <TableCell class="font-mono text-xs">{{ control.key }}</TableCell>
                    <TableCell class="text-sm">{{ control.label ?? '—' }}</TableCell>
                    <TableCell>
                      <Badge variant="outline" class="font-mono text-xs capitalize">{{ control.type }}</Badge>
                    </TableCell>
                    <TableCell>
                      <button
                        type="button"
                        @click="copySnippet(`[[[c:${control.key}]]]`)"
                        class="group flex items-center gap-1.5 rounded bg-sidebar px-2 py-1 font-mono text-xs transition-colors hover:bg-violet-600 hover:text-accent dark:hover:bg-violet-400"
                        title="Click to copy"
                      >
                        [[[c:{{ control.key }}]]]
                        <CopyIcon class="h-3 w-3 opacity-40 group-hover:opacity-100" />
                      </button>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </template>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-6 flex justify-between">
          <Link :href="route('templates.show', template)" class="btn btn-cancel">← Back to Overlay</Link>
          <button type="submit" :disabled="form.processing || !form.isDirty" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>

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
    <RekaToast v-if="showTemplateToast" :message="templateToastMessage" :type="templateToastType" @dismiss="showTemplateToast = false" />
  </AppLayout>
</template>
