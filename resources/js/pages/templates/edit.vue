<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  ChevronRight,
  Code,
  InfoIcon,
  Keyboard,
  Palette,
  RefreshCcwDot,
  Save,
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { useLinkWarning } from '@/composables/useLinkWarning';

// Define interfaces for template tags
interface TemplateTag {
  display_tag: string;
  description: string;
  category?: string;
}

interface Props {
  existingTemplate: {
    html: string;
    css: string;
  };
  template: {
    id: number;
    name: string;
    description: string;
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
}

const props = withDefaults(defineProps<Props>(), {
  existingTemplate: () => ({ html: '', css: '' }),
  availableTags: () => [],
  template: Object,
})

const isDark = ref(document.documentElement.classList.contains('dark'));
const showConditionalExamples = ref(false);
const { triggerLinkWarning } = useLinkWarning();

const form = useForm({
  name: props?.template?.name,
  description: props?.template?.description || '',
  html: props?.template?.html || '',
  css: props?.template?.css || '',
  is_public: props?.template?.is_public,
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Template Builder',
    href: route('templates.edit', props.template),
  },
];
// CodeMirror extensions
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

// Toast state
const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);
const openExternalLink = (link: any, target: string) => {
  window.open(link, target);
};

const submitForm = () => {
  form.put(route('templates.update', props.template), {
    preserveScroll: true,
    onSuccess: () => {
      showToast.value = true;
      toastMessage.value = "Template saved successfully!";
      toastType.value = 'success';
      setTimeout(() => {
        showToast.value = false;
      }, 3000);
    },
    onError: () => {
      showToast.value = true;
      toastMessage.value = "Failed to save template!";
      toastType.value = 'error';
      setTimeout(() => {
        showToast.value = false;
      }, 3000);
    },
  });
};

const copyTag = async (tagName: string) => {
  const tag = `[[[${tagName}]]]`;
  try {
    await navigator.clipboard.writeText(tag);
    showToast.value = true;
    toastMessage.value = `Copied tag to clipboard: ${tagName}`;
    toastType.value = 'success';
  } catch (err) {
    showToast.value = true;
    toastMessage.value = 'Failed to copy tag to clipboard!';
    toastType.value = 'error';
  }
};

// Watch for theme changes
watch(
  () => document.documentElement.classList.contains('dark'),
  (newDark) => {
    isDark.value = newDark;
  },
);

// Initialize keyboard shortcuts
const { register, getAllShortcuts } = useKeyboardShortcuts();
const showKeyboardShortcuts = ref(false);

// Register keyboard shortcuts
onMounted(() => {
  // Save template with Ctrl+S
  register(
    'save-template',
    'ctrl+s',
    () => {
      submitForm();
    },
    { description: 'Save template' },
  );

  // Preview with Ctrl+P
  register(
    'preview-live',
    'ctrl+p',
    () => {
      triggerLinkWarning(
        () => openExternalLink(`/overlay/${props.template?.slug}/public`, '_blank'),
        'Remember: DO NOT EVER show the overlay link with your personal access #hash in the URL on stream! Treat it like a password. If you think it has leaked, revoke or regenerate the hash immediately.',
      );
    },
    { description: 'Preview in new tab' },
  );

  // Toggle keyboard shortcuts display with Ctrl+K
  register(
    'toggle-shortcuts',
    'ctrl+k',
    () => {
      showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
    },
    { description: 'Show keyboard shortcuts' },
  );
});

// Get all keyboard shortcuts for display
const keyboardShortcutsList = computed(() => getAllShortcuts());

</script>

