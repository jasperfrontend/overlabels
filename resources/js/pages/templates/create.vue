<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types'
import Modal from '@/components/Modal.vue';
import axios from 'axios';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { truncate } from 'es-toolkit/compat';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import TemplateTagsList from '@/components/TemplateTagsList.vue';
import {
  Brackets,
  Code,
  InfoIcon,
  Palette,
  Save,
  ExternalLink,
  Keyboard,
  LayoutTemplate,
  ChevronUp,
  ChevronDown,
  Zap,
  Layout,
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';

// Define interfaces for template tags
interface TemplateTag {
  display_tag: string;
  description: string;
  category?: string;
}

interface CategoryTag {
  category?: {
    display_name: string;
    description?: string;
  };
  tags?: Array<{
    display_tag: string;
    description: string;
    sample_data?: string;
  }>;
  active_template_tags?: Array<{
    display_tag: string;
    description: string;
    sample_data: string;
  }>;
}

interface TagsResponse {
  tags: Record<string, CategoryTag>;
}

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
  {
    title: 'Create New Template',
    href: '/templates/create',
  }
]

const isDark = ref(document.documentElement.classList.contains('dark'));
const showPreview = ref(false);
const previewHtml = ref('');
const tallEditor = ref(true);

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

// Tag selection modal state
const showTagModal = ref(false);
const tagList = ref<TemplateTag[]>([]);
const currentEditor = ref<string>('');
const categoryTags = ref<Record<string, CategoryTag>>({});

// Toast state
const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

// Keyboard shortcuts
const showKeyboardShortcuts = ref(false);
const { register, getAllShortcuts } = useKeyboardShortcuts();

const submitForm = () => {
  form.post(route('templates.store'), {
    onSuccess: () => {
      // Will redirect to index or show page
    },
  });
};

const insertTag = (editor: string): void => {
  console.log('insertTag called for editor:', editor);
  currentEditor.value = editor;

  axios.get<TagsResponse>('/api/template-tags')
    .then(response => {
      const tags = response.data.tags;
      categoryTags.value = tags;

      const flattenedTags: TemplateTag[] = [];
      Object.entries(tags).forEach(([category, categoryData]) => {
        const tagsArray = categoryData.active_template_tags || categoryData.tags;

        if (tagsArray && Array.isArray(tagsArray)) {
          tagsArray.forEach(tag => {
            flattenedTags.push({
              display_tag: tag.display_tag,
              description: tag.description,
              category: categoryData.category?.display_name || category
            });
          });
        }
      });

      tagList.value = flattenedTags;
      showTagModal.value = true;
    })
    .catch(() => {
      showToast.value = true;
      toastMessage.value = 'Error retrieving tags from API';
      toastType.value = 'error';

      const tag = prompt('Enter template tag name (e.g., user_name):');
      if (tag) {
        const fullTag = `[[[${tag}]]]`;
        if (editor === 'html') {
          form.html += fullTag;
        } else if (editor === 'css') {
          form.css += fullTag;
        }
      }
    });
};

const copyTagToClipboard = (tag: string): void => {
  try {
    navigator.clipboard.writeText(tag)
      .then(() => {
        showToast.value = true;
        toastMessage.value = `Tag copied to clipboard: ${tag}`;
        toastType.value = 'success';

        if (currentEditor.value === 'html') {
          form.html += tag;
        } else if (currentEditor.value === 'css') {
          form.css += tag;
        }

        showTagModal.value = false;
      })
      .catch((error) => {
        console.error('Failed to copy to clipboard:', error);
        showToast.value = true;
        toastMessage.value = 'Failed to copy to clipboard';
        toastType.value = 'error';
      });
  } catch (error) {
    console.error('Error copying to clipboard:', error);
    showToast.value = true;
    toastMessage.value = 'Error copying to clipboard';
    toastType.value = 'error';
  }
};

