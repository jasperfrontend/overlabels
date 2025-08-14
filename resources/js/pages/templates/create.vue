
<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types'
import Modal from '@/components/Modal.vue';
import axios from 'axios';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Button } from '@/components/ui/button';
import { truncate } from 'es-toolkit/compat';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';

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
  }>;
  active_template_tags?: Array<{
    display_tag: string;
    description: string;
  }>;
}

interface TagsResponse {
  tags: Record<string, CategoryTag>;
}

const form = useForm({
  name: '',
  description: '',
  html: '',
  css: '',
  type: 'static',
  is_public: true,
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Overlay Creator',
    href: '/templates/create',
  }
]

const isDark = ref(document.documentElement.classList.contains('dark'));
const showPreview = ref(false);
const previewHtml = ref('');

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

const submitForm = () => {
  form.post(route('templates.store'), {
    onSuccess: () => {
      // Will redirect to index or show page
    },
  });
};

const insertTag = (editor: string): void => {
  console.log('insertTag called for editor:', editor);
  // Set the current editor for later use
  currentEditor.value = editor;

  console.log('showTagModal before API call:', showTagModal.value);

  // Fetch available tags from the API
  axios.get<TagsResponse>('/api/template-tags') // this route needs to be secured
    .then(response => {
      const tags = response.data.tags;

      // Store the categorized tags for the modal
      categoryTags.value = tags;

      // Flatten the tags for simple list if needed
      const flattenedTags: TemplateTag[] = [];
      Object.entries(tags).forEach(([category, categoryData]) => {
        // Check for active_template_tags first, then fall back to tags
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

      // Show the tag selection modal
      showTagModal.value = true;
      console.log('showTagModal after setting to true:', showTagModal.value);
    })
    .catch(() => {
      // Fallback to manual input
      console.log('💔 Error retrieving tags from api')
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

// Function to copy the tag to clipboard and insert it into the editor
const copyTagToClipboard = (tag: string): void => {
  try {
    // Format the tag with triple brackets

    // Copy to clipboard
    navigator.clipboard.writeText(tag)
      .then(() => {
        // Show success toast
        showToast.value = true;
        toastMessage.value = `Tag copied to clipboard: ${tag}`;
        toastType.value = 'success';

        // Insert the tag into the current editor
        if (currentEditor.value === 'html') {
          form.html += tag;
        } else if (currentEditor.value === 'css') {
          form.css += tag;
        }

        // Close the modal
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
  // Simple formatting - in production you might use a proper formatter
  if (type === 'html') {
    // Basic HTML formatting
    form.html = form.html
      .replace(/></g, '>\n<')
      .replace(/(\n\s*)+/g, '\n');
  } else if (type === 'css') {
    // Basic CSS formatting
    form.css = form.css
      .replace(/}/g, '}\n')
      .replace(/{/g, ' {\n  ')
      .replace(/;/g, ';\n  ')
      .replace(/\n\s*\n/g, '\n');
  }
};

const previewTemplate = (): void => {
  // Create a preview with sample data that matches the database tags
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

  // Simple template tag replacement for preview
  let html = form.html;
  let css = form.css;

  // Replace template tags with sample data
  Object.entries(sampleData).forEach(([tag, value]) => {
    const tagPattern = new RegExp(`\\[\\[\\[${tag}]]]`, 'g');
    html = html.replace(tagPattern, value);
    css = css.replace(tagPattern, value);
  });

  previewHtml.value = `<!DOCTYPE html>
  <html lang="en">
      <head>
      <style>${css}</style>
    </head>
    <body>
      ${html}
    </body>
  </html>`;
  showPreview.value = true;
};

// Watch for theme changes
watch(
  () => document.documentElement.classList.contains('dark'),
  (newDark) => {
    isDark.value = newDark;
  },
);
</script>

<template>
  <Head title="Create New Template" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <Heading title="Overlay Creator" description="Design a new overlay template with HTML, CSS, and Template Tags" />
      <div class="mt-4">

        <form @submit.prevent="submitForm">
          <!-- Template Name -->
          <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-accent-foreground/50 mb-1">
              Template Name *
            </label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              class="p-2 rounded border w-full hover:shadow-sm transition"
              placeholder="My Awesome Overlay"
              required
            />
            <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">
              {{ form.errors.name }}
            </div>
          </div>

          <!-- Description -->
          <div class="mb-4">
            <label for="description" class="block text-sm font-medium text-accent-foreground/50 mb-1">
              Description
            </label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              class="p-2 rounded border w-full hover:shadow-sm transition"
              placeholder="Describe what your template does..."
            />
            <div v-if="form.errors.description" class="text-red-600 text-sm mt-1">
              {{ form.errors.description }}
            </div>
          </div>

          <!-- Template Type -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-accent-foreground/50 mb-2">
              Template Type *
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition" :class="{ 'border-blue-500 bg-blue-50': form.type === 'static' }">
                <input 
                  v-model="form.type" 
                  type="radio" 
                  value="static" 
                  class="mt-1 mr-3"
                  required
                />
                <div>
                  <div class="text-sm font-medium text-gray-900">Static Overlay</div>
                  <div class="text-xs text-gray-500 mt-1">
                    Displays persistent content with Twitch data (follower count, stream title, etc.)
                  </div>
                </div>
              </label>
              
              <label class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition" :class="{ 'border-blue-500 bg-blue-50': form.type === 'alert' }">
                <input 
                  v-model="form.type" 
                  type="radio" 
                  value="alert" 
                  class="mt-1 mr-3"
                  required
                />
                <div>
                  <div class="text-sm font-medium text-gray-900">Event Alert</div>
                  <div class="text-xs text-gray-500 mt-1">
                    Shows temporarily when events occur (new follower, subscription, etc.)
                  </div>
                </div>
              </label>
            </div>
            <div v-if="form.errors.type" class="text-red-600 text-sm mt-1">
              {{ form.errors.type }}
            </div>
            
            <!-- Event-specific help -->
            <div v-if="form.type === 'alert'" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
              <div class="text-sm text-blue-800">
                <strong>💡 Alert Template Tips:</strong>
                <ul class="mt-1 list-disc list-inside space-y-1">
                  <li>Use <code class="bg-blue-100 px-1 rounded">event.user_name</code> for the person who triggered the event</li>
                  <li>Use <code class="bg-blue-100 px-1 rounded">event.type</code> to show what kind of event occurred</li>
                  <li>Mix event tags with regular tags like <code class="bg-blue-100 px-1 rounded">followers_total</code></li>
                  <li>Keep alert templates simple and readable for quick display</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Code Editors -->
          <Tabs defaultValue="html" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
              <TabsTrigger value="html">HTML *</TabsTrigger>
              <TabsTrigger value="css">CSS</TabsTrigger>
            </TabsList>

            <!-- HTML Editor Tab -->
            <TabsContent value="html" class="mt-4">
              <div>
                <div class="flex justify-between items-center mb-2">
                  <div class="space-x-2">
                    <button
                      type="button"
                      @click="formatCode('html')"
                      class="text-sm text-gray-600 hover:text-gray-800"
                    >
                      Format
                    </button>
                    <button
                      type="button"
                      @click="insertTag('html')"
                      class="text-sm text-blue-600 hover:text-blue-800"
                    >
                      Insert Template Tag
                    </button>
                  </div>
                </div>
                <div class="border rounded-md overflow-hidden">
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
                </div>
                <div v-if="form.errors.html" class="text-red-600 text-sm mt-1">
                  {{ form.errors.html }}
                </div>
              </div>
            </TabsContent>

            <!-- CSS Editor Tab -->
            <TabsContent value="css" class="mt-4">
              <div>
                <div class="flex justify-between items-center mb-2">
                  <div class="space-x-2">
                    <button
                      type="button"
                      @click="formatCode('css')"
                      class="text-sm text-gray-600 hover:text-gray-800"
                    >
                      Format
                    </button>
                    <button
                      type="button"
                      @click="insertTag('css')"
                      class="text-sm text-blue-600 hover:text-blue-800"
                    >
                      Insert Template Tag
                    </button>
                  </div>
                </div>
                <div class="border rounded-md overflow-hidden">
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
                </div>
                <div v-if="form.errors.css" class="text-red-600 text-sm mt-1">
                  {{ form.errors.css }}
                </div>
              </div>
            </TabsContent>
          </Tabs>

          <!-- Visibility -->
          <div class="mt-6">
            <label class="flex items-center">
              <input
                v-model="form.is_public"
                type="checkbox"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
              <span class="ml-2 text-sm text-accent-foreground">
                Make this template public (others can view and fork it)
              </span>
            </label>
          </div>


          <!-- Form Actions -->
          <div class="mt-6 flex justify-end space-x-3">
            <button
              @click.prevent="insertTag('html')"
              class="btn btn-cancel"
            >
              Show all template tags
            </button>
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
        <h3 class="text-lg font-semibold mb-4 text-foreground">Template Preview</h3>
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
        <div class="space-y-6 max-h-[60vh] overflow-y-auto">
          <div v-for="(categoryData, categoryName) in categoryTags" :key="categoryName" class="border border-border rounded-lg p-4">
            <!-- Category Header -->
            <h4 class="text-md font-medium text-foreground mb-1">{{ categoryData.category.display_name }}</h4>
            <p class="text-sm text-muted-foreground mb-3">{{ categoryData.category.description }}</p>

            <!-- Tags in this category -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div v-for="(tag, tagIndex) in categoryData.active_template_tags || categoryData.tags" :key="tagIndex"
                   class="border border-border rounded-lg p-3 hover:bg-accent/10 cursor-pointer transition-colors"
                   @click="copyTagToClipboard(tag.display_tag)">
                <code class="bg-muted px-2 py-1 rounded font-mono text-sm text-foreground">{{ tag.display_tag }}</code>
                <p class="mt-2 text-sm text-muted-foreground" >{{ truncate(tag.sample_data, {length: 30, omission: "..."}) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Toast Notification -->
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" />

  </AppLayout>
</template>