<template>
  <Head title="Template Builder" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <div class="flex gap-4">
        <div>
          <Heading title="Template Builder" description="Create custom HTML/CSS templates for your overlays using our CodePen-style editor." />
        </div>
        <div class="ml-auto flex items-center gap-2">
          <!-- Keyboard shortcuts indicator -->
          <button
            @click.prevent="showKeyboardShortcuts = !showKeyboardShortcuts"
            class="btn btn-cancel"
            title="Show keyboard shortcuts"
          >
            <Keyboard class="h-4 w-4 mr-2" />
            Keyboard Shortcuts
          </button>

          <button
            @click="submitForm"
            :disabled="form.processing || !form.isDirty"
            class="btn btn-primary"
          >
            <RefreshCcwDot v-if="form.processing" class="h-4 w-4 mr-2 animate-spin" />
            <Save v-else class="h-4 w-4 mr-2" />
            Save Changes
          </button>
        </div>
      </div>
      <div class="mt-4">
        <form @submit.prevent="submitForm">

          <!-- Editor Area -->
          <div class="space-y-6">
            <Tabs default-value="html" class="w-full">
              <TabsList class="grid w-full grid-cols-5 gap-1">
                <TabsTrigger value="html" class="flex cursor-pointer items-center gap-2 hover:bg-accent dark:hover:bg-accent">
                  <Code class="h-4 w-4" />
                  HTML
                </TabsTrigger>
                <TabsTrigger value="css" class="flex cursor-pointer items-center gap-2 hover:bg-accent dark:hover:bg-accent">
                  <Palette class="h-4 w-4" />
                  CSS
                </TabsTrigger>
                <TabsTrigger value="meta" class="flex cursor-pointer items-center gap-2 hover:bg-accent dark:hover:bg-accent">
                  <InfoIcon class="h-4 w-4" />
                  Meta
                </TabsTrigger>
              </TabsList>
              <TabsContent value="meta" class="mt-4">
                <Card>
                  <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                      Title and description
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <!-- Template Name -->
                    <div class="mb-4">
                      <label for="name" class="mb-1 block text-sm font-medium text-accent-foreground/50"> Title * </label>
                      <input id="name" v-model="form.name" type="text" class="w-full rounded border p-2 transition hover:shadow-sm" required />
                      <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.name }}
                      </div>
                    </div>

                    <!-- Description -->
                    <label for="description" class="mb-1 block text-sm font-medium text-accent-foreground/50"> Description </label>
                    <textarea id="description" v-model="form.description" rows="3" class="w-full rounded border p-2 transition hover:shadow-sm" />
                    <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                      {{ form.errors.description }}
                    </div>

                    <!-- Visibility -->
                    <div class="mt-6">
                      <label class="flex items-center">
                        <input
                          v-model="form.is_public"
                          type="checkbox"
                          class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="ml-2 text-sm"> Make this template public (others can view and fork it) </span>
                      </label>
                    </div>

                    <!-- Template Info -->
                    <div class="mt-6 grid grid-cols-2 gap-4 rounded-md bg-accent/30 p-4 text-sm">
                      <div>
                        <span class="text-gray-600 dark:text-gray-400">Created:</span>
                        <span class="ml-2">{{ new Date(template?.created_at).toLocaleDateString() }}</span>
                      </div>
                      <div>
                        <span class="text-gray-600 dark:text-gray-400">Last updated:</span>
                        <span class="ml-2">{{ new Date(template?.updated_at).toLocaleDateString() }}</span>
                      </div>
                      <div>
                        <span class="text-gray-600 dark:text-gray-400">Views:</span>
                        <span class="ml-2">{{ template?.view_count }}</span>
                      </div>
                      <div>
                        <span class="text-gray-600 dark:text-gray-400">Forks:</span>
                        <span class="ml-2">{{ template?.fork_count }}</span>
                      </div>
                    </div>

                    <!-- Available Tags Help -->
                    <div class="mt-6 rounded-md bg-accent/30 p-4">
                      <p class="mt-2 text-xs">
                        <a href="/tags-generator" target="_blank" class="text-blue-300 hover:underline"> View all available tags </a>
                      </p>
                      <div v-if="template?.template_tags && template?.template_tags.length > 0" class="mt-3">
                        <p class="mb-1 text-sm">Currently used tags:</p>
                        <div class="flex flex-wrap gap-1">
                          <code v-for="tag in template.template_tags" class="rounded bg-cyan-100/10 px-2 py-1 text-xs"> [[[{{ tag }}]]] </code>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
              <TabsContent value="html" class="mt-4">
                <Card>
                  <CardHeader>
                    <CardTitle class="text-base">HTML Template</CardTitle>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                      Omit <code>doctype</code>, <code>html</code>, <code>head</code> and <code>body</code>.
                    </p>
                  </CardHeader>
                  <CardContent>
                    <div class="overflow-hidden rounded-lg border">
                      <Codemirror
                        v-model="form.html"
                        :style="{ height: '500px' }"
                        :autofocus="true"
                        :indent-with-tab="true"
                        :tab-size="2"
                        :extensions="htmlExtensions"
                        placeholder="Enter your HTML template here... Use [[[tag_name]]] for dynamic content"
                      />
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
              <TabsContent value="css" class="mt-4">
                <Card>
                  <CardHeader>
                    <CardTitle class="text-base">CSS Styles</CardTitle>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                      Style your overlay with CSS. Template tags work in css as well! You can also <code>@import</code> external stylesheets, eg. for fonts.
                    </p>
                  </CardHeader>
                  <CardContent>
                    <div class="overflow-hidden rounded-lg border">
                      <Codemirror
                        v-model="form.css"
                        :style="{ height: '500px' }"
                        :indent-with-tab="true"
                        :tab-size="2"
                        :extensions="cssExtensions"
                        placeholder="Enter your CSS styles here..."
                      />
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
              <TabsContent value="preview" class="mt-4">
                <Card>
                  <CardHeader>
                    <CardTitle class="text-base">Live Preview</CardTitle>
                    <p class="text-sm text-gray-600 dark:text-gray-400">This preview shows how your overlay will look with sample data</p>
                  </CardHeader>
                  <CardContent>
                    <div class="rounded-lg border bg-gray-50 dark:bg-gray-900">
                      <iframe :srcdoc="form.html" class="h-[500px] w-full border-0" sandbox="allow-same-origin" />
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>
          </div>

          <!-- Form Actions -->
          <div class="mt-6 flex justify-between">
            <Link :href="route('templates.show', template)" class="btn btn-cancel"> ← Back to Template </Link>
            <div class="flex w-auto justify-around space-x-3">
              <button type="submit" :disabled="form.processing || !form.isDirty" class="btn btn-primary">Save Changes</button>
            </div>
          </div>
        </form>
      </div>
    </div>


    <!-- Keyboard shortcuts dialog -->
    <div
      v-if="showKeyboardShortcuts"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="showKeyboardShortcuts = false"
    >
      <div class="w-full max-w-md overflow-hidden rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-medium">Keyboard Shortcuts</h3>
          <button @click.prevent="showKeyboardShortcuts = false" class="rounded-full p-1 hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" style="fill: currentColor;">
              <path
                fill-rule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clip-rule="evenodd"
              />
            </svg>
          </button>
        </div>
        <div class="space-y-2">
          <div v-for="shortcut in keyboardShortcutsList" :key="shortcut.id" class="flex items-center justify-between rounded-md border p-2 text-sm">
            <span>{{ shortcut.description }}</span>
            <kbd class="rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-gray-700">
              {{ shortcut.keys }}
            </kbd>
          </div>
          <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Press <kbd class="rounded bg-gray-100 px-1 dark:bg-gray-700">Ctrl+K</kbd> to toggle this dialog.<br /><br />
            Keyboard shortcuts do not work when focused on the code editor.<br />
            Click outside first, then hit ctrl+s.
          </p>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" />
  </AppLayout>
</template>