const formatCode = (type: string): void => {
  if (type === 'html') {
    form.html = form.html
      .replace(/></g, '>\n<')
      .replace(/(\n\s*)+/g, '\n');
  } else if (type === 'css') {
    form.css = form.css
      .replace(/}/g, '}\n')
      .replace(/{/g, ' {\n  ')
      .replace(/;/g, ';\n  ')
      .replace(/\n\s*\n/g, '\n');
  }
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

  let head = form.head;
  let html = form.html;
  let css = form.css;

  Object.entries(sampleData).forEach(([tag, value]) => {
    const tagPattern = new RegExp(`\\[\\[\\[${tag}]]]`, 'g');
    html = html.replace(tagPattern, value);
    css = css.replace(tagPattern, value);
  });

  previewHtml.value = `<!DOCTYPE html>
  <html lang="en">
      <head>
      <style>${css}</style>
      ${head}
    </head>
    <body>
      ${html}
    </body>
  </html>`;
  showPreview.value = true;
};

// Register keyboard shortcuts
onMounted(() => {
  register(
    'save-template',
    'ctrl+s',
    () => {
      submitForm();
    },
    { description: 'Create template' },
  );

  register(
    'preview-template',
    'ctrl+p',
    () => {
      previewTemplate();
    },
    { description: 'Preview template' },
  );

  register(
    'toggle-shortcuts',
    'ctrl+k',
    () => {
      showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
    },
    { description: 'Show keyboard shortcuts' },
  );
});

const keyboardShortcutsList = computed(() => getAllShortcuts());

// Watch for theme changes
watch(
  () => document.documentElement.classList.contains('dark'),
  (newDark) => {
    isDark.value = newDark;
  },
);
</script>

<template>
  <Head title="Create Template" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">

      <!-- Header -->
      <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <LayoutTemplate class="mr-2 h-6 w-6" />
          <Heading title="Template Creator" description="Create a new overlay template with HTML, CSS, and Template Tags. Don't forget to set a title for this template under the Meta tab." />
        </div>
        <div class="ml-auto flex items-center gap-2">
          <button
            type="button"
            @click="previewTemplate"
            class="btn btn-secondary"
          >
            Preview
            <ExternalLink class="ml-2 h-4 w-4" />
          </button>
          <button
            @click="submitForm"
            :disabled="form.processing"
            class="btn btn-primary"
          >
            <Save class="mr-2 h-4 w-4" />
            Create Template
          </button>
        </div>
      </div>

      <div class="mt-4">
        <form @submit.prevent="submitForm">

          <!-- Editor Area -->
          <div class="space-y-6">
            <Tabs default-value="html" class="w-full">
              <TabsList class="grid w-full grid-cols-8 gap-2">
                <TabsTrigger value="head" class="flex cursor-pointer items-center gap-2 hover:bg-sidebar-accent">
                  <Code class="h-4 w-4" />
                  HEAD
                </TabsTrigger>
                <TabsTrigger value="html" class="flex cursor-pointer items-center gap-2 hover:bg-sidebar-accent">
                  <Code class="h-4 w-4" />
                  HTML
                </TabsTrigger>
                <TabsTrigger value="css" class="flex cursor-pointer items-center gap-2 hover:bg-sidebar-accent">
                  <Palette class="h-4 w-4" />
                  CSS
                </TabsTrigger>
                <TabsTrigger value="meta" class="flex cursor-pointer items-center gap-2 hover:bg-sidebar-accent">
                  <InfoIcon class="h-4 w-4" />
                  Meta
                </TabsTrigger>
                <TabsTrigger value="tags" class="flex cursor-pointer items-center gap-2 hover:bg-sidebar-accent">
                  <Brackets class="h-4 w-4" />
                  Template Tags
                </TabsTrigger>
              </TabsList>

              <!-- HEAD Editor Tab -->
              <TabsContent value="head" class="mt-4">
                <Card>
                  <CardHeader class="px-4">
                    <CardTitle class="text-base">HEAD Template</CardTitle>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                      Add any &lt;head&gt; stuff here like css/icon/color/font libraries. Be careful with adding scripts unless you know what you're doing, which I can safely assume if you're interested in using Overlabels for your OBS overlays.
                    </p>
                  </CardHeader>
                  <CardContent>
                    <div class="overflow-hidden rounded-sm border">
                      <textarea
                        v-model="form.head"
                        rows="10"
                        class="font-mono w-full p-2 border border-sidebar"
                        :style="{ height: tallEditor ? '500px' : '800px' }"
                        placeholder="Enter <head> content here..."
                      />
                    </div>
                    <div v-if="form.errors.head" class="text-red-600 text-sm mt-1">
                      {{ form.errors.head }}
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>

              <!-- HTML Editor Tab -->
              <TabsContent value="html" class="mt-4">
                <Card>
                  <CardHeader class="px-4">
                    <CardTitle class="text-base">HTML Template</CardTitle>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                      Omit <code>doctype</code>, <code>html</code>, <code>head</code> and <code>body</code>.
                    </p>
                    <div class="flex gap-2">
                      <button
                        type="button"
                        @click="formatCode('html')"
                        class="btn btn-xs btn-primary mb-2"
                      >
                        Format Code
                      </button>
                      <button
                        type="button"
                        @click="insertTag('html')"
                        class="btn btn-xs btn-secondary mb-2"
                      >
                        Insert Template Tag
                      </button>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div class="overflow-hidden rounded-sm border">
                      <Codemirror
                        v-model="form.html"
                        :style="{ height: tallEditor ? '500px' : '800px' }"
                        :autofocus="true"
                        :indent-with-tab="true"
                        :tab-size="2"
                        :extensions="htmlExtensions"
                        placeholder="Enter your HTML template here... Use [[[tag_name]]] for dynamic content"
                      />
                    </div>
                    <div v-if="form.errors.html" class="text-red-600 text-sm mt-1">
                      {{ form.errors.html }}
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>

              <!-- CSS Editor Tab -->
              <TabsContent value="css" class="mt-4">
                <Card>
                  <CardHeader>
                    <CardTitle class="text-base">CSS Styles</CardTitle>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                      Style your overlay with CSS. Template tags work in CSS as well! You can also <code>@import</code> external stylesheets.
                    </p>
                    <div class="flex gap-2">
                      <button
                        type="button"
                        @click="formatCode('css')"
                        class="btn btn-xs btn-primary mb-2"
                      >
                        Format Code
                      </button>
                      <button
                        type="button"
                        @click="insertTag('css')"
                        class="btn btn-xs btn-secondary mb-2"
                      >
                        Insert Template Tag
                      </button>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div class="overflow-hidden rounded-sm border">
                      <Codemirror
                        v-model="form.css"
                        :style="{ height: tallEditor ? '500px' : '800px' }"
                        :indent-with-tab="true"
                        :tab-size="2"
                        :extensions="cssExtensions"
                        placeholder="Enter your CSS styles here..."
                      />
                    </div>
                    <div v-if="form.errors.css" class="text-red-600 text-sm mt-1">
                      {{ form.errors.css }}
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>

              <!-- Meta Tab -->
              <TabsContent value="meta" class="mt-4">
                <Card>
                  <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">Template Settings</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <!-- Template Name -->
                    <div class="mb-4">
                      <label for="name" class="mb-1 block text-sm font-medium text-accent-foreground/50">
                        Template Name *
                      </label>
                      <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="w-full rounded border border-sidebar-foreground/50 p-2 focus:ring-1 ring-violet-400 focus:outline-none"
                        placeholder="My Awesome Overlay"
                        required
                      />
                      <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.name }}
                      </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                      <label for="description" class="mb-1 block text-sm font-medium text-accent-foreground/50">
                        Description
                      </label>
                      <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded border border-sidebar-foreground/50 p-2 focus:ring-1 ring-violet-400 focus:outline-none"
                        placeholder="Describe what your template does..."
                      />
                      <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                        {{ form.errors.description }}
                      </div>
                    </div>

                    <!-- Template Type -->
                    <div class="mb-6">
                      <label class="mb-2 block text-sm font-medium text-accent-foreground/50">
                        Template Type *
                      </label>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label
                          class="relative flex cursor-pointer items-start rounded-sm border p-4 transition-all hover:bg-sidebar-accent"
                          :class="{
                            'border-violet-400 bg-violet-400/10 dark:bg-violet-400/5': form.type === 'static',
                            'border-sidebar hover:border-sidebar-accent': form.type !== 'static'
                          }"
                        >
                          <input
                            v-model="form.type"
                            type="radio"
                            value="static"
                            class="sr-only"
                            required
                          />
                          <div class="flex items-start">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 mr-3 mt-0.5"
                                 :class="form.type === 'static' ? 'border-violet-500 bg-violet-500' : 'border-gray-400'">
                              <div v-if="form.type === 'static'" class="h-2 w-2 rounded-full bg-white"></div>
                            </div>
                            <div>
                              <div class="flex items-center gap-2">
                                <Layout class="h-4 w-4" />
                                <span class="text-sm font-medium">Static Overlay</span>
                              </div>
                              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Displays persistent content with Twitch data (follower count, stream title, etc.)
                              </p>
                            </div>
                          </div>
                        </label>

                        <label
                          class="relative flex cursor-pointer items-start rounded-sm border p-4 transition-all hover:bg-sidebar-accent"
                          :class="{
                            'border-violet-500 bg-violet-500/10 dark:bg-violet-500/5': form.type === 'alert',
                            'border-sidebar hover:border-sidebar-accent': form.type !== 'alert'
                          }"
                        >
                          <input
                            v-model="form.type"
                            type="radio"
                            value="alert"
                            class="sr-only"
                            required
                          />
                          <div class="flex items-start">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 mr-3 mt-0.5"
                                 :class="form.type === 'alert' ? 'border-violet-500 bg-violet-500' : 'border-gray-400'">
                              <div v-if="form.type === 'alert'" class="h-2 w-2 rounded-full bg-white"></div>
                            </div>
                            <div>
                              <div class="flex items-center gap-2">
                                <Zap class="h-4 w-4" />
                                <span class="text-sm font-medium">Event Alert</span>
                              </div>
                              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Shows temporarily when events occur (new follower, subscription, etc.)
                              </p>
                            </div>
                          </div>
                        </label>
                      </div>
                      <div v-if="form.errors.type" class="text-red-600 text-sm mt-1">
                        {{ form.errors.type }}
                      </div>
                    </div>

                    <!-- Event-specific help -->
                    <div v-if="form.type === 'alert'" class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md">
                      <div class="text-sm">
                        <strong class="text-yellow-800 dark:text-yellow-200">Alert Template Tips:</strong>
                        <ul class="mt-1 list-disc list-inside space-y-1 text-yellow-700 dark:text-yellow-300">
                          <li>To view all the Event-based template tags you can use,
                            visit the <a class="underline" href="/help" target="_blank">Help documentation</a> <span class="text-muted-foreground">(opens in a new tab)</span>.</li>
                          <li>Only the <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">channel.raid</code> event uses <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">event.from_broadcaster_user_name</code></li>
                          <li>You can mix event tags with regular tags like <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">[[[followers_total]]]</code></li>
                          <li>Keep alert templates simple and readable for quick display</li>
                        </ul>
                      </div>
                    </div>

                    <!-- Visibility -->
                    <div class="mt-6">
                      <label class="flex items-center">
                        <input
                          v-model="form.is_public"
                          type="checkbox"
                          class="rounded border-gray-300 text-violet-600 shadow-sm focus:border-violet-500 focus:ring-violet-500"
                        />
                        <span class="ml-2 text-sm">
                          Make this template public (others can view and fork it)
                        </span>
                      </label>
                    </div>

                  </CardContent>
                </Card>
              </TabsContent>

              <!-- Tags Tab -->
              <TabsContent value="tags" class="mt-4">
                <Card>
                  <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">Template Tags</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div class="mb-4">
                      <TemplateTagsList />
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>

            <!-- Editor Height Toggle & Keyboard Shortcuts -->
            <div class="mt-3 text-center flex justify-between">
              <button
                @click.prevent="tallEditor = !tallEditor"
                class="flex cursor-pointer gap-2 text-sm text-muted-foreground hover:text-accent-foreground"
              >
                <ChevronDown v-if="tallEditor" class="mr-1 h-4 w-4" />
                <ChevronUp v-else class="mr-1 h-4 w-4" />
                <span v-if="tallEditor">Expand editor</span>
                <span v-else>Collapse editor</span>
              </button>
              <a
                @click.prevent="showKeyboardShortcuts = !showKeyboardShortcuts"
                href="#"
                class="cursor-pointer flex text-sm text-muted-foreground hover:text-accent-foreground"
              >
                <Keyboard class="mr-2 mt-0.5 h-4 w-4" />
                Keyboard Shortcuts
              </a>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="mt-6 flex justify-end space-x-3">
            <button
              type="button"
              @click="previewTemplate"
              class="btn btn-secondary"
            >
              Preview
            </button>
            <button
              type="submit"
              :disabled="form.processing"
              class="btn btn-primary"
            >
              Create Template
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Preview Modal -->
    <Modal :show="showPreview" @close="showPreview = false" max-width="4xl">
      <div class="p-6">
        <div class="flex text-center justify-between">
          <h3 class="text-lg font-semibold mb-4 text-foreground">Template Preview</h3>
          <button class="btn btn-sm py-2 rounded-full mb-4" @click="showPreview = !showPreview" title="Close preview">&times;</button>
        </div>
        <div class="border border-border rounded-md p-4 bg-muted" style="height: 400px; position: relative;">
          <iframe
            v-if="previewHtml"
            :srcdoc="previewHtml"
            class="w-full h-full border-0"
            sandbox="allow-scripts"
          />
        </div>
        <div class="mt-4 text-sm text-muted-foreground">
          Note: Template tags are shown with sample data in preview
        </div>
      </div>
    </Modal>

    <!-- Tag Selection Modal -->
    <Modal :show="showTagModal" @close="showTagModal = false" max-width="3xl">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="text-lg font-semibold mb-4 text-foreground">Select a Template Tag</h3>
            <p class="text-sm text-muted-foreground mb-4">
              Click on a tag to copy it to your clipboard and insert it into your template.
            </p>
          </div>
          <div class="space-y-4">
            <button
              @click="showTagModal = false"
              class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 cursor-pointer">&times;</button>
          </div>
        </div>
        <div
          v-if="categoryTags && Object.keys(categoryTags).length > 0"
          class="space-y-6 max-h-[60vh] overflow-y-auto">
          <div
            v-for="(categoryData, categoryName) in categoryTags" :key="categoryName" class="border border-border rounded-sm p-4">
            <!-- Category Header -->
            <h4 class="text-md font-medium text-foreground mb-1">{{ categoryData?.category?.display_name }}</h4>
            <p class="text-sm text-muted-foreground mb-3">{{ categoryData?.category?.description }}</p>

            <!-- Tags in this category -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div v-for="(tag, tagIndex) in categoryData.active_template_tags || categoryData.tags" :key="tagIndex"
                   class="border border-border rounded-sm p-3 hover:bg-accent/10 cursor-pointer transition-colors"
                   @click="copyTagToClipboard(tag.display_tag)">
                <code class="bg-muted px-2 py-1 rounded font-mono text-sm text-foreground">{{ tag.display_tag }}</code>
                <p v-if="tag.sample_data" class="mt-2 text-sm text-muted-foreground" >{{ truncate(tag.sample_data, {length: 30, omission: "..."}) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Keyboard Shortcuts Dialog -->
    <div
      v-if="showKeyboardShortcuts"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="showKeyboardShortcuts = false"
    >
      <div class="w-full max-w-md overflow-hidden rounded-sm p-6 shadow-lg bg-sidebar-accent border border-sidebar">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-medium">Keyboard Shortcuts</h3>
          <button @click.prevent="showKeyboardShortcuts = false" class="rounded-full p-1 hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" style="fill: currentColor">
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
            <kbd class="rounded bg-sidebar-accent px-2 py-1 font-mono text-xs">
              {{ shortcut.keys }}
            </kbd>
          </div>
          <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Press <kbd class="rounded bg-gray-100 px-1 dark:bg-gray-700">Ctrl+K</kbd> to toggle this dialog.<br /><br />
            Keyboard shortcuts do not work when focused on the code editor.<br />
            Click outside first, then use the shortcut.
          </p>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" />
  </AppLayout>
</template>
